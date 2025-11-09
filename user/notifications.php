<?php
// user/notifications.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

$user_id = $_SESSION['user_id'];

// Mark all as read
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();
