<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/notification_functions.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . BASE_URL . 'index.php?auth=login');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle mark as read action
if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['id'])) {
    $notification_id = (int)$_GET['id'];
    $redirect_target = isset($_GET['redirect']) ? trim((string)$_GET['redirect']) : 'notifications.php';
    $is_safe_redirect = $redirect_target !== ''
        && strpos($redirect_target, '://') === false
        && strpos($redirect_target, "\n") === false
        && strpos($redirect_target, "\r") === false;

    if ($notification_id > 0) {
        markNotificationAsRead($notification_id, $user_id);
    }
    header('Location: ' . ($is_safe_redirect ? $redirect_target : 'notifications.php'));
    exit;
}

// Handle mark all as read
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    markAllNotificationsAsRead($user_id);
    header('Location: notifications.php');
    exit;
}

// Get all notifications for the user
$notifications = getUserNotifications($user_id, 50);
$unread_count  = getUnreadNotificationCount($user_id);
$total_count   = count($notifications);
$read_count    = $total_count - $unread_count;

// Helper: pick icon + color per type
function notifMeta(string $type): array
{
    $map = [
        'comment'           => ['icon' => 'fa-comment-dots',   'color' => '#6366f1', 'bg' => '#ede9fe', 'label' => 'Bid Update'],
        'auction_won'       => ['icon' => 'fa-trophy',         'color' => '#059669', 'bg' => '#d1fae5', 'label' => 'You Won'],
        'comment_approved'  => ['icon' => 'fa-check-circle',   'color' => '#059669', 'bg' => '#d1fae5', 'label' => 'You Won'],
        'announcement'      => ['icon' => 'fa-bullhorn',       'color' => '#7c3aed', 'bg' => '#ede9fe', 'label' => 'Announcement'],
        'product_sold'      => ['icon' => 'fa-hands-helping',  'color' => '#0ea5e9', 'bg' => '#e0f2fe', 'label' => 'Action Required'],
        'delivery_local_selected'    => ['icon' => 'fa-map-marker-alt', 'color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => 'Local Selected'],
        'delivery_courier_selected'  => ['icon' => 'fa-shipping-fast',  'color' => '#0ea5e9', 'bg' => '#e0f2fe', 'label' => 'Courier Selected'],
        'delivery_tracking_added'    => ['icon' => 'fa-barcode',        'color' => '#0284c7', 'bg' => '#e0f2fe', 'label' => 'Tracking Added'],
        'delivery_local_otp_required' => ['icon' => 'fa-key',            'color' => '#d97706', 'bg' => '#fef3c7', 'label' => 'OTP Required'],
        'farmer_delivery_local_selected' => ['icon' => 'fa-map-marker-alt', 'color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => 'Local Selected'],
        'farmer_delivery_courier_selected' => ['icon' => 'fa-shipping-fast', 'color' => '#0ea5e9', 'bg' => '#e0f2fe', 'label' => 'Courier Selected'],
        'farmer_tracking_added' => ['icon' => 'fa-barcode', 'color' => '#0284c7', 'bg' => '#e0f2fe', 'label' => 'Tracking Added'],
        'farmer_order_delivered' => ['icon' => 'fa-check-double', 'color' => '#16a34a', 'bg' => '#dcfce7', 'label' => 'Delivered'],
        'delivery_local_initiated'   => ['icon' => 'fa-shield-alt',     'color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => 'Local Delivery'],
        'delivery_courier_initiated' => ['icon' => 'fa-shipping-fast',  'color' => '#0ea5e9', 'bg' => '#e0f2fe', 'label' => 'Shipped'],
        'delivery_delivered'         => ['icon' => 'fa-check-double',   'color' => '#16a34a', 'bg' => '#dcfce7', 'label' => 'Delivered'],
        'followed_farmer_post' => ['icon' => 'fa-seedling',    'color' => '#10b981', 'bg' => '#dcfce7', 'label' => 'New Listing'],
        'bid'               => ['icon' => 'fa-gavel',           'color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => 'Bid'],
        'sale'              => ['icon' => 'fa-shopping-bag',    'color' => '#10b981', 'bg' => '#d1fae5', 'label' => 'Sale'],
        'order'             => ['icon' => 'fa-box',             'color' => '#0ea5e9', 'bg' => '#e0f2fe', 'label' => 'Order'],
        'review'            => ['icon' => 'fa-star',            'color' => '#f59e0b', 'bg' => '#fef9c3', 'label' => 'Review'],
        'account_warning'   => ['icon' => 'fa-triangle-exclamation', 'color' => '#d97706', 'bg' => '#fef3c7', 'label' => 'Warning'],
        'account_banned'    => ['icon' => 'fa-user-slash',      'color' => '#dc2626', 'bg' => '#fee2e2', 'label' => 'Ban Notice'],
    ];
    return $map[$type] ?? ['icon' => 'fa-bell', 'color' => '#6b7280', 'bg' => '#f3f4f6', 'label' => ucfirst(str_replace('_', ' ', $type))];
}

function notifPriority(string $type): string
{
    $success = ['auction_won', 'comment_approved', 'delivery_delivered', 'farmer_order_delivered'];
    $warning = ['delivery_local_otp_required'];

    if (in_array($type, $success, true)) return 'success';
    if (in_array($type, $warning, true)) return 'warning';
    if (in_array($type, ['account_warning', 'account_banned'], true)) return 'danger';
    if ($type === 'announcement') return 'info';
    return 'info';
}

function notifReadUrl(array $notification): string
{
    $type = (string)($notification['type'] ?? '');
    $post_id = (int)($notification['post_id'] ?? 0);
    $base = 'notifications.php?action=mark_read&id=' . (int)$notification['id'];
    $current_user_role = $_SESSION['role'] ?? 'user';

    $farmer_focus_types = [
        'product_sold',
        'farmer_delivery_local_selected',
        'farmer_delivery_courier_selected',
        'farmer_tracking_added',
        'farmer_order_delivered',
    ];

    if ($post_id > 0) {
        if ($current_user_role === 'farmer' && in_array($type, $farmer_focus_types, true)) {
            $base .= '&redirect=' . urlencode('farmer/manage_orders.php?focus_post=' . $post_id);
        } else {
            $base .= '&redirect=' . urlencode('product_detail.php?id=' . $post_id);
        }
    }

    return $base;
}

// Relative time helper
function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)       return 'Just now';
    if ($diff < 3600)     return floor($diff / 60)   . 'm ago';
    if ($diff < 86400)    return floor($diff / 3600)  . 'h ago';
    if ($diff < 604800)   return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

include 'includes/nav.php';
?>

<style>
    /* ── Page Shell ── */
    .notif-page {
        min-height: calc(100vh - 64px);
        background: #f1f5f9;
        padding: 36px 0 60px;
    }

    /* ── Header Banner ── */
    .notif-hero {
        background: linear-gradient(135deg, #065f46 0%, #059669 60%, #10b981 100%);
        border-radius: 20px;
        padding: 30px 32px;
        margin-bottom: 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
        box-shadow: 0 8px 32px rgba(5, 150, 105, 0.25);
    }

    .notif-hero-left {
        display: flex;
        align-items: center;
        gap: 18px;
    }

    .notif-hero-icon {
        width: 56px;
        height: 56px;
        background: rgba(255, 255, 255, 0.18);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #fff;
        flex-shrink: 0;
    }

    .notif-hero-title {
        color: #fff;
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        line-height: 1.2;
    }

    .notif-hero-sub {
        color: rgba(255, 255, 255, 0.72);
        font-size: 0.875rem;
        margin-top: 3px;
    }

    .notif-mark-all-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 600;
        color: #059669;
        background: #fff;
        border: none;
        cursor: pointer;
        text-decoration: none;
        white-space: nowrap;
        transition: background 0.18s, transform 0.15s;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .notif-mark-all-btn:hover {
        background: #f0fdf4;
        color: #047857;
        text-decoration: none;
        transform: translateY(-1px);
    }

    /* ── Stats Row ── */
    .notif-stats {
        display: flex;
        gap: 14px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .notif-stat-card {
        flex: 1;
        min-width: 120px;
        background: #fff;
        border-radius: 14px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 1px 6px rgba(0, 0, 0, 0.06);
    }

    .notif-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .notif-stat-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: #1e293b;
        line-height: 1;
    }

    .notif-stat-label {
        font-size: 0.72rem;
        color: #94a3b8;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 2px;
    }

    /* ── Filter Tabs ── */
    .notif-tabs {
        display: flex;
        gap: 6px;
        background: #fff;
        border-radius: 12px;
        padding: 6px;
        margin-bottom: 18px;
        box-shadow: 0 1px 6px rgba(0, 0, 0, 0.06);
        width: fit-content;
    }

    .notif-tab {
        padding: 7px 18px;
        border-radius: 8px;
        font-size: 0.855rem;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        border: none;
        background: transparent;
        transition: all 0.18s;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .notif-tab.active {
        background: #059669;
        color: #fff;
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
    }

    .notif-tab:hover:not(.active) {
        background: #f1f5f9;
        color: #374151;
    }

    .notif-tab .tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        border-radius: 10px;
        font-size: 0.68rem;
        font-weight: 700;
        background: rgba(0, 0, 0, 0.08);
        padding: 0 5px;
    }

    .notif-tab.active .tab-count {
        background: rgba(255, 255, 255, 0.25);
    }

    /* ── Notification List ── */
    .notif-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    /* ── Notification Item ── */
    .notif-item {
        background: #fff;
        border-radius: 14px;
        padding: 16px 18px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        box-shadow: 0 1px 6px rgba(0, 0, 0, 0.05);
        border: 1.5px solid transparent;
        transition: box-shadow 0.18s, border-color 0.18s, transform 0.15s;
        position: relative;
        cursor: default;
    }

    .notif-item:hover {
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.09);
        transform: translateY(-1px);
    }

    .notif-item.unread {
        border-color: #d1fae5;
        background: #f0fdf4;
    }

    .notif-item.unread::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        height: 60%;
        width: 3px;
        border-radius: 0 3px 3px 0;
        background: #059669;
    }

    .notif-item.priority-success {
        border-color: #bbf7d0;
    }

    .notif-item.priority-info {
        border-color: #bfdbfe;
    }

    .notif-item.priority-warning {
        border-color: #fde68a;
    }

    .notif-item.unread.priority-success::before {
        background: #16a34a;
    }

    .notif-item.unread.priority-info::before {
        background: #0284c7;
    }

    .notif-item.unread.priority-warning::before {
        background: #d97706;
    }

    .notif-priority-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .notif-priority-chip.success {
        background: #dcfce7;
        color: #166534;
    }

    .notif-priority-chip.info {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .notif-priority-chip.warning {
        background: #fef3c7;
        color: #92400e;
    }

    /* ── Notif Icon ── */
    .notif-icon-wrap {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    /* ── Notif Body ── */
    .notif-body {
        flex: 1;
        min-width: 0;
    }

    .notif-message {
        font-size: 0.9rem;
        color: #1e293b;
        font-weight: 500;
        line-height: 1.45;
        margin-bottom: 6px;
    }

    .notif-message a:hover {
        text-decoration: underline !important;
        color: #059669;
    }

    .notif-item.unread .notif-message {
        font-weight: 600;
    }

    .notif-meta-row {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .notif-type-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }

    .notif-product {
        font-size: 0.78rem;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .notif-time {
        font-size: 0.75rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* ── Mark Read Button ── */
    .notif-read-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 9px;
        border: 1.5px solid #d1fae5;
        background: transparent;
        color: #059669;
        font-size: 0.78rem;
        cursor: pointer;
        text-decoration: none;
        flex-shrink: 0;
        transition: all 0.15s;
        align-self: center;
    }

    .notif-read-btn:hover {
        background: #059669;
        color: #fff;
        border-color: #059669;
        text-decoration: none;
        transform: scale(1.08);
    }

    /* ── Read Tag ── */
    .notif-read-tag {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        color: #cbd5e1;
        align-self: center;
        flex-shrink: 0;
    }

    /* ── Empty State ── */
    .notif-empty {
        background: #fff;
        border-radius: 18px;
        padding: 60px 30px;
        text-align: center;
        box-shadow: 0 1px 6px rgba(0, 0, 0, 0.06);
    }

    .notif-empty-icon {
        width: 80px;
        height: 80px;
        border-radius: 24px;
        background: #f0fdf4;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: #059669;
        margin: 0 auto 20px;
    }

    .notif-empty-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 8px;
    }

    .notif-empty-sub {
        font-size: 0.88rem;
        color: #94a3b8;
        max-width: 320px;
        margin: 0 auto;
        line-height: 1.55;
    }

    /* ── Date Group Label ── */
    .notif-date-group {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        color: #94a3b8;
        padding: 6px 4px 2px;
        margin-top: 6px;
    }

    @media (max-width: 600px) {
        .notif-hero {
            padding: 20px 18px;
        }

        .notif-hero-title {
            font-size: 1.15rem;
        }

        .notif-item {
            padding: 14px 14px;
        }
    }
</style>

<div class="notif-page">
    <div class="container" style="max-width:820px;">

        <!-- Hero Header -->
        <div class="notif-hero">
            <div class="notif-hero-left">
                <div class="notif-hero-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <div>
                    <div class="notif-hero-title">
                        Notifications
                        <?php if ($unread_count > 0): ?>
                            <span style="display:inline-flex;align-items:center;justify-content:center;background:#ef4444;color:#fff;font-size:0.72rem;font-weight:700;min-width:22px;height:22px;border-radius:11px;padding:0 6px;margin-left:8px;vertical-align:middle;"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="notif-hero-sub">Stay up to date with your activity</div>
                </div>
            </div>
            <?php if ($unread_count > 0): ?>
                <a href="notifications.php?action=mark_all_read" class="notif-mark-all-btn">
                    <i class="fas fa-check-double"></i> Mark all as read
                </a>
            <?php endif; ?>
        </div>

        <!-- Stats Row -->
        <div class="notif-stats">
            <div class="notif-stat-card">
                <div class="notif-stat-icon" style="background:#f0fdf4;color:#059669;"><i class="fas fa-bell"></i></div>
                <div>
                    <div class="notif-stat-value"><?php echo $total_count; ?></div>
                    <div class="notif-stat-label">Total</div>
                </div>
            </div>
            <div class="notif-stat-card">
                <div class="notif-stat-icon" style="background:#fef3c7;color:#f59e0b;"><i class="fas fa-circle"></i></div>
                <div>
                    <div class="notif-stat-value"><?php echo $unread_count; ?></div>
                    <div class="notif-stat-label">Unread</div>
                </div>
            </div>
            <div class="notif-stat-card">
                <div class="notif-stat-icon" style="background:#e0f2fe;color:#0ea5e9;"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="notif-stat-value"><?php echo $read_count; ?></div>
                    <div class="notif-stat-label">Read</div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="notif-tabs" id="notifTabs">
            <button class="notif-tab active" data-filter="all">
                All <span class="tab-count"><?php echo $total_count; ?></span>
            </button>
            <button class="notif-tab" data-filter="unread">
                Unread <span class="tab-count"><?php echo $unread_count; ?></span>
            </button>
            <button class="notif-tab" data-filter="read">
                Read <span class="tab-count"><?php echo $read_count; ?></span>
            </button>
        </div>

        <!-- Notification List -->
        <?php if (empty($notifications)): ?>
            <div class="notif-empty">
                <div class="notif-empty-icon"><i class="fas fa-bell-slash"></i></div>
                <div class="notif-empty-title">All caught up!</div>
                <div class="notif-empty-sub">You don't have any notifications yet. Activity about bids, comments, and sales will appear here.</div>
            </div>
        <?php else: ?>
            <div class="notif-list" id="notifList">
                <?php
                $prev_date = '';
                foreach ($notifications as $notification):
                    $meta     = notifMeta($notification['type']);
                    $priority = notifPriority($notification['type']);
                    $is_read  = (bool)$notification['is_read'];
                    $group_count = (int)($notification['group_count'] ?? 1);
                    $date_key = date('Y-m-d', strtotime($notification['created_at']));
                    $today    = date('Y-m-d');
                    $yesterday = date('Y-m-d', strtotime('-1 day'));

                    if ($date_key !== $prev_date):
                        $prev_date = $date_key;
                        if ($date_key === $today) $group_label = 'Today';
                        elseif ($date_key === $yesterday) $group_label = 'Yesterday';
                        else $group_label = date('F j, Y', strtotime($notification['created_at']));
                ?>
                        <div class="notif-date-group notif-filter-item
            <?php echo $is_read ? 'filter-read' : 'filter-unread'; ?>"><?php echo $group_label; ?></div>
                    <?php endif; ?>

                    <div class="notif-item priority-<?php echo $priority; ?> <?php echo $is_read ? '' : 'unread'; ?> notif-filter-item <?php echo $is_read ? 'filter-read' : 'filter-unread'; ?>">
                        <!-- Icon -->
                        <div class="notif-icon-wrap" style="background:<?php echo $meta['bg']; ?>;color:<?php echo $meta['color']; ?>;">
                            <i class="fas <?php echo $meta['icon']; ?>"></i>
                        </div>

                        <!-- Body -->
                        <div class="notif-body">
                            <div class="notif-message">
                                <?php if (!empty($notification['post_id'])): ?>
                                    <a href="<?php echo htmlspecialchars(notifReadUrl($notification)); ?>" style="color:inherit; text-decoration:none;">
                                        <?php echo htmlspecialchars(getNotificationMessage($notification)); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars(getNotificationMessage($notification)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="notif-meta-row">
                                <span class="notif-type-tag" style="background:<?php echo $meta['bg']; ?>;color:<?php echo $meta['color']; ?>;">
                                    <?php echo $meta['label']; ?>
                                </span>
                                <span class="notif-priority-chip <?php echo $priority; ?>">
                                    <?php echo ucfirst($priority); ?>
                                </span>
                                <?php if (!empty($notification['product_name'])): ?>
                                    <span class="notif-product">
                                        <i class="fas fa-seedling" style="color:#059669;font-size:0.7rem;"></i>
                                        <?php echo htmlspecialchars($notification['product_name']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($notification['type'] === 'comment' && $group_count > 1): ?>
                                    <span class="notif-priority-chip info">x<?php echo $group_count; ?></span>
                                <?php endif; ?>
                                <span class="notif-time">
                                    <i class="far fa-clock"></i>
                                    <?php echo timeAgo($notification['created_at']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Action -->
                        <?php if (!$is_read): ?>
                            <a href="<?php echo htmlspecialchars(notifReadUrl($notification)); ?>"
                                class="notif-read-btn" title="Mark as read">
                                <i class="fas fa-check"></i>
                            </a>
                        <?php else: ?>
                            <div class="notif-read-tag">
                                <i class="fas fa-check-double"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty filter state -->
            <div class="notif-empty" id="notifFilterEmpty" style="display:none;">
                <div class="notif-empty-icon"><i class="fas fa-filter"></i></div>
                <div class="notif-empty-title">Nothing here</div>
                <div class="notif-empty-sub">No notifications match this filter.</div>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
    (function() {
        const tabs = document.querySelectorAll('.notif-tab');
        const items = document.querySelectorAll('.notif-filter-item');
        const empty = document.getElementById('notifFilterEmpty');

        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                const filter = this.dataset.filter;
                let visible = 0;

                items.forEach(item => {
                    const show = filter === 'all' ||
                        (filter === 'unread' && item.classList.contains('filter-unread')) ||
                        (filter === 'read' && item.classList.contains('filter-read'));
                    item.style.display = show ? '' : 'none';
                    if (show && item.classList.contains('notif-item')) visible++;
                });

                if (empty) empty.style.display = (visible === 0) ? 'block' : 'none';
            });
        });
    })();
</script>

<?php include 'includes/footer.php'; ?>