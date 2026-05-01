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
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
            color: #1e2d3d;
        }

        /* ── Hero ── */
        .pc-hero {
            background: linear-gradient(135deg, #0d6e5e 0%, #11998e 50%, #38ef7d 100%);
            padding: 52px 0 80px;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .pc-hero::before {
            content: '';
            position: absolute;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .06);
            top: -100px;
            right: -80px;
            pointer-events: none;
        }

        .pc-hero::after {
            content: '';
            position: absolute;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .04);
            bottom: -60px;
            left: 20%;
            pointer-events: none;
        }

        .pc-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255, 255, 255, .18);
            border: 1px solid rgba(255, 255, 255, .3);
            border-radius: 30px;
            padding: 5px 15px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .pc-hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(26px, 4vw, 40px);
            font-weight: 800;
            margin: 0 0 10px;
            letter-spacing: -.5px;
        }

        .pc-hero .sub {
            font-size: 15px;
            opacity: .85;
            max-width: 520px;
        }

        /* ── Search bar ── */
        .pc-search-wrap {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .14);
            padding: 6px 8px 6px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pc-search-wrap i {
            color: #94a3b8;
            font-size: 15px;
        }

        .pc-search-wrap input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 14.5px;
            color: #1e293b;
            background: transparent;
            padding: 8px 0;
        }

        .pc-search-wrap input::placeholder {
            color: #94a3b8;
        }

        .pc-search-btn {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            border: none;
            color: #fff;
            border-radius: 10px;
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .2s;
        }

        .pc-search-btn:hover {
            opacity: .88;
        }

        /* ── Category pills ── */
        .pc-cats-wrap {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 28px 0 8px;
        }

        .pc-cat-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            border: 1.5px solid #d1fae5;
            background: #fff;
            color: #059669;
            cursor: pointer;
            text-decoration: none;
            transition: background .18s, color .18s, transform .15s;
        }

        .pc-cat-pill:hover {
            background: #dcfce7;
            text-decoration: none;
            transform: translateY(-1px);
        }

        .pc-cat-pill.active {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: #fff;
            border-color: transparent;
        }

        .pc-cat-pill.active:hover {
            color: #fff;
        }

        /* ── Section heading ── */
        .pc-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 36px 0 18px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .pc-section-head h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 19px;
            font-weight: 800;
            color: #1a1a2e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pc-section-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            display: inline-block;
        }

        /* ── Stat strip per category ── */
        .pc-cat-stats {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .pc-stat-chip {
            background: #fff;
            border-radius: 12px;
            padding: 10px 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .07);
            border: 1px solid #e8edf4;
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 110px;
        }

        .pc-stat-chip .chip-label {
            font-size: 10.5px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .8px;
        }

        .pc-stat-chip .chip-val {
            font-family: 'Poppins', sans-serif;
            font-size: 17px;
            font-weight: 800;
            color: #1a1a2e;
        }

        .pc-stat-chip.c-green .chip-val {
            color: #059669;
        }

        .pc-stat-chip.c-red .chip-val {
            color: #dc2626;
        }

        .pc-stat-chip.c-blue .chip-val {
            color: #4f46e5;
        }

        /* ── Product cards ── */
        .pc-card {
            background: #fff;
            border-radius: 18px;
            border: 1.5px solid #e8edf4;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .06);
            overflow: hidden;
            transition: transform .22s, box-shadow .22s;
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
        }

        .pc-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 14px 36px rgba(0, 0, 0, .12);
        }

        .pc-card-img {
            aspect-ratio: 4/3;
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            overflow: hidden;
            position: relative;
        }

        .pc-card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .pc-card-img .no-img {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #86efac;
        }

        /* Price rank badge */
        .pc-rank-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            z-index: 2;
        }

        .rank-best {
            background: linear-gradient(135deg, #059669, #38ef7d);
            color: #fff;
        }

        .rank-high {
            background: linear-gradient(135deg, #dc2626, #f87171);
            color: #fff;
        }

        .rank-mid {
            background: rgba(255, 255, 255, .9);
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }

        /* % vs avg badge */
        .pc-vs-avg {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, .95);
            border-radius: 10px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .1);
        }

        .vs-cheaper {
            color: #059669;
        }

        .vs-pricier {
            color: #dc2626;
        }

        .pc-card-body {
            padding: 16px 18px 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .pc-card-title {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
        }

        .pc-card-farmer {
            font-size: 12.5px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .pc-price-row {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-top: 4px;
        }

        .pc-price {
            font-family: 'Poppins', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: #059669;
        }

        .pc-unit {
            font-size: 12px;
            color: #94a3b8;
        }

        .pc-meta-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
        }

        .pc-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f0fdf4;
            color: #047857;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .pc-meta-chip.bid {
            background: #eef2ff;
            color: #4338ca;
        }

        .pc-meta-chip.loc {
            background: #fff7ed;
            color: #b45309;
        }

        /* Price bar */
        .pc-price-bar-wrap {
            margin-top: 10px;
        }

        .pc-price-bar-label {
            font-size: 10.5px;
            color: #94a3b8;
            margin-bottom: 4px;
        }

        .pc-price-bar-track {
            height: 6px;
            background: #f1f5f9;
            border-radius: 4px;
            overflow: hidden;
        }

        .pc-price-bar-fill {
            height: 100%;
            border-radius: 4px;
            background: linear-gradient(90deg, #11998e, #38ef7d);
            transition: width .6s cubic-bezier(.25, .46, .45, .94);
        }

        .pc-price-bar-fill.bar-red {
            background: linear-gradient(90deg, #f87171, #dc2626);
        }

        .pc-card-footer {
            padding: 0 18px 16px;
            margin-top: auto;
        }

        .pc-view-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 10px;
            border-radius: 12px;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: #fff;
            font-weight: 700;
            font-size: 13.5px;
            text-decoration: none;
            transition: opacity .2s, transform .15s;
        }

        .pc-view-btn:hover {
            opacity: .88;
            transform: translateY(-1px);
            color: #fff;
            text-decoration: none;
        }

        /* ── Price bar chart (horizontal) ── */
        .pc-bar-chart {
            background: #fff;
            border-radius: 18px;
            border: 1.5px solid #e8edf4;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .06);
            padding: 24px 24px 20px;
            margin-bottom: 32px;
        }

        .pc-bar-chart h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 20px;
        }

        .pc-chart-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .pc-chart-label {
            width: 130px;
            font-size: 12.5px;
            color: #475569;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-shrink: 0;
        }

        .pc-chart-bar-wrap {
            flex: 1;
            height: 28px;
            background: #f1f5f9;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }

        .pc-chart-bar {
            height: 100%;
            border-radius: 8px;
            display: flex;
            align-items: center;
            padding-left: 10px;
            font-size: 11.5px;
            font-weight: 700;
            color: #fff;
            transition: width .7s cubic-bezier(.25, .46, .45, .94);
            min-width: 40px;
        }

        .pc-chart-price {
            width: 80px;
            text-align: right;
            font-size: 13px;
            font-weight: 700;
            color: #059669;
            flex-shrink: 0;
        }

        /* ── Empty state ── */
        .pc-empty {
            padding: 60px 20px;
            text-align: center;
            color: #94a3b8;
        }

        .pc-empty i {
            font-size: 3rem;
            margin-bottom: 14px;
            display: block;
        }

        .pc-empty p {
            font-size: 15px;
            margin: 0;
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <!-- HERO -->
    <div class="pc-hero">
        <div class="container" style="max-width:1100px; position:relative; z-index:2;">
            <div class="pc-hero-badge"><i class="fas fa-balance-scale"></i> Price Comparison</div>
            <h1>Compare Prices Across Farmers</h1>
            <p class="sub">Find the best deals on fresh produce. Compare listings, track price trends, and make informed buying decisions.</p>

            <form method="GET" action="price_compare.php">
                <?php if ($selected_category): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($selected_category); ?>">
                <?php endif; ?>
                <div class="pc-search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" id="pcSearch"
                        placeholder="Search tomatoes, rice, dairy…"
                        value="<?php echo htmlspecialchars($search_query); ?>"
                        autocomplete="off">
                    <button type="submit" class="pc-search-btn"><i class="fas fa-arrow-right"></i> Compare</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MAIN -->
    <div class="container py-4" style="max-width:1100px;">

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
                $stats   = $cat_stats[$cat];
                $prices  = array_column($items, 'price');
                $min_p   = $stats['min'];
                $max_p   = $stats['max'];
                $avg_p   = $stats['avg'];
                $range   = max(1, $max_p - $min_p);
            ?>

                <!-- Category section -->
                <div class="pc-section-head">
                    <h2>
                        <span class="pc-section-dot"></span>
                        <?php echo htmlspecialchars($cat); ?>
                    </h2>
                    <span style="font-size:12.5px;color:#94a3b8;font-weight:500;"><?php echo count($items); ?> listing<?php echo count($items) !== 1 ? 's' : ''; ?></span>
                </div>

                <!-- Stat chips -->
                <div class="pc-cat-stats">
                    <div class="pc-stat-chip c-green">
                        <span class="chip-label">Lowest Price</span>
                        <span class="chip-val"><?php echo number_format($stats['min'], 0); ?>৳</span>
                    </div>
                    <div class="pc-stat-chip c-blue">
                        <span class="chip-label">Average Price</span>
                        <span class="chip-val"><?php echo number_format($stats['avg'], 0); ?>৳</span>
                    </div>
                    <div class="pc-stat-chip c-red">
                        <span class="chip-label">Highest Price</span>
                        <span class="chip-val"><?php echo number_format($stats['max'], 0); ?>৳</span>
                    </div>
                    <div class="pc-stat-chip">
                        <span class="chip-label">Sellers</span>
                        <span class="chip-val"><?php echo count($items); ?></span>
                    </div>
                </div>

                <!-- Horizontal bar chart -->
                <?php if (count($items) > 1): ?>
                    <div class="pc-bar-chart mb-4">
                        <h4><i class="fas fa-chart-bar" style="color:#11998e;margin-right:8px;"></i>Price Comparison Chart</h4>
                        <?php foreach ($items as $item):
                            $pct   = $max_p > 0 ? round(($item['price'] / $max_p) * 100) : 0;
                            $is_low = $item['price'] == $min_p;
                            $is_high = $item['price'] == $max_p;
                            $bar_color = $is_low ? 'background:linear-gradient(90deg,#059669,#38ef7d)' : ($is_high ? 'background:linear-gradient(90deg,#dc2626,#f87171)' : 'background:linear-gradient(90deg,#6366f1,#818cf8)');
                            $farmer_label = !empty($item['farm_name']) ? $item['farm_name'] : $item['farmer_name'];
                        ?>
                            <div class="pc-chart-row">
                                <div class="pc-chart-label" title="<?php echo htmlspecialchars($farmer_label); ?>"><?php echo htmlspecialchars($farmer_label); ?></div>
                                <div class="pc-chart-bar-wrap">
                                    <div class="pc-chart-bar" style="width:0; <?php echo $bar_color; ?>" data-w="<?php echo $pct; ?>%">
                                        <?php if ($is_low): ?><i class="fas fa-crown" style="font-size:10px;"></i><?php endif; ?>
                                    </div>
                                </div>
                                <div class="pc-chart-price"><?php echo number_format($item['price'], 0); ?>৳</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Cards grid -->
                <div class="row g-3 mb-5">
                    <?php foreach ($items as $idx => $item):
                        $rank = $idx === 0 ? 'rank-best' : ($idx === count($items) - 1 && count($items) > 1 ? 'rank-high' : 'rank-mid');
                        $rank_label = $idx === 0 ? '🏆 Best Price' : ($idx === count($items) - 1 && count($items) > 1 ? '📈 Highest' : '#' . ($idx + 1));
                        $diff_pct = $avg_p > 0 ? round((($item['price'] - $avg_p) / $avg_p) * 100) : 0;
                        $bar_w = $max_p > 0 ? round(($item['price'] / $max_p) * 100) : 0;
                        $is_low_card = $item['price'] == $min_p;
                        $farmer_disp = !empty($item['farm_name']) ? $item['farm_name'] : $item['farmer_name'];
                    ?>
                        <div class="col-sm-6 col-lg-4">
                            <div class="pc-card">
                                <div class="pc-card-img">
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="assets/images/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                    <?php else: ?>
                                        <div class="no-img"><i class="fas fa-leaf"></i></div>
                                    <?php endif; ?>
                                    <span class="pc-rank-badge <?php echo $rank; ?>"><?php echo $rank_label; ?></span>
                                    <?php if (count($items) > 1): ?>
                                        <span class="pc-vs-avg <?php echo $diff_pct <= 0 ? 'vs-cheaper' : 'vs-pricier'; ?>">
                                            <?php echo $diff_pct <= 0
                                                ? '<i class="fas fa-arrow-down"></i> ' . abs($diff_pct) . '% vs avg'
                                                : '<i class="fas fa-arrow-up"></i> ' . $diff_pct . '% vs avg'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="pc-card-body">
                                    <div class="pc-card-title"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div class="pc-card-farmer">
                                        <i class="fas fa-user-tie" style="color:#059669;"></i>
                                        <a href="farmer/profile.php?id=<?php echo (int)$item['farmer_id']; ?>"
                                            style="color:#059669;font-weight:600;text-decoration:none;"><?php echo htmlspecialchars($farmer_disp); ?></a>
                                    </div>
                                    <div class="pc-price-row">
                                        <span class="pc-price"><?php echo number_format($item['price'], 0); ?>৳</span>
                                        <span class="pc-unit">/ <?php echo htmlspecialchars($item['quantity'] . ' ' . $item['unit']); ?></span>
                                    </div>
                                    <div class="pc-meta-row">
                                        <span class="pc-meta-chip bid"><i class="fas fa-gavel"></i><?php echo (int)$item['bid_count']; ?> bids</span>
                                        <?php if (!empty($item['location'])): ?>
                                            <span class="pc-meta-chip loc"><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($item['location']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pc-price-bar-wrap">
                                        <div class="pc-price-bar-label">Price relative to highest in category</div>
                                        <div class="pc-price-bar-track">
                                            <div class="pc-price-bar-fill <?php echo !$is_low_card && $bar_w > 70 ? 'bar-red' : ''; ?>"
                                                style="width:0" data-w="<?php echo $bar_w; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="pc-card-footer">
                                    <a href="product_detail.php?id=<?php echo (int)$item['id']; ?>" class="pc-view-btn">
                                        <i class="fas fa-gavel"></i> View & Bid
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Animate bar chart
            document.querySelectorAll('.pc-chart-bar[data-w]').forEach(el => {
                const w = el.getAttribute('data-w');
                setTimeout(() => {
                    el.style.width = w;
                }, 200);
            });
            // Animate price bars in cards
            document.querySelectorAll('.pc-price-bar-fill[data-w]').forEach(el => {
                const w = el.getAttribute('data-w');
                setTimeout(() => {
                    el.style.width = w;
                }, 300);
            });
        });
    </script>
</body>

</html>