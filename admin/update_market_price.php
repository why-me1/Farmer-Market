<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ratings.php';
check_login();

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$admin_name = $_SESSION['username'] ?? 'Admin';
$toast      = '';
$toast_type = 'success';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_name'], $_POST['market_price'])) {
    $product_name = trim($_POST['product_name']);
    $price        = floatval($_POST['market_price']);

    if ($product_name !== '' && $price > 0) {
        set_market_price_for_product($product_name, $price, $_SESSION['user_id']);
        header("Location: update_market_price.php?updated=" . urlencode($product_name));
        exit();
    } else {
        $toast      = "Please provide a valid product name and price.";
        $toast_type = 'danger';
    }
}

// Fetch distinct product names from posts
$products = [];
$res = $conn->query(
    "SELECT DISTINCT product_name FROM posts
     WHERE product_name IS NOT NULL AND product_name != ''
     ORDER BY product_name"
);
if ($res) {
    while ($row = $res->fetch_assoc()) $products[] = $row['product_name'];
}

// Fetch all set market prices
$market_prices = [];
$mpres = $conn->query(
    "SELECT product_name, market_price, updated_at FROM market_prices ORDER BY product_name"
);
if ($mpres) {
    while ($row = $mpres->fetch_assoc()) $market_prices[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Prices &mdash; Farmer Market</title>
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

        /* Info banner */
        .info-banner {
            background: linear-gradient(135deg, #0c4a6e 0%, #0284c7 100%);
            border-radius: 16px; padding: 26px 32px;
            color: #fff; position: relative; overflow: hidden; margin-bottom: 28px;
        }

        .info-banner::before {
            content: ''; position: absolute;
            top: -50px; right: -50px; width: 180px; height: 180px;
            background: rgba(255,255,255,.06); border-radius: 50%;
        }

        .info-banner h2 { font-size: 1.1rem; font-weight: 700; margin-bottom: 4px; }
        .info-banner p  { font-size: .82rem; opacity: .85; margin: 0; }

        .info-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.15); border-radius: 20px;
            padding: 4px 14px; font-size: .74rem; margin-bottom: 12px;
        }

        /* Two-column grid */
        .split-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 900px) { .split-grid { grid-template-columns: 1fr; } }

        /* Card */
        .panel-card {
            background: #fff; border-radius: 16px;
            border: 1px solid #e2e8f0; overflow: hidden;
        }

        .panel-header {
            padding: 18px 24px; border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: center; gap: 10px;
        }

        .panel-header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }

        .phi-sky  { background: #e0f2fe; color: #0284c7; }
        .phi-teal { background: #ccfbf1; color: #0d9488; }

        .panel-header h5 { font-size: .92rem; font-weight: 700; color: #0f172a; margin: 0; }
        .panel-header p  { font-size: .74rem; color: #94a3b8; margin: 2px 0 0; }

        .panel-body { padding: 24px; }

        /* Form */
        .form-label-custom {
            font-size: .78rem; font-weight: 600; color: #475569;
            text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px;
            display: block;
        }

        .form-ctrl {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid #e2e8f0; border-radius: 9px;
            font-size: .86rem; font-family: 'Inter', sans-serif;
            color: #0f172a; background: #fff; outline: none;
            transition: border-color .18s, box-shadow .18s;
            appearance: none;
        }

        .form-ctrl:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }

        .input-group-wrap {
            position: relative; display: flex; align-items: center;
        }

        .input-prefix {
            position: absolute; left: 13px;
            font-weight: 700; color: #64748b; font-size: .88rem;
            pointer-events: none;
        }

        .form-ctrl.with-prefix { padding-left: 28px; }

        .price-hint { font-size: .72rem; color: #94a3b8; margin-top: 5px; }

        .btn-submit {
            width: 100%; padding: 11px;
            background: #6366f1; color: #fff;
            border: none; border-radius: 9px;
            font-size: .88rem; font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer; transition: background .18s, transform .15s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 20px;
        }

        .btn-submit:hover { background: #4f46e5; transform: translateY(-1px); }

        .no-products-msg {
            text-align: center; padding: 30px 20px;
            font-size: .84rem; color: #94a3b8;
        }

        .no-products-msg i { font-size: 2.2rem; display: block; margin-bottom: 10px; color: #cbd5e1; }

        /* Price table */
        .price-table { width: 100%; border-collapse: collapse; }

        .price-table thead th {
            background: #f8fafc; font-size: .68rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: .6px;
            color: #64748b; padding: 11px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .price-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
        .price-table tbody tr:hover { background: #f8fafc; }
        .price-table tbody tr:last-child { border-bottom: none; }

        .price-table td {
            padding: 13px 16px; font-size: .83rem;
            color: #334155; vertical-align: middle;
        }

        .product-chip {
            display: inline-flex; align-items: center; gap: 7px;
            font-weight: 600; color: #0f172a;
        }

        .product-chip-dot {
            width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
        }

        .price-val { font-weight: 700; color: #0d9488; font-size: .88rem; }

        .date-val { font-size: .76rem; color: #94a3b8; }

        .empty-row td { text-align: center; color: #94a3b8; padding: 40px; font-size: .84rem; }

        /* Search bar for table */
        .search-box { position: relative; }
        .search-box i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: .85rem; }
        .search-box input {
            padding: 7px 12px 7px 32px; border: 1.5px solid #e2e8f0;
            border-radius: 8px; font-size: .8rem; font-family: 'Inter', sans-serif;
            width: 190px; outline: none; transition: border-color .18s;
        }
        .search-box input:focus { border-color: #6366f1; }

        /* Toast */
        .toast-wrap { position: fixed; top: 20px; right: 24px; z-index: 9999; display: none; }
        .toast-msg {
            background: #0f172a; color: #fff;
            padding: 12px 20px; border-radius: 10px;
            font-size: .83rem; display: flex; align-items: center; gap: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
            animation: slideIn .3s ease;
        }
        .toast-msg.success { border-left: 4px solid #22c55e; }
        .toast-msg.danger  { border-left: 4px solid #ef4444; }

        @keyframes slideIn {
            from { transform: translateX(60px); opacity: 0; }
            to   { transform: translateX(0); opacity: 1; }
        }

        /* Footer */
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
        <a href="view_statistics.php"><i class="bi bi-bar-chart-line-fill"></i> Statistics</a>
        <a href="update_market_price.php" class="active"><i class="bi bi-tags-fill"></i> Market Prices</a>
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
            Market Prices
            <small>Set reference prices for automatic post ratings</small>
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

        <!-- Info banner -->
        <div class="info-banner">
            <div class="info-badge"><i class="bi bi-info-circle-fill"></i> How it works</div>
            <h2>Market Price Management</h2>
            <p>Set the current market reference price for each product. When farmers post listings, the platform compares their price to this reference to automatically calculate ratings.</p>
        </div>

        <!-- Split grid: form + table -->
        <div class="split-grid">

            <!-- Update form -->
            <div class="panel-card">
                <div class="panel-header">
                    <div class="panel-header-icon phi-sky"><i class="bi bi-pencil-fill"></i></div>
                    <div>
                        <h5>Set / Update Price</h5>
                        <p>Choose a product and enter its market reference price</p>
                    </div>
                </div>
                <div class="panel-body">
                    <?php if (!empty($products)): ?>
                    <form method="POST" action="update_market_price.php" id="priceForm">
                        <div style="margin-bottom:16px;">
                            <label class="form-label-custom" for="product_name">Product</label>
                            <select name="product_name" id="product_name" class="form-ctrl" required onchange="prefillPrice(this)">
                                <option value="">— Select a product —</option>
                                <?php foreach ($products as $p):
                                    $mp = get_market_price_for_product($p);
                                ?>
                                    <option value="<?php echo htmlspecialchars($p); ?>"
                                            data-price="<?php echo $mp !== null ? number_format($mp, 2, '.', '') : ''; ?>">
                                        <?php echo htmlspecialchars($p); ?>
                                        <?php echo ($mp !== null) ? ' — ?' . number_format($mp, 2) : ' (not set)'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label-custom" for="market_price">Market Price</label>
                            <div class="input-group-wrap">
                                <span class="input-prefix">?</span>
                                <input type="number" step="0.01" min="0.01"
                                       name="market_price" id="market_price"
                                       class="form-ctrl with-prefix"
                                       placeholder="0.00" required>
                            </div>
                            <p class="price-hint">Enter the current fair market price per unit in BDT (?).</p>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="bi bi-check-circle-fill"></i> Update Market Price
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="no-products-msg">
                        <i class="bi bi-box-seam"></i>
                        No products found in the system.<br>
                        <span style="font-size:.76rem;">Add some product listings first before setting prices.</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Current prices table -->
            <div class="panel-card" style="display:flex;flex-direction:column;">
                <div class="panel-header" style="justify-content:space-between;flex-wrap:wrap;gap:10px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="panel-header-icon phi-teal"><i class="bi bi-table"></i></div>
                        <div>
                            <h5>Current Prices</h5>
                            <p><?php echo count($market_prices); ?> product<?php echo count($market_prices) != 1 ? 's' : ''; ?> set</p>
                        </div>
                    </div>
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="priceSearch" placeholder="Search products...">
                    </div>
                </div>
                <div style="overflow-x:auto;flex:1;">
                    <table class="price-table" id="priceTable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price (?)</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($market_prices)): ?>
                            <tr class="empty-row">
                                <td colspan="3">
                                    <i class="bi bi-tags" style="font-size:2rem;display:block;margin-bottom:8px;color:#cbd5e1;"></i>
                                    No market prices set yet.
                                </td>
                            </tr>
                        <?php else: ?>
                        <?php
                        $chipColors = ['#6366f1','#22c55e','#f59e0b','#06b6d4','#ec4899','#8b5cf6','#14b8a6'];
                        foreach ($market_prices as $ci => $row):
                            $c = $chipColors[$ci % count($chipColors)];
                        ?>
                            <tr>
                                <td>
                                    <div class="product-chip">
                                        <div class="product-chip-dot" style="background:<?php echo $c; ?>"></div>
                                        <?php echo htmlspecialchars($row['product_name']); ?>
                                    </div>
                                </td>
                                <td class="price-val">?<?php echo number_format($row['market_price'], 2); ?></td>
                                <td class="date-val"><?php echo date('M j, Y', strtotime($row['updated_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /split-grid -->
    </div><!-- /page-body -->

    <footer class="page-footer">
        &copy; <?php echo date('Y'); ?> Farmer Market Platform &mdash; All Rights Reserved.
    </footer>
</div><!-- /main-content -->

<!-- Toast -->
<div class="toast-wrap" id="toastWrap">
    <div class="toast-msg" id="toastMsg"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-fill current price when product is selected
    function prefillPrice(sel) {
        const opt = sel.options[sel.selectedIndex];
        const p = opt.getAttribute('data-price');
        document.getElementById('market_price').value = p || '';
    }

    // Live search for price table
    document.getElementById('priceSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#priceTable tbody tr:not(.empty-row)').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });

    // Toast notifications
    const params = new URLSearchParams(location.search);
    const updated = params.get('updated');
    <?php if ($toast): ?>
    showToast('bi-exclamation-circle-fill', <?php echo json_encode($toast); ?>, <?php echo json_encode($toast_type); ?>);
    <?php endif; ?>
    if (updated) showToast('bi-check-circle-fill', 'Market price updated for: ' + decodeURIComponent(updated), 'success');

    function showToast(icon, msg, type) {
        const wrap = document.getElementById('toastWrap');
        const el   = document.getElementById('toastMsg');
        el.className = 'toast-msg ' + type;
        el.innerHTML = '<i class="bi ' + icon + '"></i> ' + msg;
        wrap.style.display = 'block';
        setTimeout(() => { wrap.style.display = 'none'; }, 4500);
    }
</script>
</body>
</html>
