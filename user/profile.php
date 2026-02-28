<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ratings.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../index.php');
    exit();
}

$userId = (int)$_GET['id'];

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

// Fairness rating
$fairness_rating = get_user_automatic_rating($userId) ?? 5.0;

// Bidding statistics
$total_bids = $approved_bids = $pending_bids = 0;

$bids_stmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
$bids_stmt->bind_param("i", $userId);
$bids_stmt->execute();
$bids_stmt->bind_result($total_bids);
$bids_stmt->fetch();
$bids_stmt->close();

$approved_stmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ? AND is_approved = 1");
$approved_stmt->bind_param("i", $userId);
$approved_stmt->execute();
$approved_stmt->bind_result($approved_bids);
$approved_stmt->fetch();
$approved_stmt->close();

$pending_bids = $total_bids - $approved_bids;
$success_rate = $total_bids > 0 ? round(($approved_bids / $total_bids) * 100) : 0;

// Recent approved purchases
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
$recent_purchases = $purchases_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$purchases_stmt->close();

// Rating colour
$rating_color = $fairness_rating >= 7.5 ? '#22c55e' : ($fairness_rating >= 5 ? '#f59e0b' : '#ef4444');
$rating_label = $fairness_rating >= 7.5 ? 'Excellent' : ($fairness_rating >= 5 ? 'Good' : 'Needs Improvement');
$rating_pct = round(($fairness_rating / 10) * 100);

$member_since = date('F Y', strtotime($user['created_at'] ?? date('Y-m-d')));
$initials = strtoupper(substr($user['username'], 0, 1));
$avatar_colors = ['#6366f1', '#22c55e', '#f59e0b', '#06b6d4', '#ec4899', '#8b5cf6', '#14b8a6'];
$avatar_bg = $avatar_colors[crc32($user['username']) % count($avatar_colors)];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?> &mdash; Profile</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(160deg, #eef2ff 0%, #f1f5f9 40%, #faf5ff 100%);
            min-height: 100vh;
        }

        /* Fade-in entrance */
        .fade-up {
            opacity: 0;
            transform: translateY(24px);
            animation: fadeUp .6s cubic-bezier(.22, 1, .36, 1) forwards;
        }

        @keyframes fadeUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* -- Hero card -- */
        .profile-hero {
            background: rgba(255, 255, 255, .85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 24px;
            border: 1px solid rgba(99, 102, 241, .12);
            box-shadow: 0 4px 32px rgba(99, 102, 241, .08), 0 1px 4px rgba(0, 0, 0, .04);
            overflow: hidden;
            margin-bottom: 28px;
        }

        .hero-banner {
            height: 180px;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 25%, #4f46e5 50%, #7c3aed 75%, #a78bfa 100%);
            background-size: 200% 200%;
            animation: bannerShift 8s ease-in-out infinite;
            position: relative;
            overflow: hidden;
        }

        @keyframes bannerShift {

            0%,
            100% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }
        }

        /* Floating orbs */
        .hero-banner .orb {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, .08);
            pointer-events: none;
        }

        .hero-banner .orb-1 {
            width: 180px;
            height: 180px;
            top: -60px;
            right: -30px;
            animation: float 6s ease-in-out infinite;
        }

        .hero-banner .orb-2 {
            width: 100px;
            height: 100px;
            top: 30px;
            left: 10%;
            animation: float 8s ease-in-out infinite reverse;
        }

        .hero-banner .orb-3 {
            width: 60px;
            height: 60px;
            bottom: 30px;
            right: 20%;
            background: rgba(255, 255, 255, .06);
            animation: float 5s ease-in-out infinite;
        }

        .hero-banner .orb-4 {
            width: 40px;
            height: 40px;
            top: 20px;
            left: 55%;
            background: rgba(255, 255, 255, .10);
            animation: float 7s ease-in-out infinite reverse;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0) scale(1);
            }

            50% {
                transform: translateY(-14px) scale(1.05);
            }
        }

        /* Grid pattern overlay */
        .hero-banner .grid-pattern {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, .04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .04) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
        }

        /* Diagonal sparkle accents */
        .hero-banner .sparkle {
            position: absolute;
            width: 6px;
            height: 6px;
            background: #fff;
            border-radius: 50%;
            opacity: 0;
            animation: sparkle 3s ease-in-out infinite;
            pointer-events: none;
        }

        .hero-banner .sparkle:nth-child(5) {
            top: 22%;
            left: 30%;
            animation-delay: 0s;
        }

        .hero-banner .sparkle:nth-child(6) {
            top: 50%;
            left: 72%;
            animation-delay: 1s;
        }

        .hero-banner .sparkle:nth-child(7) {
            top: 35%;
            left: 85%;
            animation-delay: 2s;
        }

        @keyframes sparkle {

            0%,
            100% {
                opacity: 0;
                transform: scale(0);
            }

            50% {
                opacity: .7;
                transform: scale(1);
            }
        }

        /* Wave divider */
        .hero-banner .wave-divider {
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            line-height: 0;
        }

        .hero-banner .wave-divider svg {
            display: block;
            width: 100%;
            height: 40px;
        }

        .hero-body {
            padding: 0 32px 28px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .avatar-wrap {
            position: relative;
            display: inline-block;
            margin-top: -52px;
            flex-shrink: 0;
        }

        .avatar-circle {
            width: 104px;
            height: 104px;
            border-radius: 50%;
            border: 4px solid #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: 700;
            color: #fff;
            box-shadow: 0 6px 24px rgba(99, 102, 241, .25), 0 2px 8px rgba(0, 0, 0, .1);
            transition: transform .3s, box-shadow .3s;
        }

        .avatar-circle:hover {
            transform: scale(1.06);
            box-shadow: 0 8px 32px rgba(99, 102, 241, .35), 0 4px 12px rgba(0, 0, 0, .12);
        }

        .avatar-ring {
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            border: 2px dashed rgba(99, 102, 241, .3);
            animation: spin 12s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .username-row {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .username-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .member-since {
            font-size: .8rem;
            color: #94a3b8;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Rating gauge card */
        .rating-pill {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            background: linear-gradient(135deg, rgba(238, 242, 255, .9), rgba(250, 245, 255, .9));
            backdrop-filter: blur(8px);
            border: 1px solid rgba(99, 102, 241, .15);
            border-radius: 16px;
            padding: 16px 24px;
            min-width: 136px;
            box-shadow: 0 2px 12px rgba(99, 102, 241, .08);
            transition: transform .25s, box-shadow .25s;
        }

        .rating-pill:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(99, 102, 241, .14);
        }

        .rating-score {
            font-size: 1.9rem;
            font-weight: 800;
            line-height: 1;
        }

        .rating-label-sm {
            font-size: .7rem;
            color: #64748b;
            margin-top: 2px;
            font-weight: 500;
        }

        .rating-tag {
            font-size: .68rem;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 20px;
            margin-top: 6px;
            letter-spacing: .3px;
        }

        /* -- Stat cards -- */
        .stat-mini {
            background: rgba(255, 255, 255, .8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(99, 102, 241, .1);
            border-radius: 18px;
            padding: 22px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            height: 100%;
            position: relative;
            overflow: hidden;
            transition: box-shadow .3s, transform .3s, border-color .3s;
        }

        .stat-mini::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 18px;
            padding: 1px;
            background: linear-gradient(135deg, rgba(99, 102, 241, .15), rgba(124, 58, 237, .1), transparent);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity .3s;
            pointer-events: none;
        }

        .stat-mini:hover {
            box-shadow: 0 8px 32px rgba(99, 102, 241, .12), 0 2px 8px rgba(0, 0, 0, .04);
            transform: translateY(-3px);
            border-color: rgba(99, 102, 241, .18);
        }

        .stat-mini:hover::before {
            opacity: 1;
        }

        .stat-mini-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            position: relative;
        }

        .si-indigo {
            background: linear-gradient(135deg, #ede9fe, #e0e7ff);
            color: #6366f1;
            box-shadow: 0 2px 8px rgba(99, 102, 241, .15);
        }

        .si-green {
            background: linear-gradient(135deg, #dcfce7, #d1fae5);
            color: #16a34a;
            box-shadow: 0 2px 8px rgba(22, 163, 74, .12);
        }

        .si-amber {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #d97706;
            box-shadow: 0 2px 8px rgba(217, 119, 6, .12);
        }

        .si-sky {
            background: linear-gradient(135deg, #e0f2fe, #dbeafe);
            color: #0284c7;
            box-shadow: 0 2px 8px rgba(2, 132, 199, .12);
        }

        .stat-mini-val {
            font-size: 1.6rem;
            font-weight: 800;
            color: #1e1b4b;
            line-height: 1;
        }

        .stat-mini-label {
            font-size: .73rem;
            color: #64748b;
            margin-top: 3px;
            font-weight: 500;
        }

        /* -- Fairness bar -- */
        .fairness-card {
            background: rgba(255, 255, 255, .8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(99, 102, 241, .1);
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 28px;
            box-shadow: 0 4px 24px rgba(99, 102, 241, .06);
            position: relative;
            overflow: hidden;
        }

        @keyframes barShimmer {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        .fairness-title {
            font-size: .9rem;
            font-weight: 700;
            color: #1e1b4b;
            margin-bottom: 4px;
        }

        .fairness-sub {
            font-size: .75rem;
            color: #94a3b8;
            margin-bottom: 18px;
        }

        .bar-track {
            height: 12px;
            background: linear-gradient(90deg, #f1f5f9, #eef2ff);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, .06);
        }

        .bar-fill {
            height: 100%;
            border-radius: 12px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed, #a78bfa) !important;
            background-size: 200% 100%;
            animation: barGlow 2s ease-in-out infinite alternate;
            transition: width 1.2s cubic-bezier(.22, 1, .36, 1);
            box-shadow: 0 0 12px rgba(99, 102, 241, .3);
        }

        @keyframes barGlow {
            0% {
                background-position: 0% 50%;
            }

            100% {
                background-position: 100% 50%;
            }
        }

        .bar-labels {
            display: flex;
            justify-content: space-between;
            font-size: .68rem;
            color: #94a3b8;
            margin-top: 6px;
            font-weight: 500;
        }

        /* -- Purchases table card -- */
        .table-card {
            background: rgba(255, 255, 255, .82);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 20px;
            border: 1px solid rgba(99, 102, 241, .1);
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(99, 102, 241, .06);
        }

        .table-card-header {
            padding: 20px 28px;
            background: linear-gradient(135deg, rgba(238, 242, 255, .6), rgba(250, 245, 255, .6));
            border-bottom: 1px solid rgba(99, 102, 241, .08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .table-card-header h5 {
            font-size: .92rem;
            font-weight: 700;
            color: #1e1b4b;
            margin: 0;
        }

        .purchases-table {
            width: 100%;
            border-collapse: collapse;
        }

        .purchases-table thead th {
            background: linear-gradient(135deg, #f8fafc, #eef2ff);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: #4f46e5;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(99, 102, 241, .1);
            white-space: nowrap;
        }

        .purchases-table tbody tr {
            border-bottom: 1px solid rgba(241, 245, 249, .8);
            transition: background .2s, transform .2s;
        }

        .purchases-table tbody tr:hover {
            background: linear-gradient(90deg, rgba(238, 242, 255, .4), rgba(250, 245, 255, .3));
        }

        .purchases-table tbody tr:last-child {
            border-bottom: none;
        }

        .purchases-table td {
            padding: 14px 16px;
            font-size: .83rem;
            color: #334155;
            vertical-align: middle;
        }

        .product-link {
            font-weight: 600;
            color: #4f46e5;
            text-decoration: none;
            transition: color .2s;
            position: relative;
        }

        .product-link::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 0;
            height: 1.5px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            transition: width .25s;
        }

        .product-link:hover {
            color: #7c3aed;
            text-decoration: none;
        }

        .product-link:hover::after {
            width: 100%;
        }

        .farmer-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, rgba(238, 242, 255, .7), rgba(241, 245, 249, .8));
            border: 1px solid rgba(99, 102, 241, .08);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: .75rem;
            color: #475569;
            font-weight: 500;
            transition: border-color .2s;
        }

        .farmer-chip:hover {
            border-color: rgba(99, 102, 241, .2);
        }

        .farmer-chip .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            flex-shrink: 0;
            box-shadow: 0 0 6px rgba(34, 197, 94, .4);
            animation: pulse-dot 2s ease-in-out infinite;
        }

        @keyframes pulse-dot {

            0%,
            100% {
                box-shadow: 0 0 4px rgba(34, 197, 94, .3);
            }

            50% {
                box-shadow: 0 0 10px rgba(34, 197, 94, .6);
            }
        }

        .price-ask {
            font-size: .8rem;
            color: #94a3b8;
        }

        .price-bid {
            font-weight: 700;
            color: #1e1b4b;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 16px;
            border-radius: 10px;
            font-size: .75rem;
            font-weight: 600;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
            border: none;
            text-decoration: none;
            transition: transform .2s, box-shadow .2s, opacity .2s;
            box-shadow: 0 2px 8px rgba(99, 102, 241, .25);
        }

        .btn-view:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(99, 102, 241, .35);
            color: #fff;
            opacity: .92;
            text-decoration: none;
        }

        .empty-state {
            text-align: center;
            padding: 64px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            display: block;
            margin-bottom: 14px;
            background: linear-gradient(135deg, #a78bfa, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .empty-state p {
            font-size: .86rem;
            margin: 0;
            color: #64748b;
        }

        /* Section heading */
        .section-heading {
            font-size: .78rem;
            font-weight: 700;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin: 0 0 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-heading i {
            font-size: .85rem;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-heading::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, rgba(99, 102, 241, .2), transparent);
        }
    </style>
</head>

<body>

    <?php include '../includes/nav.php'; ?>

    <div class="container py-4" style="max-width:960px;">

        <!-- -- Hero profile card -- -->
        <div class="profile-hero fade-up" style="animation-delay:.1s">
            <div class="hero-banner">
                <div class="grid-pattern"></div>
                <div class="orb orb-1"></div>
                <div class="orb orb-2"></div>
                <div class="orb orb-3"></div>
                <div class="orb orb-4"></div>
                <span class="sparkle"></span>
                <span class="sparkle"></span>
                <span class="sparkle"></span>
                <div class="wave-divider">
                    <svg viewBox="0 0 1200 40" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M0,20 C300,45 400,0 600,20 C800,40 900,0 1200,20 L1200,40 L0,40 Z" fill="#fff" />
                    </svg>
                </div>
            </div>
            <div class="hero-body">
                <div style="display:flex;align-items:center;gap:20px;">
                    <div class="avatar-wrap">
                        <div class="avatar-ring"></div>
                        <div class="avatar-circle" style="background:<?php echo $avatar_bg; ?>">
                            <?php echo $initials; ?>
                        </div>
                    </div>
                    <div style="padding-top:6px;">
                        <h1 class="username-title"><?php echo htmlspecialchars($user['username']); ?></h1>
                        <div class="member-since">
                            <i class="bi bi-calendar3"></i> Member since <?php echo $member_since; ?>
                        </div>
                    </div>
                </div>
                <div class="rating-pill">
                    <div class="rating-score" style="color:<?php echo $rating_color; ?>">
                        <?php echo number_format($fairness_rating, 1); ?>
                        <span style="font-size:.9rem;color:#94a3b8;font-weight:500;">/10</span>
                    </div>
                    <div class="rating-label-sm">Bidding Fairness</div>
                    <div class="rating-tag" style="background:<?php echo $rating_color; ?>18;color:<?php echo $rating_color; ?>">
                        <?php echo $rating_label; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- -- Stat cards -- -->
        <p class="section-heading fade-up" style="animation-delay:.25s"><i class="bi bi-bar-chart-fill"></i> Activity Overview</p>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-mini fade-up" style="animation-delay:.3s">
                    <div class="stat-mini-icon si-indigo"><i class="bi bi-chat-square-text-fill"></i></div>
                    <div>
                        <div class="stat-mini-val"><?php echo $total_bids; ?></div>
                        <div class="stat-mini-label">Total Bids</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-mini fade-up" style="animation-delay:.38s">
                    <div class="stat-mini-icon si-green"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <div class="stat-mini-val"><?php echo $approved_bids; ?></div>
                        <div class="stat-mini-label">Approved</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-mini fade-up" style="animation-delay:.46s">
                    <div class="stat-mini-icon si-amber"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <div class="stat-mini-val"><?php echo $pending_bids; ?></div>
                        <div class="stat-mini-label">Pending</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-mini fade-up" style="animation-delay:.54s">
                    <div class="stat-mini-icon si-sky"><i class="bi bi-graph-up-arrow"></i></div>
                    <div>
                        <div class="stat-mini-val"><?php echo $success_rate; ?>%</div>
                        <div class="stat-mini-label">Success Rate</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- -- Fairness rating bar -- -->
        <div class="fairness-card fade-up" style="animation-delay:.6s">
            <div class="fairness-title">
                <i class="bi bi-shield-check me-2" style="color:<?php echo $rating_color; ?>"></i>
                Bidding Fairness Rating
                <span style="font-size:.72rem;color:#94a3b8;font-weight:400;margin-left:6px;"
                    title="Calculated automatically by comparing bid prices to product market prices.">
                    &#9432; auto-calculated
                </span>
            </div>
            <div class="fairness-sub">Reflects how fair this user's bids are relative to product market prices.</div>
            <div style="display:flex;align-items:center;gap:14px;">
                <div class="bar-track" style="flex:1;">
                    <div class="bar-fill" id="fairnessBar"
                        style="width:0%;background:<?php echo $rating_color; ?>"></div>
                </div>
                <div style="font-size:1rem;font-weight:800;color:<?php echo $rating_color; ?>;min-width:48px;text-align:right;">
                    <?php echo number_format($fairness_rating, 1); ?>/10
                </div>
            </div>
            <div class="bar-labels">
                <span>0 &mdash; Poor</span>
                <span>5 &mdash; Average</span>
                <span>10 &mdash; Excellent</span>
            </div>
        </div>

        <!-- -- Recent purchases -- -->
        <p class="section-heading fade-up" style="animation-delay:.7s"><i class="bi bi-bag-check-fill"></i> Recent Purchases</p>
        <div class="table-card fade-up" style="animation-delay:.78s">
            <div class="table-card-header">
                <h5><i class="bi bi-receipt me-2 text-primary"></i>Purchase History</h5>
                <span style="font-size:.76rem;color:#94a3b8;">Last 10 approved bids</span>
            </div>

            <?php if (empty($recent_purchases)): ?>
                <div class="empty-state">
                    <i class="bi bi-bag-x"></i>
                    <p>No approved purchases yet.</p>
                </div>
            <?php
            else: ?>
                <div style="overflow-x:auto;">
                    <table class="purchases-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Farmer</th>
                                <th>Asking Price</th>
                                <th>Bid Amount</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_purchases as $p): ?>
                                <tr>
                                    <td>
                                        <a class="product-link"
                                            href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $p['post_id']; ?>">
                                            <i class="bi bi-box-seam me-1" style="font-size:.75rem;"></i>
                                            <?php echo htmlspecialchars($p['product_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="farmer-chip">
                                            <div class="dot"></div>
                                            <?php echo htmlspecialchars($p['farmer_username']); ?>
                                        </div>
                                    </td>
                                    <td class="price-ask">৳<?php echo number_format($p['asking_price'], 2); ?></td>
                                    <td class="price-bid">৳<?php echo number_format($p['bid_amount'], 2); ?></td>
                                    <td style="font-size:.78rem;color:#94a3b8;">
                                        <?php echo date('M j, Y', strtotime($p['purchase_date'])); ?>
                                    </td>
                                    <td>
                                        <a class="btn-view"
                                            href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $p['post_id']; ?>">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php
                            endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php
            endif; ?>
        </div>

    </div><!-- /container -->

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animate fairness bar on load
        window.addEventListener('load', () => {
            setTimeout(() => {
                document.getElementById('fairnessBar').style.width = '<?php echo $rating_pct; ?>%';
            }, 200);
        });
    </script>
</body>

</html>