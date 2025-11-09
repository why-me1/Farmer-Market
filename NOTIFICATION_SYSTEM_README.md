# Notification System Documentation

## Overview

This notification system provides real-time notifications for farmers and buyers in the farmer market platform. The system tracks various events and sends appropriate notifications to users.

## Database Structure

### Notifications Table

```sql
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Receiver ID',
  `user_role` enum('farmer','buyer') NOT NULL COMMENT 'farmer / buyer',
  `product_id` int(11) DEFAULT NULL COMMENT 'Related product',
  `message` text NOT NULL COMMENT 'Text content',
  `type` varchar(50) NOT NULL COMMENT 'Type of event',
  `is_read` tinyint(1) DEFAULT 0 COMMENT '0 = unread, 1 = read',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date/time',
  PRIMARY KEY (`id`)
);
```

## Notification Types

### For Farmers

1. **Bid Placed** (`bid_placed`)

   - **Trigger**: When a buyer places a bid on farmer's product
   - **Message**: "User [Buyer Name] placed a bid of [Amount] BDT on your product '[Product Name]'."

2. **Product Sold** (`product_sold`)
   - **Trigger**: When farmer approves a bid (product is sold)
   - **Message**: "Your product '[Product Name]' has been sold to user [Buyer Name]."

### For Buyers

1. **Outbid** (`outbid`)

   - **Trigger**: When someone places a higher bid on a product they're bidding on
   - **Message**: "Someone placed a higher bid on '[Product Name]'. Place a new bid before it ends!"

2. **Bid Won** (`bid_won`)

   - **Trigger**: When bidding ends and user is the top bidder
   - **Message**: "Congratulations! You won the bid for '[Product Name]'."

3. **Delivery Update** (`delivery_update`)
   - **Trigger**: When farmer updates order status
   - **Message**: "Your order '[Product Name]' is marked as [Status]."

## Files Structure

### Core Files

- `includes/notification_functions.php` - Main notification functions
- `fetch_notifications.php` - AJAX endpoint for real-time notifications
- `notifications.php` - Full notifications page
- `update_notifications_table.sql` - Database schema update

### Integration Points

- `comment.php` - Triggers bid notifications
- `farmer/manage_comments.php` - Triggers sale notifications
- `farmer/manage_orders.php` - Triggers delivery notifications
- `includes/nav.php` - Displays notification count and dropdown

## Usage

### Creating Notifications

```php
// Include the notification functions
require_once 'includes/notification_functions.php';

// Create a simple notification
createNotification($user_id, $user_role, $product_id, $message, $type);

// Use specific notification functions
notifyFarmerBidPlaced($farmer_id, $product_id, $buyer_name, $bid_amount, $product_name);
notifyBuyerOutbid($buyer_id, $product_id, $product_name);
```

### Retrieving Notifications

```php
// Get user notifications
$notifications = getUserNotifications($user_id, $user_role, $limit);

// Get unread count
$count = getUnreadNotificationCount($user_id, $user_role);
```

### Marking as Read

```php
// Mark single notification as read
markNotificationAsRead($notification_id, $user_id);

// Mark all notifications as read
markAllNotificationsAsRead($user_id, $user_role);
```

## Features

### Real-time Updates

- AJAX-powered notification dropdown in navigation
- Automatic refresh every 10 seconds
- Real-time unread count display

### User Interface

- Clean notification list with timestamps
- Mark individual notifications as read
- Mark all notifications as read
- Visual indicators for unread notifications
- Product name and type information

### Security

- User-specific notification access
- Role-based notification filtering
- SQL injection protection with prepared statements

## Setup Instructions

1. **Update Database**

   ```bash
   mysql -u root farmer_market < update_notifications_table.sql
   ```

2. **Test the System**

   - Visit `test_notifications.php` to verify functionality
   - Check notification creation and retrieval

3. **Integration**
   - The system is already integrated into existing bidding flow
   - Notifications appear in navigation dropdown
   - Full notifications page available at `/notifications.php`

## Testing

Run the test script to verify all functionality:

```bash
# Visit in browser
http://localhost/demo/test_notifications.php
```

## Troubleshooting

### Common Issues

1. **Notifications not appearing**: Check database connection and user session
2. **AJAX errors**: Verify `fetch_notifications.php` is accessible
3. **Permission errors**: Ensure proper file permissions on notification files

### Debug Mode

Enable debug mode by adding error reporting to notification functions:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Future Enhancements

1. **Email Notifications**: Send email alerts for important notifications
2. **Push Notifications**: Browser push notifications for real-time updates
3. **Notification Preferences**: User settings for notification types
4. **Bulk Actions**: Mark multiple notifications as read
5. **Notification History**: Archive old notifications
