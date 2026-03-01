<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Must be logged in as a user
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Login required', 'login_required' => true]);
    exit();
}

// Ensure wishlist table exists
$conn->query("CREATE TABLE IF NOT EXISTS `wishlist` (
    `id`         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT          NOT NULL,
    `post_id`    INT          NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_wishlist` (`user_id`, `post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$user_id = (int)$_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$post_id = (int)($_POST['post_id'] ?? $_GET['post_id'] ?? 0);

if (!$post_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit();
}

// ── Check if post exists and is approved
$chk = $conn->prepare("SELECT id FROM posts WHERE id = ? AND is_approved = 1 LIMIT 1");
$chk->bind_param("i", $post_id);
$chk->execute();
$exists = $chk->get_result()->num_rows > 0;
$chk->close();

if (!$exists) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit();
}

// ── Check current wishlist state
function is_wishlisted($conn, $user_id, $post_id)
{
    $s = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND post_id = ? LIMIT 1");
    $s->bind_param("ii", $user_id, $post_id);
    $s->execute();
    $found = $s->get_result()->num_rows > 0;
    $s->close();
    return $found;
}

if ($action === 'check') {
    echo json_encode(['success' => true, 'saved' => is_wishlisted($conn, $user_id, $post_id)]);
    exit();
}

if ($action === 'toggle') {
    if (is_wishlisted($conn, $user_id, $post_id)) {
        // Remove
        $del = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND post_id = ?");
        $del->bind_param("ii", $user_id, $post_id);
        $del->execute();
        $del->close();
        echo json_encode(['success' => true, 'saved' => false, 'message' => 'Removed from wishlist']);
    } else {
        // Add
        $ins = $conn->prepare("INSERT INTO wishlist (user_id, post_id) VALUES (?, ?)");
        $ins->bind_param("ii", $user_id, $post_id);
        $ins->execute();
        $ins->close();
        echo json_encode(['success' => true, 'saved' => true, 'message' => 'Saved to wishlist']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
