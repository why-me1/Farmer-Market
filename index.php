<?php
session_start();
date_default_timezone_set('Asia/Dhaka');
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/discovery.php';

// Pre-load wishlist post IDs for logged-in buyer
$wishlist_post_ids = [];
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'user') {
    $conn->query("CREATE TABLE IF NOT EXISTS `wishlist` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL, `post_id` INT NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_wishlist` (`user_id`, `post_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $wl_pre = $conn->prepare("SELECT post_id FROM wishlist WHERE user_id = ?");
    $wl_pre->bind_param("i", $_SESSION['user_id']);
    $wl_pre->execute();
    $wl_res = $wl_pre->get_result();
    while ($wl_row = $wl_res->fetch_assoc()) {
        $wishlist_post_ids[] = $wl_row['post_id'];
    }
    $wl_pre->close();
}

$recently_viewed_products = discoveryGetRecentlyViewedProducts(4);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmers’ Marketplace - Products in the Market</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css"> -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        .wl-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 5;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.92);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            color: #94a3b8;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
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
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="main-container">
        <!-- 1. HERO SECTION -->
        <div class="hero-section">
            <div class="hero-bg-shapes" aria-hidden="true">
                <div class="hero-shape hero-shape-1"></div>
                <div class="hero-shape hero-shape-2"></div>
                <div class="hero-shape hero-shape-3"></div>
            </div>
            <div class="container hero-grid">
                <div class="hero-left">
                    <div class="hero-badge-pill">
                        <span class="hero-badge-dot"></span>
                        <span>Live auctions happening now</span>
                    </div>
                    <h1 class="hero-title">Fresh Produce,<br><span class="hero-title-highlight">Directly from Farmers</span></h1>
                    <p class="hero-sub">Handpicked, seasonal and sustainably sourced — discover the best from your community marketplace, updated daily.</p>

                    <div class="hero-search-card">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search tomatoes, apples, dairy...">
                            <div class="input-group-append">
                                <button class="btn btn-success" id="heroSearchBtn">Search</button>
                            </div>
                        </div>

                        <div class="hero-chips mt-3">
                            <span class="hero-chips-label">Popular:</span>
                            <a href="search.php?q=tomato" class="chip">🍅 Tomatoes</a>
                            <a href="search.php?q=apples" class="chip">🍎 Apples</a>
                            <a href="search.php?q=dairy" class="chip">🥛 Dairy</a>
                            <a href="search.php?q=vegetables" class="chip">🥦 Vegetables</a>
                            <a href="search.php?q=honey" class="chip">🍯 Honey</a>
                        </div>
                    </div>

                    <div class="hero-cta mt-4">
                        <a href="browse.php" class="btn btn-hero-primary btn-lg"><i class="fas fa-store me-2"></i>Explore Marketplace</a>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="#" data-auth-modal="signup" class="btn btn-hero-secondary btn-lg"><i class="fas fa-seedling me-2"></i>Start Selling</a>
                        <?php else: ?>
                            <a href="farmer/create_post.php" class="btn btn-hero-secondary btn-lg"><i class="fas fa-seedling me-2"></i>Start Selling</a>
                        <?php endif; ?>
                    </div>

                    <div class="hero-trust-row mt-4">
                        <div class="hero-trust-item"><i class="fas fa-shield-alt"></i><span>Verified Farmers</span></div>
                        <div class="hero-trust-sep"></div>
                        <div class="hero-trust-item"><i class="fas fa-leaf"></i><span>100% Organic</span></div>
                        <div class="hero-trust-sep"></div>
                        <div class="hero-trust-item"><i class="fas fa-gavel"></i><span>Fair Auctions</span></div>
                    </div>
                </div>

                <div class="hero-right d-none d-lg-flex">
                    <div class="hero-visual">
                        <div class="hero-visual-main">
                            <div class="hero-visual-emoji">🌾</div>
                        </div>
                        <div class="hero-float-card hero-float-card-1">
                            <span class="hfc-icon">🍅</span>
                            <div class="hfc-text">
                                <span class="hfc-label">Fresh Tomatoes</span>
                                <span class="hfc-sub">৳ 45 / kg</span>
                            </div>
                        </div>
                        <div class="hero-float-card hero-float-card-2">
                            <span class="hfc-icon">🔥</span>
                            <div class="hfc-text">
                                <span class="hfc-label">Live Auction</span>
                                <span class="hfc-sub">Ends in 2h 14m</span>
                            </div>
                        </div>
                        <div class="hero-float-card hero-float-card-3">
                            <span class="hfc-icon">⭐</span>
                            <div class="hfc-text">
                                <span class="hfc-label">Top Rated</span>
                                <span class="hfc-sub">4.9 / 5 stars</span>
                            </div>
                        </div>
                        <div class="hero-visual-badge">
                            <i class="fas fa-check-circle me-1"></i> Trusted Marketplace
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 1b. STATS STRIP -->
        <div class="stats-strip">
            <div class="container">
                <div class="stats-strip-grid">
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-tractor"></i></div>
                        <div class="stat-body">
                            <span class="stat-number">100+</span>
                            <span class="stat-label">Active Farmers</span>
                        </div>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-boxes"></i></div>
                        <div class="stat-body">
                            <span class="stat-number">3,200+</span>
                            <span class="stat-label">Products Listed</span>
                        </div>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-gavel"></i></div>
                        <div class="stat-body">
                            <span class="stat-number">120+</span>
                            <span class="stat-label">Daily Auctions</span>
                        </div>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-smile"></i></div>
                        <div class="stat-body">
                            <span class="stat-number">10k+</span>
                            <span class="stat-label">Happy Buyers</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Success!</strong> <?php echo $_SESSION['success_message']; ?>
            </div>

            <script>
                setTimeout(function() {
                    const alert = document.getElementById('successAlert');
                    if (alert) {
                        alert.style.transition = "opacity 0.5s ease";
                        alert.style.opacity = "0";
                        setTimeout(() => alert.remove(), 500); // Remove completely after fade out
                    }
                }, 1000);
            </script>

            <?php
            // Clear the success message after displaying it
            unset($_SESSION['success_message']);
            ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" id="errorAlert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error!</strong> <?php echo $_SESSION['error_message']; ?>
            </div>

            <script>
                // Wait 5 seconds (5000 ms) then fade out the alert
                setTimeout(function() {
                    const alert = document.getElementById('errorAlert');
                    if (alert) {
                        alert.style.transition = "opacity 0.5s ease";
                        alert.style.opacity = "0";
                        setTimeout(() => alert.remove(), 500); // Remove completely after fade out
                    }
                }, 5000);
            </script>

            <?php
            // Clear the error message after displaying it
            unset($_SESSION['error_message']);
            ?>
        <?php endif; ?>




        <!-- 2. LIVE AUCTIONS TEASER BANNER -->
        <?php
        $teaser_all_count = 0;
        $teaser_end_count = 0;
        $teaser_all = $conn->prepare("SELECT COUNT(*) FROM posts WHERE is_approved=1 AND status='active' AND auction_start_date <= NOW() AND auction_end_date > NOW() AND CAST(posts.id AS CHAR) NOT IN (SELECT CAST(post_id AS CHAR) FROM comments WHERE is_approved = 1)");
        $teaser_all->execute();
        $teaser_all->bind_result($teaser_all_count);
        $teaser_all->fetch();
        $teaser_all->close();
        $teaser_end = $conn->prepare("SELECT COUNT(*) FROM posts WHERE is_approved=1 AND status='active' AND auction_start_date <= NOW() AND auction_end_date > NOW() AND UNIX_TIMESTAMP(auction_end_date)-UNIX_TIMESTAMP(NOW()) <= 86400 AND posts.id NOT IN (SELECT post_id FROM comments WHERE is_approved = 1)");
        $teaser_end->execute();
        $teaser_end->bind_result($teaser_end_count);
        $teaser_end->fetch();
        $teaser_end->close();
        ?>
        <div id="live-auctions" class="auction-teaser-banner mb-5">
            <div class="auction-teaser-left">
                <div class="auction-teaser-icon"><i class="fas fa-gavel"></i></div>
                <div>
                    <h3 class="auction-teaser-title"><span class="live-dot" style="width:10px;height:10px;display:inline-block;background:#ef4444;border-radius:50%;animation:livePulse 1.5s infinite;margin-right:8px;"></span>Live Auctions</h3>
                    <p class="auction-teaser-sub"><?php echo $teaser_all_count; ?> auctions live &nbsp;&bull;&nbsp; <?php echo $teaser_end_count; ?> ending within 24 hours</p>
                </div>
            </div>
            <div class="auction-teaser-actions">
                <a href="auctions.php?tab=ending-soon" class="btn auction-teaser-btn-fire"><i class="fas fa-fire me-2"></i>Ending Soon <span class="auction-teaser-count"><?php echo $teaser_end_count; ?></span></a>
                <a href="auctions.php?tab=all" class="btn auction-teaser-btn-all"><i class="fas fa-gavel me-2"></i>All Live Auctions <span class="auction-teaser-count"><?php echo $teaser_all_count; ?></span></a>
            </div>
        </div>
        <style>
            .auction-teaser-banner {
                background: linear-gradient(135deg, #022c22 0%, #065f46 100%);
                border-radius: 16px;
                padding: 28px 32px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 20px;
                flex-wrap: wrap;
                overflow: hidden;
                position: relative;
            }

            .auction-teaser-banner::after {
                content: '\f0e3';
                font-family: 'Font Awesome 6 Free';
                font-weight: 900;
                position: absolute;
                right: 32px;
                top: 50%;
                transform: translateY(-50%);
                font-size: 6rem;
                color: rgba(255, 255, 255, .05);
                pointer-events: none;
            }

            .auction-teaser-left {
                display: flex;
                align-items: center;
                gap: 18px;
                z-index: 1;
            }

            .auction-teaser-icon {
                width: 54px;
                height: 54px;
                background: rgba(255, 255, 255, .12);
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                color: #6ee7b7;
                flex-shrink: 0;
            }

            .auction-teaser-title {
                font-family: 'Poppins', sans-serif;
                font-size: 1.2rem;
                font-weight: 700;
                color: #fff;
                margin: 0 0 4px;
            }

            .auction-teaser-sub {
                color: rgba(255, 255, 255, .65);
                font-size: .85rem;
                margin: 0;
            }

            .auction-teaser-actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                z-index: 1;
            }

            .auction-teaser-btn-fire {
                background: linear-gradient(135deg, #ef4444, #f97316);
                color: #fff !important;
                border: none;
                border-radius: 50px;
                padding: 10px 22px;
                font-weight: 600;
                font-size: .88rem;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                transition: opacity .2s, transform .2s;
            }

            .auction-teaser-btn-fire:hover {
                opacity: .9;
                transform: translateY(-1px);
                text-decoration: none;
            }

            .auction-teaser-btn-all {
                background: rgba(255, 255, 255, .12);
                border: 1px solid rgba(255, 255, 255, .25);
                color: #fff !important;
                border-radius: 50px;
                padding: 10px 22px;
                font-weight: 600;
                font-size: .88rem;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                transition: background .2s, transform .2s;
            }

            .auction-teaser-btn-all:hover {
                background: rgba(255, 255, 255, .2);
                transform: translateY(-1px);
                text-decoration: none;
            }

            .auction-teaser-count {
                background: rgba(255, 255, 255, .22);
                border-radius: 50px;
                padding: 2px 8px;
                font-size: .75rem;
            }
        </style>

        <!-- 3. CATEGORY SECTIONS -->
        <div class="category-sections-wrapper mb-5">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-th-large me-2"></i>Browse by Category</h2>
                <p class="section-subtitle">Find exactly what you're looking for</p>
            </div>

            <div class="cat-chip-grid">
                <?php
                $all_categories = [
                    'Vegetables'     => ['icon' => 'fa-leaf',      'emoji' => '🥦'],
                    'Fruits'         => ['icon' => 'fa-apple-alt', 'emoji' => '🍎'],
                    'Grains'         => ['icon' => 'fa-wheat',     'emoji' => '🌾'],
                    'Dairy'          => ['icon' => 'fa-cheese',    'emoji' => '🧀'],
                    'Eggs'           => ['icon' => 'fa-egg',       'emoji' => '🥚'],
                    'Honey'          => ['icon' => 'fa-jar',       'emoji' => '🍯'],
                    'Herbs'          => ['icon' => 'fa-clover',    'emoji' => '🌿'],
                    'Root Vegetables' => ['icon' => 'fa-carrot',    'emoji' => '🥕'],
                ];
                $cat_colors = [
                    'Vegetables'     => ['bg' => '#e8f5e9', 'border' => '#a5d6a7', 'icon_bg' => '#388e3c', 'text' => '#2e7d32'],
                    'Fruits'         => ['bg' => '#fff3e0', 'border' => '#ffcc80', 'icon_bg' => '#e65100', 'text' => '#bf360c'],
                    'Grains'         => ['bg' => '#fff8e1', 'border' => '#ffe082', 'icon_bg' => '#f57f17', 'text' => '#e65100'],
                    'Dairy'          => ['bg' => '#e3f2fd', 'border' => '#90caf9', 'icon_bg' => '#1565c0', 'text' => '#0d47a1'],
                    'Eggs'           => ['bg' => '#fffde7', 'border' => '#fff176', 'icon_bg' => '#f9a825', 'text' => '#f57f17'],
                    'Honey'          => ['bg' => '#fbe9e7', 'border' => '#ffab91', 'icon_bg' => '#bf360c', 'text' => '#870000'],
                    'Herbs'          => ['bg' => '#e0f2f1', 'border' => '#80cbc4', 'icon_bg' => '#00695c', 'text' => '#004d40'],
                    'Root Vegetables' => ['bg' => '#efebe9', 'border' => '#bcaaa4', 'icon_bg' => '#5d4037', 'text' => '#4e342e'],
                ];
                foreach ($all_categories as $category_name => $meta):
                    $count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM posts WHERE is_approved=1 AND status IN ('active', 'sold', 'delivered') AND category=?");
                    $count_stmt->bind_param("s", $category_name);
                    $count_stmt->execute();
                    $count = $count_stmt->get_result()->fetch_assoc()['c'];
                    $count_stmt->close();
                    $c = $cat_colors[$category_name];
                ?>
                    <a href="browse.php?category=<?php echo urlencode($category_name); ?>" class="cat-chip"
                        style="--chip-bg:<?php echo $c['bg']; ?>;--chip-border:<?php echo $c['border']; ?>;--chip-icon:<?php echo $c['icon_bg']; ?>;--chip-text:<?php echo $c['text']; ?>">
                        <span class="cat-chip-icon"><?php echo $meta['emoji']; ?></span>
                        <span class="cat-chip-name"><?php echo $category_name; ?></span>
                        <span class="cat-chip-count"><?php echo $count; ?> listing<?php echo $count !== 1 ? 's' : ''; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 3b. HOW IT WORKS -->
        <div class="how-it-works-section mb-5">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-map-signs me-2"></i>How It Works</h2>
                <p class="section-subtitle">Three simple steps to get fresh produce delivered</p>
            </div>
            <div class="hiw-grid">
                <div class="hiw-step">
                    <div class="hiw-step-number">01</div>
                    <div class="hiw-icon-wrap"><i class="fas fa-search"></i></div>
                    <h4 class="hiw-title">Browse & Discover</h4>
                    <p class="hiw-desc">Explore hundreds of fresh, seasonal products listed daily by verified local farmers near you.</p>
                </div>
                <div class="hiw-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="hiw-step">
                    <div class="hiw-step-number">02</div>
                    <div class="hiw-icon-wrap"><i class="fas fa-gavel"></i></div>
                    <h4 class="hiw-title">Bid or Buy</h4>
                    <p class="hiw-desc">Place competitive bids in live auctions or buy products directly at the listed price — your choice.</p>
                </div>
                <div class="hiw-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="hiw-step">
                    <div class="hiw-step-number">03</div>
                    <div class="hiw-icon-wrap"><i class="fas fa-box-open"></i></div>
                    <h4 class="hiw-title">Get Fresh Produce</h4>
                    <p class="hiw-desc">Connect directly with the farmer to arrange delivery or pickup of your freshly harvested goods.</p>
                </div>
            </div>
        </div>

        <!-- 3c. SELLER CTA BANNER -->
        <div class="seller-cta-banner mb-5">
            <div class="seller-cta-content">
                <div class="seller-cta-left">
                    <span class="seller-cta-emoji">🌱</span>
                    <div>
                        <h3 class="seller-cta-title">Are you a farmer?</h3>
                        <p class="seller-cta-sub">Join thousands of farmers already selling fresh produce online. Set up your store in minutes.</p>
                    </div>
                </div>
                <div class="seller-cta-actions">
                    <a href="register.php" class="btn seller-cta-btn-outline">Learn More</a>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="#" data-auth-modal="signup" class="btn seller-cta-btn-primary"><i class="fas fa-plus me-2"></i>Start Selling</a>
                    <?php else: ?>
                        <a href="farmer/create_post.php" class="btn seller-cta-btn-primary"><i class="fas fa-plus me-2"></i>Start Selling</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 4. RECENTLY LISTED SECTION -->
        <div class="recently-listed-section mb-5">
            <div class="section-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="section-title mb-0"><i class="fas fa-star me-2"></i>Recently Listed</h2>
                    <p class="section-subtitle mb-0">Newest products added to the marketplace</p>
                </div>
                <a href="browse.php" class="cat-view-all-btn">View All <i class="fas fa-arrow-right"></i></a>
            </div>

            <div id="recently-listed-grid" class="row">
                <?php
                $recent_stmt = $conn->prepare("SELECT posts.*, users.username,
                                              (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as total_bids,
                                              (SELECT MAX(comment_text) FROM comments WHERE post_id = posts.id) as max_bid,
                                              EXISTS(SELECT 1 FROM comments WHERE post_id = posts.id AND is_approved = 1) as has_winner
                                              FROM posts 
                                              JOIN users ON posts.farmer_id = users.id 
                                              WHERE posts.is_approved = 1 AND posts.status IN ('active', 'sold', 'delivered')
                                              ORDER BY posts.created_at DESC
                                              LIMIT 8");
                $recent_stmt->execute();
                $recent_result = $recent_stmt->get_result();

                if ($recent_result->num_rows > 0):
                    while ($post = $recent_result->fetch_assoc()):
                        $post_id = $post['id'];
                        $current_time = time();
                        $auction_start_time = strtotime($post['auction_start_date']);
                        $auction_end_time = strtotime($post['auction_end_date']);

                        $is_sold  = (in_array($post['status'], ['sold', 'delivered'], true) || (int)$post['has_winner'] === 1);
                        $is_ended = ($is_sold || $current_time >= $auction_end_time);
                        $is_live  = (!$is_ended && $current_time >= $auction_start_time);

                        $total_bids = $post['total_bids'];
                        $max_bid = $post['max_bid'];
                ?>
                        <div class="col-lg-3 col-md-6 product-card recently-listed-card fade-in-up" data-name="<?php echo strtolower(htmlspecialchars($post['product_name'])); ?>">
                            <a href="product_detail.php?id=<?php echo $post_id; ?>" class="br-card">

                                <div class="br-card-img-wrap">
                                    <?php if ($post['image']): ?>
                                        <img src="assets/images/<?php echo htmlspecialchars($post['image']); ?>" alt="<?php echo htmlspecialchars($post['product_name']); ?>" class="br-card-img">
                                    <?php else: ?>
                                        <div class="br-card-img-placeholder"><i class="fas fa-leaf"></i></div>
                                    <?php endif; ?>
                                    <div class="br-view-overlay">
                                        <span class="br-view-btn"><i class="fas fa-eye"></i> View Details</span>
                                    </div>
                                    <?php if ($is_sold): ?>
                                        <div class="br-status-badge br-ended">
                                            <i class="fas fa-check-circle"></i> SOLD
                                        </div>
                                    <?php elseif ($is_ended): ?>
                                        <div class="br-status-badge br-ended">
                                            <i class="fas fa-flag-checkered"></i> Ended
                                        </div>
                                    <?php elseif ($is_live): ?>
                                        <div class="br-status-badge br-live">
                                            <span class="br-live-dot"></span> LIVE
                                        </div>
                                    <?php else: ?>
                                        <div class="br-status-badge br-upcoming">
                                            <i class="fas fa-hourglass-start"></i> Upcoming
                                        </div>
                                    <?php endif; ?>
                                    <div class="br-bids-pill">
                                        <i class="fas fa-gavel"></i> <?php echo $total_bids; ?> bid<?php echo $total_bids != 1 ? 's' : ''; ?>
                                    </div>
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user' && !$is_ended): ?>
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
                                            <span class="br-price-val">৳<?php echo number_format($post['price'], 0); ?></span>
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
                                    <div class="br-farmer-info" onclick="event.preventDefault();event.stopPropagation();window.location.href='farmer/profile.php?id=<?php echo $post['farmer_id']; ?>'" title="View <?php echo htmlspecialchars($post['username']); ?>'s profile">
                                        <span class="br-farmer-avatar"><?php echo strtoupper(substr($post['username'], 0, 2)); ?></span>
                                        <span class="br-farmer-name"><?php echo htmlspecialchars($post['username']); ?></span>
                                        <i class="fas fa-external-link-alt br-farmer-link-icon"></i>
                                    </div>
                                    <?php if ($is_sold): ?>
                                        <div class="br-ended-pill">
                                            <i class="fas fa-trophy"></i>
                                            <span>Sold</span>
                                        </div>
                                    <?php elseif ($is_ended): ?>
                                        <div class="br-ended-pill">
                                            <i class="fas fa-gavel"></i>
                                            <span>Auction Ended</span>
                                        </div>
                                    <?php elseif ($is_live): ?>
                                        <div class="br-countdown" data-end="<?php echo $auction_end_time; ?>">
                                            <i class="fas fa-clock"></i>
                                            <span class="br-cd-label">Ends in</span>
                                            <span class="br-cd-text">–</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="br-starts-on" data-start="<?php echo $auction_start_time; ?>">
                                            <i class="fas fa-hourglass-start"></i>
                                            <span class="br-starts-label">Starts in</span>
                                            <span class="br-cd-text">–</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i>No recently listed products available.
                        </div>
                    </div>
                <?php endif; ?>
                <?php $recent_stmt->close(); ?>
            </div>
        </div>

        <?php if (!empty($recently_viewed_products)): ?>
            <div class="recently-listed-section mb-5">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-history me-2"></i>Recently Viewed</h2>
                    <p class="section-subtitle">Pick up where you left off</p>
                </div>

                <div id="recently-viewed-grid" class="row">
                    <?php foreach ($recently_viewed_products as $post):
                        $post_id = (int)$post['id'];
                        $current_time = time();
                        $auction_start_time = strtotime($post['auction_start_date']);
                        $auction_end_time = strtotime($post['auction_end_date']);
                        $is_sold = (in_array($post['status'], ['sold', 'delivered'], true) || (int)($post['has_winner'] ?? 0) === 1);
                        $is_ended = ($is_sold || $current_time >= $auction_end_time);
                        $is_live = (!$is_ended && $current_time >= $auction_start_time);
                        $total_bids = (int)($post['total_bids'] ?? 0);
                        $max_bid = $post['highest_bid'] ?? null;
                    ?>
                        <div class="col-lg-3 col-md-6 product-card recently-viewed-card fade-in-up" data-name="<?php echo strtolower(htmlspecialchars($post['product_name'])); ?>">
                            <a href="product_detail.php?id=<?php echo $post_id; ?>" class="br-card">
                                <div class="br-card-img-wrap">
                                    <?php if (!empty($post['image'])): ?>
                                        <img src="assets/images/<?php echo htmlspecialchars($post['image']); ?>" alt="<?php echo htmlspecialchars($post['product_name']); ?>" class="br-card-img">
                                    <?php else: ?>
                                        <div class="br-card-img-placeholder"><i class="fas fa-leaf"></i></div>
                                    <?php endif; ?>
                                    <div class="br-view-overlay"><span class="br-view-btn"><i class="fas fa-eye"></i> View Details</span></div>
                                    <?php if ($is_sold): ?>
                                        <div class="br-status-badge br-ended"><i class="fas fa-check-circle"></i> SOLD</div>
                                    <?php elseif ($is_ended): ?>
                                        <div class="br-status-badge br-ended"><i class="fas fa-flag-checkered"></i> Ended</div>
                                    <?php elseif ($is_live): ?>
                                        <div class="br-status-badge br-live"><span class="br-live-dot"></span> LIVE</div>
                                    <?php else: ?>
                                        <div class="br-status-badge br-upcoming"><i class="fas fa-hourglass-start"></i> Upcoming</div>
                                    <?php endif; ?>
                                    <div class="br-bids-pill"><i class="fas fa-eye"></i> Viewed</div>
                                </div>

                                <div class="br-card-body">
                                    <h3 class="br-card-title"><?php echo htmlspecialchars($post['product_name']); ?></h3>
                                    <div class="br-card-price-row">
                                        <div>
                                            <span class="br-price-label">Starting at</span>
                                            <span class="br-price-val">৳<?php echo number_format($post['price'], 0); ?></span>
                                        </div>
                                        <?php if ($max_bid && $max_bid > $post['price']): ?>
                                            <div class="br-current-bid">
                                                <span class="br-cb-label">Current</span>
                                                <span class="br-cb-val">৳<?php echo number_format($max_bid, 2); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="br-card-qty"><i class="fas fa-gavel"></i> <?php echo $total_bids; ?> bid<?php echo $total_bids !== 1 ? 's' : ''; ?></div>
                                </div>

                                <div class="br-card-footer">
                                    <div class="br-farmer-info" onclick="event.preventDefault();event.stopPropagation();window.location.href='farmer/profile.php?id=<?php echo $post['farmer_id']; ?>'">
                                        <span class="br-farmer-avatar"><?php echo strtoupper(substr($post['username'], 0, 2)); ?></span>
                                        <span class="br-farmer-name"><?php echo htmlspecialchars($post['username']); ?></span>
                                        <i class="fas fa-external-link-alt br-farmer-link-icon"></i>
                                    </div>
                                    <?php if ($is_sold): ?>
                                        <div class="br-ended-pill"><i class="fas fa-trophy"></i><span>Sold</span></div>
                                    <?php elseif ($is_ended): ?>
                                        <div class="br-ended-pill"><i class="fas fa-gavel"></i><span>Auction Ended</span></div>
                                    <?php elseif ($is_live): ?>
                                        <div class="br-countdown" data-end="<?php echo $auction_end_time; ?>"><i class="fas fa-clock"></i><span class="br-cd-label">Ends in</span><span class="br-cd-text">–</span></div>
                                    <?php else: ?>
                                        <div class="br-starts-on" data-start="<?php echo $auction_start_time; ?>"><i class="fas fa-hourglass-start"></i><span class="br-starts-label">Starts in</span><span class="br-cd-text">–</span></div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Search functionality - redirect to search page
        function doSearch() {
            const query = document.getElementById('searchInput').value.trim();
            if (query) {
                window.location.href = 'search.php?q=' + encodeURIComponent(query);
            }
        }

        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') doSearch();
        });

        document.getElementById('heroSearchBtn').addEventListener('click', doSearch);

        // Live auctions filter
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                let filter = this.getAttribute('data-filter');
                let cards = document.querySelectorAll('.live-auction-card');

                cards.forEach(card => {
                    if (filter === 'all' || card.getAttribute('data-category') === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // Initialize countdown timers
        document.addEventListener('DOMContentLoaded', function() {
            // br-card style countdowns (data-end)
            function pad(n) {
                return String(n).padStart(2, '0');
            }
            document.querySelectorAll('.br-countdown[data-end]').forEach(function(el) {
                const end = parseInt(el.getAttribute('data-end'));
                const textEl = el.querySelector('.br-cd-text');

                function tick() {
                    const diff = end - Math.floor(Date.now() / 1000);
                    if (diff <= 0) {
                        // swap badge to Ended
                        const card = el.closest('.br-card');
                        if (card) {
                            const badge = card.querySelector('.br-status-badge');
                            if (badge) {
                                badge.className = 'br-status-badge br-ended';
                                badge.innerHTML = '<i class="fas fa-flag-checkered"></i> Ended';
                            }
                        }
                        // swap footer pill to Ended
                        el.outerHTML = '<div class="br-ended-pill"><i class="fas fa-gavel"></i><span>Auction Ended</span></div>';
                        return;
                    }
                    const d = Math.floor(diff / 86400);
                    const h = Math.floor((diff % 86400) / 3600);
                    const m = Math.floor((diff % 3600) / 60);
                    const s = diff % 60;
                    textEl.textContent = d > 0 ? `${d}d ${pad(h)}h ${pad(m)}m` :
                        h > 0 ? `${pad(h)}h ${pad(m)}m ${pad(s)}s` : `${pad(m)}m ${pad(s)}s`;
                }
                tick();
                setInterval(tick, 1000);
            });

            // br-card start countdowns (data-start)
            document.querySelectorAll('.br-starts-on[data-start]').forEach(function(el) {
                const start = parseInt(el.getAttribute('data-start'));
                const textEl = el.querySelector('.br-cd-text');

                function tick() {
                    const diff = start - Math.floor(Date.now() / 1000);
                    if (diff <= 0) {
                        textEl.textContent = 'Starting...';
                        return;
                    }
                    const d = Math.floor(diff / 86400);
                    const h = Math.floor((diff % 86400) / 3600);
                    const m = Math.floor((diff % 3600) / 60);
                    const s = diff % 60;
                    textEl.textContent = d > 0 ? `${d}d ${pad(h)}h ${pad(m)}m` :
                        h > 0 ? `${pad(h)}h ${pad(m)}m ${pad(s)}s` : `${pad(m)}m ${pad(s)}s`;
                }
                tick();
                setInterval(tick, 1000);
            });

            countdownElements.forEach(function(element) {
                const endTime = parseInt(element.getAttribute('data-end-time'));
                const timeDisplay = element.querySelector('.countdown-time');

                function updateCountdown() {
                    const currentTime = Math.floor(Date.now() / 1000);
                    const remainingTime = endTime - currentTime;

                    if (remainingTime <= 0) {
                        timeDisplay.textContent = 'Auction Closed!';
                        timeDisplay.style.color = '#e63946';
                        element.classList.add('closed');
                    } else {
                        const days = Math.floor(remainingTime / 86400);
                        const hours = Math.floor((remainingTime % 86400) / 3600);
                        const minutes = Math.floor((remainingTime % 3600) / 60);
                        const seconds = remainingTime % 60;

                        let timeString = '';
                        if (days > 0) {
                            timeString = `${days}d ${hours}h ${minutes}m`;
                        } else if (hours > 0) {
                            timeString = `${hours}h ${minutes}m ${seconds}s`;
                        } else {
                            timeString = `${minutes}m ${seconds}s`;
                        }

                        timeDisplay.textContent = timeString;
                        timeDisplay.style.color = '#046307';
                        timeDisplay.style.fontWeight = 'bold';
                    }
                }

                updateCountdown();
                setInterval(updateCountdown, 1000);
            });

            // Animate product cards on scroll
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('fade-in-up');
            });
        });

        // Wishlist toggle
        function toggleWishlist(btn) {
            var postId = btn.dataset.postId;
            fetch('wishlist_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=toggle&post_id=' + postId
                })
                .then(function(r) {
                    return r.json();
                })
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