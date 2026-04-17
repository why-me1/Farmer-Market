<?php

/**
 * Notification System Functions
 * Handles all notification-related operations
 */

require_once 'db.php';

function ensureNotificationSchema()
{
    global $conn;

    $conn->query("ALTER TABLE `notifications`
        ADD COLUMN IF NOT EXISTS `group_count` INT NOT NULL DEFAULT 1");
}

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

    ensureNotificationSchema();

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, post_id, comment_id, type, group_count) VALUES (?, ?, ?, ?, 1)");
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
    // Keep only one unread bid notification per product for the farmer.
    // When more bids arrive, refresh the timestamp instead of stacking duplicates.
    global $conn;

    ensureNotificationSchema();

    $check = $conn->prepare("SELECT id, group_count FROM notifications WHERE user_id = ? AND post_id = ? AND type = 'comment' AND is_read = 0 LIMIT 1");
    $check->bind_param("ii", $farmer_id, $post_id);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        $update = $conn->prepare("UPDATE notifications SET group_count = group_count + 1, created_at = CURRENT_TIMESTAMP WHERE id = ?");
        $update->bind_param("i", $existing['id']);
        $update->execute();
        $update->close();

        return true;
    }

    // Create notification with type 'comment' for the first/new unread bid alert
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
    // Keep one unread outbid notification per product for the buyer and increment count.
    global $conn;

    ensureNotificationSchema();

    $check = $conn->prepare("SELECT id FROM notifications WHERE user_id = ? AND post_id = ? AND type = 'comment' AND is_read = 0 LIMIT 1");
    $check->bind_param("ii", $buyer_id, $post_id);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        $update = $conn->prepare("UPDATE notifications SET group_count = group_count + 1, created_at = CURRENT_TIMESTAMP WHERE id = ?");
        $update->bind_param("i", $existing['id']);
        $update->execute();
        $update->close();

        return true;
    }

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
    // Create a dedicated winner notification with next-step context
    return createNotification($buyer_id, $post_id, null, 'auction_won');
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
    // Create notification for specific delivery stage
    $status = strtolower(trim((string)$status));
    if ($status === 'local') {
        return createNotification($buyer_id, $post_id, null, 'delivery_local_selected');
    }
    if ($status === 'courier') {
        return createNotification($buyer_id, $post_id, null, 'delivery_courier_selected');
    }
    if ($status === 'delivered') {
        return createNotification($buyer_id, $post_id, null, 'delivery_delivered');
    }

    return createNotification($buyer_id, $post_id, null, 'order');
}

/**
 * Get delivery fields for a post to build contextual messages.
 * @param int $post_id
 * @return array|null
 */
function getPostDeliveryMeta($post_id)
{
    global $conn;

    if (empty($post_id)) {
        return null;
    }

    $stmt = $conn->prepare("SELECT courier_company, courier_tracking, delivery_type FROM posts WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
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
    $group_count = (int)($notification['group_count'] ?? 1);

    // Get the current user's role from session to determine message context
    $current_user_role = $_SESSION['role'] ?? 'user';

    switch ($type) {
        case 'comment':
            if ($current_user_role == 'farmer') {
                // Farmer receives notification about new bid
                if ($group_count > 1) {
                    return "{$group_count} new bids placed on {$product_name}";
                }

                return "New bid placed on {$product_name}";
            } else {
                // Buyer receives notification about being outbid
                if ($group_count > 1) {
                    return "You were outbid {$group_count} times on {$product_name} - place a higher bid now.";
                }
                return "You were outbid on {$product_name} - place a higher bid now.";
            }

        case 'auction_won':
            return "You won the bid for {$product_name} 🌽 - farmer will update delivery soon.";

        case 'comment_approved':
            // Backward compatibility for old winner records.
            return "You won the bid for {$product_name} 🌽 - farmer will update delivery soon.";

        case 'product_sold':
            return "Your auction for {$product_name} is successful. Choose delivery method now (Local or Courier).";

        case 'delivery_local_selected':
            return "Farmer selected Local Delivery for {$product_name} - delivery agent will contact you.";

        case 'delivery_courier_selected':
            return "Farmer selected Courier Delivery 🚚 for {$product_name}.";

        case 'delivery_tracking_added':
            $delivery_meta = getPostDeliveryMeta((int)$post_id);
            $tracking_id = $delivery_meta['courier_tracking'] ?? '';
            if (!empty($tracking_id)) {
                return "Tracking ID {$tracking_id} added 🚚 - track your order.";
            }
            return "Tracking ID added 🚚 - track your order.";

        case 'delivery_local_otp_required':
            return "Delivery agent will ask for OTP on delivery for {$product_name}.";

        case 'farmer_delivery_local_selected':
            return "You selected Local Delivery for {$product_name}.";

        case 'farmer_delivery_courier_selected':
            return "You selected Courier Delivery for {$product_name} 🚚.";

        case 'farmer_tracking_added':
            $delivery_meta = getPostDeliveryMeta((int)$post_id);
            $tracking_id = $delivery_meta['courier_tracking'] ?? '';
            if (!empty($tracking_id)) {
                return "Tracking ID {$tracking_id} added successfully.";
            }
            return "Tracking ID added successfully.";

        case 'farmer_order_delivered':
            return "Order for {$product_name} marked as delivered.";

        case 'delivery_local_initiated':
            // Backward compatibility for old records.
            return "Farmer selected Local Delivery for {$product_name} - delivery agent will contact you.";

        case 'delivery_courier_initiated':
            // Backward compatibility for old records.
            return "Farmer selected Courier Delivery 🚚 for {$product_name}.";

        case 'delivery_delivered':
            return "Delivery completed for {$product_name}. Please rate your experience.";

        case 'followed_farmer_post':
            $stmt = $conn->prepare("SELECT u.username, p.auction_start_date FROM posts p JOIN users u ON p.farmer_id = u.id WHERE p.id = ? LIMIT 1");
            $stmt->bind_param("i", $post_id);
            $stmt->execute();
            $post_row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($post_row) {
                $farmer_name = ucfirst($post_row['username']);
                $auction_start = strtotime($post_row['auction_start_date']);
                $start_label = date('M j \a\t g:i A', $auction_start);

                return "New auction: {$product_name} by {$farmer_name} 🌽 - starts {$start_label}. View auction now.";
            }

            return "New auction listed: {$product_name} 🌽. View auction now.";

        default:
            return "New notification about '{$product_name}'";
    }
}
