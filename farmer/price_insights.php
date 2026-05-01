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
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
            color: #1e2d3d;
        }

        .pi-hero {
            background: linear-gradient(135deg, #4338ca 0%, #6366f1 50%, #818cf8 100%);
            border-radius: 20px;
            padding: 40px 36px 80px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(67, 56, 202, .3);
            margin-bottom: 0;
        }

        .pi-hero::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .07);
            top: -80px;
            right: -60px;
        }

        .pi-hero-badge {
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
            margin-bottom: 12px;
        }

        .pi-hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(22px, 3.5vw, 32px);
            font-weight: 800;
            margin: 0 0 6px;
        }

        .pi-hero .sub {
            font-size: 14px;
            opacity: .85;
        }

        .pi-hero-back {
            position: absolute;
            top: 28px;
            right: 28px;
            background: rgba(255, 255, 255, .2);
            border: 1px solid rgba(255, 255, 255, .3);
            color: #fff;
            border-radius: 10px;
            padding: 7px 16px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background .2s;
        }

        .pi-hero-back:hover {
            background: rgba(255, 255, 255, .32);
            color: #fff;
            text-decoration: none;
        }

        /* Summary chips */
        .pi-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-top: -42px;
            position: relative;
            z-index: 5;
            margin-bottom: 28px;
        }

        .pi-sum-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px 18px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, .1);
            border: 1px solid #edf0f6;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .pi-sum-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .sum-total {
            background: linear-gradient(135deg, #eef2ff, #dce0ff);
            color: #4f46e5;
        }

        .sum-below {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #15803d;
        }

        .sum-above {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #b91c1c;
        }

        .sum-equal {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #b45309;
        }

        .pi-sum-val {
            font-family: 'Poppins', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: #1a1a2e;
            line-height: 1;
        }

        .pi-sum-label {
            font-size: 11.5px;
            color: #8b98a6;
            font-weight: 600;
            margin-top: 2px;
        }

        /* Table card */
        .pi-table-card {
            background: #fff;
            border-radius: 18px;
            border: 1.5px solid #edf0f6;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .06);
            overflow: hidden;
            margin-bottom: 28px;
        }

        .pi-table-head {
            padding: 18px 24px;
            border-bottom: 1px solid #f1f4f8;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .pi-table-head h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
        }

        .pi-table-head a {
            font-size: 13px;
            color: #4f46e5;
            font-weight: 600;
            text-decoration: none;
        }

        table.pi-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.pi-table th {
            font-size: 11.5px;
            font-weight: 700;
            color: #8b98a6;
            text-transform: uppercase;
            letter-spacing: .8px;
            padding: 12px 20px;
            border-bottom: 2px solid #f1f4f8;
            background: #fafbfd;
            text-align: left;
        }

        table.pi-table td {
            padding: 14px 20px;
            border-bottom: 1px solid #f6f8fb;
            font-size: 13.5px;
            vertical-align: middle;
        }

        table.pi-table tr:last-child td {
            border-bottom: none;
        }

        table.pi-table tr:hover td {
            background: #fafbfd;
        }

        /* Gauge pill */
        .pi-gauge {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pi-gauge-bar {
            flex: 1;
            height: 8px;
            background: #f1f5f9;
            border-radius: 4px;
            overflow: hidden;
            min-width: 80px;
        }

        .pi-gauge-fill {
            height: 100%;
            border-radius: 4px;
            transition: width .6s;
        }

        .gauge-green {
            background: linear-gradient(90deg, #059669, #38ef7d);
        }

        .gauge-red {
            background: linear-gradient(90deg, #dc2626, #f87171);
        }

        .gauge-yellow {
            background: linear-gradient(90deg, #d97706, #fbbf24);
        }

        .pi-diff-pill {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .diff-low {
            background: #dcfce7;
            color: #15803d;
        }

        .diff-high {
            background: #fee2e2;
            color: #b91c1c;
        }

        .diff-avg {
            background: #fef3c7;
            color: #b45309;
        }

        .diff-none {
            background: #f1f5f9;
            color: #64748b;
        }

        .pi-my-price {
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            font-weight: 800;
            color: #1a1a2e;
        }

        .pi-mkt-range {
            font-size: 11.5px;
            color: #94a3b8;
        }

        /* Tip box */
        .pi-tip-box {
            background: linear-gradient(135deg, #eef2ff, #f5f3ff);
            border: 1.5px solid #c7d2fe;
            border-radius: 18px;
            padding: 22px 24px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .pi-tip-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: linear-gradient(135deg, #4338ca, #818cf8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
            flex-shrink: 0;
        }

        .pi-tip-box h5 {
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #312e81;
            margin: 0 0 4px;
        }

        .pi-tip-box p {
            font-size: 13px;
            color: #4338ca;
            margin: 0;
            line-height: 1.6;
        }

        /* empty */
        .pi-empty {
            padding: 60px 20px;
            text-align: center;
            color: #94a3b8;
        }

        .pi-empty i {
            font-size: 3rem;
            margin-bottom: 14px;
            display: block;
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="container py-4" style="max-width:1100px;">

        <!-- HERO -->
        <div class="pi-hero mb-0">
            <div class="pi-hero-badge"><i class="fas fa-chart-line"></i> Price Insights</div>
            <h1>My Price Intelligence</h1>
            <p class="sub">See how your listing prices compare to market competitors — and price smarter.</p>
            <a href="dashboard.php" class="pi-hero-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>

        <!-- SUMMARY CARDS (overlap hero) -->
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
                <h3><i class="fas fa-table" style="color:#4f46e5;margin-right:8px;font-size:14px;"></i>Listing Price Breakdown</h3>
                <a href="../price_compare.php">View market <i class="fas fa-arrow-right"></i></a>
            </div>

            <?php if (empty($listings)): ?>
                <div class="pi-empty">
                    <i class="fas fa-seedling"></i>
                    <p>No active listings to analyse. <a href="create_post.php" style="color:#4f46e5;font-weight:600;">Create a listing</a> first.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="pi-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>My Price</th>
                                <th>Market Avg</th>
                                <th>Status</th>
                                <th>Price Position</th>
                                <th>Bids</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listings as $l):
                                $my  = (float)$l['my_price'];
                                $avg = $l['market_avg'] !== null ? (float)$l['market_avg'] : null;
                                $mn  = $l['market_min'] !== null ? (float)$l['market_min'] : null;
                                $mx  = $l['market_max'] !== null ? (float)$l['market_max'] : null;
                                $competitors = (int)$l['competitor_count'];

                                if ($avg !== null) {
                                    $diff_pct = round((($my - $avg) / $avg) * 100);
                                    // Gauge: position of my price in market range
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
                                <tr>
                                    <td style="font-weight:600;color:#1a1a2e;"><?php echo htmlspecialchars($l['product_name']); ?></td>
                                    <td><span style="background:#eef2ff;color:#4338ca;border-radius:8px;padding:3px 10px;font-size:11.5px;font-weight:600;"><?php echo htmlspecialchars($l['category']); ?></span></td>
                                    <td>
                                        <div class="pi-my-price"><?php echo number_format($my, 0); ?>৳</div>
                                        <div class="pi-mkt-range">per <?php echo htmlspecialchars($l['quantity'] . ' ' . $l['unit']); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($avg !== null): ?>
                                            <div style="font-weight:700;color:#475569;"><?php echo number_format($avg, 0); ?>৳</div>
                                            <div class="pi-mkt-range"><?php echo number_format($mn, 0); ?>৳ – <?php echo number_format($mx, 0); ?>৳ (<?php echo $competitors; ?> seller<?php echo $competitors !== 1 ? 's' : ''; ?>)</div>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;font-size:12.5px;">No data yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span style="background:#dcfce7;color:#15803d;border-radius:8px;padding:3px 10px;font-size:11.5px;font-weight:700;">Active</span></td>
                                    <td>
                                        <?php if ($avg !== null): ?>
                                            <div class="pi-gauge">
                                                <div class="pi-gauge-bar">
                                                    <div class="pi-gauge-fill <?php echo $gauge_cls; ?>" style="width:0" data-w="<?php echo $gauge_pct; ?>%"></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mt-1">
                                            <span class="pi-diff-pill <?php echo $pill_class; ?>"><?php echo $pill_txt; ?></span>
                                        </div>
                                    </td>
                                    <td><span style="font-weight:700;color:#4f46e5;"><?php echo (int)$l['my_bids']; ?></span></td>
                                    <td>
                                        <div style="display:flex;gap:8px;">
                                            <a href="../product_detail.php?id=<?php echo (int)$l['id']; ?>"
                                                style="background:#eef2ff;color:#4338ca;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:700;text-decoration:none;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../price_compare.php?category=<?php echo urlencode($l['category']); ?>"
                                                style="background:#f0fdf4;color:#059669;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:700;text-decoration:none;"
                                                title="See category market">
                                                <i class="fas fa-balance-scale"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TIP -->
        <div class="pi-tip-box mb-4">
            <div class="pi-tip-icon"><i class="fas fa-lightbulb"></i></div>
            <div>
                <h5>Pricing Strategy Tip</h5>
                <p>Listings priced <strong>5–15% below the market average</strong> attract significantly more bids. Monitor competitors regularly and adjust your prices to stay competitive. Products with the most bids often sell for <strong>higher final prices</strong> due to buyer competition.</p>
            </div>
        </div>

    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.pi-gauge-fill[data-w]').forEach(el => {
                const w = el.getAttribute('data-w');
                setTimeout(() => {
                    el.style.width = w;
                }, 200);
            });
        });
    </script>
</body>

</html>