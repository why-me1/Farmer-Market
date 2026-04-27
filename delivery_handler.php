<?php
// ── Safe includes using absolute paths (avoids CWD issues) ──
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ratings.php';
// Note: notification_functions.php has a relative require_once 'db.php' which can
// fail when called from the root. We only use createNotification() which needs $conn.
// $conn is already available from config.php, so we define a minimal wrapper here.

header('Content-Type: application/json');

// ── Catch PHP fatal errors and return JSON so the browser never sees a blank ──
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR])) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e['message']]);
    }
});

// ── Auth check ──────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';
$action    = $_POST['action'] ?? '';

/**
 * Helper: create a notification row directly (avoids notification_functions.php path issues)
 */
function delivery_notify(int $user_id, ?int $post_id, ?int $comment_id, string $type): void
{
    global $conn;
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, post_id, comment_id, type) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("iiis", $user_id, $post_id, $comment_id, $type);
    $stmt->execute();
    $stmt->close();
}

// ────────────────────────────────────────────────────────────────────────────
// ACTION: initiate_delivery  (farmer side)
// ────────────────────────────────────────────────────────────────────────────
if ($action === 'initiate_delivery' && $user_role === 'farmer') {

    $post_id       = (int)($_POST['post_id']       ?? 0);
    $delivery_type = trim($_POST['delivery_type']   ?? '');

    if (!$post_id || !in_array($delivery_type, ['local', 'courier'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }

    // Verify post belongs to this farmer and is still 'sold'
    $stmt = $conn->prepare(
        "SELECT p.id, c.user_id AS buyer_id, p.product_name
         FROM posts p
         JOIN comments c ON p.id = c.post_id AND c.is_approved = 1
         WHERE p.id = ? AND p.farmer_id = ? AND p.status = 'sold'
         LIMIT 1"
    );
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Order not found or already processed']);
        exit();
    }

    $buyer_id     = (int)$row['buyer_id'];
    $product_name = $row['product_name'];

    // ── LOCAL: generate OTP ────────────────────────────────────────────────
    if ($delivery_type === 'local') {

        $otp_code   = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $stmt = $conn->prepare(
            "INSERT INTO delivery_otps (post_id, buyer_id, farmer_id, otp_code, expires_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 otp_code   = VALUES(otp_code),
                 is_used    = 0,
                 expires_at = VALUES(expires_at),
                 created_at = CURRENT_TIMESTAMP"
        );
        $stmt->bind_param("iiiss", $post_id, $buyer_id, $user_id, $otp_code, $expires_at);
        $stmt->execute();
        $stmt->close();

        // Mark delivery type on post
        $stmt = $conn->prepare("UPDATE posts SET delivery_type = 'local' WHERE id = ? AND farmer_id = ?");
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
        $stmt->close();

        // Notify buyer in stages: method selected, then OTP expectation
        delivery_notify($buyer_id, $post_id, null, 'delivery_local_selected');
        delivery_notify($buyer_id, $post_id, null, 'delivery_local_otp_required');
        // Notify farmer progress visibility
        delivery_notify($user_id, $post_id, null, 'farmer_delivery_local_selected');

        echo json_encode([
            'success'       => true,
            'delivery_type' => 'local',
            'message'       => 'OTP generated and sent to buyer\'s dashboard.'
        ]);
        exit();
    }

    // ── COURIER: save tracking info ────────────────────────────────────────
    $courier_company  = htmlspecialchars(strip_tags(trim($_POST['courier_company']  ?? '')));
    $courier_tracking = htmlspecialchars(strip_tags(trim($_POST['courier_tracking'] ?? '')));

    if (!$courier_company || !$courier_tracking) {
        echo json_encode(['success' => false, 'message' => 'Please provide courier company and tracking number']);
        exit();
    }

    $stmt = $conn->prepare(
        "UPDATE posts
         SET delivery_type    = 'courier',
             courier_company  = ?,
             courier_tracking = ?,
             status           = 'delivered'
         WHERE id = ? AND farmer_id = ?"
    );
    $stmt->bind_param("ssii", $courier_company, $courier_tracking, $post_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // Notify buyer in stages: method selected, tracking added, then delivered.
    // In current flow courier save marks status='delivered' immediately,
    // so we emit the final delivered stage here as well.
    delivery_notify($buyer_id, $post_id, null, 'delivery_courier_selected');
    delivery_notify($buyer_id, $post_id, null, 'delivery_tracking_added');
    delivery_notify($buyer_id, $post_id, null, 'delivery_delivered');
    // Notify farmer progress visibility
    delivery_notify($user_id, $post_id, null, 'farmer_delivery_courier_selected');
    delivery_notify($user_id, $post_id, null, 'farmer_tracking_added');

    // Update reputation scores
    record_buyer_payment($buyer_id, $post_id);
    calculate_farmer_reputation($user_id, 'delivery_confirmed', ['post_id' => (int)$post_id]);

    echo json_encode([
        'success'       => true,
        'delivery_type' => 'courier',
        'message'       => 'Courier tracking saved. Buyer has been notified.'
    ]);
    exit();
}

// ────────────────────────────────────────────────────────────────────────────
// ACTION: verify_otp  (farmer enters OTP given verbally by buyer)
// ────────────────────────────────────────────────────────────────────────────
if ($action === 'verify_otp' && $user_role === 'farmer') {

    $post_id     = (int)($_POST['post_id']  ?? 0);
    $entered_otp = trim($_POST['otp_code']  ?? '');

    if (!$post_id || !$entered_otp) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }

    $stmt = $conn->prepare(
        "SELECT d.id, d.otp_code, d.is_used, d.expires_at, d.buyer_id
         FROM delivery_otps d
         JOIN posts p ON d.post_id = p.id
         WHERE d.post_id = ? AND p.farmer_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $otp_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$otp_row) {
        echo json_encode(['success' => false, 'message' => 'No OTP found for this order']);
        exit();
    }
    if ($otp_row['is_used']) {
        echo json_encode(['success' => false, 'message' => 'This OTP has already been used']);
        exit();
    }
    if (strtotime($otp_row['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please regenerate.']);
        exit();
    }
    if ($otp_row['otp_code'] !== $entered_otp) {
        echo json_encode(['success' => false, 'message' => 'Incorrect OTP. Please try again.']);
        exit();
    }

    $buyer_id = (int)$otp_row['buyer_id'];

    // Mark OTP used
    $stmt = $conn->prepare("UPDATE delivery_otps SET is_used = 1 WHERE id = ?");
    $stmt->bind_param("i", $otp_row['id']);
    $stmt->execute();
    $stmt->close();

    // Mark post delivered
    $stmt = $conn->prepare("UPDATE posts SET status = 'delivered' WHERE id = ? AND farmer_id = ?");
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // Get product name for notification
    $stmt = $conn->prepare("SELECT product_name FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $stmt->bind_result($product_name);
    $stmt->fetch();
    $stmt->close();

    // Notify buyer of confirmed delivery
    delivery_notify($buyer_id, $post_id, null, 'delivery_delivered');
    // Notify farmer progress visibility
    delivery_notify($user_id, $post_id, null, 'farmer_order_delivered');

    // Update reputation scores
    record_buyer_payment($buyer_id, $post_id);
    calculate_farmer_reputation($user_id, 'delivery_confirmed', ['post_id' => (int)$post_id]);

    echo json_encode([
        'success' => true,
        'message' => 'OTP verified! Delivery confirmed. 🎉'
    ]);
    exit();
}

// ────────────────────────────────────────────────────────────────────────────
// ACTION: get_buyer_otp  (buyer checks their OTP via AJAX — optional)
// ────────────────────────────────────────────────────────────────────────────
if ($action === 'get_buyer_otp' && $user_role === 'user') {

    $post_id = (int)($_POST['post_id'] ?? 0);
    if (!$post_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid order']);
        exit();
    }

    $stmt = $conn->prepare(
        "SELECT d.otp_code, d.is_used, d.expires_at, p.delivery_type
         FROM delivery_otps d
         JOIN posts p ON d.post_id = p.id
         WHERE d.post_id = ? AND d.buyer_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No OTP assigned yet']);
        exit();
    }

    echo json_encode([
        'success'       => true,
        'otp_code'      => $row['otp_code'],
        'is_used'       => (bool)$row['is_used'],
        'expires_at'    => $row['expires_at'],
        'delivery_type' => $row['delivery_type'],
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action or insufficient permissions']);
