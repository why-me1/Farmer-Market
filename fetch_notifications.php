<?php
session_start();
require_once 'includes/notification_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get unread count
$count = getUnreadNotificationCount($user_id);

// Get recent notifications (last 5)
$notifications = getUserNotifications($user_id, 5);

// Format notifications for display
$formatted_notifications = [];
foreach ($notifications as $notification) {
    $formatted_notifications[] = [
        'id' => $notification['id'],
        'message' => getNotificationMessage($notification),
        'type' => $notification['type'],
        'is_read' => $notification['is_read'],
        'created_at' => $notification['created_at'],
        'link' => 'notifications.php' // Link to full notifications page
    ];
}

echo json_encode([
    'count' => $count,
    'notifications' => $formatted_notifications
]);
