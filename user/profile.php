<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ratings.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../index.php');
    exit();
}

$userId = (int) $_GET['id'];

// Fetch user info
$user_stmt = $conn->prepare("SELECT id, username, created_at FROM users WHERE id = ? AND role = 'user' LIMIT 1");
$user_stmt->bind_param("i", $userId);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!$user) {
    header('Location: ../index.php');
    exit();
}

// Get user's automatic rating (bidding fairness)
$fairness_rating = get_user_automatic_rating($userId);
if ($fairness_rating === null) {
    $fairness_rating = 5.0; // Default
}

// Bidding statistics
$total_bids = 0;
$approved_bids = 0;
$pending_bids = 0;

// Count total bids
$bids_stmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
$bids_stmt->bind_param("i", $userId);
$bids_stmt->execute();
$bids_stmt->bind_result($total_bids);
$bids_stmt->fetch();
$bids_stmt->close();

// Count approved bids (purchases)
$approved_stmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ? AND is_approved = 1");
$approved_stmt->bind_param("i", $userId);
$approved_stmt->execute();
$approved_stmt->bind_result($approved_bids);
$approved_stmt->fetch();
$approved_stmt->close();

// Pending bids
$pending_bids = $total_bids - $approved_bids;

// Success rate
$success_rate = $total_bids > 0 ? round(($approved_bids / $total_bids) * 100) : 0;

// Get recent approved bids (purchases) with product info
$purchases_stmt = $conn->prepare("
    SELECT comments.id AS comment_id,
           comments.comment_text AS bid_amount,
           comments.created_at AS purchase_date,
           posts.id AS post_id,
           posts.product_name,
           posts.price AS asking_price,
           users.username AS farmer_username
    FROM comments
    JOIN posts ON comments.post_id = posts.id
    JOIN users ON posts.farmer_id = users.id
    WHERE comments.user_id = ? AND comments.is_approved = 1
    ORDER BY comments.created_at DESC
    LIMIT 10
");
$purchases_stmt->bind_param("i", $userId);
$purchases_stmt->execute();
$recent_purchases = $purchases_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - <?php echo htmlspecialchars($user['username']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
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
                        <?php echo htmlspecialchars($user['username']); ?>
                    </h2>
                    <div class="text-muted">Member Since: <?php echo date('M Y', strtotime($user['created_at'] ?? date('Y-m-d'))); ?></div>
                </div>
                <div class="text-right">
                    <div class="d-flex flex-column align-items-end">
                        <!-- Bidding Fairness Rating -->
                        <div class="mb-2">
                            <small class="text-muted d-block">
                                Bidding Fairness Rating
                                <span style="cursor: help;" title="This rating adjusts automatically based on how fair their bids are compared to product prices.">ℹ️</span>
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
                <div class="col-md-3">
                    <div class="alert alert-info mb-3">
                        <strong>Total Bids:</strong> <?php echo (int)$total_bids; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-success mb-3">
                        <strong>Approved Bids:</strong> <?php echo (int)$approved_bids; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-warning mb-3">
                        <strong>Pending Bids:</strong> <?php echo (int)$pending_bids; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-primary mb-3">
                        <strong>Success Rate:</strong> <?php echo $success_rate; ?>%
                    </div>
                </div>
            </div>

            <!-- Recent Purchases -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Recent Purchases</h5>
                </div>
                <div class="card-body">
                    <?php if ($recent_purchases->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Farmer</th>
                                        <th>Asking Price</th>
                                        <th>Bid Amount</th>
                                        <th>Purchase Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($purchase = $recent_purchases->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $purchase['post_id']; ?>">
                                                    <?php echo htmlspecialchars($purchase['product_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($purchase['farmer_username']); ?></td>
                                            <td>₹<?php echo number_format($purchase['asking_price'], 2); ?></td>
                                            <td><strong>₹<?php echo number_format($purchase['bid_amount'], 2); ?></strong></td>
                                            <td><?php echo date('M j, Y', strtotime($purchase['purchase_date'])); ?></td>
                                            <td>
                                                <a href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $purchase['post_id']; ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                            <p class="text-muted">This user hasn't made any purchases yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>