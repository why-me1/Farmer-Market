<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ratings.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../index.php');
    exit();
}

$farmerId = (int) $_GET['id'];

// Fetch farmer info
$farmer_stmt = $conn->prepare("SELECT id, username, created_at FROM users WHERE id = ? AND role = 'farmer' LIMIT 1");
$farmer_stmt->bind_param("i", $farmerId);
$farmer_stmt->execute();
$farmer = $farmer_stmt->get_result()->fetch_assoc();
$farmer_stmt->close();

if (!$farmer) {
    header('Location: ../index.php');
    exit();
}

// Stats: listings count
$total_listings = 0;
$sold_count = 0;
$success_rate = 0;

// Total posts by farmer
$total_stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE farmer_id = ?");
$total_stmt->bind_param("i", $farmerId);
$total_stmt->execute();
$total_stmt->bind_result($total_listings);
$total_stmt->fetch();
$total_stmt->close();

// Sold count (approved comment exists for the post)
$sold_stmt = $conn->prepare("SELECT COUNT(DISTINCT posts.id) 
                             FROM posts 
                             JOIN comments ON comments.post_id = posts.id AND comments.is_approved = 1 
                             WHERE posts.farmer_id = ?");
$sold_stmt->bind_param("i", $farmerId);
$sold_stmt->execute();
$sold_stmt->bind_result($sold_count);
$sold_stmt->fetch();
$sold_stmt->close();

if ($total_listings > 0) {
    $success_rate = round(($sold_count / $total_listings) * 100);
}

// Average rating across farmer's products (Customer Rating)
$avg_rating = null;
$review_count = 0;
$avg_stmt = $conn->prepare("SELECT AVG(reviews.rating) AS avg_rating, COUNT(reviews.id) AS total_reviews
                            FROM reviews 
                            JOIN posts ON posts.id = reviews.product_id 
                            WHERE posts.farmer_id = ?");
$avg_stmt->bind_param("i", $farmerId);
$avg_stmt->execute();
$avg_stmt->bind_result($avg_rating, $review_count);
$avg_stmt->fetch();
$avg_stmt->close();

// Get automatic rating (Fairness Rating)
$fairness_rating = get_user_automatic_rating($farmerId);
if ($fairness_rating === null) {
    $fairness_rating = 5.0; // Default if not found
}

// Determine seller label from average rating
$avg_rating_value = $avg_rating !== null ? (float)$avg_rating : 0.0;
$seller_stars = (int)round($avg_rating_value);
if ($seller_stars < 1 && $review_count > 0) {
    $seller_stars = 1; // If there are reviews but rounds to 0, show at least 1 star seller
}
$seller_label = $seller_stars > 0 ? $seller_stars . ' Star Seller' : 'New Seller';

// Fetch latest reviews across farmer's products
$reviews_stmt = $conn->prepare("SELECT r.id, r.rating, r.review_text, r.created_at, u.username AS reviewer_name, p.product_name
                                FROM reviews r
                                JOIN posts p ON p.id = r.product_id
                                JOIN users u ON u.id = r.user_id
                                WHERE p.farmer_id = ?
                                ORDER BY r.created_at DESC
                                LIMIT 10");
$reviews_stmt->bind_param("i", $farmerId);
$reviews_stmt->execute();
$farmer_reviews = $reviews_stmt->get_result();

// Current listings (most recent first)
$list_stmt = $conn->prepare("SELECT id, product_name, description, price, image, created_at 
                             FROM posts 
                             WHERE farmer_id = ? AND is_approved = 1 
                             ORDER BY created_at DESC LIMIT 9");
$list_stmt->bind_param("i", $farmerId);
$list_stmt->execute();
$listings = $list_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Profile &ndash; <?php echo htmlspecialchars($farmer['username']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* =======================================================
           BASE
        ======================================================= */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            background: #f0f4f1;
            font-family: 'Inter', sans-serif;
        }

        /* =======================================================
           COVER + PROFILE HEADER
        ======================================================= */
        .fp-cover {
            height: 200px;
            background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 40%, #52b788 100%);
            border-radius: 20px 20px 0 0;
            position: relative;
            overflow: hidden;
        }

        .fp-cover .cover-pattern {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle at 20% 80%, rgba(255, 255, 255, .07) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, .05) 0%, transparent 50%),
                repeating-linear-gradient(45deg, transparent, transparent 30px, rgba(255, 255, 255, .02) 30px, rgba(255, 255, 255, .02) 31px);
        }

        .fp-cover .cover-leaves {
            position: absolute;
            right: 32px;
            bottom: -10px;
            font-size: 7rem;
            opacity: .10;
            line-height: 1;
        }

        .fp-profile-card {
            background: #fff;
            border-radius: 0 0 20px 20px;
            padding: 0 36px 28px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, .08);
            margin-bottom: 28px;
            position: relative;
        }

        /* avatar */
        .fp-avatar-wrap {
            position: relative;
            display: inline-block;
            margin-top: -52px;
            margin-bottom: 14px;
        }

        .fp-avatar {
            width: 104px;
            height: 104px;
            background: linear-gradient(135deg, #2d6a4f, #52b788);
            border: 4px solid #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8rem;
            color: #fff;
            box-shadow: 0 4px 20px rgba(44, 106, 79, .30);
        }

        .fp-avatar-badge {
            position: absolute;
            bottom: 4px;
            right: 4px;
            width: 24px;
            height: 24px;
            background: #52b788;
            border: 2px solid #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .55rem;
            color: #fff;
        }

        /* name row */
        .fp-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: #1a1a2e;
            margin-bottom: 2px;
        }

        .fp-joined {
            font-size: .82rem;
            color: #9a9a9a;
        }

        .fp-seller-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: linear-gradient(135deg, #fff8e1, #fff3cd);
            border: 1.5px solid #ffc107;
            border-radius: 20px;
            padding: 3px 12px;
            font-size: .78rem;
            font-weight: 700;
            color: #856404;
            margin-left: 10px;
            vertical-align: middle;
        }

        .fp-seller-badge i {
            color: #ffc107;
        }

        /* inline star display */
        .fp-inline-stars {
            display: inline-flex;
            gap: 2px;
        }

        .fp-inline-stars .s-fill {
            color: #ffc107;
        }

        .fp-inline-stars .s-half {
            color: #ffc107;
        }

        .fp-inline-stars .s-empty {
            color: #e0e0e0;
        }

        /* quick-stats in header */
        .fp-quick-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 18px;
            border-top: 1px solid #f0f0f0;
            margin-top: 18px;
            align-items: center;
        }

        .fp-qs-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f7faf8;
            border: 1.5px solid #e2ede6;
            border-radius: 14px;
            padding: 12px 18px;
            transition: box-shadow .2s, transform .2s;
        }

        .fp-qs-item:hover {
            box-shadow: 0 4px 16px rgba(44, 106, 79, .10);
            transform: translateY(-2px);
        }

        .fp-qs-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .fp-qs-icon.blue {
            background: #e8f4fd;
            color: #0076ce;
        }

        .fp-qs-icon.green {
            background: #e6f4ea;
            color: #2d6a4f;
        }

        .fp-qs-icon.amber {
            background: #fff8e1;
            color: #d97706;
        }

        .fp-qs-item .qs-val {
            font-size: 1.4rem;
            font-weight: 800;
            color: #1a1a2e;
            line-height: 1;
        }

        .fp-qs-item .qs-lbl {
            font-size: .68rem;
            color: #9a9a9a;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-top: 2px;
        }

        .fp-qs-divider {
            display: none;
        }

        /* rating pill pair */
        .fp-rating-pair {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-left: auto;
        }

        .fp-rpill {
            background: #f7faf8;
            border: 1.5px solid #d0eada;
            border-radius: 14px;
            padding: 12px 18px;
            min-width: 148px;
            text-align: center;
        }

        .fp-rpill .rp-lbl {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #86a898;
            margin-bottom: 4px;
        }

        .fp-rpill .rp-val {
            font-size: 1.4rem;
            font-weight: 800;
            color: #2d6a4f;
            line-height: 1;
        }

        .fp-rpill .rp-sub {
            font-size: .72rem;
            color: #9a9a9a;
            margin-top: 3px;
        }

        /* =======================================================
           TABS
        ======================================================= */
        .fp-tabs {
            display: flex;
            gap: 4px;
            background: #fff;
            border-radius: 14px;
            padding: 6px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
        }

        .fp-tab-btn {
            flex: 1;
            text-align: center;
            padding: 10px 16px;
            border: none;
            background: none;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: .88rem;
            font-weight: 600;
            color: #7a8a7d;
            cursor: pointer;
            transition: all .2s;
        }

        .fp-tab-btn.active {
            background: #2d6a4f;
            color: #fff;
            box-shadow: 0 3px 12px rgba(44, 106, 79, .25);
        }

        .fp-tab-btn i {
            margin-right: 6px;
        }

        .fp-tab-pane {
            display: none;
        }

        .fp-tab-pane.active {
            display: block;
        }

        /* =======================================================
           LAYOUT &ndash; main + sidebar
        ======================================================= */
        .fp-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 24px;
        }

        @media(max-width:900px) {
            .fp-layout {
                grid-template-columns: 1fr;
            }
        }

        /* =======================================================
           SIDEBAR WIDGETS
        ======================================================= */
        .fp-widget {
            background: #fff;
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
        }

        .fp-widget-title {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #2d6a4f;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e8f5ea;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* contact rows */
        .contact-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .contact-row:last-child {
            border-bottom: none;
        }

        .contact-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: #e6f4ea;
            color: #2d6a4f;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: .85rem;
        }

        .contact-label {
            font-size: .7rem;
            color: #b0b0b0;
        }

        .contact-value {
            font-size: .88rem;
            font-weight: 600;
            color: #333;
        }

        /* success rate bar */
        .fp-rate-bar-wrap {
            margin-top: 8px;
        }

        .fp-rate-bar-wrap .bar-labels {
            display: flex;
            justify-content: space-between;
            font-size: .76rem;
            color: #9a9a9a;
            margin-bottom: 6px;
        }

        .fp-rate-bar {
            height: 8px;
            background: #e8f5ea;
            border-radius: 99px;
            overflow: hidden;
        }

        .fp-rate-bar .bar-fill {
            height: 100%;
            border-radius: 99px;
            background: linear-gradient(90deg, #52b788, #2d6a4f);
            transition: width .8s ease;
        }

        /* =======================================================
           PRODUCT GRID
        ======================================================= */
        .fp-product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 18px;
        }

        .fp-product-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
            transition: transform .22s, box-shadow .22s;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }

        .fp-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, .11);
        }

        .fp-img-wrap {
            height: 170px;
            overflow: hidden;
            background: linear-gradient(135deg, #e8f5ea, #d0eada);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .fp-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .35s;
        }

        .fp-product-card:hover .fp-img-wrap img {
            transform: scale(1.07);
        }

        .fp-img-placeholder {
            font-size: 3rem;
            color: rgba(44, 106, 79, .20);
        }

        .fp-card-body {
            padding: 14px 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .fp-card-title {
            font-size: .95rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 5px;
        }

        .fp-card-desc {
            font-size: .8rem;
            color: #7a7a7a;
            flex: 1;
            line-height: 1.5;
        }

        .fp-price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding-top: 11px;
            border-top: 1px solid #f4f4f4;
        }

        .fp-price {
            font-size: 1rem;
            font-weight: 800;
            color: #2d6a4f;
        }

        .fp-date {
            font-size: .73rem;
            color: #bbb;
        }

        /* =======================================================
           REVIEW CARDS
        ======================================================= */
        .fp-review-card {
            background: #fff;
            border-radius: 18px;
            padding: 22px 24px;
            margin-bottom: 18px;
            box-shadow: 0 4px 20px rgba(82, 183, 136, .10), 0 1px 4px rgba(0, 0, 0, .04);
            transition: transform .22s, box-shadow .22s;
            position: relative;
            overflow: hidden;
        }

        .fp-review-card::before {
            content: '\201C';
            position: absolute;
            top: -14px;
            right: 20px;
            font-size: 7rem;
            color: rgba(82, 183, 136, .07);
            font-family: Georgia, serif;
            line-height: 1;
            pointer-events: none;
            user-select: none;
        }

        .fp-review-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 36px rgba(82, 183, 136, .16), 0 2px 8px rgba(0, 0, 0, .06);
        }

        .rv-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #52b788, #2d6a4f);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(82, 183, 136, .38);
        }

        .rv-name {
            font-size: .93rem;
            font-weight: 700;
            color: #1a1a2e;
        }

        .rv-stars .s-fill {
            color: #ffc107;
            font-size: .92rem;
        }

        .rv-stars .s-empty {
            color: #dde;
            font-size: .92rem;
        }

        .rv-product {
            font-size: .74rem;
            color: #52b788;
            margin-top: 3px;
            font-weight: 600;
        }

        .rv-meta {
            font-size: .72rem;
            color: #8fa89a;
            background: #f0f7f3;
            border-radius: 20px;
            padding: 4px 11px;
            white-space: nowrap;
        }

        .rv-text {
            font-size: .87rem;
            color: #4a5568;
            margin-top: 13px;
            line-height: 1.68;
            background: linear-gradient(135deg, #f6fbf8, #edf7f2);
            border-radius: 10px;
            padding: 12px 16px;
            font-style: italic;
        }

        /* rating summary in review tab */
        .fp-rating-summary {
            background: linear-gradient(135deg, #f0faf4, #e6f4ea);
            border-radius: 14px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .rsum-big {
            font-size: 3.5rem;
            font-weight: 800;
            color: #2d6a4f;
            line-height: 1;
        }

        .rsum-stars {
            display: flex;
            gap: 4px;
            margin: 4px 0;
        }

        .rsum-stars i {
            color: #ffc107;
            font-size: 1.1rem;
        }

        .rsum-count {
            font-size: .82rem;
            color: #72987f;
        }

        /* =======================================================
           EMPTY STATES
        ======================================================= */
        .fp-empty {
            text-align: center;
            padding: 50px 20px;
            color: #c0c0c0;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .04);
        }

        .fp-empty i {
            font-size: 2.8rem;
            margin-bottom: 14px;
            display: block;
        }

        .fp-empty p {
            font-size: .9rem;
            margin: 0;
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="main-container">
        <div class="container" style="max-width:1160px;">

            <!-- ==============================================
                 PROFILE HEADER CARD
            ============================================== -->
            <div class="fp-cover">
                <div class="cover-pattern"></div>
                <div class="cover-leaves"><i class="fas fa-leaf"></i></div>
            </div>

            <div class="fp-profile-card">
                <div class="d-flex align-items-start flex-wrap" style="gap:20px;">

                    <!-- Avatar -->
                    <div class="fp-avatar-wrap">
                        <div class="fp-avatar"><i class="fas fa-seedling"></i></div>
                        <div class="fp-avatar-badge"><i class="fas fa-check"></i></div>
                    </div>

                    <!-- Name + ratings -->
                    <div class="flex-grow-1" style="padding-top:18px;">
                        <div class="d-flex align-items-center flex-wrap" style="gap:8px; margin-bottom:4px;">
                            <span class="fp-name"><?php echo htmlspecialchars($farmer['username']); ?></span>
                            <?php if ($seller_stars > 0): ?>
                                <span class="fp-seller-badge">
                                    <i class="fas fa-star"></i>
                                    <?php echo htmlspecialchars($seller_label); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="fp-joined">
                            <i class="fas fa-calendar-alt" style="color:#52b788;margin-right:4px;"></i>
                            Member since <?php echo date('F j, Y', strtotime($farmer['created_at'] ?? date('Y-m-d'))); ?>
                        </div>

                        <!-- Star display -->
                        <?php if ($review_count > 0): ?>
                            <div class="d-flex align-items-center" style="gap:6px; margin-top:10px;">
                                <div class="fp-inline-stars">
                                    <?php
                                    $full = floor($avg_rating_value);
                                    $half = ($avg_rating_value - $full) >= 0.5 ? 1 : 0;
                                    $empty = 5 - $full - $half;
                                    for ($i = 0; $i < $full; $i++)  echo '<i class="fas fa-star s-fill"></i>';
                                    if ($half)               echo '<i class="fas fa-star-half-alt s-half"></i>';
                                    for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star s-empty"></i>';
                                    ?>
                                </div>
                                <span style="font-size:.82rem;color:#7a7a7a;"><?php echo number_format($avg_rating_value, 1); ?> Â· <?php echo (int)$review_count; ?> review<?php echo $review_count == 1 ? '' : 's'; ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Quick stats -->
                        <div class="fp-quick-stats">
                            <div class="fp-qs-item">
                                <div class="fp-qs-icon blue"><i class="fas fa-list-ul"></i></div>
                                <div>
                                    <div class="qs-val"><?php echo (int)$total_listings; ?></div>
                                    <div class="qs-lbl">Listings</div>
                                </div>
                            </div>
                            <div class="fp-qs-item">
                                <div class="fp-qs-icon green"><i class="fas fa-hand-holding-usd"></i></div>
                                <div>
                                    <div class="qs-val"><?php echo (int)$sold_count; ?></div>
                                    <div class="qs-lbl">Sold</div>
                                </div>
                            </div>
                            <div class="fp-qs-item">
                                <div class="fp-qs-icon amber"><i class="fas fa-chart-line"></i></div>
                                <div>
                                    <div class="qs-val"><?php echo $success_rate; ?>%</div>
                                    <div class="qs-lbl">Success Rate</div>
                                </div>
                            </div>

                            <!-- Rating pills -->
                            <div class="fp-rating-pair ml-auto">
                                <div class="fp-rpill">
                                    <div class="rp-lbl">Customer Rating</div>
                                    <?php if ($review_count > 0): ?>
                                        <div class="rp-val"><?php echo number_format($avg_rating_value, 1); ?><span style="font-size:.75rem;font-weight:500;color:#9a9a9a;">/5</span></div>
                                        <div class="rp-sub"><?php echo (int)$review_count; ?> review<?php echo $review_count == 1 ? '' : 's'; ?></div>
                                    <?php else: ?>
                                        <div class="rp-val" style="font-size:.9rem;">No reviews</div>
                                    <?php endif; ?>
                                </div>
                                <div class="fp-rpill">
                                    <div class="rp-lbl">
                                        Fairness Rating
                                        <span title="Adjusts automatically based on how fair your prices are compared to the market." style="cursor:help;">&#8505;</span>
                                    </div>
                                    <div class="rp-val"><?php echo number_format($fairness_rating, 1); ?><span style="font-size:.75rem;font-weight:500;color:#9a9a9a;">/10</span></div>
                                    <div class="rp-sub">Market fairness</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==============================================
                 TABS
            ============================================== -->
            <div class="fp-tabs">
                <button class="fp-tab-btn active" onclick="switchTab('listings',this)">
                    <i class="fas fa-store"></i>Listings
                    <?php if ($total_listings > 0): ?>
                        <span style="background:rgba(44,106,79,.12);color:#2d6a4f;font-size:.72rem;padding:1px 7px;border-radius:10px;margin-left:4px;"><?php echo (int)$total_listings; ?></span>
                    <?php endif; ?>
                </button>
                <button class="fp-tab-btn" onclick="switchTab('reviews',this)">
                    <i class="fas fa-star"></i>Reviews
                    <?php if ($review_count > 0): ?>
                        <span style="background:rgba(44,106,79,.12);color:#2d6a4f;font-size:.72rem;padding:1px 7px;border-radius:10px;margin-left:4px;"><?php echo (int)$review_count; ?></span>
                    <?php endif; ?>
                </button>
                <button class="fp-tab-btn" onclick="switchTab('about',this)">
                    <i class="fas fa-info-circle"></i>About
                </button>
            </div>

            <!-- ==============================================
                 LISTINGS TAB
            ============================================== -->
            <div id="tab-listings" class="fp-tab-pane active">
                <?php if ($listings->num_rows > 0): ?>
                    <div class="fp-product-grid">
                        <?php while ($p = $listings->fetch_assoc()): ?>
                            <div class="fp-product-card">
                                <div class="fp-img-wrap">
                                    <?php if (!empty($p['image'])): ?>
                                        <img src="../assets/images/<?php echo htmlspecialchars($p['image']); ?>"
                                            alt="<?php echo htmlspecialchars($p['product_name']); ?>">
                                    <?php else: ?>
                                        <span class="fp-img-placeholder"><i class="fas fa-leaf"></i></span>
                                    <?php endif; ?>
                                </div>
                                <div class="fp-card-body">
                                    <div class="fp-card-title"><?php echo htmlspecialchars($p['product_name']); ?></div>
                                    <div class="fp-card-desc"><?php echo htmlspecialchars(mb_strimwidth($p['description'], 0, 90, '...')); ?></div>
                                    <div class="fp-price-row">
                                        <div class="fp-price">&#x09F3;<?php echo number_format($p['price'], 2); ?></div>
                                        <div class="fp-date"><i class="fas fa-clock" style="margin-right:3px;"></i><?php echo date('d M Y', strtotime($p['created_at'])); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="fp-empty">
                        <i class="fas fa-box-open"></i>
                        <p>No active listings yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ==============================================
                 REVIEWS TAB
            ============================================== -->
            <div id="tab-reviews" class="fp-tab-pane">

                <?php if ($review_count > 0): ?>
                    <!-- Rating summary bar -->
                    <div class="fp-rating-summary">
                        <div>
                            <div class="rsum-big"><?php echo number_format($avg_rating_value, 1); ?></div>
                            <div class="rsum-stars">
                                <?php
                                $full  = floor($avg_rating_value);
                                $half  = ($avg_rating_value - $full) >= 0.5 ? 1 : 0;
                                $empty = 5 - $full - $half;
                                for ($i = 0; $i < $full; $i++)  echo '<i class="fas fa-star"></i>';
                                if ($half)               echo '<i class="fas fa-star-half-alt"></i>';
                                for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star"></i>';
                                ?>
                            </div>
                            <div class="rsum-count"><?php echo (int)$review_count; ?> review<?php echo $review_count == 1 ? '' : 's'; ?></div>
                        </div>
                        <div style="flex:1;">
                            <?php
                            // Build per-star counts from already-fetched data â€” we'll show overview bars
                            $star_labels = [5, 4, 3, 2, 1];
                            foreach ($star_labels as $s):
                                $pct = 0; // simplified; requires separate query for exact counts
                            ?>
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                                    <span style="font-size:.72rem;color:#72987f;width:14px;"><?php echo $s; ?></span>
                                    <i class="fas fa-star" style="color:#ffc107;font-size:.72rem;"></i>
                                    <div style="flex:1;height:6px;background:#e8f5ea;border-radius:99px;overflow:hidden;">
                                        <div style="height:100%;background:linear-gradient(90deg,#ffc107,#ff9800);border-radius:99px;width:<?php echo $pct; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($farmer_reviews && $farmer_reviews->num_rows > 0): ?>
                    <?php while ($r = $farmer_reviews->fetch_assoc()):
                        $rf = (int)$r['rating'];
                    ?>
                        <div class="fp-review-card">
                            <div class="d-flex align-items-start" style="gap:12px;">
                                <div class="rv-avatar"><?php echo strtoupper(substr($r['reviewer_name'], 0, 1)); ?></div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:6px;">
                                        <div>
                                            <div class="rv-name"><?php echo htmlspecialchars($r['reviewer_name']); ?></div>
                                            <div class="rv-stars">
                                                <?php
                                                for ($i = 0; $i < $rf; $i++)   echo '<i class="fas fa-star s-fill"></i>';
                                                for ($i = $rf; $i < 5; $i++)   echo '<i class="far fa-star s-empty"></i>';
                                                ?>
                                            </div>
                                            <div class="rv-product"><i class="fas fa-tag" style="margin-right:4px;"></i><?php echo htmlspecialchars($r['product_name']); ?></div>
                                        </div>
                                        <div class="rv-meta"><i class="fas fa-calendar-alt" style="margin-right:4px;"></i><?php echo date('d M Y', strtotime($r['created_at'])); ?></div>
                                    </div>
                                    <?php if (!empty($r['review_text'])): ?>
                                        <div class="rv-text">"<?php echo htmlspecialchars($r['review_text']); ?>"</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="fp-empty">
                        <i class="fas fa-comment-slash"></i>
                        <p>No reviews yet for this farmer.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ==============================================
                 ABOUT TAB
            ============================================== -->
            <div id="tab-about" class="fp-tab-pane">
                <div class="fp-layout">
                    <!-- Left: stats breakdown -->
                    <div>
                        <div class="fp-widget">
                            <div class="fp-widget-title"><i class="fas fa-chart-bar"></i> Performance</div>

                            <div style="margin-bottom:18px;">
                                <div style="display:flex;justify-content:space-between;font-size:.82rem;color:#555;margin-bottom:6px;">
                                    <span><i class="fas fa-list-ul" style="color:#0076ce;margin-right:6px;"></i>Total Listings</span>
                                    <span style="font-weight:700;"><?php echo (int)$total_listings; ?></span>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:.82rem;color:#555;margin-bottom:6px;">
                                    <span><i class="fas fa-hand-holding-usd" style="color:#2d6a4f;margin-right:6px;"></i>Products Sold</span>
                                    <span style="font-weight:700;"><?php echo (int)$sold_count; ?></span>
                                </div>
                                <div style="margin-top:16px;">
                                    <div class="fp-rate-bar-wrap">
                                        <div class="bar-labels">
                                            <span>Success Rate</span>
                                            <span style="font-weight:700;color:#2d6a4f;"><?php echo $success_rate; ?>%</span>
                                        </div>
                                        <div class="fp-rate-bar">
                                            <div class="bar-fill" style="width:<?php echo $success_rate; ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top:8px;">
                                <div class="fp-rate-bar-wrap">
                                    <div class="bar-labels">
                                        <span>Customer Rating</span>
                                        <span style="font-weight:700;color:#2d6a4f;"><?php echo $review_count > 0 ? number_format($avg_rating_value, 1) . '/5' : 'N/A'; ?></span>
                                    </div>
                                    <div class="fp-rate-bar">
                                        <div class="bar-fill" style="width:<?php echo $review_count > 0 ? ($avg_rating_value / 5 * 100) : 0; ?>%;background:linear-gradient(90deg,#ffc107,#ff9800);"></div>
                                    </div>
                                </div>
                                <div class="fp-rate-bar-wrap" style="margin-top:12px;">
                                    <div class="bar-labels">
                                        <span>Fairness Rating</span>
                                        <span style="font-weight:700;color:#2d6a4f;"><?php echo number_format($fairness_rating, 1); ?>/10</span>
                                    </div>
                                    <div class="fp-rate-bar">
                                        <div class="bar-fill" style="width:<?php echo ($fairness_rating / 10 * 100); ?>%;background:linear-gradient(90deg,#52b788,#2d6a4f);"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: contact -->
                    <div>
                        <div class="fp-widget">
                            <div class="fp-widget-title"><i class="fas fa-address-card"></i> Contact</div>
                            <div class="contact-row">
                                <div class="contact-icon"><i class="fas fa-user"></i></div>
                                <div>
                                    <div class="contact-label">Farmer Name</div>
                                    <div class="contact-value"><?php echo htmlspecialchars($farmer['username']); ?></div>
                                </div>
                            </div>
                            <div class="contact-row">
                                <div class="contact-icon"><i class="fas fa-calendar-check"></i></div>
                                <div>
                                    <div class="contact-label">Member Since</div>
                                    <div class="contact-value"><?php echo date('d M Y', strtotime($farmer['created_at'] ?? date('Y-m-d'))); ?></div>
                                </div>
                            </div>
                            <div class="contact-row">
                                <div class="contact-icon"><i class="fas fa-phone"></i></div>
                                <div>
                                    <div class="contact-label">Phone</div>
                                    <div class="contact-value" style="color:#c0c0c0;font-style:italic;">Not provided</div>
                                </div>
                            </div>
                            <div class="contact-row">
                                <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div>
                                    <div class="contact-label">Location</div>
                                    <div class="contact-value" style="color:#c0c0c0;font-style:italic;">Not provided</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /container -->
    </div><!-- /main-container -->

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function switchTab(name, btn) {
            document.querySelectorAll('.fp-tab-pane').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.fp-tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + name).classList.add('active');
            btn.classList.add('active');
        }
    </script>
</body>

</html>