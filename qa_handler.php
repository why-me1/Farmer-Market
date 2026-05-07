<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/notification_functions.php';

header('Content-Type: application/json');

// ── Schema bootstrap (idempotent) ─────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `product_qa` (
    `id`          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `post_id`     INT          NOT NULL,
    `user_id`     INT          NOT NULL,
    `question`    TEXT         NOT NULL,
    `answer`      TEXT         DEFAULT NULL,
    `answered_at` DATETIME     DEFAULT NULL,
    `is_hidden`   TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_post_id` (`post_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `qa_helpful` (
    `id`         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `qa_id`      INT NOT NULL,
    `user_id`    INT NOT NULL,
    UNIQUE KEY `unique_helpful` (`qa_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Auth helpers ──────────────────────────────────────────────────────────
function json_error(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function json_ok(array $data = []): never {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

// ── FETCH Q&A list (GET) ──────────────────────────────────────────────────
if ($action === 'fetch') {
    $post_id = (int)($_GET['post_id'] ?? 0);
    if (!$post_id) json_error('Invalid post.');

    global $conn;

    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    $sql = "SELECT qa.*, u.username,
                   (SELECT COUNT(*) FROM qa_helpful h WHERE h.qa_id = qa.id) AS helpful_count,
                   " . ($uid ? "(SELECT COUNT(*) FROM qa_helpful h WHERE h.qa_id = qa.id AND h.user_id = $uid) AS i_helped" : "0 AS i_helped") . ",
                   fu.username AS farmer_username
            FROM product_qa qa
            JOIN users u  ON qa.user_id = u.id
            JOIN posts p  ON qa.post_id  = p.id
            JOIN users fu ON p.farmer_id = fu.id
            WHERE qa.post_id = ? AND qa.is_hidden = 0
            ORDER BY qa.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    json_ok(['qa' => $rows]);
}

// ── Require login for all write operations ────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    json_error('login_required', 401);
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

// ── ASK a question ────────────────────────────────────────────────────────
if ($action === 'ask') {
    $post_id  = (int)($_POST['post_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');

    if (!$post_id) json_error('Invalid product.');
    if (strlen($question) < 5)  json_error('Question is too short (min 5 chars).');
    if (strlen($question) > 800) json_error('Question is too long (max 800 chars).');

    // Fetch farmer ID + product name for notification
    $ps = $conn->prepare("SELECT farmer_id, product_name FROM posts WHERE id = ? AND is_approved = 1 LIMIT 1");
    $ps->bind_param('i', $post_id);
    $ps->execute();
    $post_row = $ps->get_result()->fetch_assoc();
    $ps->close();

    if (!$post_row) json_error('Product not found.');

    // Rate-limit: max 3 questions per user per product
    $rl = $conn->prepare("SELECT COUNT(*) FROM product_qa WHERE post_id = ? AND user_id = ? AND is_hidden = 0");
    $rl->bind_param('ii', $post_id, $user_id);
    $rl->execute();
    $rl->bind_result($existing_count);
    $rl->fetch();
    $rl->close();

    if ($existing_count >= 5) json_error('You have reached the question limit (5) for this product.');

    $ins = $conn->prepare("INSERT INTO product_qa (post_id, user_id, question) VALUES (?, ?, ?)");
    $ins->bind_param('iis', $post_id, $user_id, $question);
    $ins->execute();
    $qa_id = (int)$conn->insert_id;
    $ins->close();

    // Notify farmer
    $farmer_id = (int)$post_row['farmer_id'];
    if ($farmer_id !== $user_id) {
        createNotification($farmer_id, $post_id, $qa_id, 'qa_question');
    }

    // Return the new row
    $row_stmt = $conn->prepare("SELECT qa.*, u.username,
                                       0 AS helpful_count, 0 AS i_helped,
                                       fu.username AS farmer_username
                                FROM product_qa qa
                                JOIN users u  ON qa.user_id = u.id
                                JOIN posts p  ON qa.post_id  = p.id
                                JOIN users fu ON p.farmer_id = fu.id
                                WHERE qa.id = ?");
    $row_stmt->bind_param('i', $qa_id);
    $row_stmt->execute();
    $new_row = $row_stmt->get_result()->fetch_assoc();
    $row_stmt->close();

    json_ok(['qa' => $new_row]);
}

// ── ANSWER a question (farmer only) ──────────────────────────────────────
if ($action === 'answer') {
    if ($user_role !== 'farmer') json_error('Only the farmer can answer questions.', 403);

    $qa_id  = (int)($_POST['qa_id'] ?? 0);
    $answer = trim($_POST['answer'] ?? '');

    if (!$qa_id) json_error('Invalid question ID.');
    if (strlen($answer) < 2)   json_error('Answer is too short.');
    if (strlen($answer) > 1000) json_error('Answer is too long (max 1000 chars).');

    // Verify this farmer owns the product
    $check = $conn->prepare("SELECT qa.user_id AS asker_id, p.farmer_id, p.id AS post_id, p.product_name
                              FROM product_qa qa
                              JOIN posts p ON qa.post_id = p.id
                              WHERE qa.id = ? AND p.farmer_id = ? AND qa.is_hidden = 0 LIMIT 1");
    $check->bind_param('ii', $qa_id, $user_id);
    $check->execute();
    $qa_row = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$qa_row) json_error('Not authorized or question not found.', 403);

    $now = date('Y-m-d H:i:s');
    $upd = $conn->prepare("UPDATE product_qa SET answer = ?, answered_at = ? WHERE id = ?");
    $upd->bind_param('ssi', $answer, $now, $qa_id);
    $upd->execute();
    $upd->close();

    // Notify the asker
    $asker_id = (int)$qa_row['asker_id'];
    if ($asker_id !== $user_id) {
        createNotification($asker_id, (int)$qa_row['post_id'], $qa_id, 'qa_answer');
    }

    json_ok(['answer' => $answer, 'answered_at' => $now]);
}

// ── HELPFUL vote toggle ────────────────────────────────────────────────────
if ($action === 'helpful') {
    $qa_id = (int)($_POST['qa_id'] ?? 0);
    if (!$qa_id) json_error('Invalid ID.');

    // Check if already voted
    $chk = $conn->prepare("SELECT id FROM qa_helpful WHERE qa_id = ? AND user_id = ?");
    $chk->bind_param('ii', $qa_id, $user_id);
    $chk->execute();
    $already = $chk->get_result()->num_rows > 0;
    $chk->close();

    if ($already) {
        $del = $conn->prepare("DELETE FROM qa_helpful WHERE qa_id = ? AND user_id = ?");
        $del->bind_param('ii', $qa_id, $user_id);
        $del->execute();
        $del->close();
        $voted = false;
    } else {
        $ins = $conn->prepare("INSERT IGNORE INTO qa_helpful (qa_id, user_id) VALUES (?, ?)");
        $ins->bind_param('ii', $qa_id, $user_id);
        $ins->execute();
        $ins->close();
        $voted = true;
    }

    $cnt_stmt = $conn->prepare("SELECT COUNT(*) FROM qa_helpful WHERE qa_id = ?");
    $cnt_stmt->bind_param('i', $qa_id);
    $cnt_stmt->execute();
    $cnt_stmt->bind_result($count);
    $cnt_stmt->fetch();
    $cnt_stmt->close();

    json_ok(['voted' => $voted, 'count' => (int)$count]);
}

// ── DELETE question (owner or admin) ─────────────────────────────────────
if ($action === 'delete') {
    $qa_id = (int)($_POST['qa_id'] ?? 0);
    if (!$qa_id) json_error('Invalid ID.');

    if ($user_role === 'admin') {
        $del = $conn->prepare("UPDATE product_qa SET is_hidden = 1 WHERE id = ?");
        $del->bind_param('i', $qa_id);
    } else {
        // Owner can only delete unanswered questions
        $del = $conn->prepare("UPDATE product_qa SET is_hidden = 1 WHERE id = ? AND user_id = ? AND answer IS NULL");
        $del->bind_param('ii', $qa_id, $user_id);
    }
    $del->execute();
    $affected = $del->affected_rows;
    $del->close();

    if ($affected === 0) json_error('Cannot delete this question.');
    json_ok();
}

json_error('Unknown action.');
