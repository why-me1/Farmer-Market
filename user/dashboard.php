<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ratings.php';
check_login();

if ($_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user information
$user_stmt = $conn->prepare("SELECT id, username, created_at FROM users WHERE id = ? LIMIT 1");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// Get user's automatic rating (bidding fairness)
$fairness_rating = get_user_automatic_rating($user_id);
if ($fairness_rating === null) {
    $fairness_rating = 5.0; // Default
}

// Bidding summary statistics
$total_bids = 0;
$approved_bids = 0;
$pending_bids = 0;

// Count total bids
$bids_stmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
$bids_stmt->bind_param("i", $user_id);
$bids_stmt->execute();
$bids_stmt->bind_result($total_bids);
$bids_stmt->fetch();
$bids_stmt->close();

// Count approved bids
$approved_stmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ? AND is_approved = 1");
$approved_stmt->bind_param("i", $user_id);
$approved_stmt->execute();
$approved_stmt->bind_result($approved_bids);
$approved_stmt->fetch();
$approved_stmt->close();

// Pending bids
$pending_bids = $total_bids - $approved_bids;

// Get all bids (for My Bids section)
$my_bids_stmt = $conn->prepare("
    SELECT comments.id AS comment_id,
           comments.comment_text AS bid_amount,
           comments.is_approved,
           comments.created_at AS bid_date,
           posts.id AS post_id,
           posts.product_name,
           posts.price AS asking_price,
           posts.image,
           users.username AS farmer_username
    FROM comments
    JOIN posts ON comments.post_id = posts.id
    JOIN users ON posts.farmer_id = users.id
    WHERE comments.user_id = ?
    ORDER BY comments.created_at DESC
");
$my_bids_stmt->bind_param("i", $user_id);
$my_bids_stmt->execute();
$my_bids = $my_bids_stmt->get_result();

// Get purchase history (approved bids only)
$purchases_stmt = $conn->prepare("
    SELECT comments.id AS comment_id,
           comments.comment_text AS bid_amount,
           comments.created_at AS purchase_date,
           posts.id AS post_id,
           posts.product_name,
           posts.price AS asking_price,
           posts.image,
           users.username AS farmer_username
    FROM comments
    JOIN posts ON comments.post_id = posts.id
    JOIN users ON posts.farmer_id = users.id
    WHERE comments.user_id = ? AND comments.is_approved = 1
    ORDER BY comments.created_at DESC
");
$purchases_stmt->bind_param("i", $user_id);
$purchases_stmt->execute();
$purchases = $purchases_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        .section-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            font-weight: bold;
        }

        .stat-card {
            border-left: 4px solid #667eea;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .bid-item {
            border-bottom: 1px solid #e9ecef;
            padding: 15px 0;
        }

        .bid-item:last-child {
            border-bottom: none;
        }

        .badge-pending {
            background-color: #ffc107;
        }

        .badge-approved {
            background-color: #28a745;
        }

        /* Override navbar styles for dashboard tabs */
        #dashboardTabs .nav-link {
            color: #495057 !important;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 0;
            margin: 0;
            padding: 0.5rem 1rem !important;
        }

        #dashboardTabs .nav-link:hover {
            background: #e9ecef;
            border-color: #dee2e6 #dee2e6 #fff;
            transform: none;
        }

        #dashboardTabs .nav-link.active {
            color: #495057 !important;
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
        }

        #dashboardTabs {
            border-bottom: 1px solid #dee2e6;
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="main-container">
        <div class="container py-4">
            <h2 class="mb-4"><i class="fas fa-tachometer-alt"></i> User Dashboard</h2>

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs mb-4" id="dashboardTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="profile-tab" data-toggle="tab" href="#profile" role="tab">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="bids-tab" data-toggle="tab" href="#bids" role="tab">
                        <i class="fas fa-gavel"></i> My Bids
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="purchases-tab" data-toggle="tab" href="#purchases" role="tab">
                        <i class="fas fa-shopping-cart"></i> Purchase History
                    </a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="dashboardTabContent">

                <!-- PROFILE SECTION -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                    <div class="row">
                        <!-- User Information -->
                        <div class="col-md-6">
                            <div class="section-card">
                                <div class="section-header">
                                    <i class="fas fa-id-card"></i> User Information
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <strong>Username:</strong>
                                        <p class="text-muted"><?php echo htmlspecialchars($user['username']); ?></p>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Member Since:</strong>
                                        <p class="text-muted"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Bidding Fairness Rating:</strong>
                                        <span style="cursor: help;" title="This rating adjusts automatically based on how fair your bids are compared to the farmer's asking price.">ℹ️</span>
                                        <div class="badge badge-success p-2 mt-2">
                                            <?php echo number_format($fairness_rating, 1); ?>/10.0
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bidding Summary -->
                        <div class="col-md-6">
                            <div class="section-card">
                                <div class="section-header">
                                    <i class="fas fa-chart-bar"></i> Bidding Summary
                                </div>
                                <div class="card-body">
                                    <div class="stat-card">
                                        <h5 class="mb-1"><?php echo $total_bids; ?></h5>
                                        <small class="text-muted">Total Bids Placed</small>
                                    </div>
                                    <div class="stat-card" style="border-left-color: #28a745;">
                                        <h5 class="mb-1"><?php echo $approved_bids; ?></h5>
                                        <small class="text-muted">Approved Bids (Purchases)</small>
                                    </div>
                                    <div class="stat-card" style="border-left-color: #ffc107;">
                                        <h5 class="mb-1"><?php echo $pending_bids; ?></h5>
                                        <small class="text-muted">Pending Bids</small>
                                    </div>
                                    <div class="stat-card" style="border-left-color: #17a2b8;">
                                        <h5 class="mb-1">
                                            <?php
                                            $success_rate = $total_bids > 0 ? round(($approved_bids / $total_bids) * 100) : 0;
                                            echo $success_rate . '%';
                                            ?>
                                        </h5>
                                        <small class="text-muted">Success Rate</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MY BIDS SECTION -->
                <div class="tab-pane fade" id="bids" role="tabpanel">
                    <div class="section-card">
                        <div class="section-header">
                            <i class="fas fa-gavel"></i> All My Bids
                        </div>
                        <div class="card-body">
                            <?php if ($my_bids->num_rows > 0): ?>
                                <?php while ($bid = $my_bids->fetch_assoc()): ?>
                                    <div class="bid-item">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <?php if ($bid['image']): ?>
                                                    <img src="<?php echo $base_url; ?>assets/images/<?php echo htmlspecialchars($bid['image']); ?>"
                                                        class="img-fluid rounded" alt="Product" style="max-height: 80px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" style="height: 80px;">
                                                        <i class="fas fa-image fa-2x"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <h5 class="mb-1">
                                                    <a href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $bid['post_id']; ?>">
                                                        <?php echo htmlspecialchars($bid['product_name']); ?>
                                                    </a>
                                                </h5>
                                                <small class="text-muted">Farmer: <?php echo htmlspecialchars($bid['farmer_username']); ?></small><br>
                                                <small class="text-muted">Asking Price: ₹<?php echo number_format($bid['asking_price'], 2); ?></small>
                                            </div>
                                            <div class="col-md-2">
                                                <strong>Your Bid:</strong><br>
                                                <span class="text-primary">₹<?php echo number_format($bid['bid_amount'], 2); ?></span>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <?php if ($bid['is_approved'] == 1): ?>
                                                    <span class="badge badge-approved">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge badge-pending">Pending</span>
                                                <?php endif; ?>
                                                <br><small class="text-muted"><?php echo date('M j, Y', strtotime($bid['bid_date'])); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-gavel fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">You haven't placed any bids yet.</p>
                                    <a href="<?php echo $base_url; ?>index.php" class="btn btn-primary">Browse Products</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- PURCHASE HISTORY SECTION -->
                <div class="tab-pane fade" id="purchases" role="tabpanel">
                    <div class="section-card">
                        <div class="section-header">
                            <i class="fas fa-shopping-cart"></i> Purchase History (Approved Bids)
                        </div>
                        <div class="card-body">
                            <?php if ($purchases->num_rows > 0): ?>
                                <?php while ($purchase = $purchases->fetch_assoc()): ?>
                                    <div class="bid-item">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <?php if ($purchase['image']): ?>
                                                    <img src="<?php echo $base_url; ?>assets/images/<?php echo htmlspecialchars($purchase['image']); ?>"
                                                        class="img-fluid rounded" alt="Product" style="max-height: 80px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" style="height: 80px;">
                                                        <i class="fas fa-image fa-2x"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-5">
                                                <h5 class="mb-1">
                                                    <a href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $purchase['post_id']; ?>">
                                                        <?php echo htmlspecialchars($purchase['product_name']); ?>
                                                    </a>
                                                </h5>
                                                <small class="text-muted">Purchased from: <?php echo htmlspecialchars($purchase['farmer_username']); ?></small>
                                            </div>
                                            <div class="col-md-2">
                                                <strong>Purchase Price:</strong><br>
                                                <span class="text-success">₹<?php echo number_format($purchase['bid_amount'], 2); ?></span>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <small class="text-muted">Purchased on:</small><br>
                                                <strong><?php echo date('M j, Y', strtotime($purchase['purchase_date'])); ?></strong><br>
                                                <a href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $purchase['post_id']; ?>#review-section"
                                                    class="btn btn-sm btn-outline-primary mt-2">
                                                    <i class="fas fa-star"></i> Write Review
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">You don't have any approved purchases yet.</p>
                                    <a href="<?php echo $base_url; ?>index.php" class="btn btn-primary">Start Shopping</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>