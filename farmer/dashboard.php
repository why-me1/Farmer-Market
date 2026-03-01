<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Farmer info
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

// Total posts
$total_stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE farmer_id = ?");
$total_stmt->bind_param("i", $farmer_id);
$total_stmt->execute();
$total_stmt->bind_result($total_posts);
$total_stmt->fetch();
$total_stmt->close();

// Pending (awaiting approval)
$pending_stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE farmer_id = ? AND is_approved = 0");
$pending_stmt->bind_param("i", $farmer_id);
$pending_stmt->execute();
$pending_stmt->bind_result($pending_posts);
$pending_stmt->fetch();
$pending_stmt->close();

// Recent listings (last 4)
$recent_stmt = $conn->prepare(
    "SELECT id, product_name AS title, status, is_approved, created_at FROM posts WHERE farmer_id = ? ORDER BY created_at DESC LIMIT 4"
);
$recent_stmt->bind_param("i", $farmer_id);
$recent_stmt->execute();
$recent_posts = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_stmt->close();

// Greeting based on hour
$hour = (int)date('H');
if ($hour < 12)        $greeting = "Good morning";
elseif ($hour < 17)    $greeting = "Good afternoon";
else                   $greeting = "Good evening";

$initials = strtoupper(substr($farmer['username'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Dashboard - Farmers' Marketplace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
            color: #1e2d3d;
        }

        /* HERO */
        .fd-hero {
            background: linear-gradient(135deg, #0d6e5e 0%, #11998e 45%, #38ef7d 100%);
            border-radius: 20px;
            padding: 44px 40px 96px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(17, 153, 142, .35);
        }

        .fd-hero::before {
            content: '';
            position: absolute;
            width: 340px;
            height: 340px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .07);
            top: -80px;
            right: -60px;
        }

        .fd-hero::after {
            content: '';
            position: absolute;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .05);
            bottom: -70px;
            left: 30%;
        }

        .fd-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255, 255, 255, .18);
            border: 1px solid rgba(255, 255, 255, .3);
            border-radius: 30px;
            padding: 5px 14px;
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-bottom: 14px;
            backdrop-filter: blur(4px);
        }

        .fd-hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(22px, 3.5vw, 32px);
            font-weight: 800;
            margin: 0 0 8px;
            letter-spacing: -.5px;
        }

        .fd-hero .sub {
            font-size: 14px;
            opacity: .82;
            margin: 0;
            max-width: 460px;
        }

        .fd-hero-actions {
            position: absolute;
            top: 40px;
            right: 40px;
            display: flex;
            gap: 10px;
            z-index: 2;
        }

        .fd-hero-actions a {
            background: rgba(255, 255, 255, .2);
            border: 1px solid rgba(255, 255, 255, .3);
            color: #fff;
            border-radius: 10px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            backdrop-filter: blur(6px);
            transition: background .2s;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .fd-hero-actions a:hover {
            background: rgba(255, 255, 255, .32);
        }

        @media(max-width:576px) {
            .fd-hero {
                padding: 30px 20px 80px;
            }

            .fd-hero-actions {
                position: static;
                margin-top: 20px;
                flex-wrap: wrap;
            }
        }

        /* PROFILE CARD */
        .fd-profile-card {
            background: #fff;
            border-radius: 18px;
            padding: 0 28px 24px;
            margin-top: -56px;
            position: relative;
            z-index: 5;
            box-shadow: 0 6px 30px rgba(0, 0, 0, .1);
            margin-bottom: 28px;
        }

        .fd-profile-inner {
            display: flex;
            align-items: flex-end;
            gap: 18px;
            flex-wrap: wrap;
        }

        .fd-avatar {
            width: 86px;
            height: 86px;
            border-radius: 50%;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: #fff;
            font-weight: 800;
            border: 4px solid #fff;
            box-shadow: 0 4px 18px rgba(17, 153, 142, .4);
            margin-top: -20px;
            flex-shrink: 0;
        }

        .fd-profile-info {
            padding-top: 14px;
        }

        .fd-profile-info h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 19px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 4px;
        }

        .fd-profile-info .meta {
            font-size: 12.5px;
            color: #8b98a6;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .fd-profile-info .meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .fd-profile-right {
            margin-left: auto;
            align-self: flex-end;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .fd-verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #d0f5e8, #b8efdb);
            color: #0b6e52;
            border-radius: 30px;
            padding: 6px 15px;
            font-size: 12.5px;
            font-weight: 700;
        }

        .btn-edit {
            background: #f4f6fb;
            border: 1px solid #e4e8f0;
            color: #4a5568;
            border-radius: 10px;
            padding: 7px 15px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background .2s, transform .2s;
        }

        .btn-edit:hover {
            background: #e8ecf4;
            transform: translateY(-1px);
            color: #4a5568;
        }

        @media(max-width:576px) {
            .fd-profile-card {
                padding: 0 16px 18px;
            }

            .fd-profile-right {
                margin-left: 0;
            }
        }

        /* STAT CARDS */
        .fd-stats-grid {
            margin-bottom: 30px;
        }

        .fd-stat {
            background: #fff;
            border-radius: 16px;
            padding: 22px 22px 18px;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .07);
            border: 1px solid #edf0f6;
            display: flex;
            align-items: center;
            gap: 16px;
            height: 100%;
            transition: transform .2s, box-shadow .2s;
            position: relative;
            overflow: hidden;
        }

        .fd-stat::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .fd-stat.s-green::before {
            background: linear-gradient(90deg, #11998e, #38ef7d);
        }

        .fd-stat.s-amber::before {
            background: linear-gradient(90deg, #f7971e, #ffd200);
        }

        .fd-stat.s-blue::before {
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .fd-stat.s-rose::before {
            background: linear-gradient(90deg, #f093fb, #f5576c);
        }

        .fd-stat:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, .11);
        }

        .fd-stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .fd-stat-icon.s-green {
            background: linear-gradient(135deg, #e8faf3, #d0f5e8);
            color: #11998e;
        }

        .fd-stat-icon.s-amber {
            background: linear-gradient(135deg, #fff8e1, #ffefc0);
            color: #d4900a;
        }

        .fd-stat-icon.s-blue {
            background: linear-gradient(135deg, #eef0ff, #dce0ff);
            color: #667eea;
        }

        .fd-stat-icon.s-rose {
            background: linear-gradient(135deg, #fde8ff, #fcd0e0);
            color: #d63384;
        }

        .fd-stat-body {
            flex: 1;
            min-width: 0;
        }

        .fd-stat-value {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: #1a1a2e;
            line-height: 1;
            margin-bottom: 3px;
        }

        .fd-stat-label {
            font-size: 12px;
            color: #8b98a6;
            font-weight: 500;
        }

        .fd-stat-sub {
            font-size: 11px;
            color: #aab3bd;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* SECTION HEADER */
        .fd-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .fd-section-head h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .fd-section-head h3 .icon-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            display: inline-block;
        }

        .fd-section-head a {
            font-size: 13px;
            color: #11998e;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .fd-section-head a:hover {
            text-decoration: underline;
        }

        /* ACTION CARDS */
        .fd-action {
            background: #fff;
            border-radius: 18px;
            border: 1px solid #edf0f6;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .06);
            padding: 26px 22px 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            height: 100%;
            transition: transform .22s, box-shadow .22s;
            text-decoration: none !important;
            color: inherit;
            position: relative;
            overflow: hidden;
        }

        .fd-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 14px 36px rgba(0, 0, 0, .12);
            text-decoration: none !important;
            color: inherit;
        }

        .fd-action-icon {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
            transition: transform .2s;
        }

        .fd-action:hover .fd-action-icon {
            transform: scale(1.08);
        }

        .fd-action-icon.a-green {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: #fff;
        }

        .fd-action-icon.a-amber {
            background: linear-gradient(135deg, #f7971e, #ffd200);
            color: #fff;
        }

        .fd-action-icon.a-blue {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }

        .fd-action-icon.a-teal {
            background: linear-gradient(135deg, #17a2b8, #38ef7d);
            color: #fff;
        }

        .fd-action-icon.a-rose {
            background: linear-gradient(135deg, #f5576c, #f093fb);
            color: #fff;
        }

        .fd-action h5 {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
        }

        .fd-action p {
            font-size: 12.5px;
            color: #8b98a6;
            margin: 0;
            line-height: 1.55;
        }

        .fd-action-footer {
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .fd-action-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12.5px;
            font-weight: 700;
            transition: gap .2s;
        }

        .fd-action:hover .fd-action-link {
            gap: 9px;
        }

        .fd-action-link.c-green {
            color: #11998e;
        }

        .fd-action-link.c-amber {
            color: #d4900a;
        }

        .fd-action-link.c-blue {
            color: #667eea;
        }

        .fd-action-link.c-teal {
            color: #17a2b8;
        }

        .fd-action-link.c-rose {
            color: #f5576c;
        }

        .fd-action-badge {
            background: #f0f4f8;
            color: #8b98a6;
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 600;
        }

        .fd-action-badge.b-active {
            background: #e8faf3;
            color: #0b6e52;
        }

        /* RECENT LISTINGS */
        .fd-recent-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid #edf0f6;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .06);
            overflow: hidden;
            margin-bottom: 28px;
        }

        .fd-recent-card .rc-head {
            padding: 18px 24px;
            border-bottom: 1px solid #f1f4f8;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .fd-recent-card .rc-head h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .fd-recent-card .rc-head a {
            font-size: 13px;
            color: #11998e;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .fd-listing-row {
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 1px solid #f6f8fb;
            transition: background .15s;
        }

        .fd-listing-row:last-child {
            border-bottom: none;
        }

        .fd-listing-row:hover {
            background: #fafbfd;
        }

        .fd-listing-num {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: #f0f4f8;
            color: #8b98a6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .fd-listing-title {
            flex: 1;
            font-weight: 600;
            font-size: 13.5px;
            color: #1a1a2e;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .fd-listing-date {
            font-size: 12px;
            color: #aab3bd;
            white-space: nowrap;
        }

        .fd-listing-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .ls-active {
            background: #e8faf3;
            color: #0b6e52;
        }

        .ls-sold {
            background: #eef0ff;
            color: #667eea;
        }

        .ls-pending {
            background: #fff8e1;
            color: #b37a00;
        }

        .ls-inactive {
            background: #f0f4f8;
            color: #8b98a6;
        }

        .fd-empty-state {
            padding: 40px 24px;
            text-align: center;
            color: #aab3bd;
        }

        .fd-empty-state i {
            font-size: 36px;
            margin-bottom: 10px;
            display: block;
        }

        .fd-empty-state p {
            margin: 0;
            font-size: 13px;
        }

        @media(max-width:768px) {
            .fd-listing-date {
                display: none;
            }
        }

        /* TIP CARD */
        .fd-tip-card {
            background: linear-gradient(135deg, #fff8e1, #fffde7);
            border: 1px solid #ffe082;
            border-radius: 18px;
            padding: 22px 24px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 24px;
        }

        .fd-tip-card .tip-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: linear-gradient(135deg, #f7971e, #ffd200);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
            flex-shrink: 0;
        }

        .fd-tip-card h5 {
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #7a5502;
            margin: 0 0 4px;
        }

        .fd-tip-card p {
            font-size: 13px;
            color: #9a6e20;
            margin: 0;
            line-height: 1.6;
        }

        /* PROGRESS CARD */
        .fd-progress-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid #edf0f6;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .06);
            padding: 22px 24px;
            margin-bottom: 24px;
        }

        .fd-progress-card h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 16px;
        }

        .fd-prog-item {
            margin-bottom: 14px;
        }

        .fd-prog-item:last-child {
            margin-bottom: 0;
        }

        .fd-prog-header {
            display: flex;
            justify-content: space-between;
            font-size: 12.5px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 6px;
        }

        .fd-prog-bar-wrap {
            background: #f0f4f8;
            border-radius: 20px;
            height: 8px;
            overflow: hidden;
        }

        .fd-prog-bar {
            height: 100%;
            border-radius: 20px;
            transition: width 1s cubic-bezier(.4, 0, .2, 1);
        }

        .fd-prog-bar.pb-green {
            background: linear-gradient(90deg, #11998e, #38ef7d);
        }

        .fd-prog-bar.pb-blue {
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .fd-prog-bar.pb-amber {
            background: linear-gradient(90deg, #f7971e, #ffd200);
        }

        /* MARKET CTA */
        .fd-market-cta {
            background: linear-gradient(135deg, #0d6e5e, #11998e, #38ef7d);
            border-radius: 18px;
            padding: 22px 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            text-decoration: none !important;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 4px 18px rgba(17, 153, 142, .3);
        }

        .fd-market-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(17, 153, 142, .4);
        }

        .fd-market-cta .cta-inner {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .fd-market-cta .cta-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
            flex-shrink: 0;
        }

        .fd-market-cta .cta-title {
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
        }

        .fd-market-cta .cta-sub {
            font-size: 12px;
            opacity: .8;
            color: #fff;
        }

        .fd-market-cta .cta-link {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            font-weight: 700;
            color: rgba(255, 255, 255, .9);
            transition: gap .2s;
        }

        .fd-market-cta:hover .cta-link {
            gap: 10px;
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="main-container">
        <div class="container py-4" style="max-width:1200px;">

            <!-- HERO -->
            <div class="fd-hero mb-0">
                <div class="fd-hero-badge">
                    <i class="fas fa-tractor"></i> Farmer Dashboard
                </div>
                <h1><?php echo $greeting; ?>, <?php echo htmlspecialchars($farmer['username']); ?> &#127807;</h1>
                <p class="sub">Here's an overview of your marketplace activity today.</p>
                <div class="fd-hero-actions">
                    <a href="create_post.php"><i class="fas fa-plus"></i> New Listing</a>
                    <a href="view_posts.php"><i class="fas fa-layer-group"></i> My Listings</a>
                </div>
            </div>

            <!-- PROFILE CARD -->
            <div class="fd-profile-card">
                <div class="fd-profile-inner">
                    <div class="fd-avatar"><?php echo $initials; ?></div>
                    <div class="fd-profile-info">
                        <h2><?php echo htmlspecialchars($farmer['username']); ?></h2>
                        <div class="meta">
                            <span><i class="fas fa-calendar-alt"></i> Farming since <?php echo date('M Y', strtotime($farmer['created_at'])); ?></span>
                            <span><i class="fas fa-box-open"></i> <?php echo $total_posts; ?> listing<?php echo $total_posts != 1 ? 's' : ''; ?> total</span>
                        </div>
                    </div>
                    <div class="fd-profile-right">
                        <div class="fd-verified-badge"><i class="fas fa-leaf"></i> Verified Farmer</div>
                        <a href="profile.php" class="btn-edit"><i class="fas fa-pen"></i> Edit Profile</a>
                    </div>
                </div>
            </div>

            <!-- STAT CARDS -->
            <div class="row g-3 fd-stats-grid">
                <div class="col-6 col-lg-3">
                    <div class="fd-stat s-green">
                        <div class="fd-stat-icon s-green"><i class="fas fa-store"></i></div>
                        <div class="fd-stat-body">
                            <div class="fd-stat-value"><?php echo $total_posts; ?></div>
                            <div class="fd-stat-label">Total Listings</div>
                            <div class="fd-stat-sub"><i class="fas fa-circle" style="font-size:6px;color:#11998e;"></i> All time</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="fd-stat s-blue">
                        <div class="fd-stat-icon s-blue"><i class="fas fa-bolt"></i></div>
                        <div class="fd-stat-body">
                            <div class="fd-stat-value"><?php echo $active_listings; ?></div>
                            <div class="fd-stat-label">Active Listings</div>
                            <div class="fd-stat-sub"><i class="fas fa-circle" style="font-size:6px;color:#667eea;"></i> Live &amp; approved</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="fd-stat s-amber">
                        <div class="fd-stat-icon s-amber"><i class="fas fa-check-double"></i></div>
                        <div class="fd-stat-body">
                            <div class="fd-stat-value"><?php echo $total_sold; ?></div>
                            <div class="fd-stat-label">Products Sold</div>
                            <div class="fd-stat-sub"><i class="fas fa-circle" style="font-size:6px;color:#d4900a;"></i> Completed sales</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="fd-stat s-rose">
                        <div class="fd-stat-icon s-rose"><i class="fas fa-hourglass-half"></i></div>
                        <div class="fd-stat-body">
                            <div class="fd-stat-value"><?php echo $pending_posts; ?></div>
                            <div class="fd-stat-label">Pending Review</div>
                            <div class="fd-stat-sub"><i class="fas fa-circle" style="font-size:6px;color:#f5576c;"></i> Awaiting approval</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- LEFT COLUMN -->
                <div class="col-lg-8">

                    <!-- Quick Actions -->
                    <div class="fd-section-head">
                        <h3><span class="icon-dot"></span> Quick Actions</h3>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6 col-md-4">
                            <a href="create_post.php" class="fd-action">
                                <div class="fd-action-icon a-green"><i class="fas fa-plus"></i></div>
                                <h5>Create Listing</h5>
                                <p>List a new farm product and receive bids from buyers.</p>
                                <div class="fd-action-footer">
                                    <span class="fd-action-link c-green">Get started <i class="fas fa-arrow-right"></i></span>
                                </div>
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <a href="view_posts.php" class="fd-action">
                                <div class="fd-action-icon a-amber"><i class="fas fa-layer-group"></i></div>
                                <h5>My Listings</h5>
                                <p>View, edit, and track all your product listings.</p>
                                <div class="fd-action-footer">
                                    <span class="fd-action-link c-amber">View all <i class="fas fa-arrow-right"></i></span>
                                    <?php if ($active_listings > 0): ?>
                                        <span class="fd-action-badge b-active"><?php echo $active_listings; ?> active</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <a href="manage_orders.php" class="fd-action">
                                <div class="fd-action-icon a-blue"><i class="fas fa-truck"></i></div>
                                <h5>Manage Orders</h5>
                                <p>Handle fulfilment and update delivery status.</p>
                                <div class="fd-action-footer">
                                    <span class="fd-action-link c-blue">Manage <i class="fas fa-arrow-right"></i></span>
                                </div>
                            </a>
                        </div>

                    </div>

                    <!-- Recent Listings -->
                    <div class="fd-recent-card">
                        <div class="rc-head">
                            <h3><i class="fas fa-clock" style="color:#11998e;font-size:14px;"></i> Recent Listings</h3>
                            <a href="view_posts.php">View all <i class="fas fa-arrow-right"></i></a>
                        </div>
                        <?php if (empty($recent_posts)): ?>
                            <div class="fd-empty-state">
                                <i class="fas fa-seedling"></i>
                                <p>No listings yet. <a href="create_post.php" style="color:#11998e;font-weight:600;">Create your first listing</a></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_posts as $i => $post):
                                if (!$post['is_approved']) {
                                    $sClass = 'ls-pending';
                                    $sLabel = 'Pending';
                                } elseif ($post['status'] === 'sold') {
                                    $sClass = 'ls-sold';
                                    $sLabel = 'Sold';
                                } elseif ($post['status'] === 'active') {
                                    $sClass = 'ls-active';
                                    $sLabel = 'Active';
                                } else {
                                    $sClass = 'ls-inactive';
                                    $sLabel = ucfirst($post['status']);
                                }
                            ?>
                                <div class="fd-listing-row">
                                    <div class="fd-listing-num"><?php echo $i + 1; ?></div>
                                    <div class="fd-listing-title"><?php echo htmlspecialchars($post['title']); ?></div>
                                    <div class="fd-listing-date"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></div>
                                    <span class="fd-listing-status <?php echo $sClass; ?>"><?php echo $sLabel; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- RIGHT COLUMN -->
                <div class="col-lg-4">

                    <!-- Listing Performance -->
                    <?php
                    $sold_pct    = $total_posts > 0 ? round(($total_sold       / $total_posts) * 100) : 0;
                    $active_pct  = $total_posts > 0 ? round(($active_listings  / $total_posts) * 100) : 0;
                    $pending_pct = $total_posts > 0 ? round(($pending_posts    / $total_posts) * 100) : 0;
                    ?>
                    <div class="fd-progress-card">
                        <h4><i class="fas fa-chart-line me-2" style="color:#11998e;"></i> Listing Performance</h4>
                        <div class="fd-prog-item">
                            <div class="fd-prog-header">
                                <span>Active Rate</span>
                                <span><?php echo $active_pct; ?>%</span>
                            </div>
                            <div class="fd-prog-bar-wrap">
                                <div class="fd-prog-bar pb-blue" style="width:0" data-w="<?php echo $active_pct; ?>%"></div>
                            </div>
                        </div>
                        <div class="fd-prog-item">
                            <div class="fd-prog-header">
                                <span>Sold Rate</span>
                                <span><?php echo $sold_pct; ?>%</span>
                            </div>
                            <div class="fd-prog-bar-wrap">
                                <div class="fd-prog-bar pb-green" style="width:0" data-w="<?php echo $sold_pct; ?>%"></div>
                            </div>
                        </div>
                        <div class="fd-prog-item">
                            <div class="fd-prog-header">
                                <span>Pending Rate</span>
                                <span><?php echo $pending_pct; ?>%</span>
                            </div>
                            <div class="fd-prog-bar-wrap">
                                <div class="fd-prog-bar pb-amber" style="width:0" data-w="<?php echo $pending_pct; ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tip Card -->
                    <div class="fd-tip-card">
                        <div class="tip-icon"><i class="fas fa-lightbulb"></i></div>
                        <div>
                            <h5>Seller Tip</h5>
                            <p>Listings with clear photos and accurate descriptions receive up to <strong>3x more bids</strong>. Keep pricing competitive with current market rates.</p>
                        </div>
                    </div>

                    <!-- Market CTA -->
                    <a href="../browse.php" class="fd-market-cta">
                        <div class="cta-inner">
                            <div class="cta-icon"><i class="fas fa-chart-bar"></i></div>
                            <div>
                                <div class="cta-title">Browse Market</div>
                                <div class="cta-sub">View live listings &amp; pricing trends</div>
                            </div>
                        </div>
                        <div class="cta-link">Explore now <i class="fas fa-arrow-right"></i></div>
                    </a>

                </div>
            </div>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Animate progress bars
            document.querySelectorAll('.fd-prog-bar[data-w]').forEach(bar => {
                const target = bar.getAttribute('data-w');
                setTimeout(() => {
                    bar.style.width = target;
                }, 150);
            });
            // Count-up animation for stat values
            document.querySelectorAll('.fd-stat-value').forEach(el => {
                const target = parseInt(el.textContent, 10);
                if (isNaN(target) || target === 0) return;
                let cur = 0;
                const step = Math.max(1, Math.ceil(target / 40));
                const timer = setInterval(() => {
                    cur = Math.min(cur + step, target);
                    el.textContent = cur;
                    if (cur >= target) clearInterval(timer);
                }, 20);
            });
        });
    </script>
</body>

</html>