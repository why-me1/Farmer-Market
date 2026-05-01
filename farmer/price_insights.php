<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

/** @var mysqli $conn */
/** @var string $base_url */

if ($_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Farmer's own active listings with market context
$sql = "
    SELECT
        p.id,
        p.product_name,
        p.category,
        p.price      AS my_price,
        p.quantity,
        p.unit,
        p.status,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS my_bids,
        (SELECT AVG(p2.price) FROM posts p2
            WHERE LOWER(p2.product_name) = LOWER(p.product_name)
              AND p2.category = p.category
              AND p2.is_approved = 1
              AND p2.status = 'active'
              AND p2.farmer_id != p.farmer_id) AS market_avg,
        (SELECT MIN(p3.price) FROM posts p3
            WHERE LOWER(p3.product_name) = LOWER(p.product_name)
              AND p3.category = p.category
              AND p3.is_approved = 1
              AND p3.status = 'active'
              AND p3.farmer_id != p.farmer_id) AS market_min,
        (SELECT MAX(p4.price) FROM posts p4
            WHERE LOWER(p4.product_name) = LOWER(p.product_name)
              AND p4.category = p.category
              AND p4.is_approved = 1
              AND p4.status = 'active'
              AND p4.farmer_id != p.farmer_id) AS market_max,
        (SELECT COUNT(DISTINCT p5.farmer_id) FROM posts p5
            WHERE LOWER(p5.product_name) = LOWER(p.product_name)
              AND p5.category = p.category
              AND p5.is_approved = 1
              AND p5.status = 'active'
              AND p5.farmer_id != p.farmer_id) AS competitor_count
    FROM posts p
    WHERE p.farmer_id = ?
      AND p.is_approved = 1
      AND p.status = 'active'
    ORDER BY p.category ASC, p.price ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Summary counts
$total    = count($listings);
$below    = 0;
$above = 0;
$equal = 0;
$no_comp = 0;
foreach ($listings as $l) {
    if ($l['market_avg'] === null) {
        $no_comp++;
        continue;
    }
    $diff = round((($l['my_price'] - $l['market_avg']) / $l['market_avg']) * 100);
    if ($diff < -5) $below++;
    elseif ($diff > 5) $above++;
    else $equal++;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Price Insights - Farmers' Market</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
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

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark) !important;
            background-image: 
                radial-gradient(circle at 85% 10%, rgba(16, 185, 129, 0.08), transparent 25%),
                radial-gradient(circle at 15% 80%, rgba(6, 182, 212, 0.08), transparent 25%) !important;
            background-attachment: fixed !important;
            color: var(--text-main) !important;
            margin: 0;
            min-height: 100vh;
        }

        /* HERO */
        .pi-hero {
            padding: 60px 0 100px;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid var(--glass-border);
            background: linear-gradient(180deg, rgba(16, 185, 129, 0.05) 0%, transparent 100%);
            margin-bottom: 0;
            border-radius: 24px;
            margin-top: 20px;
        }

        .pi-hero::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--glass-border), transparent);
        }

        .pi-hero-badge {
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
            margin-left: 36px;
        }

        .pi-hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(28px, 4vw, 46px);
            font-weight: 800;
            margin: 0 0 12px 36px;
            color: #fff;
        }

        .pi-hero h1 span {
            background: linear-gradient(135deg, var(--accent-1), var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .pi-hero .sub {
            font-size: 15px;
            color: var(--text-muted);
            margin-left: 36px;
            max-width: 500px;
        }

        .pi-hero-back {
            position: absolute;
            top: 36px;
            right: 36px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: #fff;
            border-radius: 12px;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all .2s;
            backdrop-filter: blur(10px);
        }

        .pi-hero-back:hover {
            background: rgba(255,255,255,0.08);
            color: var(--accent-1);
            transform: translateY(-2px);
        }

        /* Summary Grid */
        .pi-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: -50px;
            position: relative;
            z-index: 5;
            margin-bottom: 40px;
            padding: 0 10px;
        }

        .pi-sum-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 24px 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.3s ease;
        }

        .pi-sum-card:hover {
            transform: translateY(-4px);
            background: rgba(255,255,255,0.06);
        }

        .pi-sum-icon {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
            box-shadow: inset 0 2px 5px rgba(255,255,255,0.2);
        }

        .sum-total { background: linear-gradient(135deg, rgba(59,130,246,0.2), rgba(96,165,250,0.2)); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); }
        .sum-below { background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(52,211,153,0.2)); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
        .sum-above { background: linear-gradient(135deg, rgba(244,63,94,0.2), rgba(251,113,133,0.2)); color: #fb7185; border: 1px solid rgba(244,63,94,0.3); }
        .sum-equal { background: linear-gradient(135deg, rgba(245,158,11,0.2), rgba(251,191,36,0.2)); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }

        .pi-sum-val {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            line-height: 1;
        }

        .pi-sum-label {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Main Listing List */
        .pi-table-card {
            background: var(--glass-bg);
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            margin-bottom: 40px;
            backdrop-filter: blur(12px);
        }

        .pi-table-head {
            padding: 24px 28px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .pi-table-head h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pi-table-head a {
            font-size: 13px;
            color: var(--accent-1);
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }

        .pi-table-head a:hover {
            color: var(--accent-2);
        }

        /* The List */
        .pi-list-wrap {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .pi-list-item {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1fr 2fr 0.5fr auto;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.03);
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .pi-list-item:hover {
            background: rgba(255,255,255,0.05);
            border-color: rgba(255,255,255,0.1);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }

        /* Product Info */
        .prod-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .prod-name { font-weight: 700; color: #fff; font-size: 15px; }
        .prod-cat {
            display: inline-block;
            background: rgba(255,255,255,0.05);
            color: var(--accent-2);
            border-radius: 8px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
            width: fit-content;
            border: 1px solid rgba(6,182,212,0.2);
        }

        /* Prices */
        .price-col { display: flex; flex-direction: column; gap: 2px; }
        .my-price { font-family: 'Poppins', sans-serif; font-size: 18px; font-weight: 800; color: var(--accent-1); }
        .mkt-avg { font-family: 'Poppins', sans-serif; font-size: 16px; font-weight: 700; color: #fff; }
        .mkt-range { font-size: 11px; color: var(--text-muted); }

        /* Gauges */
        .pi-gauge-wrap { display: flex; flex-direction: column; gap: 6px; }
        .pi-gauge {
            width: 100%;
            height: 6px;
            background: rgba(255,255,255,0.05);
            border-radius: 3px;
            overflow: hidden;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.5);
        }
        .pi-gauge-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 1s cubic-bezier(.25, .46, .45, .94);
        }
        .gauge-green { background: linear-gradient(90deg, #10b981, #34d399); box-shadow: 0 0 8px rgba(16,185,129,0.5); }
        .gauge-red { background: linear-gradient(90deg, #f43f5e, #fb7185); box-shadow: 0 0 8px rgba(244,63,94,0.5); }
        .gauge-yellow { background: linear-gradient(90deg, #f59e0b, #fbbf24); box-shadow: 0 0 8px rgba(245,158,11,0.5); }

        .pi-diff-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            width: fit-content;
            text-transform: uppercase;
        }
        .diff-low { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
        .diff-high { background: rgba(244,63,94,0.15); color: #fb7185; border: 1px solid rgba(244,63,94,0.3); }
        .diff-avg { background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
        .diff-none { background: rgba(255,255,255,0.05); color: var(--text-muted); }

        /* Bids */
        .bid-col { text-align: center; }
        .bid-val { font-weight: 800; color: var(--accent-2); font-size: 16px; }
        .bid-lbl { font-size: 10px; color: var(--text-muted); text-transform: uppercase; }

        /* Actions */
        .action-btns {
            display: flex;
            gap: 8px;
        }
        .action-btn {
            background: rgba(255,255,255,0.05);
            color: #fff;
            border-radius: 10px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.2s;
        }
        .action-btn:hover {
            transform: translateY(-2px);
        }
        .btn-view:hover { background: rgba(6,182,212,0.2); border-color: var(--accent-2); color: var(--accent-2); }
        .btn-comp:hover { background: rgba(16,185,129,0.2); border-color: var(--accent-1); color: var(--accent-1); }

        /* Tip box */
        .pi-tip-box {
            background: rgba(16,185,129,0.05);
            border: 1px solid rgba(16,185,129,0.2);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            gap: 20px;
            align-items: flex-start;
            backdrop-filter: blur(10px);
            margin-bottom: 40px;
        }

        .pi-tip-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent-1), var(--accent-2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #05080f;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(16,185,129,0.3);
        }

        .pi-tip-box h5 {
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            margin: 0 0 8px;
        }

        .pi-tip-box p {
            font-size: 14px;
            color: #cbd5e1;
            margin: 0;
            line-height: 1.6;
        }

        /* Empty */
        .pi-empty {
            padding: 80px 20px;
            text-align: center;
            color: var(--text-muted);
        }
        .pi-empty i { font-size: 3rem; margin-bottom: 16px; display: block; opacity: 0.5; }

        @media (max-width: 900px) {
            .pi-list-item {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 20px;
            }
            .bid-col { text-align: left; display: flex; gap: 8px; align-items: center; }
            .action-btns { width: 100%; display: grid; grid-template-columns: 1fr 1fr; }
            .action-btn { width: 100%; }
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="container py-4" style="max-width:1100px;">

        <!-- HERO -->
        <div class="pi-hero">
            <div class="pi-hero-badge"><i class="fas fa-chart-pie"></i> Price Intelligence</div>
            <h1>My Price <span>Insights</span></h1>
            <p class="sub">See how your listing prices compare to market competitors — and price smarter.</p>
            <a href="dashboard.php" class="pi-hero-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>

        <!-- SUMMARY CARDS -->
        <div class="pi-summary-grid">
            <div class="pi-sum-card">
                <div class="pi-sum-icon sum-total"><i class="fas fa-list"></i></div>
                <div>
                    <div class="pi-sum-val"><?php echo $total; ?></div>
                    <div class="pi-sum-label">Active Listings</div>
                </div>
            </div>
            <div class="pi-sum-card">
                <div class="pi-sum-icon sum-below"><i class="fas fa-arrow-down"></i></div>
                <div>
                    <div class="pi-sum-val"><?php echo $below; ?></div>
                    <div class="pi-sum-label">Below Market</div>
                </div>
            </div>
            <div class="pi-sum-card">
                <div class="pi-sum-icon sum-equal"><i class="fas fa-equals"></i></div>
                <div>
                    <div class="pi-sum-val"><?php echo $equal; ?></div>
                    <div class="pi-sum-label">At Market Rate</div>
                </div>
            </div>
            <div class="pi-sum-card">
                <div class="pi-sum-icon sum-above"><i class="fas fa-arrow-up"></i></div>
                <div>
                    <div class="pi-sum-val"><?php echo $above; ?></div>
                    <div class="pi-sum-label">Above Market</div>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="pi-table-card">
            <div class="pi-table-head">
                <h3><i class="fas fa-layer-group" style="color:var(--accent-2);margin-right:8px;"></i>Listing Price Breakdown</h3>
                <a href="../price_compare.php">View full market <i class="fas fa-arrow-right"></i></a>
            </div>

            <?php if (empty($listings)): ?>
                <div class="pi-empty">
                    <i class="fas fa-seedling"></i>
                    <p>No active listings to analyze. <a href="create_post.php" style="color:var(--accent-1);font-weight:600;text-decoration:none;">Create a listing</a> first.</p>
                </div>
            <?php else: ?>
                <div class="pi-list-wrap">
                    <!-- Labels (desktop only) -->
                    <div class="pi-list-item" style="background:transparent;border:none;padding:0 20px;box-shadow:none;margin-bottom:-8px;grid-template-columns: 2fr 1.5fr 1fr 2fr 0.5fr auto;">
                        <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;">Product</div>
                        <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;">My Price</div>
                        <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;">Market Avg</div>
                        <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;">Position</div>
                        <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;text-align:center;">Bids</div>
                        <div style="width:80px;"></div>
                    </div>

                    <?php foreach ($listings as $l):
                        $my  = (float)$l['my_price'];
                        $avg = $l['market_avg'] !== null ? (float)$l['market_avg'] : null;
                        $mn  = $l['market_min'] !== null ? (float)$l['market_min'] : null;
                        $mx  = $l['market_max'] !== null ? (float)$l['market_max'] : null;
                        $competitors = (int)$l['competitor_count'];

                        if ($avg !== null) {
                            $diff_pct = round((($my - $avg) / $avg) * 100);
                            $range_span = $mx - $mn;
                            $gauge_pct  = $range_span > 0 ? round(min(100, max(5, (($my - $mn) / $range_span) * 100))) : 50;
                            if ($diff_pct < -5) {
                                $pill_class = 'diff-low';
                                $pill_txt = '<i class="fas fa-arrow-down"></i> ' . abs($diff_pct) . '% below';
                                $gauge_cls = 'gauge-green';
                            } elseif ($diff_pct > 5) {
                                $pill_class = 'diff-high';
                                $pill_txt = '<i class="fas fa-arrow-up"></i> ' . $diff_pct . '% above';
                                $gauge_cls = 'gauge-red';
                            } else {
                                $pill_class = 'diff-avg';
                                $pill_txt = '<i class="fas fa-equals"></i> At market';
                                $gauge_cls = 'gauge-yellow';
                            }
                        } else {
                            $diff_pct   = null;
                            $gauge_pct  = 50;
                            $pill_class = 'diff-none';
                            $pill_txt   = 'No competitors';
                            $gauge_cls  = 'gauge-yellow';
                        }
                    ?>
                        <div class="pi-list-item">
                            <div class="prod-info">
                                <span class="prod-name"><?php echo htmlspecialchars($l['product_name']); ?></span>
                                <span class="prod-cat"><?php echo htmlspecialchars($l['category']); ?></span>
                            </div>
                            
                            <div class="price-col">
                                <span class="my-price"><?php echo number_format($my, 0); ?>৳</span>
                                <span class="mkt-range">per <?php echo htmlspecialchars($l['quantity'] . ' ' . $l['unit']); ?></span>
                            </div>

                            <div class="price-col">
                                <?php if ($avg !== null): ?>
                                    <span class="mkt-avg"><?php echo number_format($avg, 0); ?>৳</span>
                                    <span class="mkt-range"><?php echo number_format($mn, 0); ?>৳ – <?php echo number_format($mx, 0); ?>৳</span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:12px;">No data yet</span>
                                <?php endif; ?>
                            </div>

                            <div class="pi-gauge-wrap">
                                <?php if ($avg !== null): ?>
                                    <div class="pi-gauge">
                                        <div class="pi-gauge-fill <?php echo $gauge_cls; ?>" style="width:0" data-w="<?php echo $gauge_pct; ?>%"></div>
                                    </div>
                                <?php endif; ?>
                                <span class="pi-diff-pill <?php echo $pill_class; ?>"><?php echo $pill_txt; ?></span>
                            </div>

                            <div class="bid-col">
                                <div class="bid-val"><?php echo (int)$l['my_bids']; ?></div>
                            </div>

                            <div class="action-btns">
                                <a href="../product_detail.php?id=<?php echo (int)$l['id']; ?>" class="action-btn btn-view" title="View Listing">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="../price_compare.php?category=<?php echo urlencode($l['category']); ?>" class="action-btn btn-comp" title="Compare Market">
                                    <i class="fas fa-balance-scale"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TIP -->
        <div class="pi-tip-box">
            <div class="pi-tip-icon"><i class="fas fa-lightbulb"></i></div>
            <div>
                <h5>Pricing Strategy Tip</h5>
                <p>Listings priced <strong style="color:var(--accent-1);">5–15% below the market average</strong> attract significantly more bids. Monitor competitors regularly and adjust your prices to stay competitive. Products with the most bids often sell for higher final prices due to buyer competition.</p>
            </div>
        </div>

    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                document.querySelectorAll('.pi-gauge-fill[data-w]').forEach(el => {
                    const w = el.getAttribute('data-w');
                    el.style.width = w;
                });
            }, 200);
        });
    </script>
</body>

</html>