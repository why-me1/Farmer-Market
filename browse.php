<?php
session_start();
include 'includes/db.php'; // Database connection
date_default_timezone_set('Asia/Dhaka');
require_once 'includes/config.php';
require_once 'includes/functions.php';
check_login();

// Get category from URL
$category = isset($_GET['category']) ? sanitize($_GET['category']) : 'Vegetables';

// Verify category exists
$valid_categories = ['Vegetables', 'Fruits', 'Grains', 'Dairy', 'Eggs', 'Honey', 'Herbs', 'Root Vegetables'];
if (!in_array($category, $valid_categories)) {
    $category = 'Vegetables';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $category; ?> - Farmers' Marketplace</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="main-container">
        <!-- Category Header -->
        <div class="category-header-section mb-5">
            <div class="category-header-content">
                <h1 class="category-title"><?php echo $category; ?></h1>
                <p class="category-description">Browse all fresh <?php echo strtolower($category); ?> from trusted farmers</p>
            </div>
        </div>

        <!-- Category Navigation Tabs -->
        <div class="category-nav-tabs mb-5">
            <div class="category-tabs-scroll">
                <?php foreach ($valid_categories as $cat): ?>
                    <a href="browse.php?category=<?php echo urlencode($cat); ?>" class="category-tab <?php echo $cat === $category ? 'active' : ''; ?>">
                        <?php echo $cat; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="category-products-section">
            <div class="row">
                <?php
                // Get all active products in this category, sorted by price
                $products_stmt = $conn->prepare("SELECT posts.*, users.username,
                                               (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as total_bids,
                                               (SELECT MAX(comment_text) FROM comments WHERE post_id = posts.id) as max_bid
                                               FROM posts 
                                               JOIN users ON posts.farmer_id = users.id 
                                               WHERE posts.is_approved = 1 AND posts.status = 'active'
                                               AND posts.category = ?
                                               ORDER BY posts.price ASC");
                $products_stmt->bind_param("s", $category);
                $products_stmt->execute();
                $products_result = $products_stmt->get_result();

                if ($products_result->num_rows > 0):
                    while ($post = $products_result->fetch_assoc()):
                        $post_id = $post['id'];
                        $current_time = time();
                        $auction_start_time = strtotime($post['auction_start_date']);
                        $auction_end_time = strtotime($post['auction_end_date']);

                        $is_live = false;
                        if ($current_time >= $auction_start_time && $current_time < $auction_end_time) {
                            $is_live = true;
                        }

                        $total_bids = $post['total_bids'];
                ?>
                        <div class="col-lg-3 col-md-6 product-card fade-in-up" data-name="<?php echo strtolower(htmlspecialchars($post['product_name'])); ?>">
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
                            <i class="fas fa-info-circle me-2"></i>No <?php echo strtolower($category); ?> available at the moment. Check back later!
                        </div>
                    </div>
                <?php endif; ?>
                <?php $products_stmt->close(); ?>
            </div>
        </div>
    </div>

    <script>
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

            // Animate product cards
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