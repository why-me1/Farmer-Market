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
    <title>Farmer Market - Products in the Market</title>
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
        <!-- Hero Section -->
        <div class="text-center mb-5">
            <h1 class="text-gradient mb-3">Farmer Market</h1>
            <p class="lead text-muted">Discover fresh, locally-sourced products from trusted farmers</p>
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


        <!-- Search and Filter Section -->
        <div class="search-container">
            <div class="row align-items-end">
                <div class="col-md-6 mb-3">
                    <label for="searchInput" class="form-label">
                        <i class="fas fa-search me-2"></i>Search Products
                    </label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by product name...">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="categoryFilter" class="form-label">
                        <i class="fas fa-filter me-2"></i>Filter by Category
                    </label>
                    <select id="categoryFilter" class="form-select">
                        <option value="all">All Categories</option>
                        <?php
                        $category_stmt = $conn->prepare("SELECT DISTINCT category FROM posts WHERE is_approved = 1 ORDER BY category ASC");
                        $category_stmt->execute();
                        $category_result = $category_stmt->get_result();
                        while ($category_row = $category_result->fetch_assoc()):
                            $category_name = htmlspecialchars($category_row['category']);
                        ?>
                            <option value="<?php echo $category_name; ?>"><?php echo $category_name; ?></option>
                        <?php endwhile; ?>
                        <?php $category_stmt->close(); ?>
                    </select>
                </div>
            </div>
        </div>

        <div id="product-list">
            <?php
            $category_stmt = $conn->prepare("SELECT DISTINCT category FROM posts WHERE is_approved = 1 ORDER BY category ASC");
            $category_stmt->execute();
            $category_result = $category_stmt->get_result();

            while ($category_row = $category_result->fetch_assoc()):
                $category_name = htmlspecialchars($category_row['category']);
            ?>
                <div class="category-group" data-category="<?php echo $category_name; ?>">
                    <h3 class="category-header"><?php echo $category_name; ?></h3>
                    <div class="row">
                        <?php
                        $stmt = $conn->prepare("SELECT posts.*, users.username FROM posts 
                                                JOIN users ON posts.farmer_id = users.id 
                                                WHERE posts.is_approved = 1 AND posts.category = ? 
                                                ORDER BY posts.created_at DESC");
                        $stmt->bind_param("s", $category_name);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        while ($post = $result->fetch_assoc()):
                            $post_id = $post['id'];
                            $post_creation_time = strtotime($post['created_at']);
                            $current_time = time();

                            $expiry_stmt = $conn->prepare("SELECT expiry_date FROM posts WHERE id = ?");
                            $expiry_stmt->bind_param("i", $post_id);
                            $expiry_stmt->execute();
                            $expiry_stmt->bind_result($expired_time);
                            $expiry_stmt->fetch();
                            $expiry_stmt->close();

                            // If expiry_date is NULL, set it to current time + 2 minutes

                            //echo $current_time;

                            // Get bid count and highest bid
                            $comment_count_stmt = $conn->prepare("SELECT COUNT(*) as total_bids, MAX(comment_text) as max_bid FROM comments WHERE post_id = ?");
                            $comment_count_stmt->bind_param("i", $post_id);
                            $comment_count_stmt->execute();
                            $comment_result = $comment_count_stmt->get_result();
                            $comment_data = $comment_result->fetch_assoc();
                            $total_bids = $comment_data['total_bids'];
                            $max_bid = $comment_data['max_bid'];
                            $comment_count_stmt->close();

                            $is_sold = false;
                            $is_unsold = false;
                            $bidding_end_time = null;

                            // $bidding_end_time = null;
                            if ($total_bids >= 5) {
                                // If 5 bids exist, start 2-minute countdown
                                // $bidding_end_time = $post_creation_time + 120;
                                if ($expired_time == NULL) {
                                    $comment_time_stmt = $conn->prepare("SELECT UNIX_TIMESTAMP(created_at) FROM comments WHERE post_id = ? ORDER BY created_at DESC LIMIT 1");
                                    $comment_time_stmt->bind_param("i", $post_id);
                                    $comment_time_stmt->execute();
                                    $comment_time_stmt->bind_result($last_comment_time);
                                    $comment_time_stmt->fetch();
                                    $comment_time_stmt->close();

                                    //echo $last_comment_time;

                                    $bidding_end_time = $last_comment_time + 120;
                                    // Update expiry_date in the database
                                    $update_stmt = $conn->prepare("UPDATE posts SET expiry_date = ? WHERE id = ?");
                                    $update_stmt->bind_param("ii", $bidding_end_time, $post_id);
                                    $update_stmt->execute();
                                    $update_stmt->close();
                                } else {
                                    $bidding_end_time = $expired_time;
                                }
                                //$bidding_end_time = $post_creation_time + 120;
                                if ($bidding_end_time <= $current_time) {
                                    if ($max_bid >= $post['price']) {
                                        $is_sold = true;
                                        $approve_stmt = $conn->prepare("UPDATE comments SET is_approved = 1 WHERE post_id = ? AND comment_text = ?");
                                        $approve_stmt->bind_param("id", $post_id, $max_bid);
                                        $approve_stmt->execute();
                                        $approve_stmt->close();
                                    } else {
                                        $is_unsold = true;
                                    }
                                } else {
                                    $is_sold = false;
                                    $is_unsold = false;
                                }
                            }
                        ?>

                            <div class="col-lg-4 col-md-6 product-card fade-in-up" data-name="<?php echo strtolower(htmlspecialchars($post['product_name'])); ?>">
                                <a href="product_detail.php?id=<?php echo $post_id; ?>" class="product-card-link">
                                    <div class="card h-100 bidding-card">
                                        <?php if ($post['image']): ?>
                                            <div class="product-image">
                                                <img src="assets/images/<?php echo htmlspecialchars($post['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($post['product_name']); ?>">
                                                <?php if ($is_sold): ?>
                                                    <img src="assets/images/sold.png" class="sold-stamp" alt="Sold">
                                                <?php elseif ($is_unsold): ?>
                                                    <img src="assets/images/unsold.png" class="unsold-stamp" alt="Unsold">
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($post['product_name']); ?></h5>

                                            <div class="countdown-section mb-3">
                                                <?php if ($bidding_end_time && $bidding_end_time > $current_time): ?>
                                                    <div class="countdown-timer" id="countdown-<?php echo $post_id; ?>" data-end-time="<?php echo $bidding_end_time; ?>">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <span class="countdown-text">Time Remaining: </span>
                                                        <span class="countdown-time"></span>
                                                    </div>
                                                <?php elseif ($is_sold): ?>
                                                    <div class="status-badge sold-badge">
                                                        <i class="fas fa-check-circle me-1"></i>Sold
                                                    </div>
                                                <?php elseif ($is_unsold): ?>
                                                    <div class="status-badge unsold-badge">
                                                        <i class="fas fa-times-circle me-1"></i>Unsold
                                                    </div>
                                                <?php else: ?>
                                                    <div class="status-badge active-badge">
                                                        <i class="fas fa-circle me-1"></i>Active Bidding
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="product-meta mb-3">
                                                <div class="d-flex justify-content-between align-items-center text-muted small">
                                                    <span class="d-flex align-items-center">
                                                        <i class="fas fa-user mr-1"></i>
                                                        <a href="farmer/profile.php?id=<?php echo (int)$post['farmer_id']; ?>"
                                                            class="farmer-name-link"
                                                            onclick="event.stopPropagation();">
                                                            <?php echo htmlspecialchars($post['username']); ?>
                                                        </a>
                                                    </span>
                                                    <span class="d-flex align-items-center">
                                                        <i class="fas fa-calendar mr-1"></i>
                                                        <?php echo date("d M Y", strtotime($post['created_at'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <script>
        document.getElementById('searchInput').addEventListener('input', function() {
            let filter = this.value.toLowerCase();
            let productCards = document.querySelectorAll('.product-card');

            productCards.forEach(card => {
                let productName = card.getAttribute('data-name');
                if (productName.includes(filter)) {
                    card.style.display = "block";
                } else {
                    card.style.display = "none";
                }
            });
        });
    </script>
    <script>
        document.getElementById('categoryFilter').addEventListener('change', function() {
            let selectedCategory = this.value.toLowerCase();
            let productCategories = document.querySelectorAll('.category-group');

            productCategories.forEach(category => {
                if (selectedCategory === "all" || category.getAttribute('data-category').toLowerCase() === selectedCategory) {
                    category.style.display = "block";
                } else {
                    category.style.display = "none";
                }
            });
        });
    </script>
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
                        timeDisplay.textContent = 'Bidding Closed!';
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
        });
    </script>
    <script>
        // Add smooth scrolling and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to product cards
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('fade-in-up');
            });
        });
    </script>


</body>

</html>