<?php

/**
 * Notification System Functions
 * Handles all notification-related operations
 */

require_once 'db.php';

/**
 * Create a new notification
 * @param int $user_id - Receiver ID
 * @param int $post_id - Related post ID (optional)
 * @param int $comment_id - Related comment ID (optional)
 * @param string $type - Type of event ('comment' or 'comment_approved')
 * @return bool - Success status
 */
function createNotification($user_id, $post_id = null, $comment_id = null, $type = 'comment')
{
    global $conn;

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, post_id, comment_id, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $user_id, $post_id, $comment_id, $type);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Get notifications for a specific user
 * @param int $user_id - User ID
 * @param int $limit - Number of notifications to fetch
 * @return array - Array of notifications
 */
function getUserNotifications($user_id, $limit = 10)
{
    global $conn;

    $stmt = $conn->prepare("SELECT n.*, p.product_name FROM notifications n 
                          LEFT JOIN posts p ON n.post_id = p.id 
                          WHERE n.user_id = ? 
                          ORDER BY n.created_at DESC 
                          LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $notifications;
}

/**
 * Get unread notification count for a user
 * @param int $user_id - User ID
 * @return int - Count of unread notifications
 */
function getUnreadNotificationCount($user_id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return $count;
}

/**
 * Mark notification as read
 * @param int $notification_id - Notification ID
 * @param int $user_id - User ID (for security)
 * @return bool - Success status
 */
function markNotificationAsRead($notification_id, $user_id)
{
    global $conn;

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Mark all notifications as read for a user
 * @param int $user_id - User ID
 * @return bool - Success status
 */
function markAllNotificationsAsRead($user_id)
{
    global $conn;

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Notification Types and their handlers
 */

/**
 * Notify farmer when buyer places a bid (comment)
 * @param int $farmer_id - Farmer ID
 * @param int $post_id - Post ID
 * @param string $buyer_name - Buyer's name (not stored, just for logging)
 * @param float $bid_amount - Bid amount (not stored, just for logging)
 * @param string $product_name - Product name (not stored, just for logging)
 */
function notifyFarmerBidPlaced($farmer_id, $post_id, $buyer_name, $bid_amount, $product_name)
{
    // Create notification with type 'comment' for new bid
    return createNotification($farmer_id, $post_id, null, 'comment');
}

/**
 * Notify farmer when product is sold (comment approved)
 * @param int $farmer_id - Farmer ID
 * @param int $post_id - Post ID
 * @param string $buyer_name - Buyer's name (not stored)
 * @param string $product_name - Product name (not stored)
 */
function notifyFarmerProductSold($farmer_id, $post_id, $buyer_name, $product_name)
{
    // Create notification with type 'comment_approved' for sold product
    return createNotification($farmer_id, $post_id, null, 'comment_approved');
}

/**
 * Notify buyer when outbid
 * @param int $buyer_id - Buyer ID
 * @param int $post_id - Post ID
 * @param string $product_name - Product name (not stored)
 */
function notifyBuyerOutbid($buyer_id, $post_id, $product_name)
{
    // Create notification for outbid user
    return createNotification($buyer_id, $post_id, null, 'comment');
}

/**
 * Notify buyer when they win the bid
 * @param int $buyer_id - Buyer ID
 * @param int $post_id - Post ID
 * @param string $product_name - Product name (not stored)
 */
function notifyBuyerWonBid($buyer_id, $post_id, $product_name)
{
    // Create notification for bid winner
    return createNotification($buyer_id, $post_id, null, 'comment_approved');
}

/**
 * Notify buyer about delivery update
 * @param int $buyer_id - Buyer ID
 * @param int $post_id - Post ID
 * @param string $product_name - Product name (not stored)
 * @param string $status - Delivery status (not stored)
 */
function notifyBuyerDeliveryUpdate($buyer_id, $post_id, $product_name, $status)
{
    // Create notification for delivery update
    return createNotification($buyer_id, $post_id, null, 'comment_approved');
}

/**
 * Get user role from user ID
 * @param int $user_id - User ID
 * @return string - User role
 */
function getUserRole($user_id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($role);
    $stmt->fetch();
    $stmt->close();

    // Convert role to notification system format
    if ($role === 'user') {
        return 'buyer';
    } elseif ($role === 'farmer') {
        return 'farmer';
    }

    return $role;
}

/**
 * Get username by user ID
 * @param int $user_id - User ID
 * @return string - Username
 */
function getUsername($user_id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($username);
    $stmt->fetch();
    $stmt->close();

    return $username;
}

/**
 * Generate notification message from notification data
 * @param array $notification - Notification data
 * @return string - Formatted message
 */
function getNotificationMessage($notification)
{
    global $conn;

    $type = $notification['type'];
    $product_name = $notification['product_name'] ?? 'a product';
    $post_id = $notification['post_id'];

    // Get the current user's role from session to determine message context
    $current_user_role = $_SESSION['role'] ?? 'user';

    switch ($type) {
        case 'comment':
            if ($current_user_role == 'farmer') {
                // Farmer receives notification about new bid
                return "New bid placed on '{$product_name}'";
            } else {
                // Buyer receives notification about being outbid
                return "You've been outbid on '{$product_name}' - Place a higher bid!";
            }

        case 'comment_approved':
            if ($current_user_role == 'farmer') {
                // This shouldn't happen anymore, but just in case
                return "Sale completed for '{$product_name}'";
            } else {
                // Buyer won the bid
                return "🎉 Congratulations! You won the bid for '{$product_name}'";
            }

        default:
            return "New notification about '{$product_name}'";
    }
}
