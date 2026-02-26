<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_name = $_SESSION['username'] ?? 'Admin';

// Core counts
$queries = [
    'total_users'    => "SELECT COUNT(*) AS total FROM users",
    'total_posts'    => "SELECT COUNT(*) AS total FROM posts",
    'total_comments' => "SELECT COUNT(*) AS total FROM comments",
    'approved_posts' => "SELECT COUNT(*) AS total FROM posts WHERE is_approved = 1",
    'pending_posts'  => "SELECT COUNT(*) AS total FROM posts WHERE is_approved = 0",
    'total_farmers'  => "SELECT COUNT(*) AS total FROM users WHERE role = 'farmer'",
    'total_buyers'   => "SELECT COUNT(*) AS total FROM users WHERE role = 'buyer'",
    'total_admins'   => "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'",
    'total_value'    => "SELECT SUM(price) AS total FROM posts WHERE is_approved = 1",
];

$stats = [];
foreach ($queries as $key => $sql) {
    $r = $conn->query($sql);
    $stats[$key] = $r ? ($r->fetch_assoc()['total'] ?? 0) : 0;
}
$stats['total_value'] = $stats['total_value'] ?? 0;

// Posts per month (last 6 months)
$months_labels = [];
$months_data   = [];
for ($i = 5; $i >= 0; $i--) {
    $ts    = strtotime("-$i months");
    $month = date('Y-m', $ts);
    $label = date('M Y', $ts);
    $months_labels[] = $label;
    $r = $conn->query("SELECT COUNT(*) AS c FROM posts WHERE DATE_FORMAT(created_at,'%Y-%m') = '$month'");
    $months_data[] = $r ? (int)$r->fetch_assoc()['c'] : 0;
}

// Users per month (last 6 months)
$user_months_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE DATE_FORMAT(created_at,'%Y-%m') = '$month'");
    $user_months_data[] = $r ? (int)$r->fetch_assoc()['c'] : 0;
}

// Top farmers by post count
$top_farmers = [];
$r = $conn->query(
    "SELECT users.username, COUNT(posts.id) AS post_count
     FROM posts JOIN users ON posts.farmer_id = users.id
     WHERE posts.is_approved = 1
     GROUP BY posts.farmer_id ORDER BY post_count DESC LIMIT 5"
);
if ($r) while ($row = $r->fetch_assoc()) $top_farmers[] = $row;

// Recent users
$recent_users = [];
$r = $conn->query("SELECT username, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
if ($r) while ($row = $r->fetch_assoc()) $recent_users[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics &mdash; Farmer Market</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #0f172a;
            --sidebar-accent: #1e293b;
            --primary: #6366f1;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            margin: 0; min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-width); height: 100vh;
            background: var(--sidebar-bg);
            display: flex; flex-direction: column;
            z-index: 1000; overflow-y: auto;
        }

        .sidebar-brand { padding: 24px 20px 20px; border-bottom: 1px solid rgba(255,255,255,.07); }
        .sidebar-brand h2 { font-size: 1.05rem; font-weight: 700; color: #fff; margin: 0; }
        .sidebar-brand span { font-size: .73rem; color: #94a3b8; }

        .brand-icon {
            width: 38px; height: 38px; background: var(--primary);
            border-radius: 10px; display: flex; align-items: center;
            justify-content: center; font-size: 1.1rem; color: #fff; margin-bottom: 10px;
        }

        .sidebar-section-label {
            font-size: .63rem; font-weight: 600; letter-spacing: 1px;
            text-transform: uppercase; color: #475569; padding: 20px 20px 6px;
        }

        .sidebar-nav a {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 20px; color: #94a3b8; text-decoration: none;
            font-size: .875rem; font-weight: 500;
            border-left: 3px solid transparent; transition: all .18s;
        }

        .sidebar-nav a:hover, .sidebar-nav a.active {
            color: #fff; background: var(--sidebar-accent);
            border-left-color: var(--primary);
        }

        .sidebar-nav a i { font-size: 1rem; width: 20px; text-align: center; }

        .sidebar-footer {
            margin-top: auto; padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,.07);
        }

        .sidebar-footer a {
            display: flex; align-items: center; gap: 10px;
            color: #94a3b8; font-size: .83rem; text-decoration: none; transition: color .18s;
        }

        .sidebar-footer a:hover { color: #f87171; }

        /* Main */
        .main-content { margin-left: var(--sidebar-width); min-height: 100vh; display: flex; flex-direction: column; }

        .topbar {
            background: #fff; border-bottom: 1px solid #e2e8f0;
            padding: 14px 32px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }

        .topbar-title { font-size: .95rem; font-weight: 600; color: #0f172a; }
        .topbar-title small { display: block; font-size: .73rem; font-weight: 400; color: #94a3b8; }

        .admin-badge { display: flex; align-items: center; gap: 10px; }

        .admin-avatar {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #6366f1, #818cf8);
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; color: #fff; font-weight: 700; font-size: .85rem;
        }

        .admin-info strong { font-size: .85rem; color: #0f172a; display: block; }
        .admin-info span   { font-size: .72rem; color: #94a3b8; }

        .page-body { padding: 32px; flex: 1; }

        /* Stat cards */
        .stat-card {
            background: #fff; border: 1px solid #e2e8f0;
            border-radius: 14px; padding: 20px 22px;
            display: flex; align-items: center; gap: 16px;
            height: 100%; transition: box-shadow .2s, transform .2s;
        }

        .stat-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.08); transform: translateY(-2px); }

        .stat-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }

        .si-purple { background: #ede9fe; color: #7c3aed; }
        .si-green  { background: #dcfce7; color: #16a34a; }
        .si-amber  { background: #fef3c7; color: #d97706; }
        .si-cyan   { background: #cffafe; color: #0891b2; }
        .si-rose   { background: #ffe4e6; color: #e11d48; }
        .si-indigo { background: #e0e7ff; color: #4338ca; }
        .si-teal   { background: #ccfbf1; color: #0d9488; }
        .si-sky    { background: #e0f2fe; color: #0284c7; }

        .stat-value { font-size: 1.65rem; font-weight: 700; color: #0f172a; line-height: 1; }
        .stat-label { font-size: .74rem; color: #64748b; margin-top: 4px; }

        /* Section title */
        .section-title {
            font-size: .82rem; font-weight: 700; color: #0f172a;
            text-transform: uppercase; letter-spacing: .5px;
            margin: 0 0 16px; display: flex; align-items: center; gap: 8px;
        }

        .section-title::after {
            content: ''; flex: 1; height: 1px; background: #e2e8f0;
        }

        /* Chart cards */
        .chart-card {
            background: #fff; border-radius: 16px;
            border: 1px solid #e2e8f0; padding: 24px;
            height: 100%;
        }

        .chart-card-title {
            font-size: .9rem; font-weight: 700; color: #0f172a; margin-bottom: 6px;
        }

        .chart-card-sub { font-size: .75rem; color: #94a3b8; margin-bottom: 20px; }

        /* Donut legend */
        .donut-legend { list-style: none; padding: 0; margin: 0; }
        .donut-legend li {
            display: flex; align-items: center; gap: 8px;
            font-size: .8rem; color: #334155; padding: 5px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .donut-legend li:last-child { border-bottom: none; }

        .legend-dot {
            width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0;
        }

        .legend-val { margin-left: auto; font-weight: 700; color: #0f172a; }

        /* Progress bars */
        .prog-item { margin-bottom: 14px; }
        .prog-label { display: flex; justify-content: space-between; font-size: .78rem; color: #334155; margin-bottom: 5px; }
        .prog-label strong { font-weight: 600; }
        .prog-label span { color: #94a3b8; font-size: .72rem; }

        .prog-bar-track {
            height: 8px; background: #f1f5f9; border-radius: 10px; overflow: hidden;
        }

        .prog-bar-fill { height: 100%; border-radius: 10px; transition: width 1s ease; }

        /* Recent table */
        .recent-card {
            background: #fff; border-radius: 16px;
            border: 1px solid #e2e8f0; overflow: hidden;
        }

        .recent-card-header {
            padding: 16px 20px; border-bottom: 1px solid #e2e8f0;
        }

        .recent-card-header h5 { font-size: .9rem; font-weight: 700; color: #0f172a; margin: 0; }

        .recent-table { width: 100%; border-collapse: collapse; }

        .recent-table th {
            background: #f8fafc; font-size: .68rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: .6px;
            color: #64748b; padding: 10px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .recent-table td {
            padding: 11px 16px; font-size: .82rem; color: #334155;
            border-bottom: 1px solid #f1f5f9; vertical-align: middle;
        }

        .recent-table tr:last-child td { border-bottom: none; }

        .role-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 9px; border-radius: 20px;
            font-size: .7rem; font-weight: 600;
        }

        .role-badge.farmer { background: #d1fae5; color: #065f46; }
        .role-badge.buyer  { background: #dbeafe; color: #1e40af; }
        .role-badge.admin  { background: #ede9fe; color: #5b21b6; }

        .u-avatar {
            width: 30px; height: 30px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; font-weight: 700; color: #fff; flex-shrink: 0;
        }

        .u-cell { display: flex; align-items: center; gap: 9px; }

        /* Page footer */
        .page-footer {
            background: #fff; border-top: 1px solid #e2e8f0;
            text-align: center; padding: 14px; font-size: .76rem; color: #94a3b8;
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .page-body { padding: 16px; }
            .topbar { padding: 12px 16px; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
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
        <a href="view_statistics.php" class="active"><i class="bi bi-bar-chart-line-fill"></i> Statistics</a>
        <a href="update_market_price.php"><i class="bi bi-tags-fill"></i> Market Prices</a>
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

<!-- Main Content -->
<div class="main-content">

    <header class="topbar">
        <div class="topbar-title">
            Statistics
            <small>Platform-wide analytics &amp; insights</small>
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

        <!-- KPI row -->
        <p class="section-title"><i class="bi bi-lightning-charge-fill text-warning"></i> Key Metrics</p>
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon si-purple"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon si-indigo"><i class="bi bi-card-list"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($stats['total_posts']); ?></div>
                        <div class="stat-label">Total Posts</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon si-cyan"><i class="bi bi-chat-dots-fill"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($stats['total_comments']); ?></div>
                        <div class="stat-label">Total Comments</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon si-green"><i class="bi bi-currency-dollar"></i></div>
                    <div>
                        <div class="stat-value">?<?php echo number_format($stats['total_value'], 0); ?></div>
                        <div class="stat-label">Listed Value</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second KPI row -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon si-amber"><i class="bi bi-person-badge-fill"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($stats['total_farmers']); ?></div>
                        <div class="stat-label">Farmers</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon si-sky"><i class="bi bi-bag-heart-fill"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($stats['total_buyers']); ?></div>
                        <div class="stat-label">Buyers</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon si-teal"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($stats['approved_posts']); ?></div>
                        <div class="stat-label">Approved Posts</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon si-rose"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($stats['pending_posts']); ?></div>
                        <div class="stat-label">Pending Posts</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts row -->
        <p class="section-title"><i class="bi bi-bar-chart-line-fill text-primary"></i> Trends &amp; Breakdown</p>
        <div class="row g-3 mb-4">

            <!-- Posts over time -->
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="chart-card-title">Posts &amp; New Users &mdash; Last 6 Months</div>
                    <div class="chart-card-sub">Monthly activity across the platform</div>
                    <canvas id="lineChart" height="110"></canvas>
                </div>
            </div>

            <!-- User role donut -->
            <div class="col-lg-4">
                <div class="chart-card d-flex flex-column">
                    <div class="chart-card-title">User Roles</div>
                    <div class="chart-card-sub">Breakdown by account type</div>
                    <div style="max-width:200px;margin:0 auto 20px;">
                        <canvas id="donutChart"></canvas>
                    </div>
                    <ul class="donut-legend">
                        <li>
                            <span class="legend-dot" style="background:#6366f1"></span> Farmers
                            <span class="legend-val"><?php echo $stats['total_farmers']; ?></span>
                        </li>
                        <li>
                            <span class="legend-dot" style="background:#06b6d4"></span> Buyers
                            <span class="legend-val"><?php echo $stats['total_buyers']; ?></span>
                        </li>
                        <li>
                            <span class="legend-dot" style="background:#f59e0b"></span> Admins
                            <span class="legend-val"><?php echo $stats['total_admins']; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Bottom row: top farmers + recent users -->
        <p class="section-title"><i class="bi bi-award-fill text-warning"></i> Rankings &amp; Recent Activity</p>
        <div class="row g-3">

            <!-- Top farmers -->
            <div class="col-lg-6">
                <div class="recent-card">
                    <div class="recent-card-header">
                        <h5><i class="bi bi-trophy-fill text-warning me-2"></i>Top Farmers by Listings</h5>
                    </div>
                    <div style="padding:20px 24px;">
                    <?php if (empty($top_farmers)): ?>
                        <p style="font-size:.83rem;color:#94a3b8;text-align:center;padding:20px 0 0;">No approved posts yet.</p>
                    <?php else: ?>
                    <?php
                    $maxPosts = max(array_column($top_farmers, 'post_count'));
                    $barColors = ['#6366f1','#22c55e','#f59e0b','#06b6d4','#ec4899'];
                    foreach ($top_farmers as $fi => $f):
                        $pct = $maxPosts > 0 ? round(($f['post_count'] / $maxPosts) * 100) : 0;
                    ?>
                    <div class="prog-item">
                        <div class="prog-label">
                            <strong><?php echo htmlspecialchars($f['username']); ?></strong>
                            <span><?php echo $f['post_count']; ?> post<?php echo $f['post_count'] != 1 ? 's' : ''; ?></span>
                        </div>
                        <div class="prog-bar-track">
                            <div class="prog-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $barColors[$fi % count($barColors)]; ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent users -->
            <div class="col-lg-6">
                <div class="recent-card">
                    <div class="recent-card-header">
                        <h5><i class="bi bi-person-plus-fill text-primary me-2"></i>Recently Joined Users</h5>
                    </div>
                    <?php if (empty($recent_users)): ?>
                        <p style="font-size:.83rem;color:#94a3b8;text-align:center;padding:30px;">No users yet.</p>
                    <?php else: ?>
                    <?php
                    $avatarColors = ['#6366f1','#22c55e','#f59e0b','#06b6d4','#ec4899'];
                    ?>
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_users as $ui => $u): ?>
                            <tr>
                                <td>
                                    <div class="u-cell">
                                        <div class="u-avatar" style="background:<?php echo $avatarColors[$ui % count($avatarColors)]; ?>">
                                            <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                        </div>
                                        <span style="font-weight:600;color:#0f172a;"><?php echo htmlspecialchars($u['username']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge <?php echo $u['role']; ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td style="font-size:.78rem;color:#64748b;"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /bottom row -->
    </div><!-- /page-body -->

    <footer class="page-footer">
        &copy; <?php echo date('Y'); ?> Farmer Market Platform &mdash; All Rights Reserved.
    </footer>
</div><!-- /main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const months = <?php echo json_encode($months_labels); ?>;
const postsData = <?php echo json_encode($months_data); ?>;
const usersData = <?php echo json_encode($user_months_data); ?>;

// Line chart
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: months,
        datasets: [
            {
                label: 'Posts',
                data: postsData,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99,102,241,.08)',
                tension: .4, fill: true,
                pointBackgroundColor: '#6366f1',
                pointRadius: 4, pointHoverRadius: 6,
                borderWidth: 2.5
            },
            {
                label: 'New Users',
                data: usersData,
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34,197,94,.06)',
                tension: .4, fill: true,
                pointBackgroundColor: '#22c55e',
                pointRadius: 4, pointHoverRadius: 6,
                borderWidth: 2.5
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
                labels: { font: { family: 'Inter', size: 12 }, usePointStyle: true, boxWidth: 8 }
            },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 11 } } },
            y: {
                beginAtZero: true, ticks: { precision: 0, font: { family: 'Inter', size: 11 } },
                grid: { color: '#f1f5f9' }
            }
        }
    }
});

// Donut chart
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: ['Farmers', 'Buyers', 'Admins'],
        datasets: [{
            data: [
                <?php echo (int)$stats['total_farmers']; ?>,
                <?php echo (int)$stats['total_buyers']; ?>,
                <?php echo (int)$stats['total_admins']; ?>
            ],
            backgroundColor: ['#6366f1', '#06b6d4', '#f59e0b'],
            borderWidth: 0,
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        cutout: '70%',
        plugins: { legend: { display: false }, tooltip: { callbacks: {
            label: ctx => ' ' + ctx.label + ': ' + ctx.parsed
        }}}
    }
});
</script>
</body>
</html>
