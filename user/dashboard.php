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

// User info
$user_stmt = $conn->prepare("SELECT id, username, created_at FROM users WHERE id = ? LIMIT 1");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// Fairness rating
$fairness_rating = get_user_automatic_rating($user_id);
if ($fairness_rating === null) $fairness_rating = 5.0;

// Total bids
$bids_stmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
$bids_stmt->bind_param("i", $user_id);
$bids_stmt->execute();
$bids_stmt->bind_result($total_bids);
$bids_stmt->fetch();
$bids_stmt->close();

// Auctions won
$won_stmt = $conn->prepare("SELECT COUNT(DISTINCT post_id) FROM comments WHERE user_id = ? AND is_approved = 1");
$won_stmt->bind_param("i", $user_id);
$won_stmt->execute();
$won_stmt->bind_result($auctions_won);
$won_stmt->fetch();
$won_stmt->close();

// Approved bids
$approved_stmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ? AND is_approved = 1");
$approved_stmt->bind_param("i", $user_id);
$approved_stmt->execute();
$approved_stmt->bind_result($approved_bids);
$approved_stmt->fetch();
$approved_stmt->close();

// Total auctions participated (ended)
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

// Pending bids
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

// All bids
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

// Purchase history (approved bids)
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

// Wishlist
$conn->query("CREATE TABLE IF NOT EXISTS `wishlist` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL, `post_id` INT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_wishlist` (`user_id`, `post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$wl_stmt = $conn->prepare("
    SELECT w.post_id, w.created_at AS saved_at,
           p.product_name, p.price, p.image, p.category,
           p.auction_end_date, p.status,
           u.username AS farmer_username, u.id AS farmer_id,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as total_bids
    FROM wishlist w
    JOIN posts p ON w.post_id = p.id
    JOIN users u ON p.farmer_id = u.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
");
$wl_stmt->bind_param("i", $user_id);
$wl_stmt->execute();
$wishlist_items = $wl_stmt->get_result();
$wishlist_count = $wishlist_items->num_rows;

// Greeting
$hour = (int)date('H');
if ($hour < 12)      $greeting = "Good morning";
elseif ($hour < 17)  $greeting = "Good afternoon";
else                 $greeting = "Good evening";

$success_rate = $total_auctions_participated > 0
    ? round(($auctions_won / $total_auctions_participated) * 100)
    : 0;
$initials = strtoupper(substr($user['username'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Farmers' Marketplace</title>
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
        .ud-hero {
            background: linear-gradient(135deg, #4a1fa8 0%, #667eea 50%, #a78bfa 100%);
            border-radius: 20px;
            padding: 44px 40px 96px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(102, 126, 234, .38);
        }

        .ud-hero::before {
            content: '';
            position: absolute;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .07);
            top: -90px;
            right: -70px;
        }

        .ud-hero::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .05);
            bottom: -60px;
            left: 35%;
        }

        .ud-hero-badge {
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

        .ud-hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(22px, 3.5vw, 32px);
            font-weight: 800;
            margin: 0 0 8px;
            letter-spacing: -.5px;
        }

        .ud-hero .sub {
            font-size: 14px;
            opacity: .82;
            margin: 0;
            max-width: 460px;
        }

        .ud-hero-actions {
            position: absolute;
            top: 40px;
            right: 40px;
            display: flex;
            gap: 10px;
            z-index: 2;
        }

        .ud-hero-actions a {
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

        .ud-hero-actions a:hover {
            background: rgba(255, 255, 255, .32);
        }

        @media(max-width:576px) {
            .ud-hero {
                padding: 30px 20px 80px;
            }

            .ud-hero-actions {
                position: static;
                margin-top: 20px;
                flex-wrap: wrap;
            }
        }

        /* PROFILE CARD */
        .ud-profile-card {
            background: #fff;
            border-radius: 18px;
            padding: 0 28px 24px;
            margin-top: -56px;
            position: relative;
            z-index: 5;
            box-shadow: 0 6px 30px rgba(0, 0, 0, .1);
            margin-bottom: 28px;
        }

        .ud-profile-inner {
            display: flex;
            align-items: flex-end;
            gap: 18px;
            flex-wrap: wrap;
        }

        .ud-avatar {
            width: 86px;
            height: 86px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #a78bfa);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: #fff;
            font-weight: 800;
            border: 4px solid #fff;
            box-shadow: 0 4px 18px rgba(102, 126, 234, .45);
            margin-top: -20px;
            flex-shrink: 0;
        }

        .ud-profile-info {
            padding-top: 14px;
        }

        .ud-profile-info h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 19px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 4px;
        }

        .ud-profile-info .meta {
            font-size: 12.5px;
            color: #8b98a6;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .ud-profile-info .meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .ud-profile-right {
            margin-left: auto;
            align-self: flex-end;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .ud-fairness-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: linear-gradient(135deg, #eef0ff, #dde1ff);
            color: #4a3fbf;
            border-radius: 30px;
            padding: 7px 16px;
            font-size: 12.5px;
            font-weight: 700;
        }

        .ud-fairness-badge i {
            color: #667eea;
        }

        .ud-btn-edit {
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

        .ud-btn-edit:hover {
            background: #e8ecf4;
            transform: translateY(-1px);
            color: #4a5568;
        }

        @media(max-width:576px) {
            .ud-profile-card {
                padding: 0 16px 18px;
            }

            .ud-profile-right {
                margin-left: 0;
            }
        }

        /* STAT CARDS */
        .ud-stat {
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

        .ud-stat::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .ud-stat.s-purple::before {
            background: linear-gradient(90deg, #667eea, #a78bfa);
        }

        .ud-stat.s-green::before {
            background: linear-gradient(90deg, #11998e, #38ef7d);
        }

        .ud-stat.s-amber::before {
            background: linear-gradient(90deg, #f7971e, #ffd200);
        }

        .ud-stat.s-teal::before {
            background: linear-gradient(90deg, #17a2b8, #56ccf2);
        }

        .ud-stat:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, .11);
        }

        .ud-stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .ud-stat-icon.s-purple {
            background: linear-gradient(135deg, #eef0ff, #dce0ff);
            color: #667eea;
        }

        .ud-stat-icon.s-green {
            background: linear-gradient(135deg, #e8faf3, #d0f5e8);
            color: #11998e;
        }

        .ud-stat-icon.s-amber {
            background: linear-gradient(135deg, #fff8e1, #ffefc0);
            color: #d4900a;
        }

        .ud-stat-icon.s-teal {
            background: linear-gradient(135deg, #e0f7fa, #b2ebf2);
            color: #17a2b8;
        }

        .ud-stat-body {
            flex: 1;
            min-width: 0;
        }

        .ud-stat-value {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: #1a1a2e;
            line-height: 1;
            margin-bottom: 3px;
        }

        .ud-stat-label {
            font-size: 12px;
            color: #8b98a6;
            font-weight: 500;
        }

        .ud-stat-sub {
            font-size: 11px;
            color: #aab3bd;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* TAB NAV */
        .ud-tab-nav {
            background: #fff;
            border-radius: 14px;
            padding: 6px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
            border: 1px solid #edf0f6;
            margin-bottom: 20px;
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .ud-tab-nav .nav-link {
            border-radius: 10px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            color: #6b7280 !important;
            border: none;
            background: transparent;
            transition: all .2s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .ud-tab-nav .nav-link:hover {
            background: #f4f6fb;
            color: #374151 !important;
        }

        .ud-tab-nav .nav-link.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff !important;
            box-shadow: 0 4px 14px rgba(102, 126, 234, .35);
        }

        .ud-tab-nav .tab-cnt {
            background: rgba(255, 255, 255, .25);
            color: #fff;
            border-radius: 20px;
            padding: 1px 8px;
            font-size: 11px;
            font-weight: 700;
        }

        .ud-tab-nav .nav-link:not(.active) .tab-cnt {
            background: #eef0ff;
            color: #667eea;
        }

        /* CONTENT PANEL */
        .ud-panel {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .07);
            border: 1px solid #edf0f6;
            overflow: hidden;
        }

        .ud-panel-head {
            padding: 18px 24px;
            border-bottom: 1px solid #f1f4f8;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ud-panel-head .ph-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .ud-panel-head h5 {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
        }

        .ud-panel-head .ph-sub {
            font-size: 12px;
            color: #aab3bd;
            font-weight: 400;
            margin-left: 4px;
        }

        /* BID / PURCHASE ROW */
        .ud-item-row {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 24px;
            border-bottom: 1px solid #f6f8fb;
            transition: background .15s;
        }

        .ud-item-row:last-child {
            border-bottom: none;
        }

        .ud-item-row:hover {
            background: #fafbff;
        }

        .ud-thumb {
            width: 68px;
            height: 68px;
            border-radius: 12px;
            object-fit: cover;
            flex-shrink: 0;
            border: 1px solid #edf0f6;
        }

        .ud-thumb-ph {
            width: 68px;
            height: 68px;
            border-radius: 12px;
            background: linear-gradient(135deg, #eef0ff, #dce0ff);
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .ud-item-info {
            flex: 1;
            min-width: 0;
        }

        .ud-item-title {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 4px;
            text-decoration: none;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ud-item-title:hover {
            color: #667eea;
        }

        .ud-item-meta {
            font-size: 12px;
            color: #8b98a6;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .ud-item-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .ud-price {
            text-align: right;
            flex-shrink: 0;
            min-width: 100px;
        }

        .ud-price .pv {
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            font-weight: 800;
            color: #11998e;
        }

        .ud-price .pl {
            font-size: 11px;
            color: #aab3bd;
            margin-bottom: 2px;
        }

        .ud-price .ask {
            font-size: 11px;
            color: #c0c8d0;
            text-decoration: line-through;
        }

        .ud-status {
            text-align: center;
            flex-shrink: 0;
            min-width: 88px;
        }

        .ud-pill {
            display: inline-block;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .3px;
        }

        .ud-pill.approved {
            background: #e8faf3;
            color: #0b6e52;
        }

        .ud-pill.pending {
            background: #fff8e1;
            color: #b37a00;
        }

        .ud-pill.purchased {
            background: #eef0ff;
            color: #667eea;
        }

        .ud-status .ud-date {
            font-size: 11px;
            color: #c0c8d0;
            margin-top: 5px;
        }

        .ud-btn-review {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #eef0ff;
            color: #667eea;
            border: none;
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all .2s;
        }

        .ud-btn-review:hover {
            background: #667eea;
            color: #fff;
        }

        /* EMPTY STATE */
        .ud-empty {
            text-align: center;
            padding: 60px 30px;
        }

        .ud-empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #eef0ff, #dce0ff);
            color: #667eea;
            font-size: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
        }

        .ud-empty h5 {
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 8px;
        }

        .ud-empty p {
            font-size: 13px;
            color: #aab3bd;
            margin-bottom: 20px;
        }

        .ud-btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all .25s;
            box-shadow: 0 4px 14px rgba(102, 126, 234, .35);
        }

        .ud-btn-primary:hover {
            transform: translateY(-2px);
            color: #fff;
            box-shadow: 0 8px 22px rgba(102, 126, 234, .45);
        }

        /* QUICK LINKS SIDEBAR */
        .ud-quick-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid #edf0f6;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .06);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .ud-quick-card .qc-head {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f4f8;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #1a1a2e;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ud-quick-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 20px;
            border-bottom: 1px solid #f6f8fb;
            text-decoration: none;
            color: inherit;
            transition: background .15s;
        }

        .ud-quick-link:last-child {
            border-bottom: none;
        }

        .ud-quick-link:hover {
            background: #fafbff;
        }

        .udql-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .udql-icon.purple {
            background: #eef0ff;
            color: #667eea;
        }

        .udql-icon.green {
            background: #e8faf3;
            color: #11998e;
        }

        .udql-icon.amber {
            background: #fff8e1;
            color: #d4900a;
        }

        .udql-text {
            flex: 1;
        }

        .udql-text .ql-title {
            font-size: 13.5px;
            font-weight: 600;
            color: #1a1a2e;
        }

        .udql-text .ql-sub {
            font-size: 11.5px;
            color: #aab3bd;
        }

        .udql-arrow {
            color: #c0c8d0;
            font-size: 12px;
        }

        .ud-quick-link:hover .udql-arrow {
            color: #667eea;
        }

        /* TIP CARD */
        .ud-tip-card {
            background: linear-gradient(135deg, #eef0ff, #f5f7ff);
            border: 1px solid #d1d9ff;
            border-radius: 18px;
            padding: 20px;
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .ud-tip-card .tip-icon {
            width: 44px;
            height: 44px;
            border-radius: 13px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #fff;
            flex-shrink: 0;
        }

        .ud-tip-card h5 {
            font-family: 'Poppins', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            color: #3730a3;
            margin: 0 0 4px;
        }

        .ud-tip-card p {
            font-size: 12.5px;
            color: #5548c8;
            margin: 0;
            line-height: 1.6;
        }

        @media(max-width:768px) {
            .ud-price .ask {
                display: none;
            }
        }

        @media(max-width:576px) {
            .ud-item-row {
                flex-wrap: wrap;
            }

            .ud-price,
            .ud-status {
                min-width: auto;
                text-align: left;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="main-container">
        <div class="container py-4" style="max-width:1200px;">

            <!-- HERO -->
            <div class="ud-hero mb-0">
                <div class="ud-hero-badge"><i class="fas fa-tachometer-alt"></i> My Dashboard</div>
                <h1><?php echo $greeting; ?>, <?php echo htmlspecialchars($user['username']); ?> &#128075;</h1>
                <p class="sub">Track your bids, purchases, and marketplace activity.</p>
                <div class="ud-hero-actions">
                    <a href="<?php echo $base_url; ?>browse.php"><i class="fas fa-store"></i> Browse Market</a>
                    <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                </div>
            </div>

            <!-- PROFILE CARD -->
            <div class="ud-profile-card">
                <div class="ud-profile-inner">
                    <div class="ud-avatar"><?php echo $initials; ?></div>
                    <div class="ud-profile-info">
                        <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                        <div class="meta">
                            <span><i class="fas fa-calendar-alt"></i> Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                            <span><i class="fas fa-gavel"></i> <?php echo $total_bids; ?> bid<?php echo $total_bids != 1 ? 's' : ''; ?> placed</span>
                        </div>
                    </div>
                    <div class="ud-profile-right">
                        <div class="ud-fairness-badge">
                            <i class="fas fa-star"></i>
                            Fairness: <strong><?php echo number_format($fairness_rating, 1); ?> / 10</strong>
                        </div>
                        <a href="profile.php" class="ud-btn-edit"><i class="fas fa-pen"></i> Edit Profile</a>
                    </div>
                </div>
            </div>

            <!-- STAT CARDS -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="ud-stat s-purple">
                        <div class="ud-stat-icon s-purple"><i class="fas fa-gavel"></i></div>
                        <div class="ud-stat-body">
                            <div class="ud-stat-value"><?php echo $total_bids; ?></div>
                            <div class="ud-stat-label">Total Bids</div>
                            <div class="ud-stat-sub"><i class="fas fa-circle" style="font-size:6px;color:#667eea;"></i> All time</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="ud-stat s-green">
                        <div class="ud-stat-icon s-green"><i class="fas fa-check-circle"></i></div>
                        <div class="ud-stat-body">
                            <div class="ud-stat-value"><?php echo $approved_bids; ?></div>
                            <div class="ud-stat-label">Approved Bids</div>
                            <div class="ud-stat-sub"><i class="fas fa-circle" style="font-size:6px;color:#11998e;"></i> Won auctions</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="ud-stat s-amber">
                        <div class="ud-stat-icon s-amber"><i class="fas fa-hourglass-half"></i></div>
                        <div class="ud-stat-body">
                            <div class="ud-stat-value"><?php echo $pending_bids; ?></div>
                            <div class="ud-stat-label">Pending Bids</div>
                            <div class="ud-stat-sub"><i class="fas fa-circle" style="font-size:6px;color:#d4900a;"></i> Awaiting review</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="ud-stat s-teal">
                        <div class="ud-stat-icon s-teal"><i class="fas fa-trophy"></i></div>
                        <div class="ud-stat-body">
                            <div class="ud-stat-value"><?php echo $success_rate; ?>%</div>
                            <div class="ud-stat-label">Win Rate</div>
                            <div class="ud-stat-sub"><i class="fas fa-circle" style="font-size:6px;color:#17a2b8;"></i> Ended auctions</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- MAIN COLUMN -->
                <div class="col-lg-8">

                    <!-- Tab Nav -->
                    <div class="ud-tab-nav" id="udTabs" role="tablist">
                        <button class="nav-link active" id="tab-bids" data-bs-toggle="tab" data-bs-target="#pane-bids" type="button" role="tab">
                            <i class="fas fa-gavel"></i> My Bids
                            <span class="tab-cnt"><?php echo $total_bids; ?></span>
                        </button>
                        <button class="nav-link" id="tab-purchases" data-bs-toggle="tab" data-bs-target="#pane-purchases" type="button" role="tab">
                            <i class="fas fa-shopping-bag"></i> Purchases
                            <span class="tab-cnt"><?php echo $approved_bids; ?></span>
                        </button>
                        <button class="nav-link" id="tab-wishlist" data-bs-toggle="tab" data-bs-target="#pane-wishlist" type="button" role="tab">
                            <i class="fas fa-heart"></i> Wishlist
                            <span class="tab-cnt"><?php echo $wishlist_count; ?></span>
                        </button>
                    </div>

                    <!-- Tab Content -->
                    <div class="tab-content">

                        <!-- MY BIDS -->
                        <div class="tab-pane fade show active" id="pane-bids" role="tabpanel">
                            <div class="ud-panel">
                                <div class="ud-panel-head">
                                    <div class="ph-icon"><i class="fas fa-gavel"></i></div>
                                    <h5>All My Bids</h5>
                                </div>
                                <?php if ($my_bids->num_rows > 0): ?>
                                    <?php while ($bid = $my_bids->fetch_assoc()): ?>
                                        <div class="ud-item-row">
                                            <?php if ($bid['image']): ?>
                                                <img src="<?php echo $base_url; ?>assets/images/<?php echo htmlspecialchars($bid['image']); ?>"
                                                    class="ud-thumb" alt="<?php echo htmlspecialchars($bid['product_name']); ?>">
                                            <?php else: ?>
                                                <div class="ud-thumb-ph"><i class="fas fa-seedling"></i></div>
                                            <?php endif; ?>
                                            <div class="ud-item-info">
                                                <a class="ud-item-title" href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $bid['post_id']; ?>">
                                                    <?php echo htmlspecialchars($bid['product_name']); ?>
                                                </a>
                                                <div class="ud-item-meta">
                                                    <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($bid['farmer_username']); ?></span>
                                                    <span><i class="fas fa-tag"></i> Ask: &#2547;<?php echo number_format($bid['asking_price'], 2); ?></span>
                                                </div>
                                            </div>
                                            <div class="ud-price">
                                                <div class="pl">Your Bid</div>
                                                <div class="pv">&#2547;<?php echo number_format($bid['bid_amount'], 2); ?></div>
                                            </div>
                                            <div class="ud-status">
                                                <?php if ($bid['is_approved'] == 1): ?>
                                                    <span class="ud-pill approved"><i class="fas fa-check"></i> Approved</span>
                                                <?php else: ?>
                                                    <span class="ud-pill pending"><i class="fas fa-clock"></i> Pending</span>
                                                <?php endif; ?>
                                                <div class="ud-date"><?php echo date('M j, Y', strtotime($bid['bid_date'])); ?></div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="ud-empty">
                                        <div class="ud-empty-icon"><i class="fas fa-gavel"></i></div>
                                        <h5>No bids yet</h5>
                                        <p>Start bidding on fresh farm products and they'll show up here.</p>
                                        <a href="<?php echo $base_url; ?>browse.php" class="ud-btn-primary">
                                            <i class="fas fa-search"></i> Browse Products
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- PURCHASE HISTORY -->
                        <div class="tab-pane fade" id="pane-purchases" role="tabpanel">
                            <div class="ud-panel">
                                <div class="ud-panel-head">
                                    <div class="ph-icon"><i class="fas fa-shopping-bag"></i></div>
                                    <h5>Purchase History <span class="ph-sub">(approved bids only)</span></h5>
                                </div>
                                <?php if ($purchases->num_rows > 0): ?>
                                    <?php while ($purchase = $purchases->fetch_assoc()): ?>
                                        <div class="ud-item-row">
                                            <?php if ($purchase['image']): ?>
                                                <img src="<?php echo $base_url; ?>assets/images/<?php echo htmlspecialchars($purchase['image']); ?>"
                                                    class="ud-thumb" alt="<?php echo htmlspecialchars($purchase['product_name']); ?>">
                                            <?php else: ?>
                                                <div class="ud-thumb-ph"><i class="fas fa-seedling"></i></div>
                                            <?php endif; ?>
                                            <div class="ud-item-info">
                                                <a class="ud-item-title" href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $purchase['post_id']; ?>">
                                                    <?php echo htmlspecialchars($purchase['product_name']); ?>
                                                </a>
                                                <div class="ud-item-meta">
                                                    <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($purchase['farmer_username']); ?></span>
                                                    <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($purchase['purchase_date'])); ?></span>
                                                </div>
                                            </div>
                                            <div class="ud-price">
                                                <div class="pl">Paid</div>
                                                <div class="pv">&#2547;<?php echo number_format($purchase['bid_amount'], 2); ?></div>
                                                <div class="ask">Ask &#2547;<?php echo number_format($purchase['asking_price'], 2); ?></div>
                                            </div>
                                            <div class="ud-status">
                                                <span class="ud-pill purchased"><i class="fas fa-check"></i> Purchased</span>
                                                <div class="ud-date mt-2">
                                                    <a href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $purchase['post_id']; ?>#review-section"
                                                        class="ud-btn-review">
                                                        <i class="fas fa-star"></i> Review
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="ud-empty">
                                        <div class="ud-empty-icon"><i class="fas fa-shopping-bag"></i></div>
                                        <h5>No purchases yet</h5>
                                        <p>Once a farmer approves your bid it will appear here.</p>
                                        <a href="<?php echo $base_url; ?>browse.php" class="ud-btn-primary">
                                            <i class="fas fa-store"></i> Start Shopping
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- WISHLIST -->
                        <div class="tab-pane fade" id="pane-wishlist" role="tabpanel">
                            <div class="ud-panel">
                                <div class="ud-panel-head">
                                    <div class="ph-icon" style="background:linear-gradient(135deg,#fff1f2,#ffe4e6);color:#ef4444;"><i class="fas fa-heart"></i></div>
                                    <h5>My Wishlist</h5>
                                </div>
                                <?php if ($wishlist_count > 0): ?>
                                    <?php $wishlist_items->data_seek(0);
                                    while ($wl = $wishlist_items->fetch_assoc()):
                                        $auction_end   = strtotime($wl['auction_end_date']);
                                        $now           = time();
                                        $wl_is_live    = ($now < $auction_end);
                                        $wl_is_ended   = ($now >= $auction_end);
                                    ?>
                                        <div class="ud-item-row" id="wl-row-<?php echo $wl['post_id']; ?>">
                                            <?php if ($wl['image']): ?>
                                                <img src="<?php echo $base_url; ?>assets/images/<?php echo htmlspecialchars($wl['image']); ?>"
                                                    class="ud-thumb" alt="<?php echo htmlspecialchars($wl['product_name']); ?>">
                                            <?php else: ?>
                                                <div class="ud-thumb-ph"><i class="fas fa-seedling"></i></div>
                                            <?php endif; ?>
                                            <div class="ud-item-info">
                                                <a class="ud-item-title" href="<?php echo $base_url; ?>product_detail.php?id=<?php echo $wl['post_id']; ?>">
                                                    <?php echo htmlspecialchars($wl['product_name']); ?>
                                                </a>
                                                <div class="ud-item-meta">
                                                    <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($wl['farmer_username']); ?></span>
                                                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($wl['category']); ?></span>
                                                    <span><i class="fas fa-gavel"></i> <?php echo $wl['total_bids']; ?> bid<?php echo $wl['total_bids'] != 1 ? 's' : ''; ?></span>
                                                </div>
                                            </div>
                                            <div class="ud-price">
                                                <div class="pl">Starting</div>
                                                <div class="pv">&#2547;<?php echo number_format($wl['price'], 2); ?></div>
                                            </div>
                                            <div class="ud-status">
                                                <?php if ($wl_is_ended): ?>
                                                    <span class="ud-pill" style="background:#f1f5f9;color:#94a3b8;"><i class="fas fa-flag-checkered"></i> Ended</span>
                                                <?php else: ?>
                                                    <span class="ud-pill approved"><i class="fas fa-circle" style="font-size:.5rem;"></i> Active</span>
                                                <?php endif; ?>
                                                <button onclick="removeDashWishlist(<?php echo $wl['post_id']; ?>, this)"
                                                    style="margin-top:6px;background:none;border:none;color:#ef4444;font-size:.72rem;cursor:pointer;padding:0;font-weight:600;">
                                                    <i class="fas fa-times"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="ud-empty">
                                        <div class="ud-empty-icon"><i class="fas fa-heart"></i></div>
                                        <h5>Wishlist is empty</h5>
                                        <p>Tap the heart icon on any listing to save it for later.</p>
                                        <a href="<?php echo $base_url; ?>browse.php" class="ud-btn-primary">
                                            <i class="fas fa-store"></i> Browse Products
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div><!-- /tab-content -->
                </div>

                <!-- SIDEBAR -->
                <div class="col-lg-4">

                    <!-- Quick Links -->
                    <div class="ud-quick-card">
                        <div class="qc-head"><i class="fas fa-th-large" style="color:#667eea;"></i> Quick Links</div>
                        <a href="<?php echo $base_url; ?>browse.php" class="ud-quick-link">
                            <div class="udql-icon purple"><i class="fas fa-store"></i></div>
                            <div class="udql-text">
                                <div class="ql-title">Browse Market</div>
                                <div class="ql-sub">Find new products to bid on</div>
                            </div>
                            <i class="fas fa-chevron-right udql-arrow"></i>
                        </a>
                        <a href="notifications.php" class="ud-quick-link">
                            <div class="udql-icon amber"><i class="fas fa-bell"></i></div>
                            <div class="udql-text">
                                <div class="ql-title">Notifications</div>
                                <div class="ql-sub">View your latest alerts</div>
                            </div>
                            <i class="fas fa-chevron-right udql-arrow"></i>
                        </a>
                        <a href="profile.php" class="ud-quick-link">
                            <div class="udql-icon green"><i class="fas fa-user-circle"></i></div>
                            <div class="udql-text">
                                <div class="ql-title">My Profile</div>
                                <div class="ql-sub">Update your account info</div>
                            </div>
                            <i class="fas fa-chevron-right udql-arrow"></i>
                        </a>
                        <a href="#pane-wishlist" onclick="document.getElementById('tab-wishlist').click();window.scrollTo({top:document.getElementById('udTabs').offsetTop-80,behavior:'smooth'});return false;" class="ud-quick-link">
                            <div class="udql-icon" style="background:linear-gradient(135deg,#fff1f2,#ffe4e6);color:#ef4444;"><i class="fas fa-heart"></i></div>
                            <div class="udql-text">
                                <div class="ql-title">My Wishlist</div>
                                <div class="ql-sub"><?php echo $wishlist_count; ?> saved item<?php echo $wishlist_count != 1 ? 's' : ''; ?></div>
                            </div>
                            <i class="fas fa-chevron-right udql-arrow"></i>
                        </a>
                    </div>

                    <!-- Tip Card -->
                    <div class="ud-tip-card">
                        <div class="tip-icon"><i class="fas fa-lightbulb"></i></div>
                        <div>
                            <h5>Bidding Tip</h5>
                            <p>A higher fairness rating increases the chance farmers approve your bids. Bid close to the asking price to improve your score.</p>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Count-up animation for stat values
            document.querySelectorAll('.ud-stat-value').forEach(el => {
                const raw = el.textContent.replace('%', '').trim();
                const target = parseInt(raw, 10);
                const hasPct = el.textContent.includes('%');
                if (isNaN(target) || target === 0) return;
                let cur = 0;
                const step = Math.max(1, Math.ceil(target / 40));
                const timer = setInterval(() => {
                    cur = Math.min(cur + step, target);
                    el.textContent = cur + (hasPct ? '%' : '');
                    if (cur >= target) clearInterval(timer);
                }, 20);
            });
        });

        function removeDashWishlist(postId, btn) {
            fetch('../wishlist_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=toggle&post_id=' + postId
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success && !d.saved) {
                        var row = document.getElementById('wl-row-' + postId);
                        if (row) {
                            row.style.transition = 'opacity .3s, transform .3s';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(20px)';
                            setTimeout(() => {
                                row.remove();

                                // ── Update tab badge count live ──
                                var badge = document.querySelector('#tab-wishlist .tab-cnt');
                                if (badge) {
                                    var cur = parseInt(badge.textContent, 10) || 0;
                                    var next = Math.max(0, cur - 1);
                                    badge.textContent = next;

                                    // ── If no items left, show empty state ──
                                    if (next === 0) {
                                        var panel = document.querySelector('#pane-wishlist .ud-panel');
                                        // Remove all item rows (the container holding them)
                                        var existing = panel.querySelectorAll('.ud-item-row');
                                        existing.forEach(function(el) {
                                            el.remove();
                                        });
                                        // Inject empty state
                                        var emptyHtml = '<div class="ud-empty">' +
                                            '<div class="ud-empty-icon"><i class="fas fa-heart"></i></div>' +
                                            '<h5>Wishlist is empty</h5>' +
                                            '<p>Tap the heart icon on any listing to save it for later.</p>' +
                                            '<a href="<?php echo $base_url; ?>browse.php" class="ud-btn-primary">' +
                                            '<i class="fas fa-store"></i> Browse Products</a></div>';
                                        panel.insertAdjacentHTML('beforeend', emptyHtml);
                                    }
                                }
                            }, 320);
                        }
                    }
                });
        }
    </script>
</body>

</html>