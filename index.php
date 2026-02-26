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
            <div class="hero-grid container">
                <div class="hero-left">
                    <h1 class="hero-title">Fresh produce, directly from local farmers</h1>
                    <p class="hero-sub">Handpicked, seasonal and sustainably sourced — find the best from your community marketplace.</p>

                    <div class="hero-search-card">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search organic tomatoes, apples, dairy...">
                            <div class="input-group-append">
                                <button class="btn btn-success" id="heroSearchBtn">Search</button>
                            </div>
                        </div>

                        <div class="hero-chips mt-3">
                            <a href="search.php?q=tomato" class="chip">Tomatoes</a>
                            <a href="search.php?q=apples" class="chip">Apples</a>
                            <a href="search.php?q=dairy" class="chip">Dairy</a>
                            <a href="search.php?q=vegetables" class="chip">Vegetables</a>
                        </div>
                    </div>

                    <div class="hero-cta mt-4">
                        <a href="browse.php" class="btn btn-outline-success btn-lg">Explore Marketplace</a>
                        <a href="how_to_sell.php" class="btn btn-link ms-3">Sell with us</a>
                    </div>
                </div>

                <div class="hero-right d-none d-lg-flex">
                    <div class="hero-illustration" aria-hidden="true"></div>
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
        <div class="live-auctions-section mb-5">
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
                            <a href="product_detail.php?id=<?php echo $post_id; ?>" class="product-card-link">
                                <div class="card h-100 bidding-card">
                                    <?php if ($post['image']): ?>
                                        <div class="product-image">
                                            <img src="assets/images/<?php echo htmlspecialchars($post['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($post['product_name']); ?>">
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($post['product_name']); ?></h5>

                                        <div class="product-price mb-3">
                                            <h6 class="price-label">Price: <span class="price-value">৳ <?php echo number_format($post['price'], 2); ?></span></h6>
                                            <p class="quantity-label">Quantity: <span class="quantity-value"><?php echo htmlspecialchars($post['quantity']); ?> <?php echo htmlspecialchars($post['unit']); ?></span></p>
                                        </div>

                                        <div class="countdown-section mb-3">
                                            <div class="status-badge live-badge">
                                                <i class="fas fa-circle-notch fa-spin me-1"></i>LIVE
                                            </div>
                                            <div class="countdown-timer" id="countdown-<?php echo $post_id; ?>" data-end-time="<?php echo $auction_end_time; ?>">
                                                <i class="fas fa-clock me-1"></i>
                                                <span class="countdown-text">Ending in: </span>
                                                <span class="countdown-time"></span>
                                            </div>
                                        </div>

                                        <div class="product-meta mb-3">
                                            <div class="d-flex justify-content-between align-items-center text-muted small">
                                                <span class="d-flex align-items-center">
                                                    <i class="fas fa-gavel mr-1"></i>
                                                    <?php echo $total_bids; ?> bid<?php echo $total_bids !== 1 ? 's' : ''; ?>
                                                </span>
                                                <span class="d-flex align-items-center">
                                                    <i class="fas fa-user mr-1"></i>
                                                    <a href="farmer/profile.php?id=<?php echo (int)$post['farmer_id']; ?>" class="farmer-name-link" onclick="event.stopPropagation();">
                                                        <?php echo htmlspecialchars($post['username']); ?>
                                                    </a>
                                                </span>
                                            </div>
                                        </div>
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
                    <div class="col-lg-3 col-md-3 col-sm-6 mb-3">
                        <a href="browse.php?category=<?php echo urlencode($category_name); ?>" class="category-card-link">
                            <div class="category-card">
                                <div class="category-icon">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <h4 class="category-name"><?php echo $category_name; ?></h4>
                                <p class="category-count"><?php echo $count; ?> auction<?php echo $count !== 1 ? 's' : ''; ?></p>
                                <div class="category-action">
                                    <small class="text-primary">Browse <i class="fas fa-arrow-right ms-1"></i></small>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
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

                        $is_live = false;
                        if ($current_time >= $auction_start_time && $current_time < $auction_end_time) {
                            $is_live = true;
                        }

                        $total_bids = $post['total_bids'];
                        $max_bid = $post['max_bid'];
                ?>
                        <div class="col-lg-3 col-md-6 product-card recently-listed-card fade-in-up" data-name="<?php echo strtolower(htmlspecialchars($post['product_name'])); ?>">
                            <a href="product_detail.php?id=<?php echo $post_id; ?>" class="product-card-link">
                                <div class="card h-100 bidding-card">
                                    <?php if ($post['image']): ?>
                                        <div class="product-image">
                                            <img src="assets/images/<?php echo htmlspecialchars($post['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($post['product_name']); ?>">
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($post['product_name']); ?></h5>

                                        <div class="product-price mb-3">
                                            <h6 class="price-label">Price: <span class="price-value">৳ <?php echo number_format($post['price'], 2); ?></span></h6>
                                            <p class="quantity-label">Quantity: <span class="quantity-value"><?php echo htmlspecialchars($post['quantity']); ?> <?php echo htmlspecialchars($post['unit']); ?></span></p>
                                        </div>

                                        <div class="countdown-section mb-3">
                                            <?php if ($is_live): ?>
                                                <div class="status-badge live-badge">
                                                    <i class="fas fa-circle-notch fa-spin me-1"></i>LIVE
                                                </div>
                                                <div class="countdown-timer" id="countdown-<?php echo $post_id; ?>" data-end-time="<?php echo $auction_end_time; ?>">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <span class="countdown-text">Ending in: </span>
                                                    <span class="countdown-time"></span>
                                                </div>
                                            <?php else: ?>
                                                <div class="status-badge pending-badge">
                                                    <i class="fas fa-hourglass-start me-1"></i>Upcoming
                                                </div>
                                                <p class="auction-date-text"><small>Starts: <?php echo date("d M, h:i A", $auction_start_time); ?></small></p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="product-meta mb-3">
                                            <div class="d-flex justify-content-between align-items-center text-muted small">
                                                <span class="d-flex align-items-center">
                                                    <i class="fas fa-calendar mr-1"></i>
                                                    <?php echo date("d M Y", strtotime($post['created_at'])); ?>
                                                </span>
                                                <span class="d-flex align-items-center">
                                                    <i class="fas fa-user mr-1"></i>
                                                    <a href="farmer/profile.php?id=<?php echo (int)$post['farmer_id']; ?>" class="farmer-name-link" onclick="event.stopPropagation();">
                                                        <?php echo htmlspecialchars($post['username']); ?>
                                                    </a>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
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
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query) {
                    window.location.href = 'search.php?q=' + encodeURIComponent(query);
                }
            }
        });

        // Also allow clicking search icon if it exists
        const searchIcon = document.querySelector('.search-bar-hero .input-group-text');
        if (searchIcon) {
            searchIcon.style.cursor = 'pointer';
            searchIcon.addEventListener('click', function() {
                const input = document.getElementById('searchInput');
                const query = input.value.trim();
                if (query) {
                    window.location.href = 'search.php?q=' + encodeURIComponent(query);
                }
            });
        }

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
            const countdownElements = document.querySelectorAll('.countdown-timer');

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

</body>

</html>