<?php

require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle actions
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $action  = $_GET['action'];
    $user_id = intval($_GET['user_id']);

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: manage_users.php?deleted=1");
        exit();
    }

    if ($action === 'promote') {
        $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: manage_users.php?promoted=1");
        exit();
    }
}

// Fetch users
$stmt = $conn->prepare("SELECT id, username, role, created_at FROM users WHERE role != 'admin' ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$admin_name = $_SESSION['username'] ?? 'Admin';

// Stats
$total      = count($users);
$farmers    = count(array_filter($users, fn($u) => $u['role'] === 'farmer'));
$buyers     = count(array_filter($users, fn($u) => $u['role'] === 'buyer'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users &mdash; Farmer Market</title>
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

        /* -- Sidebar -- */
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

        /* -- Main -- */
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

        /* -- Page header -- */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header h1 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .page-header p {
            font-size: .82rem;
            color: #64748b;
            margin: 4px 0 0;
        }

        /* -- Mini stat cards -- */
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

        .mini-stat-icon.cyan {
            background: #cffafe;
            color: #0891b2;
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

        /* -- Table card -- */
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
            width: 220px;
            outline: none;
            transition: border-color .18s;
        }

        .search-box input:focus {
            border-color: #6366f1;
        }

        /* -- Custom table -- */
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table thead th {
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

        .users-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background .15s;
        }

        .users-table tbody tr:hover {
            background: #f8fafc;
        }

        .users-table tbody tr:last-child {
            border-bottom: none;
        }

        .users-table td {
            padding: 14px 16px;
            font-size: .84rem;
            color: #334155;
            vertical-align: middle;
        }

        /* User avatar cell */
        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .user-name {
            font-weight: 600;
            color: #0f172a;
            font-size: .86rem;
        }

        .user-email {
            font-size: .74rem;
            color: #94a3b8;
        }

        /* Role badge */
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 600;
        }

        .role-badge.farmer {
            background: #d1fae5;
            color: #065f46;
        }

        .role-badge.buyer {
            background: #dbeafe;
            color: #1e40af;
        }

        .role-badge.admin {
            background: #ede9fe;
            color: #5b21b6;
        }

        /* Date */
        .date-cell {
            font-size: .8rem;
            color: #64748b;
        }

        /* Action buttons */
        .action-group {
            display: flex;
            gap: 6px;
            flex-wrap: nowrap;
        }

        .btn-promote {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            border-radius: 7px;
            font-size: .78rem;
            font-weight: 600;
            background: #fef3c7;
            color: #92400e;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: background .18s;
            white-space: nowrap;
        }

        .btn-promote:hover {
            background: #fde68a;
            color: #78350f;
        }

        .btn-del {
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

        .btn-del:hover {
            background: #fecaca;
            color: #7f1d1d;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
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

        .toast-msg.warning {
            border-left: 4px solid #f59e0b;
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
                width: 160px;
            }
        }
    </style>
</head>

<body>

    <!-- -- Sidebar -- -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon"><i class="bi bi-basket2-fill"></i></div>
            <h2>Farmer Market</h2>
            <span>Administration Panel</span>
        </div>

        <div class="sidebar-section-label">Main Menu</div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="manage_users.php" class="active"><i class="bi bi-people-fill"></i> Manage Users</a>
            <a href="manage_posts.php"><i class="bi bi-card-list"></i> Manage Posts</a>
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

    <!-- -- Main Content -- -->
    <div class="main-content">

        <header class="topbar">
            <div class="topbar-title">
                Manage Users
                <small>View, promote, or remove platform accounts</small>
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

            <!-- Page header -->
            <div class="page-header">
                <div>
                    <h1>User Management</h1>
                    <p>All non-admin accounts registered on the platform.</p>
                </div>
            </div>

            <!-- Mini stats -->
            <div class="row g-3">
                <div class="col-6 col-lg-4">
                    <div class="mini-stat">
                        <div class="mini-stat-icon purple"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="mini-stat-value"><?php echo $total; ?></div>
                            <div class="mini-stat-label">Total Users</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-4">
                    <div class="mini-stat">
                        <div class="mini-stat-icon amber"><i class="bi bi-person-badge-fill"></i></div>
                        <div>
                            <div class="mini-stat-value"><?php echo $farmers; ?></div>
                            <div class="mini-stat-label">Farmers</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-4">
                    <div class="mini-stat">
                        <div class="mini-stat-icon cyan"><i class="bi bi-bag-heart-fill"></i></div>
                        <div>
                            <div class="mini-stat-value"><?php echo $buyers; ?></div>
                            <div class="mini-stat-label">Buyers</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table card -->
            <div class="table-card">
                <div class="table-card-header">
                    <h5><i class="bi bi-people me-2 text-primary"></i>All Users</h5>
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchInput" placeholder="Search users...">
                    </div>
                </div>

                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <i class="bi bi-person-x"></i>
                        <p>No users found on the platform yet.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="users-table" id="usersTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $avatarColors = ['#6366f1', '#22c55e', '#f59e0b', '#06b6d4', '#ec4899', '#8b5cf6', '#14b8a6'];
                                foreach ($users as $i => $user):
                                    $color = $avatarColors[$i % count($avatarColors)];
                                    $initials = strtoupper(substr($user['username'], 0, 1));
                                    $regDate = date('M j, Y', strtotime($user['created_at']));
                                ?>
                                    <tr>
                                        <td style="color:#94a3b8;font-size:.78rem;"><?php echo $user['id']; ?></td>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar" style="background:<?php echo $color; ?>">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div>
                                                    <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge <?php echo htmlspecialchars($user['role']); ?>">
                                                <?php if ($user['role'] === 'farmer'): ?>
                                                    <i class="bi bi-person-badge-fill"></i>
                                                <?php elseif ($user['role'] === 'buyer'): ?>
                                                    <i class="bi bi-bag-heart-fill"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-shield-fill"></i>
                                                <?php endif; ?>
                                                <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                            </span>
                                        </td>
                                        <td class="date-cell"><?php echo $regDate; ?></td>
                                        <td>
                                            <div class="action-group">
                                                <?php if ($user['role'] !== 'admin'): ?>
                                                    <a href="?action=promote&user_id=<?php echo $user['id']; ?>"
                                                        class="btn-promote"
                                                        onclick="return confirm('Promote <?php echo htmlspecialchars($user['username']); ?> to admin?')">
                                                        <i class="bi bi-shield-plus"></i> Promote
                                                    </a>
                                                <?php endif; ?>
                                                <button class="btn-del"
                                                    onclick="openDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')">
                                                    <i class="bi bi-trash3"></i> Delete
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

        </div><!-- /page-body -->

        <footer class="page-footer">
            &copy; <?php echo date('Y'); ?> Farmer Market Platform &mdash; All Rights Reserved.
        </footer>
    </div>

    <!-- Delete Confirm Modal -->
    <div class="modal fade modal-confirm" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
            <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 20px 50px rgba(0,0,0,.15)">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete <strong id="deleteUserName"></strong>? This action
                    <strong>cannot be undone</strong> and will permanently remove the account.
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <a id="confirmDeleteBtn" href="#"
                        class="btn btn-sm px-4"
                        style="background:#ef4444;color:#fff;border:none;border-radius:7px;font-weight:600;">
                        <i class="bi bi-trash3 me-1"></i>Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast notifications -->
    <div class="toast-wrap" id="toastWrap" style="display:none">
        <div class="toast-msg" id="toastMsg"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete modal
        function openDelete(id, name) {
            document.getElementById('deleteUserName').textContent = name;
            document.getElementById('confirmDeleteBtn').href = '?action=delete&user_id=' + id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Live search
        document.getElementById('searchInput').addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#usersTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });

        // Toast on redirect
        const params = new URLSearchParams(location.search);
        if (params.get('deleted') === '1') showToast('bi-check-circle-fill', 'User deleted successfully.', 'success');
        if (params.get('promoted') === '1') showToast('bi-shield-check', 'User promoted to Admin.', 'warning');

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