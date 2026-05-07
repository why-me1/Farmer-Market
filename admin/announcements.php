<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/notification_functions.php';
check_login();
global $conn;

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$admin_name = $_SESSION['username'] ?? 'Admin';
$toast = '';
$toast_type = 'success';
$selected_audiences = ['user', 'farmer'];
$announcement_title = '';
$announcement_message = '';
$sent_count = 0;

ensureNotificationSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $announcement_title = trim((string) ($_POST['announcement_title'] ?? ''));
    $announcement_message = trim((string) ($_POST['announcement_message'] ?? ''));
    $selected_audiences = isset($_POST['audiences']) && is_array($_POST['audiences'])
        ? array_values(array_intersect($_POST['audiences'], ['user', 'farmer', 'admin']))
        : [];

    if ($announcement_title === '' || $announcement_message === '') {
        $toast = 'Please enter both a title and a message.';
        $toast_type = 'danger';
    } elseif (empty($selected_audiences)) {
        $toast = 'Please choose at least one audience.';
        $toast_type = 'danger';
    } else {
        $sent_count = broadcastAnnouncement($announcement_title, $announcement_message, $selected_audiences);

        if ($sent_count > 0) {
            $audience_label = implode(', ', array_map(static function ($role) {
                return ucfirst($role === 'user' ? 'buyer' : $role);
            }, $selected_audiences));
            $toast = 'Announcement sent to ' . $sent_count . ' account' . ($sent_count === 1 ? '' : 's') . ' (' . $audience_label . ').';
            $toast_type = 'success';
            $announcement_title = '';
            $announcement_message = '';
            $selected_audiences = ['user', 'farmer'];
        } else {
            $toast = 'No recipients matched the selected audience.';
            $toast_type = 'danger';
        }
    }
}

$announcement_stats = [
    'broadcasts' => 0,
    'recipients' => 0,
    'latest_sent_at' => null,
];

$stats_result = $conn->query("SELECT COUNT(*) AS broadcasts, COUNT(*) AS recipients, MAX(created_at) AS latest_sent_at FROM notifications WHERE type = 'announcement'");
if ($stats_result) {
    $row = $stats_result->fetch_assoc();
    $announcement_stats['broadcasts'] = (int) ($row['broadcasts'] ?? 0);
    $announcement_stats['recipients'] = (int) ($row['recipients'] ?? 0);
    $announcement_stats['latest_sent_at'] = $row['latest_sent_at'] ?? null;
}

$recent_announcements = [];
$recent_result = $conn->query("SELECT announcement_title, announcement_message, announcement_target, COUNT(*) AS recipient_count, MAX(created_at) AS sent_at FROM notifications WHERE type = 'announcement' GROUP BY announcement_title, announcement_message, announcement_target, created_at ORDER BY sent_at DESC LIMIT 10");
if ($recent_result) {
    while ($row = $recent_result->fetch_assoc()) {
        $recent_announcements[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements &mdash; Farmer Market</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #0f172a;
            --sidebar-accent: #1e293b;
            --primary: #6366f1;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            margin: 0;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-brand {
            padding: 24px 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
        }

        .sidebar-brand h2 {
            font-size: 1.05rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
        }

        .sidebar-brand span {
            font-size: .73rem;
            color: #94a3b8;
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            background: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: #fff;
            margin-bottom: 10px;
        }

        .sidebar-section-label {
            font-size: .63rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #475569;
            padding: 20px 20px 6px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 20px;
            color: #94a3b8;
            text-decoration: none;
            font-size: .875rem;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: all .18s;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            color: #fff;
            background: var(--sidebar-accent);
            border-left-color: var(--primary);
        }

        .sidebar-nav a i {
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px 20px;
            border-top: 1px solid rgba(255, 255, 255, .07);
        }

        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #94a3b8;
            font-size: .83rem;
            text-decoration: none;
            transition: color .18s;
        }

        .sidebar-footer a:hover {
            color: #f87171;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar-title {
            font-size: .95rem;
            font-weight: 600;
            color: #0f172a;
        }

        .topbar-title small {
            display: block;
            font-size: .73rem;
            font-weight: 400;
            color: #94a3b8;
        }

        .admin-badge {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #6366f1, #818cf8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: .85rem;
        }

        .admin-info strong {
            font-size: .85rem;
            color: #0f172a;
            display: block;
        }

        .admin-info span {
            font-size: .72rem;
            color: #94a3b8;
        }

        .page-body {
            padding: 32px;
            flex: 1;
        }

        .hero-banner {
            background: linear-gradient(135deg, #7c3aed 0%, #6366f1 60%, #4f46e5 100%);
            border-radius: 18px;
            padding: 28px 32px;
            color: #fff;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        .hero-banner::after {
            content: '';
            position: absolute;
            right: -40px;
            top: -40px;
            width: 180px;
            height: 180px;
            background: rgba(255, 255, 255, .08);
            border-radius: 50%;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, .14);
            border-radius: 999px;
            padding: 4px 12px;
            font-size: .74rem;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .hero-banner h1 {
            font-size: 1.55rem;
            font-weight: 700;
            margin: 0 0 8px;
        }

        .hero-banner p {
            margin: 0;
            font-size: .9rem;
            opacity: .9;
            max-width: 760px;
        }

        .mini-stat {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            height: 100%;
        }

        .mini-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .mini-stat-icon.purple {
            background: #ede9fe;
            color: #7c3aed;
        }

        .mini-stat-icon.sky {
            background: #e0f2fe;
            color: #0284c7;
        }

        .mini-stat-icon.green {
            background: #dcfce7;
            color: #16a34a;
        }

        .mini-stat-value {
            font-size: 1.45rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }

        .mini-stat-label {
            font-size: .74rem;
            color: #64748b;
            margin-top: 3px;
        }

        .panel-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            height: 100%;
        }

        .panel-header {
            padding: 18px 22px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-header-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .phi-violet {
            background: #ede9fe;
            color: #7c3aed;
        }

        .phi-sky {
            background: #e0f2fe;
            color: #0284c7;
        }

        .panel-header h5 {
            font-size: .94rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .panel-header p {
            font-size: .74rem;
            color: #94a3b8;
            margin: 2px 0 0;
        }

        .panel-body {
            padding: 22px;
        }

        .form-label-custom {
            font-size: .78rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 6px;
            display: block;
        }

        .form-ctrl {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            font-size: .86rem;
            font-family: 'Inter', sans-serif;
            color: #0f172a;
            background: #fff;
            outline: none;
            transition: border-color .18s, box-shadow .18s;
        }

        .form-ctrl:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .12);
        }

        .audience-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .audience-pill {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all .18s;
            background: #fff;
        }

        .audience-pill:hover {
            border-color: #c7d2fe;
            background: #f8fafc;
        }

        .audience-pill input {
            margin-top: 0;
        }

        .audience-pill strong {
            display: block;
            font-size: .84rem;
            color: #0f172a;
        }

        .audience-pill span {
            display: block;
            font-size: .72rem;
            color: #64748b;
        }

        .btn-submit {
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #6366f1, #7c3aed);
            color: #fff;
            font-weight: 600;
            font-size: .9rem;
            padding: 11px 18px;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform .15s, box-shadow .18s;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(99, 102, 241, .24);
        }

        .announcement-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 12px;
        }

        .announcement-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .announcement-card h6 {
            margin: 0 0 4px;
            font-size: .92rem;
            font-weight: 700;
            color: #0f172a;
        }

        .announcement-card p {
            margin: 0;
            font-size: .84rem;
            color: #475569;
            line-height: 1.55;
        }

        .announcement-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            font-size: .74rem;
            color: #64748b;
            margin-top: 10px;
        }

        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            padding: 4px 10px;
        }

        .toast-wrap {
            position: fixed;
            top: 20px;
            right: 24px;
            z-index: 9999;
            display: <?php echo $toast !== '' ? 'block' : 'none'; ?>;
        }

        .toast-msg {
            background: #0f172a;
            color: #fff;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: .83rem;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .18);
            animation: slideIn .3s ease;
        }

        .toast-msg.success {
            border-left: 4px solid #22c55e;
        }

        .toast-msg.danger {
            border-left: 4px solid #ef4444;
        }

        @keyframes slideIn {
            from {
                transform: translateX(60px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .page-footer {
            background: #fff;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            padding: 14px;
            font-size: .76rem;
            color: #94a3b8;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .page-body {
                padding: 16px;
            }

            .topbar {
                padding: 12px 16px;
            }

            .audience-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon"><i class="bi bi-basket2-fill"></i></div>
            <h2>Farmer Market</h2>
            <span>Administration Panel</span>
        </div>

        <div class="sidebar-section-label">Main Menu</div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="manage_users.php"><i class="bi bi-people-fill"></i> Manage Users</a>
            <a href="manage_posts.php"><i class="bi bi-card-list"></i> Manage Posts</a>
            <a href="view_statistics.php"><i class="bi bi-bar-chart-line-fill"></i> Statistics</a>
            <a href="update_market_price.php"><i class="bi bi-tags-fill"></i> Market Prices</a>
            <a href="announcements.php" class="active"><i class="bi bi-megaphone-fill"></i> Announcements</a>
        </nav>

        <div class="sidebar-section-label">Platform</div>
        <nav class="sidebar-nav">
            <a href="../index.php"><i class="bi bi-house-fill"></i> View Site</a>
            <a href="../browse.php"><i class="bi bi-grid-fill"></i> Browse Listings</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php"><i class="bi bi-box-arrow-left"></i> Sign Out</a>
        </div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <div class="topbar-title">
                Announcements
                <small>Broadcast updates to buyers and farmers</small>
            </div>
            <div class="admin-badge">
                <div class="admin-avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
                <div class="admin-info">
                    <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                    <span>Administrator</span>
                </div>
            </div>
        </header>

        <div class="page-body">
            <div class="hero-banner">
                <div class="hero-kicker"><i class="bi bi-megaphone-fill"></i> Broadcast center</div>
                <h1>Send platform-wide announcements</h1>
                <p>Post one message and deliver it as an in-app notification to buyers, farmers, or admins. It uses the existing notification system, so the announcement appears inside the bell menu and notification inbox automatically.</p>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-4">
                    <div class="mini-stat">
                        <div class="mini-stat-icon purple"><i class="bi bi-megaphone-fill"></i></div>
                        <div>
                            <div class="mini-stat-value"><?php echo number_format($announcement_stats['broadcasts']); ?></div>
                            <div class="mini-stat-label">Broadcasts Sent</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-4">
                    <div class="mini-stat">
                        <div class="mini-stat-icon sky"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="mini-stat-value"><?php echo number_format($announcement_stats['recipients']); ?></div>
                            <div class="mini-stat-label">Recipient Rows</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="mini-stat">
                        <div class="mini-stat-icon green"><i class="bi bi-clock-history"></i></div>
                        <div>
                            <div class="mini-stat-value" style="font-size:1.05rem;line-height:1.2;">
                                <?php echo $announcement_stats['latest_sent_at'] ? date('M j, Y', strtotime($announcement_stats['latest_sent_at'])) : 'None yet'; ?>
                            </div>
                            <div class="mini-stat-label">Latest Broadcast</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="panel-card">
                        <div class="panel-header">
                            <div class="panel-header-icon phi-violet"><i class="bi bi-pencil-square"></i></div>
                            <div>
                                <h5>Create Announcement</h5>
                                <p>Write the message and select who should receive it</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <form method="POST" action="announcements.php">
                                <div class="mb-3">
                                    <label for="announcement_title" class="form-label-custom">Title</label>
                                    <input type="text" id="announcement_title" name="announcement_title" class="form-ctrl" maxlength="180" placeholder="Example: Marketplace Maintenance Notice" value="<?php echo htmlspecialchars($announcement_title); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="announcement_message" class="form-label-custom">Message</label>
                                    <textarea id="announcement_message" name="announcement_message" class="form-ctrl" rows="6" maxlength="2000" placeholder="Write the announcement text here..." required><?php echo htmlspecialchars($announcement_message); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label-custom">Audience</label>
                                    <div class="audience-grid">
                                        <label class="audience-pill">
                                            <input type="checkbox" name="audiences[]" value="user" <?php echo in_array('user', $selected_audiences, true) ? 'checked' : ''; ?>>
                                            <div>
                                                <strong>Buyers</strong>
                                                <span>Users with the buyer role</span>
                                            </div>
                                        </label>
                                        <label class="audience-pill">
                                            <input type="checkbox" name="audiences[]" value="farmer" <?php echo in_array('farmer', $selected_audiences, true) ? 'checked' : ''; ?>>
                                            <div>
                                                <strong>Farmers</strong>
                                                <span>Listing owners and sellers</span>
                                            </div>
                                        </label>
                                        <label class="audience-pill">
                                            <input type="checkbox" name="audiences[]" value="admin" <?php echo in_array('admin', $selected_audiences, true) ? 'checked' : ''; ?>>
                                            <div>
                                                <strong>Admins</strong>
                                                <span>Back-office administrators</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <button type="submit" class="btn-submit">
                                    <i class="bi bi-send-fill"></i> Send Announcement
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="panel-card">
                        <div class="panel-header">
                            <div class="panel-header-icon phi-sky"><i class="bi bi-bell-fill"></i></div>
                            <div>
                                <h5>Recent Broadcasts</h5>
                                <p>Latest messages pushed to the platform</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <?php if (empty($recent_announcements)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-megaphone" style="font-size:2.2rem;display:block;margin-bottom:10px;color:#cbd5e1;"></i>
                                    No announcements have been sent yet.
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_announcements as $announcement): ?>
                                    <div class="announcement-card">
                                        <div class="announcement-card-head">
                                            <div>
                                                <h6><?php echo htmlspecialchars($announcement['announcement_title'] ?: 'Untitled announcement'); ?></h6>
                                                <p><?php echo nl2br(htmlspecialchars($announcement['announcement_message'] ?: '')); ?></p>
                                            </div>
                                        </div>
                                        <div class="announcement-meta">
                                            <span class="meta-chip"><i class="bi bi-people-fill"></i> <?php echo number_format((int) $announcement['recipient_count']); ?> recipients</span>
                                            <span class="meta-chip"><i class="bi bi-bullseye"></i> <?php echo htmlspecialchars($announcement['announcement_target'] ?: 'all'); ?></span>
                                            <span class="meta-chip"><i class="bi bi-clock"></i> <?php echo date('M j, Y g:i A', strtotime($announcement['sent_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="page-footer">
            &copy; <?php echo date('Y'); ?> Farmer Market Platform &mdash; All Rights Reserved.
        </footer>
    </div>

    <div class="toast-wrap" id="toastWrap">
        <div class="toast-msg <?php echo htmlspecialchars($toast_type); ?>">
            <i class="bi bi-info-circle-fill"></i>
            <span><?php echo htmlspecialchars($toast); ?></span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>