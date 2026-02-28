<?php
session_start();
include 'includes/db.php'; // Database connection
date_default_timezone_set('Asia/Dhaka');
require_once 'includes/config.php';
require_once 'includes/functions.php';
check_login();
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
    <!-- browser cache problem solution --- add version number for production and add echo time for development -->
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
                        <a href="how_to_sell.php" class="btn btn-hero-secondary btn-lg"><i class="fas fa-seedling me-2"></i>Start Selling</a>
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




        <!-- 2. LIVE AUCTIONS - ENDING SOON SECTION -->
        <div id="live-auctions" class="live-auctions-section mb-5">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-fire me-2"></i>Live Auctions - Ending Soon</h2>
                <p class="section-subtitle">Products ending in the next 24 hours</p>
            </div>

            <!-- Filter Bar for Live Auctions -->
            <div class="filter-bar-live mb-4">
                <button class="filter-btn active" data-filter="all">All</button>
                <?php
                $live_categories = $conn->prepare("SELECT DISTINCT category FROM posts 
                                                   WHERE is_approved = 1 AND status = 'active' 
                                                   AND auction_start_date <= NOW() 
                                                   AND auction_end_date > NOW()
                                                   ORDER BY category ASC");
                $live_categories->execute();
                $live_cat_result = $live_categories->get_result();
                while ($cat_row = $live_cat_result->fetch_assoc()):
                    $cat_name = htmlspecialchars($cat_row['category']);
                ?>
                    <button class="filter-btn" data-filter="<?php echo $cat_name; ?>"><?php echo $cat_name; ?></button>
                <?php endwhile; ?>
                <?php $live_categories->close(); ?>
            </div>

            <!-- Live Auctions Grid -->
            <div id="live-auctions-grid" class="row">
                <?php
                // Get live auctions ending within next 24 hours, sorted by time remaining
                $live_stmt = $conn->prepare("SELECT posts.*, users.username, 
                                             (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as total_bids,
                                             (SELECT MAX(comment_text) FROM comments WHERE post_id = posts.id) as max_bid
                                             FROM posts 
                                             JOIN users ON posts.farmer_id = users.id 
                                             WHERE posts.is_approved = 1 AND posts.status = 'active'
                                             AND posts.auction_start_date <= NOW() 
                                             AND posts.auction_end_date > NOW()
                                             AND UNIX_TIMESTAMP(posts.auction_end_date) - UNIX_TIMESTAMP(NOW()) <= 86400
                                             ORDER BY posts.auction_end_date ASC
                                             LIMIT 8");
                $live_stmt->execute();
                $live_result = $live_stmt->get_result();

                if ($live_result->num_rows > 0):
                    while ($post = $live_result->fetch_assoc()):
                        $post_id = $post['id'];
                        $current_time = time();
                        $auction_end_time = strtotime($post['auction_end_date']);
                        $time_remaining = $auction_end_time - $current_time;
                        $total_bids = $post['total_bids'];
                        $max_bid = $post['max_bid'];
                ?>
                        <div class="col-lg-3 col-md-6 product-card live-auction-card fade-in-up" data-category="<?php echo htmlspecialchars($post['category']); ?>" data-name="<?php echo strtolower(htmlspecialchars($post['product_name'])); ?>">
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
                                    <div class="br-status-badge br-live">
                                        <span class="br-live-dot"></span> LIVE
                                    </div>
                                    <div class="br-bids-pill">
                                        <i class="fas fa-gavel"></i> <?php echo $total_bids; ?> bid<?php echo $total_bids != 1 ? 's' : ''; ?>
                                    </div>
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
                                    <div class="br-farmer-info" onclick="event.preventDefault();event.stopPropagation();window.location.href='farmer/profile.php?id=<?php echo $post['farmer_id']; ?>'" title="View <?php echo htmlspecialchars($post['username']); ?>'s profile">
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
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i>No live auctions ending soon. Check back later!
                        </div>
                    </div>
                <?php endif; ?>
                <?php $live_stmt->close(); ?>
            </div>
        </div>

        <!-- 3. CATEGORY SECTIONS -->
        <div class="category-sections-wrapper mb-5">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-th-large me-2"></i>Browse by Category</h2>
            </div>

            <div class="row category-cards-grid">
                <?php
                // Define all 8 categories with icons
                $all_categories = [
                    'Vegetables' => 'fa-leaf',
                    'Fruits' => 'fa-apple-alt',
                    'Grains' => 'fa-wheat',
                    'Dairy' => 'fa-cheese',
                    'Eggs' => 'fa-egg',
                    'Honey' => 'fa-jar',
                    'Herbs' => 'fa-clover',
                    'Root Vegetables' => 'fa-carrot'
                ];

                foreach ($all_categories as $category_name => $icon):
                    // Get count of active products in this category
                    $count_stmt = $conn->prepare("SELECT COUNT(*) as product_count FROM posts 
                                                WHERE is_approved = 1 AND status = 'active' 
                                                AND category = ?");
                    $count_stmt->bind_param("s", $category_name);
                    $count_stmt->execute();
                    $count_result = $count_stmt->get_result();
                    $count_row = $count_result->fetch_assoc();
                    $count = $count_row['product_count'];
                    $count_stmt->close();
                ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <a href="browse.php?category=<?php echo urlencode($category_name); ?>" class="category-card-link">
                            <div class="category-card">
                                <div class="category-ghost-icon">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div class="category-icon">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <h4 class="category-name"><?php echo $category_name; ?></h4>
                                <span class="category-count-badge"><?php echo $count; ?> auction<?php echo $count !== 1 ? 's' : ''; ?></span>
                                <div class="category-browse-btn">
                                    Browse <i class="fas fa-arrow-right"></i>
                                </div>
                            </div>
                        </a>
                    </div>
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
                    <a href="how_to_sell.php" class="btn seller-cta-btn-outline">Learn More</a>
                    <a href="register.php" class="btn seller-cta-btn-primary"><i class="fas fa-plus me-2"></i>Start Selling</a>
                </div>
            </div>
        </div>

        <!-- 4. RECENTLY LISTED SECTION -->
        <div class="recently-listed-section mb-5">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-star me-2"></i>Recently Listed</h2>
                <p class="section-subtitle">Newest products added to the marketplace</p>
            </div>

            <div id="recently-listed-grid" class="row">
                <?php
                $recent_stmt = $conn->prepare("SELECT posts.*, users.username,
                                              (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as total_bids,
                                              (SELECT MAX(comment_text) FROM comments WHERE post_id = posts.id) as max_bid
                                              FROM posts 
                                              JOIN users ON posts.farmer_id = users.id 
                                              WHERE posts.is_approved = 1 AND posts.status = 'active'
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

                        $is_ended = ($current_time >= $auction_end_time);
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
                                    <?php if ($is_ended): ?>
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
                                    <div class="br-farmer-info" onclick="event.preventDefault();event.stopPropagation();window.location.href='farmer/profile.php?id=<?php echo $post['farmer_id']; ?>'" title="View <?php echo htmlspecialchars($post['username']); ?>'s profile">
                                        <span class="br-farmer-avatar"><?php echo strtoupper(substr($post['username'], 0, 2)); ?></span>
                                        <span class="br-farmer-name"><?php echo htmlspecialchars($post['username']); ?></span>
                                        <i class="fas fa-external-link-alt br-farmer-link-icon"></i>
                                    </div>
                                    <?php if ($is_ended): ?>
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
    </script>

    <?php include 'includes/footer.php'; ?>
</body>

</html>