<?php
session_start();
include 'includes/db.php';
date_default_timezone_set('Asia/Dhaka');
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Pre-load wishlist post IDs for logged-in buyer
$wishlist_post_ids = [];
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'user') {
    $wl_pre = $conn->prepare("SELECT post_id FROM wishlist WHERE user_id = ?");
    if ($wl_pre) {
        $wl_pre->bind_param("i", $_SESSION['user_id']);
        $wl_pre->execute();
        $wl_res = $wl_pre->get_result();
        while ($wl_row = $wl_res->fetch_assoc()) {
            $wishlist_post_ids[] = $wl_row['post_id'];
        }
        $wl_pre->close();
    }
}

// Which tab was requested?
$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'ending-soon' ? 'ending-soon' : 'all';
$location_filter = isset($_GET['location']) ? sanitize($_GET['location']) : '';
$location_sql = '';
if ($location_filter !== '') {
    $location_escaped = $conn->real_escape_string($location_filter);
    $location_sql = " AND users.location LIKE '%{$location_escaped}%'";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Auctions – Farmers' Marketplace</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        /* ── Page hero banner ── */
        .auctions-hero {
            background: linear-gradient(135deg, #064e3b 0%, #065f46 60%, #047857 100%);
            padding: 52px 0 44px;
            margin-bottom: 0;
            position: relative;
            overflow: hidden;
        }

        .auctions-hero::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, .04);
            border-radius: 50%;
        }

        .auctions-hero::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -40px;
            width: 220px;
            height: 220px;
            background: rgba(255, 255, 255, .04);
            border-radius: 50%;
        }

        .auctions-hero-inner {
            position: relative;
            z-index: 2;
        }

        .auctions-hero h1 {
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 6px;
        }

        .auctions-hero p {
            color: rgba(255, 255, 255, .75);
            font-size: .95rem;
            margin: 0;
        }

        .auctions-live-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 50px;
            padding: 5px 14px;
            color: #fff;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .4px;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .auctions-live-pill .live-dot {
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            display: inline-block;
            animation: livePulse 1.5s infinite;
        }

        /* ── Tab bar ── */
        .auctions-tab-bar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
        }

        .auctions-tab-bar .container {
            display: flex;
            gap: 0;
        }

        .auctions-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 16px 24px;
            font-family: 'Poppins', sans-serif;
            font-size: .9rem;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: color .2s, border-color .2s;
            white-space: nowrap;
        }

        .auctions-tab-btn:hover {
            color: #065f46;
            text-decoration: none;
        }

        .auctions-tab-btn.active {
            color: #065f46;
            border-bottom-color: #10b981;
        }

        .auctions-tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            background: #f1f5f9;
            border-radius: 50px;
            font-size: .72rem;
            font-weight: 700;
            color: #475569;
            transition: background .2s, color .2s;
        }

        .auctions-tab-btn.active .auctions-tab-badge {
            background: #d1fae5;
            color: #065f46;
        }

        .auctions-tab-badge.ending-badge {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* ── Filter bar ── */
        .auction-filter-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .auction-sort-select {
            margin-left: auto;
            padding: 7px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: .84rem;
            color: #374151;
            background: #fff;
            cursor: pointer;
            outline: none;
        }

        /* ── Empty state ── */
        .auctions-empty {
            text-align: center;
            padding: 64px 24px;
            color: #94a3b8;
        }

        .auctions-empty i {
            font-size: 3rem;
            margin-bottom: 16px;
            display: block;
            color: #cbd5e1;
        }

        .auctions-empty h4 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }

        /* ── Wishlist btn (same as index) ── */
        .wl-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 5;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .92);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            color: #94a3b8;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
            transition: color .2s, transform .2s, background .2s;
        }

        .wl-btn:hover {
            transform: scale(1.15);
            background: #fff;
        }

        .wl-btn.saved {
            color: #ef4444;
        }

        .wl-btn .fa-heart {
            pointer-events: none;
        }

        /* ── Fire badge on ending-soon cards ── */
        .ending-soon-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, #ef4444, #f97316);
            color: #fff;
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
            padding: 3px 9px;
            border-radius: 50px;
            z-index: 5;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        @keyframes livePulse {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, .7);
            }

            70% {
                box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <!-- ══ PAGE HERO ══════════════════════════════════════════════════════════ -->
    <div class="auctions-hero">
        <div class="container auctions-hero-inner">
            <div class="auctions-live-pill">
                <span class="live-dot"></span> Live Now
            </div>
            <h1><i class="fas fa-gavel me-2"></i>Live Auctions</h1>
            <p>Browse all active auctions or find ones ending soon before you miss out.</p>
        </div>
    </div>

    <!-- ══ TAB BAR ══════════════════════════════════════════════════════════ -->
    <?php
    // Count totals for tab badges
    $total_all_stmt = $conn->prepare("SELECT COUNT(*) FROM posts
    JOIN users ON posts.farmer_id = users.id
    WHERE posts.is_approved=1 AND posts.status='active'
    AND posts.auction_start_date <= NOW() AND posts.auction_end_date > NOW()
    AND posts.id NOT IN (SELECT post_id FROM comments WHERE is_approved = 1)
    {$location_sql}");
    $total_all_stmt->execute();
    $total_all_stmt->bind_result($total_all_count);
    $total_all_stmt->fetch();
    $total_all_stmt->close();

    $total_ending_stmt = $conn->prepare("SELECT COUNT(*) FROM posts
    JOIN users ON posts.farmer_id = users.id
    WHERE posts.is_approved=1 AND posts.status='active'
    AND posts.auction_start_date <= NOW() AND posts.auction_end_date > NOW()
    AND UNIX_TIMESTAMP(posts.auction_end_date) - UNIX_TIMESTAMP(NOW()) <= 86400
    AND posts.id NOT IN (SELECT post_id FROM comments WHERE is_approved = 1)
    {$location_sql}");
    $total_ending_stmt->execute();
    $total_ending_stmt->bind_result($total_ending_count);
    $total_ending_stmt->fetch();
    $total_ending_stmt->close();
    ?>
    <div class="auctions-tab-bar">
        <div class="container">
            <a href="auctions.php?tab=all<?php echo $location_filter !== '' ? '&location=' . urlencode($location_filter) : ''; ?>"
                class="auctions-tab-btn <?php echo $active_tab === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-gavel"></i>
                All Live Auctions
                <span class="auctions-tab-badge"><?php echo $total_all_count; ?></span>
            </a>
            <a href="auctions.php?tab=ending-soon<?php echo $location_filter !== '' ? '&location=' . urlencode($location_filter) : ''; ?>"
                class="auctions-tab-btn <?php echo $active_tab === 'ending-soon' ? 'active' : ''; ?>">
                <i class="fas fa-fire"></i>
                Ending Soon
                <span class="auctions-tab-badge ending-badge"><?php echo $total_ending_count; ?></span>
            </a>
        </div>
    </div>

    <!-- ══ MAIN CONTENT ══════════════════════════════════════════════════════ -->
    <div class="main-container">
        <div class="container py-4">

            <?php if ($active_tab === 'all'): ?>
                <!-- ─── ALL LIVE AUCTIONS TAB ─────────────────────────────────── -->
                <div class="section-header mb-3">
                    <h2 class="section-title"><i class="fas fa-gavel me-2"></i>All Live Auctions</h2>
                    <p class="section-subtitle">Every auction currently open for bidding</p>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar-live mb-4">
                    <div class="auction-filter-wrap">
                        <form method="GET" action="auctions.php" class="d-inline-flex align-items-center gap-2 me-2">
                            <input type="hidden" name="tab" value="all">
                            <input type="text" name="location" class="form-control form-control-sm" style="min-width:220px;"
                                placeholder="Filter by farmer location" value="<?php echo htmlspecialchars($location_filter); ?>">
                            <button class="btn btn-sm btn-outline-success" type="submit"><i class="fas fa-map-marker-alt"></i></button>
                        </form>
                        <button class="filter-btn active" data-filter="all">All</button>
                        <?php
                        $all_cats = $conn->prepare("SELECT DISTINCT posts.category FROM posts
                    JOIN users ON posts.farmer_id = users.id
                    WHERE is_approved=1 AND status='active'
                    AND auction_start_date <= NOW() AND auction_end_date > NOW()
                    {$location_sql}
                    ORDER BY category ASC");
                        $all_cats->execute();
                        $all_cats_res = $all_cats->get_result();
                        while ($cat_row = $all_cats_res->fetch_assoc()):
                            $cat_name = htmlspecialchars($cat_row['category']);
                        ?>
                            <button class="filter-btn" data-filter="<?php echo $cat_name; ?>"><?php echo $cat_name; ?></button>
                        <?php endwhile;
                        $all_cats->close(); ?>
                        <select class="auction-sort-select" id="sortSelectAll">
                            <option value="ending_asc">Ending Soonest</option>
                            <option value="ending_desc">Ending Latest</option>
                            <option value="price_asc">Price: Low → High</option>
                            <option value="price_desc">Price: High → Low</option>
                            <option value="bids_desc">Most Bids</option>
                        </select>
                    </div>
                </div>

                <!-- Cards Grid -->
                <div id="all-auctions-grid" class="row">
                    <?php
                    $all_stmt = $conn->prepare("SELECT posts.*, users.username,
                (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as total_bids,
                (SELECT MAX(CAST(comment_text AS DECIMAL(15,2))) FROM comments WHERE post_id = posts.id AND comment_text REGEXP '^[0-9]+(\\.[0-9]+)?$') as max_bid
                FROM posts
                JOIN users ON posts.farmer_id = users.id
                WHERE posts.is_approved=1 AND posts.status='active'
                AND posts.auction_start_date <= NOW()
                AND posts.auction_end_date > NOW()
                AND posts.id NOT IN (SELECT post_id FROM comments WHERE is_approved = 1)
                {$location_sql}
                ORDER BY posts.auction_end_date ASC");
                    $all_stmt->execute();
                    $all_result = $all_stmt->get_result();
                    $current_time = time();

                    if ($all_result->num_rows > 0):
                        while ($post = $all_result->fetch_assoc()):
                            $post_id = $post['id'];
                            $auction_end_time = strtotime($post['auction_end_date']);
                            $time_remaining = $auction_end_time - $current_time;
                            $total_bids = $post['total_bids'];
                            $max_bid = $post['max_bid'];
                            $is_ending_soon = $time_remaining <= 86400;
                    ?>
                            <div class="col-lg-3 col-md-6 product-card live-auction-card fade-in-up"
                                data-category="<?php echo htmlspecialchars($post['category']); ?>"
                                data-name="<?php echo strtolower(htmlspecialchars($post['product_name'])); ?>"
                                data-price="<?php echo $post['price']; ?>"
                                data-bids="<?php echo $total_bids; ?>"
                                data-end="<?php echo $auction_end_time; ?>">
                                <a href="product_detail.php?id=<?php echo $post_id; ?>" class="br-card">
                                    <div class="br-card-img-wrap">
                                        <?php if ($post['image']): ?>
                                            <img src="assets/images/<?php echo htmlspecialchars($post['image']); ?>"
                                                alt="<?php echo htmlspecialchars($post['product_name']); ?>" class="br-card-img">
                                        <?php else: ?>
                                            <div class="br-card-img-placeholder"><i class="fas fa-leaf"></i></div>
                                        <?php endif; ?>
                                        <div class="br-view-overlay">
                                            <span class="br-view-btn"><i class="fas fa-eye"></i> View Details</span>
                                        </div>
                                        <?php if ($is_ending_soon): ?>
                                            <div class="ending-soon-badge">
                                                <i class="fas fa-fire"></i> Ending Soon
                                            </div>
                                        <?php else: ?>
                                            <div class="br-status-badge br-live">
                                                <span class="br-live-dot"></span> LIVE
                                            </div>
                                        <?php endif; ?>
                                        <div class="br-bids-pill">
                                            <i class="fas fa-gavel"></i> <?php echo $total_bids; ?> bid<?php echo $total_bids != 1 ? 's' : ''; ?>
                                        </div>
                                        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user'): ?>
                                            <button class="wl-btn <?php echo in_array($post_id, $wishlist_post_ids) ? 'saved' : ''; ?>"
                                                data-post-id="<?php echo $post_id; ?>"
                                                title="<?php echo in_array($post_id, $wishlist_post_ids) ? 'Remove from wishlist' : 'Save to wishlist'; ?>"
                                                onclick="event.preventDefault();event.stopPropagation();toggleWishlist(this);">
                                                <i class="fas fa-heart"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="br-card-body">
                                        <h3 class="br-card-title"><?php echo htmlspecialchars($post['product_name']); ?></h3>
                                        <div class="br-card-price-row">
                                            <div>
                                                <span class="br-price-label">Starting at</span>
                                                <span class="br-price-val">৳<?php echo number_format($post['price'], 2); ?></span>
                                            </div>
                                            <?php if ($max_bid && $max_bid > $post['price']): ?>
                                                <div class="br-current-bid">
                                                    <span class="br-cb-label">Current</span>
                                                    <span class="br-cb-val">৳<?php echo number_format($max_bid, 2); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="br-card-qty">
                                            <i class="fas fa-balance-scale"></i>
                                            <?php echo htmlspecialchars($post['quantity']); ?> <?php echo htmlspecialchars($post['unit']); ?>
                                        </div>
                                    </div>
                                    <div class="br-card-footer">
                                        <div class="br-farmer-info"
                                            onclick="event.preventDefault();event.stopPropagation();window.location.href='farmer/profile.php?id=<?php echo $post['farmer_id']; ?>'"
                                            title="View <?php echo htmlspecialchars($post['username']); ?>'s profile">
                                            <span class="br-farmer-avatar"><?php echo strtoupper(substr($post['username'], 0, 2)); ?></span>
                                            <span class="br-farmer-name"><?php echo htmlspecialchars($post['username']); ?></span>
                                            <i class="fas fa-external-link-alt br-farmer-link-icon"></i>
                                        </div>
                                        <div class="br-countdown" data-end="<?php echo $auction_end_time; ?>">
                                            <i class="fas fa-clock"></i>
                                            <span class="br-cd-label">Ends in</span>
                                            <span class="br-cd-text">–</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php
                        endwhile;
                    else:
                        ?>
                        <div class="col-12">
                            <div class="auctions-empty">
                                <i class="fas fa-gavel"></i>
                                <h4>No Live Auctions Right Now</h4>
                                <p>Check back soon — new auctions open every day.</p>
                                <a href="browse.php" class="btn btn-success mt-2">Browse All Products</a>
                            </div>
                        </div>
                    <?php endif;
                    $all_stmt->close(); ?>
                </div>

            <?php else: ?>
                <!-- ─── ENDING SOON TAB ───────────────────────────────────────── -->
                <div class="section-header mb-3">
                    <h2 class="section-title"><i class="fas fa-fire me-2"></i>Ending Soon</h2>
                    <p class="section-subtitle">Auctions closing within the next 24 hours — bid now before time runs out!</p>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar-live mb-4">
                    <div class="auction-filter-wrap">
                        <form method="GET" action="auctions.php" class="d-inline-flex align-items-center gap-2 me-2">
                            <input type="hidden" name="tab" value="ending-soon">
                            <input type="text" name="location" class="form-control form-control-sm" style="min-width:220px;"
                                placeholder="Filter by farmer location" value="<?php echo htmlspecialchars($location_filter); ?>">
                            <button class="btn btn-sm btn-outline-success" type="submit"><i class="fas fa-map-marker-alt"></i></button>
                        </form>
                        <button class="filter-btn active" data-filter="all">All</button>
                        <?php
                        $es_cats = $conn->prepare("SELECT DISTINCT posts.category FROM posts
                    JOIN users ON posts.farmer_id = users.id
                    WHERE is_approved=1 AND status='active'
                    AND auction_start_date <= NOW() AND auction_end_date > NOW()
                    AND UNIX_TIMESTAMP(auction_end_date) - UNIX_TIMESTAMP(NOW()) <= 86400
                    {$location_sql}
                    ORDER BY category ASC");
                        $es_cats->execute();
                        $es_cats_res = $es_cats->get_result();
                        while ($cat_row = $es_cats_res->fetch_assoc()):
                            $cat_name = htmlspecialchars($cat_row['category']);
                        ?>
                            <button class="filter-btn" data-filter="<?php echo $cat_name; ?>"><?php echo $cat_name; ?></button>
                        <?php endwhile;
                        $es_cats->close(); ?>
                    </div>
                </div>

                <!-- Cards Grid -->
                <div id="ending-auctions-grid" class="row">
                    <?php
                    $es_stmt = $conn->prepare("SELECT posts.*, users.username,
                (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as total_bids,
                (SELECT MAX(CAST(comment_text AS DECIMAL(15,2))) FROM comments WHERE post_id = posts.id AND comment_text REGEXP '^[0-9]+(\\.[0-9]+)?$') as max_bid
                FROM posts
                JOIN users ON posts.farmer_id = users.id
                WHERE posts.is_approved=1 AND posts.status='active'
                AND posts.auction_start_date <= NOW()
                AND posts.auction_end_date > NOW()
                AND UNIX_TIMESTAMP(posts.auction_end_date) - UNIX_TIMESTAMP(NOW()) <= 86400
                AND posts.id NOT IN (SELECT post_id FROM comments WHERE is_approved = 1)
                {$location_sql}
                ORDER BY posts.auction_end_date ASC");
                    $es_stmt->execute();
                    $es_result = $es_stmt->get_result();
                    $current_time = time();

                    if ($es_result->num_rows > 0):
                        while ($post = $es_result->fetch_assoc()):
                            $post_id = $post['id'];
                            $auction_end_time = strtotime($post['auction_end_date']);
                            $total_bids = $post['total_bids'];
                            $max_bid = $post['max_bid'];
                    ?>
                            <div class="col-lg-3 col-md-6 product-card live-auction-card fade-in-up"
                                data-category="<?php echo htmlspecialchars($post['category']); ?>"
                                data-name="<?php echo strtolower(htmlspecialchars($post['product_name'])); ?>">
                                <a href="product_detail.php?id=<?php echo $post_id; ?>" class="br-card">
                                    <div class="br-card-img-wrap">
                                        <?php if ($post['image']): ?>
                                            <img src="assets/images/<?php echo htmlspecialchars($post['image']); ?>"
                                                alt="<?php echo htmlspecialchars($post['product_name']); ?>" class="br-card-img">
                                        <?php else: ?>
                                            <div class="br-card-img-placeholder"><i class="fas fa-leaf"></i></div>
                                        <?php endif; ?>
                                        <div class="br-view-overlay">
                                            <span class="br-view-btn"><i class="fas fa-eye"></i> View Details</span>
                                        </div>
                                        <div class="ending-soon-badge">
                                            <i class="fas fa-fire"></i> Ending Soon
                                        </div>
                                        <div class="br-bids-pill">
                                            <i class="fas fa-gavel"></i> <?php echo $total_bids; ?> bid<?php echo $total_bids != 1 ? 's' : ''; ?>
                                        </div>
                                        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user'): ?>
                                            <button class="wl-btn <?php echo in_array($post_id, $wishlist_post_ids) ? 'saved' : ''; ?>"
                                                data-post-id="<?php echo $post_id; ?>"
                                                title="<?php echo in_array($post_id, $wishlist_post_ids) ? 'Remove from wishlist' : 'Save to wishlist'; ?>"
                                                onclick="event.preventDefault();event.stopPropagation();toggleWishlist(this);">
                                                <i class="fas fa-heart"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="br-card-body">
                                        <h3 class="br-card-title"><?php echo htmlspecialchars($post['product_name']); ?></h3>
                                        <div class="br-card-price-row">
                                            <div>
                                                <span class="br-price-label">Starting at</span>
                                                <span class="br-price-val">৳<?php echo number_format($post['price'], 2); ?></span>
                                            </div>
                                            <?php if ($max_bid && $max_bid > $post['price']): ?>
                                                <div class="br-current-bid">
                                                    <span class="br-cb-label">Current</span>
                                                    <span class="br-cb-val">৳<?php echo number_format($max_bid, 2); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="br-card-qty">
                                            <i class="fas fa-balance-scale"></i>
                                            <?php echo htmlspecialchars($post['quantity']); ?> <?php echo htmlspecialchars($post['unit']); ?>
                                        </div>
                                    </div>
                                    <div class="br-card-footer">
                                        <div class="br-farmer-info"
                                            onclick="event.preventDefault();event.stopPropagation();window.location.href='farmer/profile.php?id=<?php echo $post['farmer_id']; ?>'"
                                            title="View <?php echo htmlspecialchars($post['username']); ?>'s profile">
                                            <span class="br-farmer-avatar"><?php echo strtoupper(substr($post['username'], 0, 2)); ?></span>
                                            <span class="br-farmer-name"><?php echo htmlspecialchars($post['username']); ?></span>
                                            <i class="fas fa-external-link-alt br-farmer-link-icon"></i>
                                        </div>
                                        <div class="br-countdown" data-end="<?php echo $auction_end_time; ?>">
                                            <i class="fas fa-clock"></i>
                                            <span class="br-cd-label">Ends in</span>
                                            <span class="br-cd-text">–</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php
                        endwhile;
                    else:
                        ?>
                        <div class="col-12">
                            <div class="auctions-empty">
                                <i class="fas fa-fire"></i>
                                <h4>No Auctions Ending Within 24 Hours</h4>
                                <p>You're all caught up! Browse all live auctions instead.</p>
                                <a href="auctions.php?tab=all" class="btn btn-success mt-2">View All Live Auctions</a>
                            </div>
                        </div>
                    <?php endif;
                    $es_stmt->close(); ?>
                </div>
            <?php endif; ?>

        </div><!-- /container -->
    </div><!-- /main-container -->

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // ── Category filter buttons ──────────────────────────────────────────────
        document.querySelectorAll('.filter-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const filter = this.getAttribute('data-filter');
                document.querySelectorAll('.product-card').forEach(function(card) {
                    if (filter === 'all' || card.getAttribute('data-category') === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // ── Sort (All Auctions tab) ───────────────────────────────────────────────
        const sortSelect = document.getElementById('sortSelectAll');
        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                const grid = document.getElementById('all-auctions-grid');
                if (!grid) return;
                const cards = Array.from(grid.querySelectorAll('.product-card'));
                const val = this.value;

                cards.sort(function(a, b) {
                    if (val === 'ending_asc') return parseInt(a.dataset.end) - parseInt(b.dataset.end);
                    if (val === 'ending_desc') return parseInt(b.dataset.end) - parseInt(a.dataset.end);
                    if (val === 'price_asc') return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                    if (val === 'price_desc') return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                    if (val === 'bids_desc') return parseInt(b.dataset.bids) - parseInt(a.dataset.bids);
                    return 0;
                });
                cards.forEach(c => grid.appendChild(c));
            });
        }

        // ── Countdown timers ─────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            function pad(n) {
                return String(n).padStart(2, '0');
            }

            document.querySelectorAll('.br-countdown[data-end]').forEach(function(el) {
                const end = parseInt(el.getAttribute('data-end'));
                const textEl = el.querySelector('.br-cd-text');

                function tick() {
                    const diff = end - Math.floor(Date.now() / 1000);
                    if (diff <= 0) {
                        const card = el.closest('.br-card');
                        if (card) {
                            const badge = card.querySelector('.br-status-badge');
                            if (badge) {
                                badge.className = 'br-status-badge br-ended';
                                badge.innerHTML = '<i class="fas fa-flag-checkered"></i> Ended';
                            }
                        }
                        el.outerHTML = '<div class="br-ended-pill"><i class="fas fa-gavel"></i><span>Auction Ended</span></div>';
                        return;
                    }
                    const d = Math.floor(diff / 86400);
                    const h = Math.floor((diff % 86400) / 3600);
                    const m = Math.floor((diff % 3600) / 60);
                    const s = diff % 60;
                    textEl.textContent = d > 0 ?
                        `${d}d ${pad(h)}h ${pad(m)}m` :
                        h > 0 ? `${pad(h)}h ${pad(m)}m ${pad(s)}s` : `${pad(m)}m ${pad(s)}s`;
                }
                tick();
                setInterval(tick, 1000);
            });
        });

        // ── Wishlist toggle ───────────────────────────────────────────────────────
        function toggleWishlist(btn) {
            var postId = btn.dataset.postId;
            fetch('wishlist_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=toggle&post_id=' + postId
                })
                .then(r => r.json())
                .then(function(data) {
                    if (data.login_required) {
                        window.location.href = 'index.php?auth=login';
                        return;
                    }
                    if (data.success) {
                        if (data.saved) {
                            btn.classList.add('saved');
                            btn.title = 'Remove from wishlist';
                            showWlToast('♥ Saved to wishlist');
                        } else {
                            btn.classList.remove('saved');
                            btn.title = 'Save to wishlist';
                            showWlToast('Removed from wishlist');
                        }
                    }
                });
        }

        function showWlToast(msg) {
            var t = document.getElementById('wlToast');
            if (!t) {
                t = document.createElement('div');
                t.id = 'wlToast';
                t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(20px);background:#1e293b;color:#fff;padding:10px 20px;border-radius:50px;font-size:.82rem;font-weight:600;z-index:9999;opacity:0;transition:opacity .25s,transform .25s;pointer-events:none;';
                document.body.appendChild(t);
            }
            t.textContent = msg;
            t.style.opacity = '1';
            t.style.transform = 'translateX(-50%) translateY(0)';
            clearTimeout(t._timer);
            t._timer = setTimeout(function() {
                t.style.opacity = '0';
                t.style.transform = 'translateX(-50%) translateY(20px)';
            }, 2200);
        }
    </script>

    <?php include 'includes/footer.php'; ?>
</body>

</html>