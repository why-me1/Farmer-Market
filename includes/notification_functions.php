<?php

/**
 * Notification System Functions
 * Handles all notification-related operations
 */

require_once 'db.php';

/**
 * Create a new notification
 * @param int $user_id - Receiver ID
 * @param string $user_role - 'farmer' or 'buyer'
 * @param int $product_id - Related product ID
 * @param string $message - Notification message
 * @param string $type - Type of event
 * @return bool - Success status
 */
function createNotification($user_id, $user_role, $product_id, $message, $type)
{
    global $conn;

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, product_id, message, type) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isiss", $user_id, $user_role, $product_id, $message, $type);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Get notifications for a specific user
 * @param int $user_id - User ID
 * @param string $user_role - User role
 * @param int $limit - Number of notifications to fetch
 * @return array - Array of notifications
 */
function getUserNotifications($user_id, $user_role, $limit = 10)
{
    global $conn;

    $stmt = $conn->prepare("SELECT n.*, p.product_name FROM notifications n 
                          LEFT JOIN posts p ON n.product_id = p.id 
                          WHERE n.user_id = ? AND n.user_role = ? 
                          ORDER BY n.created_at DESC 
                          LIMIT ?");
    $stmt->bind_param("isi", $user_id, $user_role, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $notifications;
}

/**
 * Get unread notification count for a user
 * @param int $user_id - User ID
 * @param string $user_role - User role
 * @return int - Count of unread notifications
 */
function getUnreadNotificationCount($user_id, $user_role)
{
    global $conn;

    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND user_role = ? AND is_read = 0");
    $stmt->bind_param("is", $user_id, $user_role);
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
 * @param string $user_role - User role
 * @return bool - Success status
 */
function markAllNotificationsAsRead($user_id, $user_role)
{
    global $conn;

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_role = ?");
    $stmt->bind_param("is", $user_id, $user_role);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Notification Types and their handlers
 */

/**
 * Notify farmer when buyer places a bid
 * @param int $farmer_id - Farmer ID
 * @param int $product_id - Product ID
 * @param string $buyer_name - Buyer's name
 * @param float $bid_amount - Bid amount
 * @param string $product_name - Product name
 */
function notifyFarmerBidPlaced($farmer_id, $product_id, $buyer_name, $bid_amount, $product_name)
{
    $message = "User {$buyer_name} placed a bid of {$bid_amount} BDT on your product '{$product_name}'.";
    return createNotification($farmer_id, 'farmer', $product_id, $message, 'bid_placed');
}

/**
 * Notify farmer when product is sold
 * @param int $farmer_id - Farmer ID
 * @param int $product_id - Product ID
 * @param string $buyer_name - Buyer's name
 * @param string $product_name - Product name
 */
function notifyFarmerProductSold($farmer_id, $product_id, $buyer_name, $product_name)
{
    $message = "Your product '{$product_name}' has been sold to user {$buyer_name}.";
    return createNotification($farmer_id, 'farmer', $product_id, $message, 'product_sold');
}

/**
 * Notify buyer when outbid
 * @param int $buyer_id - Buyer ID
 * @param int $product_id - Product ID
 * @param string $product_name - Product name
 */
function notifyBuyerOutbid($buyer_id, $product_id, $product_name)
{
    $message = "Someone placed a higher bid on '{$product_name}'. Place a new bid before it ends!";
    return createNotification($buyer_id, 'buyer', $product_id, $message, 'outbid');
}

/**
 * Notify buyer when they win the bid
 * @param int $buyer_id - Buyer ID
 * @param int $product_id - Product ID
 * @param string $product_name - Product name
 */
function notifyBuyerWonBid($buyer_id, $product_id, $product_name)
{
    $message = "Congratulations! You won the bid for '{$product_name}'.";
    return createNotification($buyer_id, 'buyer', $product_id, $message, 'bid_won');
}

/**
 * Notify buyer about delivery update
 * @param int $buyer_id - Buyer ID
 * @param int $product_id - Product ID
 * @param string $product_name - Product name
 * @param string $status - Delivery status
 */
function notifyBuyerDeliveryUpdate($buyer_id, $product_id, $product_name, $status)
{
    $message = "Your order '{$product_name}' is marked as {$status}.";
    return createNotification($buyer_id, 'buyer', $product_id, $message, 'delivery_update');
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
