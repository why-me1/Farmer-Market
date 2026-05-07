<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/moderation_functions.php';
check_login();
global $conn;

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

ensureModerationSchema();

$admin_name = $_SESSION['username'] ?? 'Admin';
$toast = '';
$toast_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_id'], $_POST['moderation_action'])) {
    $report_id = (int) $_POST['report_id'];
    $action = (string) $_POST['moderation_action'];
    $notes = trim((string) ($_POST['admin_notes'] ?? ''));

    if ($report_id > 0 && applyModerationAction($report_id, (int) $_SESSION['user_id'], $action, $notes)) {
        $toast = 'Moderation action saved.';
        $toast_type = 'success';
    } else {
        $toast = 'Unable to complete moderation action.';
        $toast_type = 'danger';
    }
}

$status_filter = strtolower(trim((string) ($_GET['status'] ?? 'pending')));
$reports = getModerationReports($status_filter);

$counts = [
    'pending' => 0,
    'resolved' => 0,
    'dismissed' => 0,
];
$count_result = $conn->query("SELECT status, COUNT(*) AS total FROM content_reports GROUP BY status");
if ($count_result) {
    while ($row = $count_result->fetch_assoc()) {
        $counts[$row['status']] = (int) $row['total'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Queue &mdash; Farmer Market</title>
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

        .filter-bar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid #e2e8f0;
            color: #334155;
            text-decoration: none;
            font-size: .82rem;
            font-weight: 600;
        }

        .filter-chip.active {
            background: #4f46e5;
            border-color: #4f46e5;
            color: #fff;
        }

        .report-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 14px;
        }

        .report-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .report-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            font-size: .74rem;
            color: #64748b;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 4px 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .report-title {
            margin: 0;
            font-size: .95rem;
            font-weight: 800;
            color: #0f172a;
        }

        .report-sub {
            margin: 4px 0 0;
            font-size: .83rem;
            color: #475569;
        }

        .report-details {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 14px;
            margin-top: 12px;
            font-size: .82rem;
            color: #334155;
        }

        .action-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        .btn-mod {
            border: none;
            border-radius: 10px;
            padding: 8px 14px;
            font-size: .8rem;
            font-weight: 700;
            color: #fff;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-hide {
            background: #d97706;
        }

        .btn-approve {
            background: #059669;
        }

        .btn-warn {
            background: #f59e0b;
            color: #111827;
        }

        .btn-ban {
            background: #dc2626;
        }

        .btn-view {
            background: #6366f1;
        }

        .notes-box {
            margin-top: 12px;
        }

        .notes-box textarea {
            width: 100%;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            font-family: 'Inter', sans-serif;
            font-size: .85rem;
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
        }

        .toast-msg.success {
            border-left: 4px solid #22c55e;
        }

        .toast-msg.danger {
            border-left: 4px solid #ef4444;
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
            <a href="announcements.php"><i class="bi bi-megaphone-fill"></i> Announcements</a>
            <a href="reports.php" class="active"><i class="bi bi-flag-fill"></i> Reports Queue</a>
        </nav>
        <div class="sidebar-section-label">Platform</div>
        <nav class="sidebar-nav">
            <a href="../index.php"><i class="bi bi-house-fill"></i> View Site</a>
            <a href="../browse.php"><i class="bi bi-grid-fill"></i> Browse Listings</a>
        </nav>
        <div class="sidebar-footer"><a href="../logout.php"><i class="bi bi-box-arrow-left"></i> Sign Out</a></div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <div class="topbar-title">Reports Queue <small>Review reported posts, comments, and users</small></div>
            <div class="admin-badge">
                <div class="admin-avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
                <div class="admin-info"><strong><?php echo htmlspecialchars($admin_name); ?></strong><span>Administrator</span></div>
            </div>
        </header>

        <div class="page-body">
            <div class="hero-banner">
                <div class="hero-kicker"><i class="bi bi-shield-exclamation"></i> Moderation center</div>
                <h1>One queue for everything reported</h1>
                <p>Review user, post, and comment reports in one place. Hide content, approve it, warn the owner, or ban abusive accounts.</p>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-4">
                    <div class="mini-stat">
                        <div class="mini-stat-icon purple"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <div class="mini-stat-value"><?php echo number_format($counts['pending']); ?></div>
                            <div class="mini-stat-label">Pending</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-4">
                    <div class="mini-stat">
                        <div class="mini-stat-icon sky"><i class="bi bi-check2-circle"></i></div>
                        <div>
                            <div class="mini-stat-value"><?php echo number_format($counts['resolved']); ?></div>
                            <div class="mini-stat-label">Resolved</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="mini-stat">
                        <div class="mini-stat-icon green"><i class="bi bi-x-circle"></i></div>
                        <div>
                            <div class="mini-stat-value"><?php echo number_format($counts['dismissed']); ?></div>
                            <div class="mini-stat-label">Dismissed</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="filter-bar">
                <a class="filter-chip <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" href="reports.php?status=pending">Pending (<?php echo $counts['pending']; ?>)</a>
                <a class="filter-chip <?php echo $status_filter === 'resolved' ? 'active' : ''; ?>" href="reports.php?status=resolved">Resolved (<?php echo $counts['resolved']; ?>)</a>
                <a class="filter-chip <?php echo $status_filter === 'dismissed' ? 'active' : ''; ?>" href="reports.php?status=dismissed">Dismissed (<?php echo $counts['dismissed']; ?>)</a>
                <a class="filter-chip <?php echo $status_filter === 'all' ? 'active' : ''; ?>" href="reports.php?status=all">All</a>
            </div>

            <?php if (empty($reports)): ?>
                <div class="report-card text-center py-5">
                    <i class="bi bi-inbox" style="font-size:2.5rem;color:#cbd5e1;display:block;margin-bottom:10px;"></i>
                    <div style="font-weight:700;color:#0f172a;">No reports found</div>
                    <div style="color:#64748b;font-size:.85rem;">Reports submitted by users will appear here.</div>
                </div>
            <?php else: ?>
                <?php foreach ($reports as $report): ?>
                    <div class="report-card">
                        <div class="report-top">
                            <div>
                                <p class="report-title"><?php echo htmlspecialchars($report['summary']['title']); ?></p>
                                <p class="report-sub"><?php echo htmlspecialchars($report['summary']['subtitle']); ?></p>
                            </div>
                            <div class="report-meta">
                                <span class="chip"><i class="bi bi-flag-fill"></i> <?php echo htmlspecialchars($report['summary']['type_label']); ?></span>
                                <span class="chip"><i class="bi bi-person"></i> <?php echo htmlspecialchars($report['reporter_name']); ?></span>
                                <span class="chip"><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($report['reason']); ?></span>
                                <span class="chip"><i class="bi bi-clock"></i> <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($report['details'])): ?>
                            <div class="report-details"><?php echo nl2br(htmlspecialchars($report['details'])); ?></div>
                        <?php endif; ?>

                        <div class="action-row">
                            <?php if ($report['target_type'] === 'post' || $report['target_type'] === 'comment'): ?>
                                <form method="POST" action="reports.php?status=<?php echo urlencode($status_filter); ?>" class="d-inline">
                                    <input type="hidden" name="report_id" value="<?php echo (int) $report['id']; ?>">
                                    <input type="hidden" name="moderation_action" value="hide">
                                    <button class="btn-mod btn-hide" type="submit"><i class="bi bi-eye-slash-fill"></i> Hide</button>
                                </form>
                                <form method="POST" action="reports.php?status=<?php echo urlencode($status_filter); ?>" class="d-inline">
                                    <input type="hidden" name="report_id" value="<?php echo (int) $report['id']; ?>">
                                    <input type="hidden" name="moderation_action" value="approve">
                                    <button class="btn-mod btn-approve" type="submit"><i class="bi bi-check2-circle"></i> Approve</button>
                                </form>
                            <?php elseif ($report['target_type'] === 'user'): ?>
                                <form method="POST" action="reports.php?status=<?php echo urlencode($status_filter); ?>" class="d-inline">
                                    <input type="hidden" name="report_id" value="<?php echo (int) $report['id']; ?>">
                                    <input type="hidden" name="moderation_action" value="warn">
                                    <button class="btn-mod btn-warn" type="submit"><i class="bi bi-exclamation-triangle-fill"></i> Warn</button>
                                </form>
                                <form method="POST" action="reports.php?status=<?php echo urlencode($status_filter); ?>" class="d-inline">
                                    <input type="hidden" name="report_id" value="<?php echo (int) $report['id']; ?>">
                                    <input type="hidden" name="moderation_action" value="ban">
                                    <button class="btn-mod btn-ban" type="submit"><i class="bi bi-ban"></i> Ban</button>
                                </form>
                                <form method="POST" action="reports.php?status=<?php echo urlencode($status_filter); ?>" class="d-inline">
                                    <input type="hidden" name="report_id" value="<?php echo (int) $report['id']; ?>">
                                    <input type="hidden" name="moderation_action" value="approve">
                                    <button class="btn-mod btn-approve" type="submit"><i class="bi bi-check2-circle"></i> Approve</button>
                                </form>
                            <?php endif; ?>

                            <a class="btn-mod btn-view" href="<?php echo htmlspecialchars($report['summary']['link']); ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> View Target</a>
                        </div>

                        <div class="notes-box mt-3">
                            <form method="POST" action="reports.php?status=<?php echo urlencode($status_filter); ?>">
                                <input type="hidden" name="report_id" value="<?php echo (int) $report['id']; ?>">
                                <input type="hidden" name="moderation_action" value="approve">
                                <label class="form-label-custom" for="admin_notes_<?php echo (int) $report['id']; ?>">Admin notes</label>
                                <textarea name="admin_notes" id="admin_notes_<?php echo (int) $report['id']; ?>" rows="2" placeholder="Optional notes before dismissing or resolving this report."></textarea>
                                <button type="submit" class="btn-mod btn-view mt-2"><i class="bi bi-save2-fill"></i> Save Notes & Dismiss</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <footer class="page-footer">&copy; <?php echo date('Y'); ?> Farmer Market Platform &mdash; All Rights Reserved.</footer>
    </div>

    <div class="toast-wrap">
        <div class="toast-msg <?php echo htmlspecialchars($toast_type); ?>"><i class="bi bi-info-circle-fill"></i><span><?php echo htmlspecialchars($toast); ?></span></div>
    </div>
</body>

</html>