<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$current_user = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Ensure messages table exists ─────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `messages` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `sender_id`   INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `message`     TEXT NOT NULL,
    `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sender`   (`sender_id`),
    INDEX `idx_receiver` (`receiver_id`),
    INDEX `idx_created`  (`created_at`),
    FOREIGN KEY (`sender_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

switch ($action) {

    // ── Send a message ────────────────────────────────────────────────────────
    case 'send':
        $receiver_id = (int)($_POST['receiver_id'] ?? 0);
        $message     = trim($_POST['message'] ?? '');

        if (!$receiver_id || $message === '') {
            echo json_encode(['success' => false, 'error' => 'Invalid input']);
            exit();
        }
        if ($receiver_id === $current_user) {
            echo json_encode(['success' => false, 'error' => 'Cannot message yourself']);
            exit();
        }

        // Verify receiver exists
        $check = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $check->bind_param("i", $receiver_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit();
        }
        $check->close();

        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $current_user, $receiver_id, $message);
        if ($stmt->execute()) {
            $msg_id = $stmt->insert_id;
            $stmt->close();
            // Return the new message row
            $fetch = $conn->prepare("SELECT m.*, u.username AS sender_name FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.id = ?");
            $fetch->bind_param("i", $msg_id);
            $fetch->execute();
            $row = $fetch->get_result()->fetch_assoc();
            $fetch->close();
            echo json_encode(['success' => true, 'message' => $row]);
        } else {
            echo json_encode(['success' => false, 'error' => 'DB error']);
        }
        break;

    // ── Fetch chat messages between current user and another user ─────────────
    case 'get_messages':
        $other_id = (int)($_GET['other_id'] ?? 0);
        $since_id = (int)($_GET['since_id'] ?? 0); // for polling new messages only

        if (!$other_id) {
            echo json_encode(['success' => false, 'error' => 'Invalid user']);
            exit();
        }

        // Mark received messages as read
        $mark = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $mark->bind_param("ii", $other_id, $current_user);
        $mark->execute();
        $mark->close();

        $query = "SELECT m.id, m.sender_id, m.receiver_id, m.message, m.is_read,
                         m.created_at, u.username AS sender_name
                  FROM messages m
                  JOIN users u ON u.id = m.sender_id
                  WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                  AND m.id > ?
                  ORDER BY m.created_at ASC, m.id ASC
                  LIMIT 200";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiii", $current_user, $other_id, $other_id, $current_user, $since_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success' => true, 'messages' => $rows]);
        break;

    // ── Get all conversations (inbox) ─────────────────────────────────────────
    case 'get_conversations':
        $sql = "SELECT 
                    other_user,
                    u.username    AS other_name,
                    u.role        AS other_role,
                    last_message,
                    last_time,
                    unread_count
                FROM (
                    SELECT
                        CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS other_user,
                        message  AS last_message,
                        created_at AS last_time,
                        SUM(CASE WHEN receiver_id = ? AND is_read = 0 THEN 1 ELSE 0 END)
                            OVER (PARTITION BY CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END) AS unread_count,
                        ROW_NUMBER() OVER (
                            PARTITION BY CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
                            ORDER BY created_at DESC, id DESC
                        ) AS rn
                    FROM messages
                    WHERE sender_id = ? OR receiver_id = ?
                ) t
                JOIN users u ON u.id = t.other_user
                WHERE t.rn = 1
                ORDER BY t.last_time DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiii", $current_user, $current_user, $current_user, $current_user, $current_user, $current_user);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'conversations' => $rows]);
        break;

    // ── Search users (for new message picker) ─────────────────────────────────
    case 'search_users':
        $q = '%' . $conn->real_escape_string(trim($_GET['q'] ?? '')) . '%';
        $self = $current_user;
        $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE username LIKE ? AND id != ? AND role != 'admin' ORDER BY username LIMIT 15");
        $stmt->bind_param("si", $q, $self);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'users' => $rows]);
        break;

    // ── Unread message count (for nav badge) ─────────────────────────────────
    case 'unread_count':
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->bind_param("i", $current_user);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        echo json_encode(['success' => true, 'count' => (int)$row['cnt']]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
