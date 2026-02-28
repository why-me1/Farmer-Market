<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle actions
if (isset($_GET['action']) && isset($_GET['post_id'])) {
    $action  = $_GET['action'];
    $post_id = intval($_GET['post_id']);

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE posts SET is_approved = 1 WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();
        header("Location: manage_posts.php?approved=1");
        exit();
    }

    if ($action === 'reject') {
        $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();
        header("Location: manage_posts.php?rejected=1");
        exit();
    }
}

// Fetch pending posts
$pending_result = $conn->query(
    "SELECT posts.*, users.username FROM posts
     JOIN users ON posts.farmer_id = users.id
     WHERE posts.is_approved = 0
     ORDER BY posts.created_at DESC"
);
$pending_posts = $pending_result ? $pending_result->fetch_all(MYSQLI_ASSOC) : [];

// Fetch approved posts
$approved_result = $conn->query(
    "SELECT posts.*, users.username FROM posts
     JOIN users ON posts.farmer_id = users.id
     WHERE posts.is_approved = 1
     ORDER BY posts.created_at DESC
     LIMIT 50"
);
$approved_posts = $approved_result ? $approved_result->fetch_all(MYSQLI_ASSOC) : [];

$admin_name  = $_SESSION['username'] ?? 'Admin';
$total_posts = count($pending_posts) + count($approved_posts);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Posts &mdash; Farmer Market</title>
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

        /* Sidebar */
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

        /* Main */
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

        /* Mini stats */
        .mini-stat {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
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
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .mini-stat-icon.purple {
            background: #ede9fe;
            color: #7c3aed;
        }

        .mini-stat-icon.amber {
            background: #fef3c7;
            color: #d97706;
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

        /* Table card */
        .table-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-top: 24px;
        }

        .table-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .table-card-header h5 {
            font-size: .95rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        /* Tab pills */
        .tab-pills {
            display: flex;
            gap: 6px;
        }

        .tab-pill {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            background: #f1f5f9;
            color: #64748b;
            transition: all .18s;
        }

        .tab-pill.active {
            background: #6366f1;
            color: #fff;
        }

        .tab-pill.active-amber {
            background: #f59e0b;
            color: #fff;
        }

        /* Search */
        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: .9rem;
        }

        .search-box input {
            padding: 8px 12px 8px 34px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: .82rem;
            font-family: 'Inter', sans-serif;
            width: 200px;
            outline: none;
            transition: border-color .18s;
        }

        .search-box input:focus {
            border-color: #6366f1;
        }

        /* Table */
        .posts-table {
            width: 100%;
            border-collapse: collapse;
        }

        .posts-table thead th {
            background: #f8fafc;
            font-size: .72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #64748b;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .posts-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background .15s;
        }

        .posts-table tbody tr:hover {
            background: #f8fafc;
        }

        .posts-table tbody tr:last-child {
            border-bottom: none;
        }

        .posts-table td {
            padding: 13px 16px;
            font-size: .84rem;
            color: #334155;
            vertical-align: middle;
        }

        /* Farmer cell */
        .farmer-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .farmer-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .78rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .farmer-name {
            font-weight: 600;
            font-size: .84rem;
            color: #0f172a;
        }

        /* Product cell */
        .product-name {
            font-weight: 600;
            color: #0f172a;
            font-size: .85rem;
        }

        .product-qty {
            font-size: .74rem;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* Price */
        .price-cell {
            font-weight: 700;
            color: #059669;
            font-size: .88rem;
        }

        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 600;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.approved {
            background: #dcfce7;
            color: #065f46;
        }

        /* Action buttons */
        .action-group {
            display: flex;
            gap: 6px;
        }

        .btn-approve {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            border-radius: 7px;
            font-size: .78rem;
            font-weight: 600;
            background: #dcfce7;
            color: #065f46;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: background .18s;
            white-space: nowrap;
        }

        .btn-approve:hover {
            background: #bbf7d0;
            color: #064e3b;
        }

        .btn-reject {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            border-radius: 7px;
            font-size: .78rem;
            font-weight: 600;
            background: #fee2e2;
            color: #991b1b;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: background .18s;
            white-space: nowrap;
        }

        .btn-reject:hover {
            background: #fecaca;
            color: #7f1d1d;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 56px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            display: block;
            margin-bottom: 12px;
        }

        .empty-state p {
            font-size: .88rem;
            margin: 0;
        }

        /* Toast */
        .toast-wrap {
            position: fixed;
            top: 20px;
            right: 24px;
            z-index: 9999;
            display: none;
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

        /* Modal */
        .modal-confirm .modal-header {
            background: #fee2e2;
            border-bottom: none;
            border-radius: 12px 12px 0 0;
        }

        .modal-confirm .modal-title {
            font-weight: 700;
            color: #991b1b;
            font-size: .95rem;
        }

        .modal-confirm .modal-body {
            font-size: .86rem;
            color: #334155;
        }

        .modal-confirm .modal-footer {
            border-top: none;
        }

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

            .search-box input {
                width: 150px;
            }
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
            <a href="manage_posts.php" class="active"><i class="bi bi-card-list"></i> Manage Posts</a>
            <a href="view_statistics.php"><i class="bi bi-bar-chart-line-fill"></i> Statistics</a>
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
                Manage Posts
                <small>Review, approve, or reject farmer listings</small>
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

            <!-- Mini stats -->
            <div class="row g-3 mb-0">
                <div class="col-6 col-lg-4">
                    <div class="mini-stat">
                        <div class="mini-stat-icon purple"><i class="bi bi-card-list"></i></div>
                        <div>
                            <div class="mini-stat-value"><?php echo $total_posts; ?></div>
                            <div class="mini-stat-label">Total Posts</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-4">
                    <div class="mini-stat">
                        <div class="mini-stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <div class="mini-stat-value"><?php echo count($pending_posts); ?></div>
                            <div class="mini-stat-label">Pending Review</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-4">
                    <div class="mini-stat">
                        <div class="mini-stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
                        <div>
                            <div class="mini-stat-value"><?php echo count($approved_posts); ?></div>
                            <div class="mini-stat-label">Approved</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table card -->
            <div class="table-card">
                <div class="table-card-header">
                    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                        <h5 style="margin:0"><i class="bi bi-card-list me-2 text-primary"></i>All Listings</h5>
                        <div class="tab-pills">
                            <button class="tab-pill active-amber active" id="tabPending" onclick="switchTab('pending')">
                                Pending <span id="pendingCount" style="background:rgba(0,0,0,.12);border-radius:10px;padding:1px 7px;margin-left:4px;"><?php echo count($pending_posts); ?></span>
                            </button>
                            <button class="tab-pill" id="tabApproved" onclick="switchTab('approved')">
                                Approved <span id="approvedCount" style="background:rgba(0,0,0,.08);border-radius:10px;padding:1px 7px;margin-left:4px;"><?php echo count($approved_posts); ?></span>
                            </button>
                        </div>
                    </div>
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchInput" placeholder="Search posts...">
                    </div>
                </div>

                <?php $avatarColors = ['#6366f1', '#22c55e', '#f59e0b', '#06b6d4', '#ec4899', '#8b5cf6', '#14b8a6']; ?>

                <!-- Pending tab -->
                <div id="panePending">
                    <?php if (empty($pending_posts)): ?>
                        <div class="empty-state">
                            <i class="bi bi-hourglass"></i>
                            <p>No posts pending review. You&rsquo;re all caught up!</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="posts-table searchable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Farmer</th>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Submitted</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    foreach ($pending_posts as $i => $post):
                                        $color = $avatarColors[$i % count($avatarColors)];
                                        $initials = strtoupper(substr($post['username'], 0, 1));
                                        $submitted = date('M j, Y', strtotime($post['created_at']));
                                    ?>
                                        <tr>
                                            <td style="color:#94a3b8;font-size:.78rem;"><?php echo $post['id']; ?></td>
                                            <td>
                                                <div class="farmer-cell">
                                                    <div class="farmer-avatar" style="background:<?php echo $color; ?>"><?php echo $initials; ?></div>
                                                    <div class="farmer-name"><?php echo htmlspecialchars($post['username']); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="product-name"><?php echo htmlspecialchars($post['product_name']); ?></div>
                                                <?php if (!empty($post['quantity'])): ?>
                                                    <div class="product-qty">Qty: <?php echo htmlspecialchars($post['quantity']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="price-cell">?<?php echo number_format($post['price'], 2); ?></td>
                                            <td style="font-size:.8rem;color:#64748b;"><?php echo $submitted; ?></td>
                                            <td><span class="status-badge pending"><i class="bi bi-hourglass-split"></i> Pending</span></td>
                                            <td>
                                                <div class="action-group">
                                                    <a href="?action=approve&post_id=<?php echo $post['id']; ?>" class="btn-approve">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </a>
                                                    <button class="btn-reject"
                                                        onclick="openReject(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars($post['product_name'], ENT_QUOTES); ?>')">
                                                        <i class="bi bi-x-lg"></i> Reject
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Approved tab -->
                <div id="paneApproved" style="display:none;">
                    <?php if (empty($approved_posts)): ?>
                        <div class="empty-state">
                            <i class="bi bi-card-list"></i>
                            <p>No approved posts yet.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="posts-table searchable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Farmer</th>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Submitted</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approved_posts as $i => $post):
                                        $color = $avatarColors[$i % count($avatarColors)];
                                        $initials = strtoupper(substr($post['username'], 0, 1));
                                        $submitted = date('M j, Y', strtotime($post['created_at']));
                                    ?>
                                        <tr>
                                            <td style="color:#94a3b8;font-size:.78rem;"><?php echo $post['id']; ?></td>
                                            <td>
                                                <div class="farmer-cell">
                                                    <div class="farmer-avatar" style="background:<?php echo $color; ?>"><?php echo $initials; ?></div>
                                                    <div class="farmer-name"><?php echo htmlspecialchars($post['username']); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="product-name"><?php echo htmlspecialchars($post['product_name']); ?></div>
                                                <?php if (!empty($post['quantity'])): ?>
                                                    <div class="product-qty">Qty: <?php echo htmlspecialchars($post['quantity']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="price-cell">?<?php echo number_format($post['price'], 2); ?></td>
                                            <td style="font-size:.8rem;color:#64748b;"><?php echo $submitted; ?></td>
                                            <td><span class="status-badge approved"><i class="bi bi-check-circle-fill"></i> Approved</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div><!-- /table-card -->
        </div><!-- /page-body -->

        <footer class="page-footer">
            &copy; <?php echo date('Y'); ?> Farmer Market Platform &mdash; All Rights Reserved.
        </footer>
    </div>

    <!-- Reject Confirm Modal -->
    <div class="modal fade modal-confirm" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
            <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 20px 50px rgba(0,0,0,.15)">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Reject Post</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to reject and delete <strong id="rejectPostName"></strong>?
                    This action <strong>cannot be undone</strong>.
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <a id="confirmRejectBtn" href="#" class="btn btn-sm px-4"
                        style="background:#ef4444;color:#fff;border:none;border-radius:7px;font-weight:600;">
                        <i class="bi bi-x-lg me-1"></i>Reject &amp; Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast-wrap" id="toastWrap">
        <div class="toast-msg" id="toastMsg"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab switching
        function switchTab(tab) {
            const isPending = tab === 'pending';
            document.getElementById('panePending').style.display = isPending ? '' : 'none';
            document.getElementById('paneApproved').style.display = isPending ? 'none' : '';
            document.getElementById('tabPending').className = 'tab-pill' + (isPending ? ' active active-amber' : '');
            document.getElementById('tabApproved').className = 'tab-pill' + (!isPending ? ' active' : '');
            document.getElementById('searchInput').value = '';
            filterTable('');
        }

        // Reject modal
        function openReject(id, name) {
            document.getElementById('rejectPostName').textContent = name;
            document.getElementById('confirmRejectBtn').href = '?action=reject&post_id=' + id;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        // Live search � only visible pane
        document.getElementById('searchInput').addEventListener('input', function() {
            filterTable(this.value.toLowerCase());
        });

        function filterTable(q) {
            const activePanes = document.querySelectorAll('[id^="pane"]:not([style*="display: none"])');
            activePanes.forEach(pane => {
                pane.querySelectorAll('tbody tr').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
                });
            });
        }

        // Toast + auto-tab switch
        const params = new URLSearchParams(location.search);
        if (params.get('approved') === '1') {
            showToast('bi-check-circle-fill', 'Post approved successfully.', 'success');
            switchTab('approved');
        }
        if (params.get('rejected') === '1') showToast('bi-x-circle-fill', 'Post rejected and removed.', 'danger');

        function showToast(icon, msg, type) {
            const wrap = document.getElementById('toastWrap');
            const el = document.getElementById('toastMsg');
            el.className = 'toast-msg ' + type;
            el.innerHTML = '<i class="bi ' + icon + '"></i> ' + msg;
            wrap.style.display = 'block';
            setTimeout(() => {
                wrap.style.display = 'none';
            }, 4000);
        }
    </script>
</body>

</html>