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
    <title>Farmer Profile - <?php echo htmlspecialchars($farmer['username']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css"> -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <!-- browser cache problem solution --- add version number for production and add echo time for development -->
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="main-container">
        <div class="container">
            <!-- Header -->
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h2 class="mb-1 d-flex align-items-center">
                        <span class="mr-2"><i class="fas fa-user-circle"></i></span>
                        <?php echo htmlspecialchars($farmer['username']); ?>
                    </h2>
                    <div class="text-muted">Joined: <?php echo date('M Y', strtotime($farmer['created_at'] ?? date('Y-m-d'))); ?></div>
                </div>
                <div class="text-right">
                    <div class="d-flex flex-column align-items-end">
                        <!-- Customer Rating (Average of Product Reviews) -->
                        <div class="mb-2">
                            <small class="text-muted d-block">Customer Rating</small>
                            <div class="badge badge-primary p-2">
                                <?php
                                if ($review_count > 0) {
                                    echo number_format($avg_rating_value, 1) . '/5.0 (' . $review_count . ' reviews)';
                                } else {
                                    echo 'No reviews yet';
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Fairness Rating (Automatic Rating) -->
                        <div class="mb-2">
                            <small class="text-muted d-block">
                                Fairness Rating
                                <span style="cursor: help;" title="This rating adjusts automatically based on how fair your product prices are compared to the market.">ℹ️</span>
                            </small>
                            <div class="badge badge-success p-2">
                                <?php echo number_format($fairness_rating, 1) . '/10.0'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="alert alert-info mb-3">
                        <strong>Total Listings:</strong> <?php echo (int)$total_listings; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-success mb-3">
                        <strong>Sold:</strong> <?php echo (int)$sold_count; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-warning mb-3">
                        <strong>Success Rate:</strong> <?php echo $success_rate; ?>%
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3"><i class="fas fa-address-card mr-2"></i>Contact Information</h5>
                            <p class="mb-1"><strong>Farmer:</strong> <?php echo htmlspecialchars($farmer['username']); ?></p>
                            <p class="mb-1 text-muted">Member since <?php echo date('M Y', strtotime($farmer['created_at'] ?? date('Y-m-d'))); ?></p>
                            <p class="mb-0"><em>Contact details not provided.</em></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Listings -->
            <h4 class="mb-3">Current Listings</h4>
            <div class="row">
                <?php if ($listings->num_rows > 0): ?>
                    <?php while ($p = $listings->fetch_assoc()): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card h-100">
                                <?php if (!empty($p['image'])): ?>
                                    <img src="../assets/images/<?php echo htmlspecialchars($p['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($p['product_name']); ?>">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($p['product_name']); ?></h5>
                                    <p class="card-text text-muted mb-3" style="min-height:48px;">
                                        <?php echo htmlspecialchars(mb_strimwidth($p['description'], 0, 100, '…')); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Price</span>
                                        <span class="h6 mb-0 text-primary"><?php echo number_format($p['price'], 2); ?>৳</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <p class="text-muted">No active listings for this farmer.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Ratings & Reviews -->
            <div class="mt-4">
                <h4 class="mb-3">Ratings & Reviews
                    <?php if ($review_count > 0): ?>
                        <small class="text-muted">
                            (<?php echo number_format($avg_rating_value, 1); ?> / 5 based on <?php echo (int)$review_count; ?> review<?php echo $review_count == 1 ? '' : 's'; ?>)
                        </small>
                    <?php endif; ?>
                </h4>

                <?php if ($farmer_reviews && $farmer_reviews->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($r = $farmer_reviews->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($r['reviewer_name']); ?></strong>
                                        <span class="ml-2">
                                            <?php
                                            $rf = (int)$r['rating'];
                                            echo str_repeat('★', $rf) . str_repeat('☆', 5 - $rf);
                                            ?>
                                        </span>
                                        <div class="text-muted small">on <?php echo htmlspecialchars($r['product_name']); ?></div>
                                    </div>
                                    <small class="text-muted"><?php echo date('d M Y', strtotime($r['created_at'])); ?></small>
                                </div>
                                <p class="mb-0 mt-2"><?php echo htmlspecialchars($r['review_text']); ?></p>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No reviews yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>