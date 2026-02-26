<?php

require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

// Regenerate session ID for security
session_regenerate_id(true);

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Fetch quick stats
$stats = [];

$result = $conn->query("SELECT COUNT(*) AS total FROM users");
$stats['users'] = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM posts");
$stats['posts'] = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'farmer'");
$stats['farmers'] = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'buyer'");
$stats['buyers'] = $result ? $result->fetch_assoc()['total'] : 0;

$admin_name = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard &mdash; Farmer Market</title>
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

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            margin: 0;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
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
            border-bottom: 1px solid rgba(255,255,255,.07);
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
            width: 38px; height: 38px;
            background: var(--primary);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; color: #fff;
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

        .sidebar-nav a i { font-size: 1rem; width: 20px; text-align: center; }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,.07);
        }

        .sidebar-footer a {
            display: flex; align-items: center; gap: 10px;
            color: #94a3b8; font-size: .83rem; text-decoration: none;
            transition: color .18s;
        }

        .sidebar-footer a:hover { color: #f87171; }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top bar */
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky; top: 0;
            z-index: 100;
        }

        .topbar-title { font-size: .95rem; font-weight: 600; color: #0f172a; }
        .topbar-title small { display: block; font-size: .73rem; font-weight: 400; color: #94a3b8; }

        .admin-badge { display: flex; align-items: center; gap: 10px; }

        .admin-avatar {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #6366f1, #818cf8);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: .85rem;
        }

        .admin-info strong { font-size: .85rem; color: #0f172a; display: block; }
        .admin-info span   { font-size: .72rem; color: #94a3b8; }

        /* Page body */
        .page-body { padding: 32px; flex: 1; }

        /* Hero banner */
        .hero-banner {
            background: linear-gradient(135deg, #312e81 0%, #4f46e5 50%, #6366f1 100%);
            border-radius: 16px;
            padding: 32px 36px;
            color: #fff;
            position: relative;
            overflow: hidden;
            margin-bottom: 32px;
        }

        .hero-banner::before {
            content: '';
            position: absolute; top: -60px; right: -60px;
            width: 220px; height: 220px;
            background: rgba(255,255,255,.06);
            border-radius: 50%;
        }

        .hero-banner::after {
            content: '';
            position: absolute; bottom: -80px; right: 80px;
            width: 300px; height: 300px;
            background: rgba(255,255,255,.04);
            border-radius: 50%;
        }

        .hero-banner h1 { font-size: 1.65rem; font-weight: 700; margin-bottom: 6px; }
        .hero-banner p  { opacity: .82; font-size: .9rem; margin: 0; }

        .hero-date {
            display: inline-block;
            background: rgba(255,255,255,.15);
            border-radius: 20px;
            padding: 4px 14px;
            font-size: .74rem;
            margin-bottom: 14px;
        }

        /* Stat cards */
        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px 22px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: box-shadow .2s, transform .2s;
            height: 100%;
        }

        .stat-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.08); transform: translateY(-2px); }

        .stat-icon {
            width: 50px; height: 50px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .stat-icon.purple  { background: #ede9fe; color: #7c3aed; }
        .stat-icon.green   { background: #dcfce7; color: #16a34a; }
        .stat-icon.amber   { background: #fef3c7; color: #d97706; }
        .stat-icon.cyan    { background: #cffafe; color: #0891b2; }

        .stat-value { font-size: 1.7rem; font-weight: 700; color: #0f172a; line-height: 1; }
        .stat-label { font-size: .76rem; color: #64748b; margin-top: 4px; }

        /* Section heading */
        .section-heading { font-size: .95rem; font-weight: 700; color: #0f172a; margin: 0 0 18px; }

        /* Action cards */
        .action-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 28px 22px;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: box-shadow .2s, transform .2s;
        }

        .action-card:hover { box-shadow: 0 10px 30px rgba(0,0,0,.09); transform: translateY(-3px); }

        .action-icon {
            width: 62px; height: 62px;
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 16px;
        }

        .action-icon.indigo  { background: #ede9fe; color: #6366f1; }
        .action-icon.emerald { background: #d1fae5; color: #059669; }
        .action-icon.amber   { background: #fef3c7; color: #d97706; }
        .action-icon.sky     { background: #e0f2fe; color: #0284c7; }

        .action-card h5 { font-size: .92rem; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .action-card p  { font-size: .79rem; color: #64748b; flex: 1; margin-bottom: 18px; line-height: 1.5; }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: .82rem;
            font-weight: 600;
            text-decoration: none;
            transition: opacity .18s, transform .18s;
            width: 100%;
            justify-content: center;
        }

        .action-btn:hover { opacity: .88; transform: scale(.98); }

        .btn-indigo  { background: #6366f1; color: #fff; }
        .btn-emerald { background: #059669; color: #fff; }
        .btn-amber   { background: #d97706; color: #fff; }
        .btn-sky     { background: #0284c7; color: #fff; }

        /* Footer */
        .page-footer {
            background: #fff;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            padding: 14px;
            font-size: .76rem;
            color: #94a3b8;
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
            <a href="dashboard.php" class="active">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="manage_users.php">
                <i class="bi bi-people-fill"></i> Manage Users
            </a>
            <a href="manage_posts.php">
                <i class="bi bi-card-list"></i> Manage Posts
            </a>
            <a href="view_statistics.php">
                <i class="bi bi-bar-chart-line-fill"></i> Statistics
            </a>
            <a href="update_market_price.php">
                <i class="bi bi-tags-fill"></i> Market Prices
            </a>
        </nav>

        <div class="sidebar-section-label">Platform</div>
        <nav class="sidebar-nav">
            <a href="../index.php">
                <i class="bi bi-house-fill"></i> View Site
            </a>
            <a href="../browse.php">
                <i class="bi bi-grid-fill"></i> Browse Listings
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php">
                <i class="bi bi-box-arrow-left"></i> Sign Out
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Top Bar -->
        <header class="topbar">
            <div class="topbar-title">
                Dashboard
                <small>Overview &amp; quick actions</small>
            </div>
            <div class="admin-badge">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                </div>
                <div class="admin-info">
                    <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                    <span>Administrator</span>
                </div>
            </div>
        </header>

        <!-- Page Body -->
        <div class="page-body">

            <!-- Hero Banner -->
            <div class="hero-banner">
                <div class="hero-date">
                    <i class="bi bi-calendar3 me-1"></i>
                    <?php echo date('l, F j, Y'); ?>
                </div>
                <h1>Welcome back, <?php echo htmlspecialchars($admin_name); ?>!</h1>
                <p>Here&rsquo;s what&rsquo;s happening on your platform today. Manage users, listings, and market prices all in one place.</p>
            </div>

            <!-- Stats Row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['users']); ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="bi bi-card-list"></i></div>
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['posts']); ?></div>
                            <div class="stat-label">Total Listings</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon amber"><i class="bi bi-person-badge-fill"></i></div>
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['farmers']); ?></div>
                            <div class="stat-label">Farmers</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon cyan"><i class="bi bi-bag-heart-fill"></i></div>
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['buyers']); ?></div>
                            <div class="stat-label">Buyers</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <p class="section-heading">Quick Actions</p>
            <div class="row g-3">

                <div class="col-sm-6 col-xl-3">
                    <div class="action-card">
                        <div class="action-icon indigo"><i class="bi bi-people-fill"></i></div>
                        <h5>Manage Users</h5>
                        <p>View, edit, or remove farmer and buyer accounts from the platform.</p>
                        <a href="manage_users.php" class="action-btn btn-indigo">
                            <i class="bi bi-arrow-right-circle"></i> Go to Users
                        </a>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="action-card">
                        <div class="action-icon emerald"><i class="bi bi-card-list"></i></div>
                        <h5>Manage Posts</h5>
                        <p>Approve, edit, or remove product listings posted by farmers.</p>
                        <a href="manage_posts.php" class="action-btn btn-emerald">
                            <i class="bi bi-arrow-right-circle"></i> Go to Posts
                        </a>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="action-card">
                        <div class="action-icon amber"><i class="bi bi-bar-chart-line-fill"></i></div>
                        <h5>View Statistics</h5>
                        <p>Analyze platform activity, user growth, and sales insights.</p>
                        <a href="view_statistics.php" class="action-btn btn-amber">
                            <i class="bi bi-arrow-right-circle"></i> View Stats
                        </a>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="action-card">
                        <div class="action-icon sky"><i class="bi bi-tags-fill"></i></div>
                        <h5>Market Prices</h5>
                        <p>Set or update product market prices used for automatic ratings.</p>
                        <a href="update_market_price.php" class="action-btn btn-sky">
                            <i class="bi bi-arrow-right-circle"></i> Update Prices
                        </a>
                    </div>
                </div>

            </div>
        </div>

        <footer class="page-footer">
            &copy; <?php echo date('Y'); ?> Farmer Market Platform &mdash; All Rights Reserved.
        </footer>

    </div><!-- /main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
