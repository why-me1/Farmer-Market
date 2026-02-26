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
$total_auctions_participated = 0;
$auctions_won = 0;

// Count total bids
$bids_stmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
$bids_stmt->bind_param("i", $user_id);
$bids_stmt->execute();
$bids_stmt->bind_result($total_bids);
$bids_stmt->fetch();
$bids_stmt->close();

// Count unique auctions won (distinct posts where user has approved bid)
$won_stmt = $conn->prepare("SELECT COUNT(DISTINCT post_id) FROM comments WHERE user_id = ? AND is_approved = 1");
$won_stmt->bind_param("i", $user_id);
$won_stmt->execute();
$won_stmt->bind_result($auctions_won);
$won_stmt->fetch();
$won_stmt->close();

// Count approved bids (total number of approved bids for display)
$approved_stmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ? AND is_approved = 1");
$approved_stmt->bind_param("i", $user_id);
$approved_stmt->execute();
$approved_stmt->bind_result($approved_bids);
$approved_stmt->fetch();
$approved_stmt->close();

// Count total unique auctions participated where bidding has ended
// An auction has ended if: status = 'sold' OR (expiry_date is set AND expired)
$auctions_stmt = $conn->prepare("SELECT COUNT(DISTINCT c.post_id) 
                                  FROM comments c
                                  JOIN posts p ON c.post_id = p.id
                                  WHERE c.user_id = ? 
                                  AND (p.status = 'sold' 
                                       OR (p.expiry_date IS NOT NULL AND p.expiry_date <= UNIX_TIMESTAMP(NOW())))");
$auctions_stmt->bind_param("i", $user_id);
$auctions_stmt->execute();
$auctions_stmt->bind_result($total_auctions_participated);
$auctions_stmt->fetch();
$auctions_stmt->close();

// Pending bids (bids on active/ongoing auctions)
$pending_stmt = $conn->prepare("SELECT COUNT(*) FROM comments c
                                JOIN posts p ON c.post_id = p.id
                                WHERE c.user_id = ? 
                                AND c.is_approved = 0 
                                AND p.status = 'active'
                                AND (p.expiry_date IS NULL OR p.expiry_date > UNIX_TIMESTAMP(NOW()))");
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_stmt->bind_result($pending_bids);
$pending_stmt->fetch();
$pending_stmt->close();

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
    <title>My Dashboard – Farmers' Marketplace</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        /* ── Base ── */
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6fb;
        }

        /* ── Profile Hero ── */
        .profile-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 36px 36px 80px;
            position: relative;
            color: white;
            margin-bottom: 0;
            box-shadow: 0 8px 30px rgba(102, 126, 234, .35);
        }

        .profile-hero .hero-label {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            opacity: .75;
            margin-bottom: 6px;
        }

        .profile-hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 26px;
            font-weight: 700;
            margin: 0;
        }

        /* ── Avatar + Name strip ── */
        .profile-strip {
            background: white;
            border-radius: 16px;
            padding: 0 28px 24px;
            margin-top: -60px;
            position: relative;
            z-index: 2;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .08);
            margin-bottom: 24px;
        }

        .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            color: white;
            font-weight: 700;
            border: 4px solid white;
            box-shadow: 0 4px 14px rgba(102, 126, 234, .4);
            margin-top: -20px;
            flex-shrink: 0;
        }

        .profile-strip-inner {
            display: flex;
            align-items: flex-end;
            gap: 20px;
            flex-wrap: wrap;
        }

        .profile-name-block {
            padding-top: 18px;
        }

        .profile-name-block h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 3px;
        }

        .profile-name-block .meta {
            font-size: 13px;
            color: #888;
        }

        .profile-name-block .meta i {
            margin-right: 4px;
        }

        .fairness-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-radius: 30px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            margin-left: auto;
            align-self: flex-end;
            margin-bottom: 6px;
        }

        .fairness-badge i {
            color: #28a745;
        }

        /* ── Stat Cards ── */
        .stats-row {
            margin-bottom: 24px;
        }

        .stat-box {
            background: white;
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform .2s, box-shadow .2s;
            border: 1px solid #ebebeb;
            height: 100%;
        }

        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .11);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .stat-icon.purple {
            background: #eef0ff;
            color: #667eea;
        }

        .stat-icon.green {
            background: #e8f8ee;
            color: #28a745;
        }

        .stat-icon.yellow {
            background: #fff8e1;
            color: #e6a817;
        }

        .stat-icon.teal {
            background: #e0f7fa;
            color: #17a2b8;
        }

        .stat-value {
            font-family: 'Poppins', sans-serif;
            font-size: 26px;
            font-weight: 700;
            color: #1a1a2e;
            line-height: 1;
            margin-bottom: 3px;
        }

        .stat-label {
            font-size: 12px;
            color: #888;
            font-weight: 500;
        }

        /* ── Tab Pill Nav ── */
        .dash-tabs {
            background: white;
            border-radius: 14px;
            padding: 6px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .07);
            border: 1px solid #ebebeb;
            margin-bottom: 20px;
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .dash-tabs .nav-link {
            border-radius: 10px !important;
            padding: 10px 20px !important;
            font-size: 14px;
            font-weight: 600;
            color: #666 !important;
            border: none !important;
            background: transparent;
            transition: all .2s;
            display: flex;
            align-items: center;
            gap: 7px;
            white-space: nowrap;
        }

        .dash-tabs .nav-link:hover {
            background: #f4f6fb;
            color: #333 !important;
            transform: none;
        }

        .dash-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, .35);
        }

        .dash-tabs .nav-link .badge-count {
            background: rgba(255, 255, 255, .25);
            color: white;
            border-radius: 20px;
            padding: 1px 8px;
            font-size: 11px;
            font-weight: 700;
        }

        .dash-tabs .nav-link:not(.active) .badge-count {
            background: #eef0ff;
            color: #667eea;
        }

        /* ── Content Panel ── */
        .dash-panel {
            background: white;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
            border: 1px solid #ebebeb;
            overflow: hidden;
        }

        .dash-panel-header {
            padding: 18px 22px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dash-panel-header h5 {
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
        }

        .dash-panel-header .header-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        /* ── Bid / Purchase Row Item ── */
        .item-row {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 22px;
            border-bottom: 1px solid #f5f5f5;
            transition: background .15s;
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-row:hover {
            background: #fafbff;
        }

        .item-thumb {
            width: 68px;
            height: 68px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
            border: 1px solid #ebebeb;
        }

        .item-thumb-placeholder {
            width: 68px;
            height: 68px;
            border-radius: 10px;
            background: #eef0ff;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .item-info {
            flex: 1;
            min-width: 0;
        }

        .item-info .item-title {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 3px;
            text-decoration: none !important;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-info .item-title:hover {
            color: #667eea;
        }

        .item-info .item-meta {
            font-size: 12px;
            color: #999;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .item-info .item-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .item-price {
            text-align: right;
            flex-shrink: 0;
            min-width: 100px;
        }

        .item-price .price-val {
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #28a745;
        }

        .item-price .price-label {
            font-size: 11px;
            color: #aaa;
            margin-bottom: 2px;
        }

        .item-price .asking {
            font-size: 11px;
            color: #bbb;
            text-decoration: line-through;
        }

        .item-status {
            text-align: center;
            flex-shrink: 0;
            min-width: 90px;
        }

        .status-pill {
            display: inline-block;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .status-pill.approved {
            background: #e8f8ee;
            color: #1a7d3a;
        }

        .status-pill.pending {
            background: #fff8e1;
            color: #b87a00;
        }

        .item-status .item-date {
            font-size: 11px;
            color: #bbb;
            margin-top: 5px;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 60px 30px;
        }

        .empty-state .empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #eef0ff;
            color: #667eea;
            font-size: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
        }

        .empty-state h5 {
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: #aaa;
            margin-bottom: 20px;
        }

        .btn-dash-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 9px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none !important;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all .25s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, .3);
        }

        .btn-dash-primary:hover {
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 7px 20px rgba(102, 126, 234, .42);
        }

        .btn-review {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #eef0ff;
            color: #667eea;
            border: none;
            border-radius: 8px;
            padding: 6px 13px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none !important;
            transition: all .2s;
        }

        .btn-review:hover {
            background: #667eea;
            color: white;
        }

        @media (max-width: 576px) {
            .profile-hero {
                padding: 24px 18px 70px;
            }

            .profile-strip {
                padding: 0 16px 18px;
            }

            .item-row {
                flex-wrap: wrap;
                gap: 10px;
            }

            .item-price,
            .item-status {
                min-width: unset;
                text-align: left;
            }

            .stat-box {
                padding: 16px;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="main-container">
        <div class="container py-4" style="max-width: 1200px;">

            <?php
            $success_rate = $total_auctions_participated > 0
                ? round(($auctions_won / $total_auctions_participated) * 100)
                : 0;
            $initials = strtoupper(substr($user['username'], 0, 1));
            ?>

            <!-- Hero Banner -->
            <div class="profile-hero">
                <div class="hero-label"><i class="fas fa-tachometer-alt mr-1"></i> My Dashboard</div>
                <h1>Welcome back, <?php echo htmlspecialchars($user['username']); ?> 👋</h1>
            </div>

            <!-- Profile Strip -->
            <div class="profile-strip">
                <div class="profile-strip-inner">
                    <div class="profile-avatar"><?php echo $initials; ?></div>
                    <div class="profile-name-block">
                        <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                        <div class="meta">
                            <i class="fas fa-calendar-alt"></i>
                            Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                        </div>
                    </div>
                    <div class="fairness-badge" title="Auto-updated based on how fair your bids are vs asking price">
                        <i class="fas fa-star"></i>
                        Fairness Rating: <strong><?php echo number_format($fairness_rating, 1); ?> / 10</strong>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row stats-row">
                <div class="col-6 col-md-3 mb-3">
                    <div class="stat-box">
                        <div class="stat-icon purple"><i class="fas fa-gavel"></i></div>
                        <div>
                            <div class="stat-value"><?php echo $total_bids; ?></div>
                            <div class="stat-label">Total Bids</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <div class="stat-box">
                        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <div class="stat-value"><?php echo $approved_bids; ?></div>
                            <div class="stat-label">Approved Bids</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <div class="stat-box">
                        <div class="stat-icon yellow"><i class="fas fa-hourglass-half"></i></div>
                        <div>
                            <div class="stat-value"><?php echo $pending_bids; ?></div>
                            <div class="stat-label">Pending Bids</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <div class="stat-box">
                        <div class="stat-icon teal"><i class="fas fa-trophy"></i></div>
                        <div>
                            <div class="stat-value"><?php echo $success_rate; ?>%</div>
                            <div class="stat-label">Win Rate</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Nav -->
            <div class="dash-tabs" id="dashboardTabs" role="tablist">
                <a class="nav-link active" id="bids-tab" data-toggle="tab" href="#bids" role="tab">
                    <i class="fas fa-gavel"></i> My Bids
                    <span class="badge-count"><?php echo $total_bids; ?></span>
                </a>
                <a class="nav-link" id="purchases-tab" data-toggle="tab" href="#purchases" role="tab">
                    <i class="fas fa-shopping-bag"></i> Purchases
                    <span class="badge-count"><?php echo $approved_bids; ?></span>
                </a>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="dashboardTabContent">

                <!-- MY BIDS -->
                <div class="tab-pane fade show active" id="bids" role="tabpanel">
                    <div class="dash-panel">
                        <div class="dash-panel-header">
                            <div class="header-icon"><i class="fas fa-gavel"></i></div>
                            <h5>All My Bids</h5>
                        </div>
                        <?php if ($my_bids->num_rows > 0): ?>
                            <?php while ($bid = $my_bids->fetch_assoc()): ?>
                                <div class="item-row">
                                    <?php if ($bid['image']): ?>
                                        <img src="<?php echo $base_url; ?>assets/images/<?php echo htmlspecialchars($bid['image']); ?>"
                                            class="item-thumb" alt="<?php echo htmlspecialchars($bid['product_name']); ?>">
                                    <?php else: ?>
                                        <div class="item-thumb-placeholder"><i class="fas fa-seedling"></i></div>
                                    <?php endif; ?>

                                    <div class="item-info">
                                        <a class="item-title" href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $bid['post_id']; ?>">
                                            <?php echo htmlspecialchars($bid['product_name']); ?>
                                        </a>
                                        <div class="item-meta">
                                            <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($bid['farmer_username']); ?></span>
                                            <span><i class="fas fa-tag"></i> Ask: ৳<?php echo number_format($bid['asking_price'], 2); ?></span>
                                        </div>
                                    </div>

                                    <div class="item-price">
                                        <div class="price-label">Your Bid</div>
                                        <div class="price-val">৳<?php echo number_format($bid['bid_amount'], 2); ?></div>
                                    </div>

                                    <div class="item-status">
                                        <?php if ($bid['is_approved'] == 1): ?>
                                            <span class="status-pill approved"><i class="fas fa-check mr-1"></i>Approved</span>
                                        <?php else: ?>
                                            <span class="status-pill pending"><i class="fas fa-clock mr-1"></i>Pending</span>
                                        <?php endif; ?>
                                        <div class="item-date"><?php echo date('M j, Y', strtotime($bid['bid_date'])); ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-gavel"></i></div>
                                <h5>No bids yet</h5>
                                <p>Start bidding on fresh farm products and they'll show up here.</p>
                                <a href="<?php echo $base_url; ?>index.php" class="btn-dash-primary">
                                    <i class="fas fa-search"></i> Browse Products
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- PURCHASE HISTORY -->
                <div class="tab-pane fade" id="purchases" role="tabpanel">
                    <div class="dash-panel">
                        <div class="dash-panel-header">
                            <div class="header-icon"><i class="fas fa-shopping-bag"></i></div>
                            <h5>Purchase History <span style="font-weight:400;color:#aaa;font-size:13px;">(approved bids only)</span></h5>
                        </div>
                        <?php if ($purchases->num_rows > 0): ?>
                            <?php while ($purchase = $purchases->fetch_assoc()): ?>
                                <div class="item-row">
                                    <?php if ($purchase['image']): ?>
                                        <img src="<?php echo $base_url; ?>assets/images/<?php echo htmlspecialchars($purchase['image']); ?>"
                                            class="item-thumb" alt="<?php echo htmlspecialchars($purchase['product_name']); ?>">
                                    <?php else: ?>
                                        <div class="item-thumb-placeholder"><i class="fas fa-seedling"></i></div>
                                    <?php endif; ?>

                                    <div class="item-info">
                                        <a class="item-title" href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $purchase['post_id']; ?>">
                                            <?php echo htmlspecialchars($purchase['product_name']); ?>
                                        </a>
                                        <div class="item-meta">
                                            <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($purchase['farmer_username']); ?></span>
                                            <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($purchase['purchase_date'])); ?></span>
                                        </div>
                                    </div>

                                    <div class="item-price">
                                        <div class="price-label">Paid</div>
                                        <div class="price-val">৳<?php echo number_format($purchase['bid_amount'], 2); ?></div>
                                    </div>

                                    <div class="item-status">
                                        <span class="status-pill approved"><i class="fas fa-check mr-1"></i>Purchased</span>
                                        <div class="item-date mt-2">
                                            <a href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $purchase['post_id']; ?>#review-section"
                                                class="btn-review">
                                                <i class="fas fa-star"></i> Review
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-shopping-bag"></i></div>
                                <h5>No purchases yet</h5>
                                <p>Once a farmer approves your bid it will appear here.</p>
                                <a href="<?php echo $base_url; ?>index.php" class="btn-dash-primary">
                                    <i class="fas fa-store"></i> Start Shopping
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /tab-content -->
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>