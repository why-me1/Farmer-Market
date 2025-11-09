<?php
session_start();
include 'includes/db.php';
date_default_timezone_set('Asia/Dhaka');
require_once 'includes/config.php';
require_once 'includes/functions.php';
check_login();

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$post_id = (int)$_GET['id'];

// Fetch product details
$stmt = $conn->prepare("SELECT posts.*, users.username FROM posts 
                        JOIN users ON posts.farmer_id = users.id 
                        WHERE posts.id = ? AND posts.is_approved = 1");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: index.php");
    exit();
}

$post = $result->fetch_assoc();
$stmt->close();

$current_time = time();
$expiry_stmt = $conn->prepare("SELECT expiry_date FROM posts WHERE id = ?");
$expiry_stmt->bind_param("i", $post_id);
$expiry_stmt->execute();
$expiry_stmt->bind_result($expired_time);
$expiry_stmt->fetch();
$expiry_stmt->close();

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

if ($total_bids >= 5) {
    if ($expired_time == NULL) {
        $comment_time_stmt = $conn->prepare("SELECT UNIX_TIMESTAMP(created_at) FROM comments WHERE post_id = ? ORDER BY created_at DESC LIMIT 1");
        $comment_time_stmt->bind_param("i", $post_id);
        $comment_time_stmt->execute();
        $comment_time_stmt->bind_result($last_comment_time);
        $comment_time_stmt->fetch();
        $comment_time_stmt->close();

        $bidding_end_time = $last_comment_time + 120;
        $update_stmt = $conn->prepare("UPDATE posts SET expiry_date = ? WHERE id = ?");
        $update_stmt->bind_param("ii", $bidding_end_time, $post_id);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        $bidding_end_time = $expired_time;
    }

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
    }
}

// Fetch recent bids (limit to top 10 for the card)
$bids_stmt = $conn->prepare("SELECT comments.*, users.username FROM comments 
                             JOIN users ON comments.user_id = users.id 
                             WHERE comments.post_id = ? ORDER BY comment_text DESC LIMIT 10");
$bids_stmt->bind_param("i", $post_id);
$bids_stmt->execute();
$bids_result = $bids_stmt->get_result();

// Fetch all bids for the full list
$all_bids_stmt = $conn->prepare("SELECT comments.*, users.username FROM comments 
                                  JOIN users ON comments.user_id = users.id 
                                  WHERE comments.post_id = ? ORDER BY comment_text DESC");
$all_bids_stmt->bind_param("i", $post_id);
$all_bids_stmt->execute();
$all_bids_result = $all_bids_stmt->get_result();

// Fetch reviews
$reviews_stmt = $conn->prepare("SELECT reviews.*, users.username FROM reviews 
                                JOIN users ON reviews.user_id = users.id 
                                WHERE reviews.product_id = ? ORDER BY reviews.created_at DESC");
$reviews_stmt->bind_param("i", $post_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();

// Calculate average rating
$avg_rating_stmt = $conn->prepare("SELECT COUNT(*) AS total_reviews, AVG(rating) AS avg_rating FROM reviews WHERE product_id = ?");
$avg_rating_stmt->bind_param("i", $post_id);
$avg_rating_stmt->execute();
$avg_rating_result = $avg_rating_stmt->get_result();
$avg_data = $avg_rating_result->fetch_assoc();
$total_reviews = $avg_data['total_reviews'];
$avg_rating = $avg_data['avg_rating'] ? round($avg_data['avg_rating'], 1) : 0;
$avg_rating_stmt->close();

// Calculate minimum bid
$min_bid = $post['price'];
if ($max_bid && $max_bid > $post['price']) {
    $min_bid = $max_bid;
}
$min_bid += 0.01;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['product_name']); ?> - Product Details</title>
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

    <!-- <div style="background: yellow; padding: 10px; margin: 10px;">
        <strong>Debug Base URL:</strong> <?php echo $base_url; ?><br>
        <strong>CSS Path:</strong> <?php echo $base_url; ?>assets/css/styles.css
    </div> -->

    <div class="main-container">
        <div class="product-detail-page">
            <!-- Main Content: Image Left, Bidding Right -->
            <div class="row mb-5">
                <!-- Left Column: Product Image (Fixed Size) -->
                <div class="col-lg-5 col-md-12 mb-4 mb-lg-0">
                    <div class="product-image-wrapper">
                        <?php if ($post['image']): ?>
                            <div class="product-image-fixed">
                                <img src="assets/images/<?php echo htmlspecialchars($post['image']); ?>"
                                    class="product-main-image"
                                    alt="<?php echo htmlspecialchars($post['product_name']); ?>">
                                <?php if ($is_sold): ?>
                                    <img src="assets/images/sold.png" class="sold-stamp" alt="Sold">
                                <?php elseif ($is_unsold): ?>
                                    <img src="assets/images/unsold.png" class="unsold-stamp" alt="Unsold">
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Product Basic Info Below Image -->
                        <div class="product-basic-info">
                            <h1 class="product-title-small"><?php echo htmlspecialchars($post['product_name']); ?></h1>
                            <p class="product-description-small"><?php echo htmlspecialchars($post['description']); ?></p>
                            <div class="product-meta-list">
                                <div class="meta-item">
                                    <i class="fas fa-tag"></i>
                                    <span>Category: <strong><?php echo htmlspecialchars($post['category']); ?></strong></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-dollar-sign"></i>
                                    <span>Starting Price: <strong class="text-primary"><?php echo number_format($post['price'], 2); ?>৳</strong></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-user"></i>
                                    <span>Farmer: <a href="farmer/profile.php?id=<?php echo (int)$post['farmer_id']; ?>" class="farmer-link"><?php echo htmlspecialchars($post['username']); ?> <i class="fas fa-external-link-alt"></i></a></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>Posted: <?php echo date("d M Y, h:i A", strtotime($post['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Bidding Section (Sticky Sidebar) -->
                <div class="col-lg-7 col-md-12">
                    <div class="bidding-sidebar-wrapper">
                        <!-- Place Bids Card -->
                        <?php if (!$is_sold && !$is_unsold): ?>
                            <div class="bidding-card-right place-bid-card">
                                <h3 class="card-title-right">Place Bids</h3>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <form action="comment.php" method="POST">
                                        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                        <div class="bid-input-group">
                                            <label class="bid-label">Your Bid Amount (৳)</label>
                                            <input type="number" name="comment_text"
                                                class="bid-input"
                                                placeholder="Enter bid amount"
                                                required step="0.01" min="<?php echo $min_bid; ?>"
                                                value="<?php echo $min_bid; ?>">
                                        </div>
                                        <div class="bid-info-small mb-3">
                                            <small>
                                                <i class="fas fa-info-circle"></i>
                                                Minimum bid: <strong><?php echo number_format($min_bid, 2); ?>৳</strong>
                                                <?php if ($max_bid): ?>
                                                    (Current highest: <?php echo number_format($max_bid, 2); ?>৳)
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <?php if ($bidding_end_time && $bidding_end_time > $current_time): ?>
                                            <div class="time-remaining-small mb-3" id="detail-countdown-small" data-end-time="<?php echo $bidding_end_time; ?>">
                                                <i class="fas fa-clock"></i>
                                                <span class="countdown-text-small"></span>
                                            </div>
                                        <?php elseif ($is_sold): ?>
                                            <div class="status-badge-large sold mb-3">
                                                <i class="fas fa-check-circle"></i> Sold
                                            </div>
                                        <?php elseif ($is_unsold): ?>
                                            <div class="status-badge-large unsold mb-3">
                                                <i class="fas fa-times-circle"></i> Unsold
                                            </div>
                                        <?php else: ?>
                                            <div class="status-badge-large active mb-3">
                                                <i class="fas fa-circle"></i> Active Bidding
                                            </div>
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-primary btn-block btn-bid">
                                            <i class="fas fa-gavel"></i> Place Bid
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="login-prompt-small">
                                        <p>Please <a href="login.php">login</a> to place a bid</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Status Card for Sold/Unsold -->
                            <div class="bidding-card-right">
                                <?php if ($is_sold): ?>
                                    <div class="status-message-large sold">
                                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                                        <h4>Product Sold!</h4>
                                        <p>Congratulations to the winning bidder!</p>
                                    </div>
                                <?php elseif ($is_unsold): ?>
                                    <div class="status-message-large unsold">
                                        <i class="fas fa-times-circle fa-3x mb-3"></i>
                                        <h4>Product Unsold</h4>
                                        <p>Minimum price not met</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Recent Top Bids Card -->
                        <div class="bidding-card-right bids-card-only">
                            <h3 class="card-title-right">Recent Top Bids</h3>
                            <div class="recent-bids-list">
                                <?php if ($bids_result->num_rows > 0): ?>
                                    <?php
                                    $bid_count = 0;
                                    while ($bid = $bids_result->fetch_assoc()):
                                        $bid_count++;
                                    ?>
                                        <div class="bid-item-single-line <?php echo $bid['is_approved'] ? 'winning-bid-line' : ''; ?>">
                                            <span class="bid-name-single">
                                                <?php echo htmlspecialchars($bid['username']); ?>
                                                <?php if ($bid['is_approved']): ?>
                                                    <i class="fas fa-crown winning-icon-small"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="bid-price-single"><?php echo number_format($bid['comment_text'], 2); ?>৳</span>
                                            <span class="bid-date-single"><?php echo date("h:i A", strtotime($bid['created_at'])); ?></span>
                                        </div>
                                    <?php
                                    endwhile;
                                    if ($bid_count >= 10):
                                    ?>
                                        <div class="text-center mt-2">
                                            <small class="text-muted">Showing top 10 bids</small>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="no-bids-message-small">
                                        <i class="fas fa-gavel"></i>
                                        <p>No bids yet. Be the first!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reviews Section (At the bottom) -->
            


            <section class="reviews-section-full">
                <div class="section-header">
                    <h2><i class="fas fa-star me-2"></i>Customer Reviews</h2>
                </div>

                <!-- Average Rating Summary at Top -->
                <div class="rating-summary-top mb-4">
                    <div class="rating-display-large">
                        <div class="avg-rating-box">
                            <div class="avg-rating-big"><?php echo $avg_rating > 0 ? $avg_rating : '0.0'; ?></div>
                            <div class="avg-rating-stars-top">
                                <?php
                                if ($avg_rating > 0) {
                                    $starsFilled = (int)round($avg_rating);
                                    echo str_repeat('★', $starsFilled) . str_repeat('☆', 5 - $starsFilled);
                                } else {
                                    echo '☆☆☆☆☆';
                                }
                                ?>
                            </div>
                            <div class="total-reviews-count">
                                <?php echo $total_reviews; ?> Review<?php echo $total_reviews != 1 ? 's' : ''; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- All Reviews List -->
                <div class="reviews-list-full mb-4">
                    <?php if ($reviews_result->num_rows > 0): ?>
                        <?php while ($review = $reviews_result->fetch_assoc()): ?>
                            <div class="review-box">
                                <div class="review-box-header">
                                    <div class="reviewer-info">
                                        <strong class="reviewer-name"><?php echo htmlspecialchars($review['username']); ?></strong>
                                        <div class="rating-stars-small mt-1">
                                            <?php
                                            $reviewStars = (int)$review['rating'];
                                            echo str_repeat('★', $reviewStars) . str_repeat('☆', 5 - $reviewStars);
                                            ?>
                                        </div>
                                    </div>
                                    <small class="review-date-text"><?php echo date("d M Y", strtotime($review['created_at'])); ?></small>
                                </div>
                                <div class="review-box-content">
                                    <p class="review-text"><?php echo htmlspecialchars($review['review_text']); ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-reviews-message">
                            <i class="fas fa-comments fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No reviews yet for this product.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Leave a Review Form (At the very bottom) -->
                <?php if ($is_sold && isset($_SESSION['user_id'])): ?>
                    <div class="review-form-card">
                        <h5><i class="fas fa-edit me-2"></i>Leave a Review</h5>
                        <form id="reviewForm" method="POST" action="submit_review.php">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="rating" class="form-label">Rating:</label>
                                    <select name="rating" id="rating" class="form-control" required>
                                        <option value="">Select Rating</option>
                                        <option value="5">★★★★★ Excellent</option>
                                        <option value="4">★★★★☆ Good</option>
                                        <option value="3">★★★☆☆ Average</option>
                                        <option value="2">★★☆☆☆ Poor</option>
                                        <option value="1">★☆☆☆☆ Very Poor</option>
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label for="review_text" class="form-label">Review:</label>
                                    <textarea name="review_text" id="review_text" class="form-control" rows="3" required placeholder="Write your review here..."></textarea>
                                </div>
                            </div>
                            <input type="hidden" name="product_id" value="<?php echo $post_id; ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Submit Review
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <script>
        // Countdown timer for detail page
        <?php if ($bidding_end_time && $bidding_end_time > $current_time): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const countdownElement = document.getElementById('detail-countdown-small');
                const timeDisplay = countdownElement.querySelector('.countdown-text-small');
                const endTime = <?php echo $bidding_end_time; ?>;

                function updateCountdown() {
                    const currentTime = Math.floor(Date.now() / 1000);
                    const remainingTime = endTime - currentTime;

                    if (remainingTime <= 0) {
                        timeDisplay.textContent = 'Bidding Closed!';
                        timeDisplay.style.color = '#e63946';
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
                    }
                }

                updateCountdown();
                setInterval(updateCountdown, 1000);
            });
        <?php endif; ?>
    </script>

    <?php include 'includes/footer.php'; ?>
</body>

</html>

<?php
$bids_stmt->close();
$all_bids_stmt->close();
$reviews_stmt->close();
?>