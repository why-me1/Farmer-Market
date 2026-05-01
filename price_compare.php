<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

/** @var mysqli $conn */
/** @var string $base_url */

$selected_category = isset($_GET['category']) ? trim($_GET['category']) : '';
$search_query      = isset($_GET['q'])        ? trim($_GET['q'])        : '';

// All distinct categories for live listings only
$cat_stmt = $conn->prepare("SELECT DISTINCT category FROM posts WHERE is_approved = 1 AND status = 'active' AND auction_start_date <= NOW() AND auction_end_date > NOW() ORDER BY category ASC");
$cat_stmt->execute();
$cat_result = $cat_stmt->get_result();
$categories = [];
while ($row = $cat_result->fetch_assoc()) {
    $categories[] = $row['category'];
}
$cat_stmt->close();

// Build comparison data
$where_parts = ["p.is_approved = 1", "p.status = 'active'", "p.auction_start_date <= NOW()", "p.auction_end_date > NOW()"];
$params      = [];
$types       = '';

if ($selected_category !== '') {
    $where_parts[] = "p.category = ?";
    $params[]      = $selected_category;
    $types        .= 's';
}
if ($search_query !== '') {
    $where_parts[] = "(p.product_name LIKE ? OR p.category LIKE ?)";
    $like          = '%' . $search_query . '%';
    $params[]      = $like;
    $params[]      = $like;
    $types        .= 'ss';
}

$where_sql = implode(' AND ', $where_parts);

$sql = "
    SELECT
        p.id,
        p.product_name,
        p.category,
        p.price,
        p.quantity,
        p.unit,
        p.image,
        p.auction_start_date,
        p.auction_end_date,
        u.id        AS farmer_id,
        u.username  AS farmer_name,
        u.farm_name,
        u.location,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS bid_count,
        (SELECT MAX(CAST(c2.comment_text AS DECIMAL(12,2))) FROM comments c2 WHERE c2.post_id = p.id) AS highest_bid
    FROM posts p
    JOIN users u ON p.farmer_id = u.id
    WHERE $where_sql
    ORDER BY p.category ASC, p.price ASC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group by category
$grouped = [];
foreach ($rows as $row) {
    $grouped[$row['category']][] = $row;
}

// Stats per category
$cat_stats = [];
foreach ($grouped as $cat => $items) {
    $prices           = array_column($items, 'price');
    $cat_stats[$cat]  = [
        'count'   => count($items),
        'min'     => min($prices),
        'max'     => max($prices),
        'avg'     => round(array_sum($prices) / count($prices), 0),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price Comparison - Farmers' Market</title>
    <meta name="description" content="Compare prices of fresh farm products across multiple farmers. Find the best deals on vegetables, fruits, dairy and more.">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        :root {
            --bg-dark: #090e17;
            --bg-darker: #05080f;
            --glass-bg: rgba(255, 255, 255, 0.04);
            --glass-border: rgba(255, 255, 255, 0.08);
            --accent-1: #10b981; /* Emerald */
            --accent-2: #06b6d4; /* Cyan */
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark) !important;
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(16, 185, 129, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(6, 182, 212, 0.08), transparent 25%) !important;
            background-attachment: fixed !important;
            color: var(--text-main) !important;
            margin: 0;
            min-height: 100vh;
        }

        /* ── HERO ── */
        .pc-hero {
            padding: 80px 0 130px;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid var(--glass-border);
            background: linear-gradient(180deg, rgba(16, 185, 129, 0.05) 0%, transparent 100%);
        }

        .pc-hero::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--glass-border), transparent);
        }

        .pc-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 30px;
            padding: 6px 18px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--accent-1);
            margin-bottom: 20px;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.2);
        }

        .pc-hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(32px, 5vw, 56px);
            font-weight: 800;
            line-height: 1.1;
            margin: 0 0 16px;
            color: #fff;
        }

        .pc-hero h1 span {
            background: linear-gradient(135deg, var(--accent-1), var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .pc-hero .sub {
            font-size: 16px;
            color: var(--text-muted);
            max-width: 600px;
            line-height: 1.6;
        }

        /* ── Search ── */
        .pc-search-wrap {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 8px 10px 8px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .pc-search-wrap:focus-within {
            border-color: rgba(6, 182, 212, 0.5);
            box-shadow: 0 8px 32px rgba(6, 182, 212, 0.15);
        }

        .pc-search-wrap > i {
            color: var(--text-muted);
            font-size: 18px;
        }

        .pc-search-wrap input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 16px;
            color: var(--text-main);
            background: transparent;
            padding: 12px 0;
        }

        .pc-search-wrap input::placeholder {
            color: #64748b;
        }

        .pc-search-btn {
            background: linear-gradient(135deg, var(--accent-1), var(--accent-2));
            border: none;
            color: #05080f;
            border-radius: 12px;
            padding: 12px 28px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s ease;
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .pc-search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        /* ── Floating Stats Bar ── */
        .pc-float-bar {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255,255,255,0.1);
            padding: 28px 32px;
            display: flex;
            gap: 0;
            flex-wrap: wrap;
            margin-top: -60px;
            position: relative;
            z-index: 10;
            margin-bottom: 40px;
        }

        .pc-float-stat {
            flex: 1;
            min-width: 130px;
            padding: 0 24px;
            border-right: 1px solid rgba(255,255,255,0.05);
            text-align: center;
        }

        .pc-float-stat:first-child { padding-left: 0; }
        .pc-float-stat:last-child { border-right: none; padding-right: 0; }

        .pfs-val {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            line-height: 1;
            text-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }

        .pfs-label {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .pfs-accent { color: var(--accent-1); text-shadow: 0 0 15px rgba(16,185,129,0.4); }

        /* ── Category Pills ── */
        .pc-cats-wrap {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0 0 32px;
            animation: fadeUp 0.6s ease forwards;
            opacity: 0;
            transform: translateY(10px);
        }

        .pc-cat-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-muted);
            cursor: pointer;
            text-decoration: none;
            transition: all .2s ease;
            backdrop-filter: blur(10px);
        }

        .pc-cat-pill:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.2);
            color: #fff;
            transform: translateY(-2px);
        }

        .pc-cat-pill.active {
            background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(6,182,212,0.15));
            border-color: var(--accent-1);
            color: #fff;
            box-shadow: 0 0 20px rgba(16,185,129,0.15);
        }

        /* ── Section Heading ── */
        .pc-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 50px 0 24px;
            border-bottom: 1px solid var(--glass-border);
            padding-bottom: 16px;
        }

        .pc-section-head h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: #fff;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pc-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--accent-1);
            box-shadow: 0 0 12px var(--accent-1);
        }

        /* ── Stat Chips ── */
        .pc-cat-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .pc-stat-chip {
            background: var(--glass-bg);
            border-radius: 16px;
            padding: 16px 20px;
            border: 1px solid var(--glass-border);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 6px;
            backdrop-filter: blur(10px);
            transition: transform 0.2s;
        }
        
        .pc-stat-chip:hover {
            transform: translateY(-2px);
            background: rgba(255,255,255,0.06);
        }

        .chip-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .chip-val {
            font-family: 'Poppins', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: #fff;
        }

        .c-green .chip-val { color: var(--accent-1); text-shadow: 0 0 10px rgba(16,185,129,0.3); }
        .c-red .chip-val { color: #f43f5e; text-shadow: 0 0 10px rgba(244,63,94,0.3); }
        .c-blue .chip-val { color: #60a5fa; text-shadow: 0 0 10px rgba(96,165,250,0.3); }

        /* ── Modern Product List (Replaces Table) ── */
        .pc-product-group {
            background: var(--glass-bg);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            padding: 24px;
            margin-bottom: 32px;
            backdrop-filter: blur(12px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: fadeUp 0.6s ease forwards;
            opacity: 0;
            transform: translateY(10px);
        }

        .pc-product-group:nth-child(even) { animation-delay: 0.1s; }
        
        .pg-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .pg-title {
            font-family: 'Poppins', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pg-meta {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
            background: rgba(255,255,255,0.05);
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        /* Chart rows inside group */
        .pc-chart-row {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
            padding: 12px 16px;
            background: rgba(0,0,0,0.2);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.02);
            transition: all 0.2s ease;
        }

        .pc-chart-row:hover {
            background: rgba(255,255,255,0.04);
            border-color: rgba(255,255,255,0.08);
        }

        .pc-chart-label {
            width: 140px;
            font-size: 13px;
            color: #e2e8f0;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pc-chart-bar-wrap {
            flex: 1;
            height: 12px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.5);
            position: relative;
        }

        .pc-chart-bar {
            height: 100%;
            border-radius: 10px;
            transition: width 1s cubic-bezier(.25, .46, .45, .94);
            position: relative;
            box-shadow: 0 0 10px rgba(255,255,255,0.2);
        }

        .pc-chart-price {
            width: 90px;
            text-align: right;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        /* List Items */
        .pc-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 24px;
        }

        .pc-list-item {
            display: grid;
            grid-template-columns: 85px 2fr 1fr 1fr 1fr auto;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .pc-list-item:hover {
            background: rgba(255,255,255,0.05);
            border-color: rgba(255,255,255,0.15);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }

        .pct-rank {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 32px;
            padding: 0 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .rank-gold { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
        .rank-high { background: rgba(244, 63, 94, 0.1); color: #fb7185; border: 1px solid rgba(244,63,94,0.2); }
        .rank-mid { background: rgba(255,255,255,0.05); color: #cbd5e1; border: 1px solid rgba(255,255,255,0.1); }

        .pct-farmer {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pct-img {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            object-fit: cover;
            background: rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 1.2rem;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .pct-img img { width: 100%; height: 100%; object-fit: cover; }
        
        .farmer-info a {
            color: #fff;
            font-weight: 600;
            text-decoration: none;
            font-size: 14px;
            display: block;
            margin-bottom: 2px;
            transition: color 0.2s;
        }
        .farmer-info a:hover { color: var(--accent-1); }
        
        .pct-loc { font-size: 12px; color: var(--text-muted); }

        .price-block {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .pct-price { font-family: 'Poppins', sans-serif; font-size: 18px; font-weight: 800; color: #fff; }
        .pct-qty { color: var(--text-muted); font-size: 12px; }

        .pct-vs {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .vs-cheap { background: rgba(16,185,129,0.1); color: var(--accent-1); }
        .vs-pricey { background: rgba(244,63,94,0.1); color: #fb7185; }
        .vs-mid { background: rgba(255,255,255,0.05); color: #94a3b8; }

        .bids-block { text-align: center; }
        .bids-val { font-weight: 800; color: var(--accent-2); font-size: 16px; }
        .bids-lbl { font-size: 11px; color: var(--text-muted); text-transform: uppercase; }

        .view-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(255,255,255,0.05);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all .2s ease;
            white-space: nowrap;
        }

        .view-btn:hover {
            background: linear-gradient(135deg, var(--accent-1), var(--accent-2));
            border-color: transparent;
            color: #05080f;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16,185,129,0.3);
        }

        @media (max-width: 900px) {
            .pc-list-item {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 20px;
            }
            .pct-rank { align-self: flex-start; }
            .bids-block { text-align: left; display: flex; gap: 8px; align-items: center; }
            .view-btn { width: 100%; margin-top: 8px; }
        }

        /* ── Empty state ── */
        .pc-empty {
            padding: 100px 20px;
            text-align: center;
            color: var(--text-muted);
            background: var(--glass-bg);
            border-radius: 20px;
            border: 1px dashed var(--glass-border);
            backdrop-filter: blur(10px);
        }

        .pc-empty i { font-size: 4rem; margin-bottom: 20px; display: block; opacity: 0.5; }
        .pc-empty p { font-size: 16px; margin: 0; }

        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <!-- HERO -->
    <div class="pc-hero">
        <div class="container" style="max-width:1100px;position:relative;z-index:2;">
            <div class="pc-hero-badge"><i class="fas fa-balance-scale"></i> Price Comparison</div>
            <h1>Compare Prices <span>Across Farmers</span></h1>
            <p class="sub">Find the best deals on fresh produce. Compare live listings, track price trends, and make smarter buying decisions.</p>
            <form method="GET" action="price_compare.php">
                <?php if ($selected_category): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($selected_category); ?>">
                <?php endif; ?>
                <div class="pc-search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" id="pcSearch"
                        placeholder="Search mango, tomato, dairy…"
                        value="<?php echo htmlspecialchars($search_query); ?>"
                        autocomplete="off">
                    <button type="submit" class="pc-search-btn"><i class="fas fa-arrow-right"></i> Compare</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MAIN -->
    <div class="container" style="max-width:1100px;">

        <!-- Floating global stats bar -->
        <?php
        $total_listings = array_sum(array_map('count', $grouped));
        $all_prices     = array_merge(...array_values(array_map(fn($g) => array_column($g, 'price'), $grouped ?: [[]])));
        $global_min     = $all_prices ? min($all_prices) : 0;
        $global_max     = $all_prices ? max($all_prices) : 0;
        $global_avg     = $all_prices ? round(array_sum($all_prices) / count($all_prices)) : 0;
        ?>
        <div class="pc-float-bar">
            <div class="pc-float-stat">
                <div class="pfs-val"><?php echo count($categories); ?></div>
                <div class="pfs-label">Categories</div>
            </div>
            <div class="pc-float-stat">
                <div class="pfs-val"><?php echo $total_listings; ?></div>
                <div class="pfs-label">Live Listings</div>
            </div>
            <div class="pc-float-stat">
                <div class="pfs-val pfs-accent"><?php echo number_format($global_min, 0); ?>৳</div>
                <div class="pfs-label">Lowest Price</div>
            </div>
            <div class="pc-float-stat">
                <div class="pfs-val"><?php echo number_format($global_avg, 0); ?>৳</div>
                <div class="pfs-label">Market Average</div>
            </div>
            <div class="pc-float-stat">
                <div class="pfs-val" style="color:#f43f5e;"><?php echo number_format($global_max, 0); ?>৳</div>
                <div class="pfs-label">Highest Price</div>
            </div>
        </div>

        <!-- Category filter pills -->
        <div class="pc-cats-wrap">
            <a href="price_compare.php<?php echo $search_query ? '?q=' . urlencode($search_query) : ''; ?>"
                class="pc-cat-pill <?php echo $selected_category === '' ? 'active' : ''; ?>">
                <i class="fas fa-th"></i> All
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="price_compare.php?category=<?php echo urlencode($cat); ?><?php echo $search_query ? '&q=' . urlencode($search_query) : ''; ?>"
                    class="pc-cat-pill <?php echo $selected_category === $cat ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($grouped)): ?>
            <div class="pc-empty">
                <i class="fas fa-seedling"></i>
                <p>No active listings found<?php echo $search_query ? ' for "' . htmlspecialchars($search_query) . '"' : ''; ?>.</p>
            </div>
        <?php else: ?>

            <?php foreach ($grouped as $cat => $items):
                $stats = $cat_stats[$cat];
                $min_p = $stats['min'];
                $max_p = $stats['max'];
                $avg_p = $stats['avg'];

                // Group items by product_name within this category
                $by_product = [];
                foreach ($items as $item) {
                    $by_product[$item['product_name']][] = $item;
                }
            ?>

                <!-- Category Section Heading -->
                <div class="pc-section-head">
                    <h2><span class="pc-dot"></span><?php echo htmlspecialchars($cat); ?></h2>
                    <span style="font-size:13px;color:var(--text-muted);font-weight:500;background:rgba(255,255,255,0.05);padding:4px 12px;border-radius:20px;"><?php echo count($items); ?> listing<?php echo count($items) !== 1 ? 's' : ''; ?></span>
                </div>

                <!-- Category stat chips -->
                <div class="pc-cat-stats">
                    <div class="pc-stat-chip c-green"><span class="chip-label">Lowest</span><span class="chip-val"><?php echo number_format($min_p, 0); ?>৳</span></div>
                    <div class="pc-stat-chip c-blue"><span class="chip-label">Avg Price</span><span class="chip-val"><?php echo number_format($avg_p, 0); ?>৳</span></div>
                    <div class="pc-stat-chip c-red"><span class="chip-label">Highest</span><span class="chip-val"><?php echo number_format($max_p, 0); ?>৳</span></div>
                    <div class="pc-stat-chip"><span class="chip-label">Sellers</span><span class="chip-val"><?php echo count($items); ?></span></div>
                </div>

                <?php foreach ($by_product as $prod_name => $prod_items):
                    $prod_prices = array_column($prod_items, 'price');
                    $prod_min = min($prod_prices);
                    $prod_max = max($prod_prices);
                    $prod_avg = round(array_sum($prod_prices) / count($prod_prices));

                    // Sort cheapest first
                    usort($prod_items, fn($a, $b) => $a['price'] <=> $b['price']);
                ?>

                    <!-- Product Group Panel -->
                    <div class="pc-product-group">
                        <div class="pg-header">
                            <div class="pg-title"><i class="fas fa-leaf" style="color:var(--accent-1);"></i> <?php echo htmlspecialchars($prod_name); ?></div>
                            <div class="pg-meta"><?php echo count($prod_items); ?> seller<?php echo count($prod_items) !== 1 ? 's' : ''; ?> • avg <?php echo number_format($prod_avg, 0); ?>৳</div>
                        </div>

                        <!-- Bar chart for this product -->
                        <?php if (count($prod_items) > 1): ?>
                            <div style="margin-bottom:24px;">
                                <?php foreach ($prod_items as $pi):
                                    $pct = $prod_max > 0 ? round(($pi['price'] / $prod_max) * 100) : 0;
                                    $is_lo = $pi['price'] == $prod_min;
                                    $is_hi = $pi['price'] == $prod_max;
                                    $bc = $is_lo ? 'background:linear-gradient(90deg, #10b981, #34d399)'
                                        : ($is_hi ? 'background:linear-gradient(90deg, #f43f5e, #fb7185)'
                                            : 'background:linear-gradient(90deg, #3b82f6, #60a5fa)');
                                    $fl = !empty($pi['farm_name']) ? $pi['farm_name'] : $pi['farmer_name'];
                                ?>
                                    <div class="pc-chart-row">
                                        <div class="pc-chart-label" title="<?php echo htmlspecialchars($fl); ?>">
                                            <?php if ($is_lo): ?><i class="fas fa-crown" style="color:#fbbf24;"></i><?php endif; ?>
                                            <?php echo htmlspecialchars($fl); ?>
                                        </div>
                                        <div class="pc-chart-bar-wrap">
                                            <div class="pc-chart-bar" style="width:0;<?php echo $bc; ?>" data-w="<?php echo $pct; ?>%"></div>
                                        </div>
                                        <div class="pc-chart-price"><?php echo number_format($pi['price'], 0); ?>৳</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Interactive List of Sellers -->
                        <div class="pc-list">
                            <?php foreach ($prod_items as $ri => $pi):
                                $is_lo  = $pi['price'] == $prod_min;
                                $is_hi  = $pi['price'] == $prod_max && count($prod_items) > 1;
                                $dp     = $prod_avg > 0 ? round((($pi['price'] - $prod_avg) / $prod_avg) * 100) : 0;
                                $fd     = !empty($pi['farm_name']) ? $pi['farm_name'] : $pi['farmer_name'];

                                if ($is_lo) {
                                    $rk = 'rank-gold';
                                    $rl = '<i class="fas fa-trophy" style="font-size:10px;"></i> Best';
                                } elseif ($is_hi) {
                                    $rk = 'rank-high';
                                    $rl = '<i class="fas fa-arrow-trend-up" style="font-size:10px;"></i> High';
                                } else {
                                    $rk = 'rank-mid';
                                    $rl = '#' . ($ri + 1);
                                }

                                if ($dp < -5) {
                                    $vc = 'vs-cheap';
                                    $vt = '<i class="fas fa-arrow-down"></i> ' . abs($dp) . '%';
                                } elseif ($dp > 5) {
                                    $vc = 'vs-pricey';
                                    $vt = '<i class="fas fa-arrow-up"></i> ' . $dp . '%';
                                } else {
                                    $vc = 'vs-mid';
                                    $vt = '≈ avg';
                                }
                            ?>
                                <div class="pc-list-item">
                                    <span class="pct-rank <?php echo $rk; ?>"><?php echo $rl; ?></span>
                                    
                                    <div class="pct-farmer">
                                        <div class="pct-img">
                                            <?php if (!empty($pi['image'])): ?>
                                                <img src="assets/images/<?php echo htmlspecialchars($pi['image']); ?>" alt="">
                                            <?php else: ?><i class="fas fa-image"></i><?php endif; ?>
                                        </div>
                                        <div class="farmer-info">
                                            <a href="farmer/profile.php?id=<?php echo (int)$pi['farmer_id']; ?>"><?php echo htmlspecialchars($fd); ?></a>
                                            <div class="pct-loc"><i class="fas fa-map-marker-alt" style="margin-right:4px;"></i><?php echo htmlspecialchars($pi['location'] ?? '—'); ?></div>
                                        </div>
                                    </div>

                                    <div class="price-block">
                                        <span class="pct-price"><?php echo number_format($pi['price'], 0); ?>৳</span>
                                        <span class="pct-qty">per <?php echo htmlspecialchars($pi['quantity'] . ' ' . $pi['unit']); ?></span>
                                    </div>

                                    <div>
                                        <span class="pct-vs <?php echo $vc; ?>" title="Compared to average"><?php echo $vt; ?></span>
                                    </div>

                                    <div class="bids-block">
                                        <div class="bids-val"><?php echo (int)$pi['bid_count']; ?></div>
                                        <div class="bids-lbl">Bids</div>
                                    </div>

                                    <a href="product_detail.php?id=<?php echo (int)$pi['id']; ?>" class="view-btn"><i class="fas fa-gavel"></i> Bid Now</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php endforeach; /* by_product */ ?>

            <?php endforeach; /* grouped */ ?>
        <?php endif; ?>

        <div style="height:60px;"></div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Animate bars with a slight delay for cool effect
            setTimeout(() => {
                document.querySelectorAll('.pc-chart-bar[data-w]').forEach(el => {
                    const w = el.getAttribute('data-w');
                    el.style.width = w;
                });
            }, 100);
        });
    </script>
</body>

</html>