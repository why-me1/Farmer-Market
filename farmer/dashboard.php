<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Farmer username
$u_stmt = $conn->prepare("SELECT username, created_at FROM users WHERE id = ? LIMIT 1");
$u_stmt->bind_param("i", $farmer_id);
$u_stmt->execute();
$farmer = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

// Active listings
$active_stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE farmer_id = ? AND status = 'active' AND is_approved = 1");
$active_stmt->bind_param("i", $farmer_id);
$active_stmt->execute();
$active_stmt->bind_result($active_listings);
$active_stmt->fetch();
$active_stmt->close();

// Total sold posts
$sold_stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE farmer_id = ? AND status = 'sold'");
$sold_stmt->bind_param("i", $farmer_id);
$sold_stmt->execute();
$sold_stmt->bind_result($total_sold);
$sold_stmt->fetch();
$sold_stmt->close();

// Total posts (all)
$total_stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE farmer_id = ?");
$total_stmt->bind_param("i", $farmer_id);
$total_stmt->execute();
$total_stmt->bind_result($total_posts);
$total_stmt->fetch();
$total_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Dashboard – Farmers' Marketplace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        /* ── Base ── */
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6fb;
        }

        /* ── Hero ── */
        .farm-hero {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border-radius: 16px;
            padding: 36px 36px 80px;
            color: white;
            box-shadow: 0 8px 30px rgba(17, 153, 142, .3);
            position: relative;
            overflow: hidden;
        }

        .farm-hero::after {
            content: "\f06c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            right: -10px;
            bottom: -20px;
            font-size: 160px;
            opacity: .08;
            line-height: 1;
        }

        .farm-hero .hero-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            opacity: .8;
            margin-bottom: 6px;
        }

        .farm-hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 26px;
            font-weight: 700;
            margin: 0;
        }

        /* ── Profile Strip ── */
        .profile-strip {
            background: white;
            border-radius: 16px;
            padding: 0 28px 24px;
            margin-top: -58px;
            position: relative;
            z-index: 2;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .08);
            margin-bottom: 24px;
        }

        .profile-avatar {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            font-weight: 700;
            border: 4px solid white;
            box-shadow: 0 4px 14px rgba(17, 153, 142, .4);
            margin-top: -18px;
            flex-shrink: 0;
        }

        .profile-strip-inner {
            display: flex;
            align-items: flex-end;
            gap: 20px;
            flex-wrap: wrap;
        }

        .profile-name-block {
            padding-top: 16px;
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

        .farmer-badge {
            margin-left: auto;
            align-self: flex-end;
            margin-bottom: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #d4f7ee, #c3eedd);
            color: #0d6b5e;
            border-radius: 30px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
        }

        .farmer-badge i {
            color: #11998e;
        }

        /* ── Stat Cards ── */
        .stats-row {
            margin-bottom: 28px;
        }

        .stat-box {
            background: white;
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid #ebebeb;
            height: 100%;
            transition: transform .2s, box-shadow .2s;
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
            font-size: 21px;
            flex-shrink: 0;
        }

        .stat-icon.green {
            background: #e8f8ee;
            color: #11998e;
        }

        .stat-icon.yellow {
            background: #fff8e1;
            color: #e6a817;
        }

        .stat-icon.teal {
            background: #e0f7fa;
            color: #17a2b8;
        }

        .stat-icon.purple {
            background: #eef0ff;
            color: #667eea;
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

        /* ── Section Title ── */
        .section-title {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #ebebeb;
            margin-left: 8px;
        }

        /* ── Action Cards ── */
        .action-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #ebebeb;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
            padding: 28px 24px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
            height: 100%;
            transition: transform .2s, box-shadow .2s;
            text-decoration: none !important;
            color: inherit;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 28px rgba(0, 0, 0, .12);
            text-decoration: none !important;
            color: inherit;
        }

        .action-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .action-icon.green {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
        }

        .action-icon.amber {
            background: linear-gradient(135deg, #f7971e, #ffd200);
            color: white;
        }

        .action-icon.blue {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .action-card h5 {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
        }

        .action-card p {
            font-size: 13px;
            color: #888;
            margin: 0;
            line-height: 1.55;
        }

        .action-card .card-arrow {
            margin-top: auto;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #11998e;
        }

        .action-card .card-arrow.amber-txt {
            color: #e6a817;
        }

        .action-card .card-arrow.purple-txt {
            color: #667eea;
        }

        @media (max-width: 576px) {
            .farm-hero {
                padding: 24px 18px 68px;
            }

            .profile-strip {
                padding: 0 16px 18px;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="main-container">
        <div class="container py-4" style="max-width: 1200px;">

            <?php $initials = strtoupper(substr($farmer['username'], 0, 1)); ?>

            <!-- Hero -->
            <div class="farm-hero">
                <div class="hero-label"><i class="fas fa-tractor me-1"></i> Farmer Dashboard</div>
                <h1>Welcome back, <?php echo htmlspecialchars($farmer['username']); ?> 🌿</h1>
            </div>

            <!-- Profile Strip -->
            <div class="profile-strip">
                <div class="profile-strip-inner">
                    <div class="profile-avatar"><?php echo $initials; ?></div>
                    <div class="profile-name-block">
                        <h2><?php echo htmlspecialchars($farmer['username']); ?></h2>
                        <div class="meta">
                            <i class="fas fa-calendar-alt me-1"></i>
                            Farmer since <?php echo date('F Y', strtotime($farmer['created_at'])); ?>
                        </div>
                    </div>
                    <div class="farmer-badge">
                        <i class="fas fa-leaf"></i> Verified Farmer
                    </div>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="row stats-row g-3">
                <div class="col-6 col-md-4">
                    <div class="stat-box">
                        <div class="stat-icon green"><i class="fas fa-store"></i></div>
                        <div>
                            <div class="stat-value"><?php echo $total_posts; ?></div>
                            <div class="stat-label">Total Listings</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="stat-box">
                        <div class="stat-icon purple"><i class="fas fa-bolt"></i></div>
                        <div>
                            <div class="stat-value"><?php echo $active_listings; ?></div>
                            <div class="stat-label">Active Listings</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="stat-box">
                        <div class="stat-icon teal"><i class="fas fa-check-double"></i></div>
                        <div>
                            <div class="stat-value"><?php echo $total_sold; ?></div>
                            <div class="stat-label">Products Sold</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="section-title"><i class="fas fa-th-large" style="color:#11998e;"></i> Quick Actions</div>
            <div class="row g-3 mb-2">

                <!-- Create Post -->
                <div class="col-md-4">
                    <a href="create_post.php" class="action-card">
                        <div class="action-icon green"><i class="fas fa-plus"></i></div>
                        <h5>Create New Listing</h5>
                        <p>List a new farm product and start receiving bids from buyers immediately.</p>
                        <span class="card-arrow">Get started <i class="fas fa-arrow-right"></i></span>
                    </a>
                </div>

                <!-- View Posts -->
                <div class="col-md-4">
                    <a href="view_posts.php" class="action-card">
                        <div class="action-icon amber"><i class="fas fa-layer-group"></i></div>
                        <h5>My Listings</h5>
                        <p>View, edit, and track all your product listings and their bidding status.</p>
                        <span class="card-arrow amber-txt">View all <i class="fas fa-arrow-right"></i></span>
                    </a>
                </div>

                <!-- Manage Orders -->
                <div class="col-md-4">
                    <a href="manage_orders.php" class="action-card">
                        <div class="action-icon blue"><i class="fas fa-truck"></i></div>
                        <h5>Manage Orders</h5>
                        <p>Update delivery status and handle fulfilment for your completed sales.</p>
                        <span class="card-arrow purple-txt">Manage <i class="fas fa-arrow-right"></i></span>
                    </a>
                </div>

            </div>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>