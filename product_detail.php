<?php
session_start();
include 'includes/db.php';
date_default_timezone_set('Asia/Dhaka');
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/ratings.php';
require_once 'includes/notification_functions.php';

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

// Fetch farmer automatic rating (default 5.0)
$farmer_auto_rating = get_user_automatic_rating($post['farmer_id']);

$current_time = time();

// Get auction dates
$auction_start_time = strtotime($post['auction_start_date']);
$auction_end_time = strtotime($post['auction_end_date']);

// Determine auction status
$is_live = false;
$is_ended = false;
$time_remaining = 0;

if ($current_time >= $auction_start_time && $current_time < $auction_end_time) {
    $is_live = true;
    $time_remaining = $auction_end_time - $current_time;
} elseif ($current_time >= $auction_end_time) {
    $is_ended = true;
}

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

// Check if auction ended with winning bid
if ($is_ended && $total_bids >= 5 && $max_bid >= $post['price']) {
    $is_sold = true;
    // Approve the highest bid
    $approve_stmt = $conn->prepare("UPDATE comments SET is_approved = 1 WHERE post_id = ? AND comment_text = ?");
    $approve_stmt->bind_param("id", $post_id, $max_bid);
    $approve_stmt->execute();
    $approve_stmt->close();
} elseif ($is_ended && $total_bids < 5) {
    $is_unsold = true;
}

// Send notifications and adjust ratings if needed
if ($is_sold) {
    // Get winner's user_id for notifications
    $winner_stmt = $conn->prepare("SELECT user_id FROM comments WHERE post_id = ? AND comment_text = ? LIMIT 1");
    $winner_stmt->bind_param("id", $post_id, $max_bid);
    $winner_stmt->execute();
    $winner_stmt->bind_result($winner_user_id);
    $winner_stmt->fetch();
    $winner_stmt->close();

    // Send notifications if winner found
    if ($winner_user_id) {
        // Check if notification already exists before creating
        $check_notif = $conn->prepare("SELECT id FROM notifications WHERE user_id = ? AND post_id = ? AND type = 'comment_approved' LIMIT 1");
        $check_notif->bind_param("ii", $winner_user_id, $post_id);
        $check_notif->execute();
        $notif_result = $check_notif->get_result();

        if ($notif_result->num_rows == 0) {
            // Notify buyer about winning bid
            notifyBuyerWonBid($winner_user_id, $post_id, $post['product_name']);

            // Adjust farmer rating for successful sale
            adjust_rating_for_sale($post['farmer_id'], $post_id, $max_bid);

            // Adjust farmer rating based on bidding activity
            adjust_rating_for_bidding_activity($post['farmer_id'], $post_id, $total_bids);

            // Note: Auction status is managed by farmers, not updated automatically on view
        }
        $check_notif->close();
    }
} elseif ($is_unsold) {
    // Adjust farmer rating for unsold product (not enough bids)
    $check_unsold = $conn->prepare("SELECT id FROM notifications WHERE post_id = ? AND type = 'comment_approved' LIMIT 1");
    $check_unsold->bind_param("i", $post_id);
    $check_unsold->execute();
    $unsold_result = $check_unsold->get_result();

    if ($unsold_result->num_rows == 0) {
        adjust_rating_for_unsold($post['farmer_id'], $post_id);

        // Note: Auction status is managed by farmers, not updated automatically on view
    }
    $check_unsold->close();
}

// Fetch recent bids (limit to top 10 for the card) - Sort by most recent first
$bids_stmt = $conn->prepare("SELECT comments.*, users.username, users.id as bidder_id FROM comments 
                             JOIN users ON comments.user_id = users.id 
                             WHERE comments.post_id = ? ORDER BY comments.created_at DESC LIMIT 10");
$bids_stmt->bind_param("i", $post_id);
$bids_stmt->execute();
$bids_result = $bids_stmt->get_result();

// Fetch all bids for the full list
$all_bids_stmt = $conn->prepare("SELECT comments.*, users.username, users.id as bidder_id FROM comments 
                                  JOIN users ON comments.user_id = users.id 
                                  WHERE comments.post_id = ? ORDER BY comments.created_at DESC");
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
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="pd-page-wrapper">

        <!-- Breadcrumb -->
        <div class="pd-breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <span class="pd-breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
            <a href="browse.php">Browse</a>
            <span class="pd-breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
            <span><?php echo htmlspecialchars($post['product_name']); ?></span>
        </div>

        <!-- Main two-column grid -->
        <div class="pd-main-grid">

            <!-- ===== LEFT COLUMN ===== -->
            <div class="pd-left-col">

                <!-- Image Card -->
                <div class="pd-image-card">
                    <?php if ($post['image']): ?>
                        <div class="pd-image-container">
                            <img src="assets/images/<?php echo htmlspecialchars($post['image']); ?>"
                                alt="<?php echo htmlspecialchars($post['product_name']); ?>"
                                class="pd-main-img">
                            <!-- Status overlays -->
                            <?php if ($is_live): ?>
                                <div class="pd-float-badge live-badge">
                                    <span class="live-dot"></span> LIVE
                                </div>
                            <?php elseif (!$is_live && !$is_ended): ?>
                                <div class="pd-float-badge upcoming-badge">
                                    <i class="fas fa-hourglass-start"></i> UPCOMING
                                </div>
                            <?php elseif ($is_sold): ?>
                                <div class="pd-float-badge sold-badge">
                                    <i class="fas fa-check-circle"></i> SOLD
                                </div>
                            <?php elseif ($is_unsold): ?>
                                <div class="pd-float-badge unsold-badge">
                                    <i class="fas fa-times-circle"></i> UNSOLD
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="pd-image-placeholder">
                            <i class="fas fa-seedling fa-4x"></i>
                            <p>No image available</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Product Info Card -->
                <div class="pd-info-card">
                    <div class="pd-info-header">
                        <span class="pd-category-pill">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($post['category']); ?>
                        </span>
                        <?php if ($avg_rating > 0): ?>
                            <span class="pd-rating-pill">
                                <i class="fas fa-star"></i> <?php echo $avg_rating; ?> / 5
                            </span>
                        <?php endif; ?>
                    </div>

                    <h1 class="pd-product-title"><?php echo htmlspecialchars($post['product_name']); ?></h1>

                    <?php if (!empty($post['description'])): ?>
                        <p class="pd-description"><?php echo nl2br(htmlspecialchars($post['description'])); ?></p>
                    <?php endif; ?>

                    <!-- Key stats row -->
                    <div class="pd-stats-strip">
                        <div class="pd-stat-item">
                            <div class="pd-stat-label">Starting Price</div>
                            <div class="pd-stat-value price-val"><?php echo number_format($post['price'], 2); ?>৳</div>
                        </div>
                        <div class="pd-stat-divider"></div>
                        <div class="pd-stat-item">
                            <div class="pd-stat-label">Quantity</div>
                            <div class="pd-stat-value"><?php echo htmlspecialchars($post['quantity']); ?> <small><?php echo htmlspecialchars($post['unit']); ?></small></div>
                        </div>
                        <div class="pd-stat-divider"></div>
                        <div class="pd-stat-item">
                            <div class="pd-stat-label">Total Bids</div>
                            <div class="pd-stat-value"><?php echo $total_bids; ?></div>
                        </div>
                    </div>

                    <!-- Meta rows -->
                    <div class="pd-meta-grid">
                        <div class="pd-meta-row">
                            <div class="pd-meta-icon"><i class="fas fa-user-tie"></i></div>
                            <div class="pd-meta-content">
                                <span class="pd-meta-label">Farmer</span>
                                <span class="pd-meta-val">
                                    <a href="farmer/profile.php?id=<?php echo (int)$post['farmer_id']; ?>" class="pd-farmer-link">
                                        <?php echo htmlspecialchars($post['username']); ?>
                                        <i class="fas fa-external-link-alt pd-ext-icon"></i>
                                    </a>
                                    <span class="pd-fairness-badge">
                                        <i class="fas fa-shield-alt"></i>
                                        <?php echo number_format($farmer_auto_rating, 1); ?>/10 fairness
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div class="pd-meta-row">
                            <div class="pd-meta-icon"><i class="fas fa-calendar-alt"></i></div>
                            <div class="pd-meta-content">
                                <span class="pd-meta-label">Posted</span>
                                <span class="pd-meta-val"><?php echo date("d M Y, h:i A", strtotime($post['created_at'])); ?></span>
                            </div>
                        </div>
                        <div class="pd-meta-row">
                            <div class="pd-meta-icon"><i class="fas fa-play-circle"></i></div>
                            <div class="pd-meta-content">
                                <span class="pd-meta-label">Auction Start</span>
                                <span class="pd-meta-val"><?php echo date("d M Y, h:i A", $auction_start_time); ?></span>
                            </div>
                        </div>
                        <div class="pd-meta-row">
                            <div class="pd-meta-icon"><i class="fas fa-stop-circle"></i></div>
                            <div class="pd-meta-content">
                                <span class="pd-meta-label">Auction End</span>
                                <span class="pd-meta-val"><?php echo date("d M Y, h:i A", $auction_end_time); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== RIGHT COLUMN ===== -->
            <div class="pd-right-col">

                <!-- Bidding / Status Card -->
                <?php if ($is_live): ?>
                    <div class="pd-bid-card pd-bid-live">
                        <div class="pd-bid-card-header">
                            <div class="pd-live-label">
                                <span class="live-pulse-dot"></span>
                                LIVE AUCTION
                            </div>
                            <div class="pd-countdown-badge" id="pd-countdown">
                                <i class="fas fa-clock"></i>
                                <span id="pd-countdown-text">Loading…</span>
                            </div>
                        </div>

                        <div class="pd-current-bid-box">
                            <?php if ($max_bid): ?>
                                <div class="pd-bid-highlight">
                                    <span class="pd-bid-highlight-label">Current Highest Bid</span>
                                    <span class="pd-bid-highlight-amount"><?php echo number_format($max_bid, 2); ?>৳</span>
                                </div>
                            <?php else: ?>
                                <div class="pd-no-bid-msg">
                                    <i class="fas fa-gavel fa-2x"></i>
                                    <p>No bids yet — be the first!</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (isset($_SESSION['user_id'])): ?>
                            <form action="comment.php" method="POST" class="pd-bid-form">
                                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                <label class="pd-bid-input-label">Your Bid Amount</label>
                                <div class="pd-bid-input-wrap">
                                    <span class="pd-currency-sym">৳</span>
                                    <input type="number" name="comment_text"
                                        class="pd-bid-input"
                                        placeholder="0.00"
                                        required step="0.01" min="0.01"
                                        value="<?php echo $min_bid; ?>">
                                </div>
                                <p class="pd-min-bid-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Minimum bid: <strong><?php echo number_format($min_bid, 2); ?>৳</strong>
                                </p>
                                <button type="submit" class="pd-place-bid-btn">
                                    <i class="fas fa-gavel"></i> Place Bid
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="pd-login-prompt">
                                <i class="fas fa-lock fa-2x"></i>
                                <p>Please <a href="#" data-auth-modal="login">login</a> or <a href="#" data-auth-modal="signup">register</a> to place a bid</p>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif (!$is_live && !$is_ended): ?>
                    <div class="pd-bid-card pd-bid-upcoming">
                        <div class="pd-upcoming-icon"><i class="fas fa-hourglass-half fa-2x"></i></div>
                        <h3 class="pd-upcoming-title">Upcoming Auction</h3>
                        <p class="pd-upcoming-desc">This auction has not started yet.</p>
                        <div class="pd-upcoming-date-box">
                            <span class="pd-upcoming-date-label">Starts on</span>
                            <span class="pd-upcoming-date-val"><?php echo date("d M Y", $auction_start_time); ?></span>
                            <span class="pd-upcoming-time-val"><?php echo date("h:i A", $auction_start_time); ?></span>
                        </div>
                    </div>

                <?php else: ?>
                    <?php if ($is_sold): ?>
                        <div class="pd-bid-card pd-bid-sold">
                            <div class="pd-sold-icon"><i class="fas fa-trophy fa-3x"></i></div>
                            <h3>Auction Closed</h3>
                            <p class="pd-sold-sub">This product was sold!</p>
                            <?php if ($max_bid): ?>
                                <div class="pd-sold-final-price">
                                    Final Price: <strong><?php echo number_format($max_bid, 2); ?>৳</strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="pd-bid-card pd-bid-unsold">
                            <div class="pd-unsold-icon"><i class="fas fa-times-circle fa-3x"></i></div>
                            <h3>Auction Ended</h3>
                            <p class="pd-unsold-sub">This product did not meet the reserve price.</p>
                            <div class="pd-unsold-detail">
                                <small>Minimum 5 bids required &bull; <?php echo $total_bids; ?> bid<?php echo $total_bids != 1 ? 's' : ''; ?> received</small>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Bid History Card -->
                <div class="pd-bids-card">
                    <div class="pd-bids-card-header">
                        <h3 class="pd-bids-title"><i class="fas fa-list-ol"></i> Bid History</h3>
                        <span class="pd-bids-count-pill"><?php echo $total_bids; ?> bid<?php echo $total_bids != 1 ? 's' : ''; ?></span>
                    </div>

                    <div class="pd-bids-list">
                        <?php if ($bids_result->num_rows > 0): ?>
                            <?php
                            $bid_rank = 0;
                            while ($bid = $bids_result->fetch_assoc()):
                                $bid_rank++;
                                $initials = strtoupper(substr($bid['username'], 0, 2));
                            ?>
                                <div class="pd-bid-row <?php echo $bid['is_approved'] ? 'pd-bid-winner' : ''; ?>">
                                    <span class="pd-bid-rank-num"><?php echo $bid_rank; ?></span>
                                    <span class="pd-bidder-avatar-sm"><?php echo $initials; ?></span>
                                    <span class="pd-bidder-name">
                                        <a href="<?php echo $base_url; ?>user/profile.php?id=<?php echo (int)$bid['bidder_id']; ?>">
                                            <?php echo htmlspecialchars($bid['username']); ?>
                                        </a>
                                        <?php if ($bid['is_approved']): ?>
                                            <i class="fas fa-crown pd-winner-crown" title="Winner"></i>
                                        <?php endif; ?>
                                    </span>
                                    <span class="pd-bid-amount-col"><?php echo number_format($bid['comment_text'], 2); ?>৳</span>
                                    <span class="pd-bid-time-col"><?php echo date("h:i A", strtotime($bid['created_at'])); ?></span>
                                </div>
                            <?php
                            endwhile;
                            if ($bid_rank >= 10):
                            ?>
                                <p class="pd-bids-note">Showing latest 10 bids</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="pd-no-bids-placeholder">
                                <i class="fas fa-gavel fa-2x"></i>
                                <p>No bids placed yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /pd-right-col -->
        </div><!-- /pd-main-grid -->

        <!-- ===== REVIEWS SECTION ===== -->
        <div class="pd-reviews-section">
            <div class="pd-reviews-header">
                <h2><i class="fas fa-star"></i> Customer Reviews</h2>
            </div>

            <div class="pd-reviews-body">
                <!-- Rating Summary -->
                <div class="pd-rating-summary">
                    <div class="pd-rating-big-num"><?php echo $avg_rating > 0 ? $avg_rating : '—'; ?></div>
                    <div class="pd-rating-stars-display">
                        <?php
                        if ($avg_rating > 0) {
                            $starsFilled = (int)round($avg_rating);
                            for ($s = 1; $s <= 5; $s++) {
                                echo '<i class="fas fa-star' . ($s <= $starsFilled ? '' : '-o') . '"></i>';
                            }
                        } else {
                            for ($s = 0; $s < 5; $s++) echo '<i class="far fa-star"></i>';
                        }
                        ?>
                    </div>
                    <div class="pd-rating-total-label"><?php echo $total_reviews; ?> review<?php echo $total_reviews != 1 ? 's' : ''; ?></div>
                </div>

                <!-- Review cards -->
                <div class="pd-review-cards-col">
                    <?php if ($reviews_result->num_rows > 0): ?>
                        <?php while ($review = $reviews_result->fetch_assoc()):
                            $rInitials = strtoupper(substr($review['username'], 0, 2));
                            $rStars    = (int)$review['rating'];
                        ?>
                            <div class="pd-review-card">
                                <div class="pd-review-top">
                                    <div class="pd-reviewer-left">
                                        <span class="pd-reviewer-avatar"><?php echo $rInitials; ?></span>
                                        <div>
                                            <strong class="pd-reviewer-name"><?php echo htmlspecialchars($review['username']); ?></strong>
                                            <div class="pd-review-stars">
                                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                                    <i class="fas fa-star <?php echo $s <= $rStars ? 'star-filled' : 'star-empty'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="pd-review-date"><?php echo date("d M Y", strtotime($review['created_at'])); ?></span>
                                </div>
                                <p class="pd-review-text"><?php echo htmlspecialchars($review['review_text']); ?></p>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="pd-no-reviews">
                            <i class="far fa-comment-dots fa-3x"></i>
                            <p>No reviews yet for this product.</p>
                        </div>
                    <?php endif; ?>

                    <!-- Leave a Review -->
                    <?php if ($is_sold && isset($_SESSION['user_id'])): ?>
                        <div class="pd-leave-review-card" id="review-section">
                            <h5><i class="fas fa-pen"></i> Leave a Review</h5>
                            <form id="reviewForm" method="POST" action="submit_review.php">
                                <div class="pd-review-form-row">
                                    <div class="pd-form-group">
                                        <label for="rating">Rating</label>
                                        <select name="rating" id="rating" class="pd-form-select" required>
                                            <option value="">Select…</option>
                                            <option value="5">★★★★★ Excellent</option>
                                            <option value="4">★★★★☆ Good</option>
                                            <option value="3">★★★☆☆ Average</option>
                                            <option value="2">★★☆☆☆ Poor</option>
                                            <option value="1">★☆☆☆☆ Very Poor</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="pd-form-group">
                                    <label for="review_text">Your Review</label>
                                    <textarea name="review_text" id="review_text" class="pd-form-textarea"
                                        rows="3" required
                                        placeholder="Share your experience with this product…"></textarea>
                                </div>
                                <input type="hidden" name="product_id" value="<?php echo $post_id; ?>">
                                <button type="submit" class="pd-submit-review-btn">
                                    <i class="fas fa-paper-plane"></i> Submit Review
                                </button>
                            </form>
                        </div>
                    <?php elseif ($is_sold && !isset($_SESSION['user_id'])): ?>
                        <div class="pd-login-prompt" style="margin-top:1rem;">
                            <i class="fas fa-lock fa-2x"></i>
                            <p><a href="#" data-auth-modal="login">Login</a> or <a href="#" data-auth-modal="signup">register</a> to leave a review</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /pd-page-wrapper -->

    <script>
        // Live countdown timer
        <?php if ($is_live && $auction_end_time > $current_time): ?>
                (function() {
                    const endTime = <?php echo $auction_end_time; ?>;
                    const textEl = document.getElementById('pd-countdown-text');

                    function pad(n) {
                        return String(n).padStart(2, '0');
                    }

                    function tick() {
                        const now = Math.floor(Date.now() / 1000);
                        const diff = endTime - now;
                        if (!textEl) return;
                        if (diff <= 0) {
                            textEl.textContent = 'Ended';
                            textEl.style.color = '#e63946';
                            return;
                        }
                        const d = Math.floor(diff / 86400);
                        const h = Math.floor((diff % 86400) / 3600);
                        const m = Math.floor((diff % 3600) / 60);
                        const s = diff % 60;
                        textEl.textContent = d > 0 ?
                            `${d}d ${pad(h)}h ${pad(m)}m` :
                            `${pad(h)}h ${pad(m)}m ${pad(s)}s`;
                    }

                    tick();
                    setInterval(tick, 1000);
                })();
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