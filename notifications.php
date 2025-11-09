<?php
session_start();
require_once 'includes/notification_functions.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = ($_SESSION['role'] === 'user') ? 'buyer' : $_SESSION['role'];

// Handle mark as read action
if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['id'])) {
    markNotificationAsRead($_GET['id'], $user_id);
    header('Location: notifications.php');
    exit;
}

// Handle mark all as read
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    markAllNotificationsAsRead($user_id, $user_role);
    header('Location: notifications.php');
    exit;
}

// Get all notifications for the user
$notifications = getUserNotifications($user_id, $user_role, 50);
$unread_count = getUnreadNotificationCount($user_id, $user_role);

include 'includes/nav.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-bell me-2"></i>Notifications
                        <?php if ($unread_count > 0): ?>
                            <span class="badge badge-danger"><?php echo $unread_count; ?> unread</span>
                        <?php endif; ?>
                    </h4>
                    <?php if ($unread_count > 0): ?>
                        <a href="notifications.php?action=mark_all_read" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-check-double me-1"></i>Mark All as Read
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No notifications yet</h5>
                            <p class="text-muted">You'll receive notifications about bids, sales, and updates here.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="list-group-item <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <div class="mb-1">
                                            <p class="mb-1 <?php echo $notification['is_read'] ? '' : 'font-weight-bold'; ?>">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>
                                            <small class="text-muted">
                                                <i class="fas fa-tag me-1"></i><?php echo ucfirst(str_replace('_', ' ', $notification['type'])); ?>
                                                <?php if ($notification['product_name']): ?>
                                                    | <i class="fas fa-box me-1"></i><?php echo htmlspecialchars($notification['product_name']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <small class="text-muted me-3">
                                                <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                            </small>
                                            <?php if (!$notification['is_read']): ?>
                                                <a href="notifications.php?action=mark_read&id=<?php echo $notification['id']; ?>"
                                                    class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .list-group-item {
        border-left: 4px solid transparent;
        transition: all 0.3s ease;
    }

    .list-group-item:hover {
        background-color: #f8f9fa !important;
    }

    .list-group-item:not(.bg-light) {
        border-left-color: #28a745;
    }

    .bg-light {
        border-left-color: #007bff;
    }

    .font-weight-bold {
        font-weight: bold !important;
    }
</style>

<?php include 'includes/footer.php'; ?>