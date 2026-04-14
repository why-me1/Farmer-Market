<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ratings.php';
require_once '../includes/discovery.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../index.php');
    exit();
}

$farmerId = (int) $_GET['id'];

// Ensure location columns exist (created lazily if edit_profile was never visited)
$conn->query("ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `latitude`  DECIMAL(10,7) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `longitude` DECIMAL(10,7) DEFAULT NULL");

// Fetch farmer info
$farmer_stmt = $conn->prepare("SELECT id, username, full_name, farm_name, email, phone, location, bio, profile_picture, created_at, latitude, longitude FROM users WHERE id = ? AND role = 'farmer' LIMIT 1");
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

// Per-star rating counts for distribution bars
$star_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$star_dist_stmt = $conn->prepare("SELECT r.rating, COUNT(*) as cnt FROM reviews r JOIN posts p ON p.id = r.product_id WHERE p.farmer_id = ? GROUP BY r.rating");
$star_dist_stmt->bind_param("i", $farmerId);
$star_dist_stmt->execute();
$star_dist_result = $star_dist_stmt->get_result();
while ($row = $star_dist_result->fetch_assoc()) {
    $star_counts[(int)$row['rating']] = (int)$row['cnt'];
}
$star_dist_stmt->close();

// Get automatic rating (Reputation Score)
$fairness_rating = get_user_automatic_rating($farmerId);
if ($fairness_rating === null) {
    $fairness_rating = 2.5;
}

// Determine seller label from average rating
$avg_rating_value = $avg_rating !== null ? (float)$avg_rating : 0.0;
$seller_stars = (int)round($avg_rating_value);
if ($seller_stars < 1 && $review_count > 0) $seller_stars = 1;
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

// Avatar initials
$fp_initials  = strtoupper(substr($farmer['username'], 0, 2));
$has_avatar   = !empty($farmer['profile_picture']) && file_exists(dirname(__DIR__) . '/' . $farmer['profile_picture']);
$fp_avatar_url = $has_avatar ? $base_url . $farmer['profile_picture'] : null;
$display_name    = !empty($farmer['farm_name']) ? $farmer['farm_name'] : (!empty($farmer['full_name']) ? $farmer['full_name'] : $farmer['username']);

$fp_follower_count = discoveryGetFarmerFollowerCount($farmerId);
$fp_is_following = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] !== (int)$farmerId
    ? discoveryIsFollowingFarmer((int)$_SESSION['user_id'], (int)$farmerId)
    : false;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($farmer['username']); ?> &ndash; Farmer Profile</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            background: #f1f5f9;
            font-family: 'Inter', sans-serif;
        }

        /* ── Page wrapper ── */
        .fp-page {
            padding: 0 0 60px;
        }

        /* ── HERO ── */
        .fp-hero {
            position: relative;
            height: 220px;
            background: linear-gradient(135deg, #065f46 0%, #059669 55%, #10b981 100%);
            overflow: hidden;
        }

        .fp-hero-pattern {
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 15% 85%, rgba(255, 255, 255, .07) 0%, transparent 45%),
                radial-gradient(circle at 85% 15%, rgba(255, 255, 255, .05) 0%, transparent 45%),
                repeating-linear-gradient(45deg, transparent, transparent 28px, rgba(255, 255, 255, .025) 28px, rgba(255, 255, 255, .025) 29px);
        }

        .fp-hero-emoji {
            position: absolute;
            right: 48px;
            bottom: -8px;
            font-size: 9rem;
            opacity: .08;
            line-height: 1;
            pointer-events: none;
        }

        /* wave cut at bottom */
        .fp-hero::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: #f1f5f9;
            clip-path: ellipse(55% 100% at 50% 100%);
        }

        /* ── PROFILE CARD ── */
        .fp-profile-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, .09);
            margin: -80px auto 28px;
            max-width: 900px;
            padding: 28px 32px 26px;
            position: relative;
            z-index: 2;
        }

        /* Message button */
        .fp-msg-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 9px 20px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(17, 153, 142, .35);
            transition: transform .2s, box-shadow .2s;
        }

        .fp-msg-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(17, 153, 142, .45);
            color: #fff;
        }

        .fp-msg-btn--ghost {
            background: transparent;
            color: #11998e;
            border: 1.5px solid #11998e;
            box-shadow: none;
        }

        .fp-msg-btn--ghost:hover {
            background: #f0fdf4;
            transform: translateY(-1px);
            box-shadow: none;
            color: #0d6e5e;
        }

        .fp-follow-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 10px;
            padding: 9px 16px;
            border: 1.5px solid #d1fae5;
            background: #f0fdf4;
            color: #059669;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(17, 153, 142, .12);
            transition: transform .2s, box-shadow .2s, background .2s, border-color .2s;
        }

        .fp-follow-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(17, 153, 142, .18);
            background: #dcfce7;
            border-color: #86efac;
            color: #0d6e5e;
        }

        .fp-follow-btn.is-following {
            background: #ecfeff;
            border-color: #a5f3fc;
            color: #0f766e;
        }

        .fp-follow-hint {
            font-size: .75rem;
            color: #94a3b8;
            margin-top: 6px;
        }

        /* Avatar */
        .fp-avatar-wrap {
            position: relative;
            flex-shrink: 0;
        }

        .fp-avatar {
            width: 96px;
            height: 96px;
            background: linear-gradient(135deg, #059669, #065f46);
            border: 4px solid #fff;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            box-shadow: 0 8px 24px rgba(5, 150, 105, .30);
            letter-spacing: 1px;
        }

        .fp-avatar-badge {
            position: absolute;
            bottom: -4px;
            right: -4px;
            width: 26px;
            height: 26px;
            background: #059669;
            border: 3px solid #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .52rem;
            color: #fff;
        }

        /* Name row */
        .fp-name {
            font-size: 1.55rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 2px;
            line-height: 1.2;
        }

        .fp-seller-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 1.5px solid #fbbf24;
            border-radius: 20px;
            padding: 3px 11px;
            font-size: .72rem;
            font-weight: 700;
            color: #92400e;
        }

        .fp-seller-badge i {
            color: #f59e0b;
        }

        .fp-joined {
            font-size: .78rem;
            color: #94a3b8;
            margin-top: 4px;
        }

        .fp-joined i {
            color: #10b981;
            margin-right: 4px;
        }

        /* Inline stars */
        .fp-stars {
            display: inline-flex;
            gap: 2px;
        }

        .fp-stars .sf {
            color: #f59e0b;
        }

        .fp-stars .sh {
            color: #f59e0b;
        }

        .fp-stars .se {
            color: #e2e8f0;
        }

        /* ── STAT PILLS (inline below name) ── */
        .fp-stat-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #f1f5f9;
        }

        .fp-stat-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 12px 16px;
            transition: box-shadow .2s, transform .18s;
            flex: 1;
            min-width: 120px;
        }

        .fp-stat-pill:hover {
            box-shadow: 0 6px 20px rgba(5, 150, 105, .10);
            transform: translateY(-2px);
        }

        .fp-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
            flex-shrink: 0;
        }

        .fp-stat-icon.g {
            background: #d1fae5;
            color: #059669;
        }

        .fp-stat-icon.b {
            background: #dbeafe;
            color: #2563eb;
        }

        .fp-stat-icon.a {
            background: #fef3c7;
            color: #d97706;
        }

        .fp-stat-icon.p {
            background: #ede9fe;
            color: #7c3aed;
        }

        .fp-stat-icon.t {
            background: #e0f2fe;
            color: #0284c7;
        }

        .stat-val {
            font-size: 1.3rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }

        .stat-lbl {
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* Rating pair */
        .fp-rating-pair {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-left: auto;
        }

        .fp-rpill {
            background: #f0fdf4;
            border: 1.5px solid #bbf7d0;
            border-radius: 14px;
            padding: 12px 18px;
            text-align: center;
            min-width: 140px;
            transition: box-shadow .2s, transform .18s;
        }

        .fp-rpill:hover {
            box-shadow: 0 4px 16px rgba(5, 150, 105, .12);
            transform: translateY(-2px);
        }

        .fp-rpill .rp-lbl {
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #6b9080;
            margin-bottom: 4px;
        }

        .fp-rpill .rp-val {
            font-size: 1.4rem;
            font-weight: 800;
            color: #059669;
            line-height: 1;
        }

        .fp-rpill .rp-sub {
            font-size: .7rem;
            color: #94a3b8;
            margin-top: 3px;
        }

        /* ── TABS ── */
        .fp-tabs {
            display: flex;
            gap: 4px;
            background: #fff;
            border-radius: 16px;
            padding: 6px;
            margin: 0 auto 24px;
            max-width: 900px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
        }

        .fp-tab-btn {
            flex: 1;
            text-align: center;
            padding: 10px 14px;
            border: none;
            background: none;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: .85rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .fp-tab-btn.active {
            background: linear-gradient(135deg, #059669, #065f46);
            color: #fff;
            box-shadow: 0 4px 14px rgba(5, 150, 105, .30);
        }

        .fp-tab-btn:not(.active):hover {
            background: #f1f5f9;
            color: #374151;
        }

        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            font-size: .65rem;
            font-weight: 700;
            padding: 0 5px;
            background: rgba(5, 150, 105, .12);
            color: #059669;
        }

        .fp-tab-btn.active .tab-badge {
            background: rgba(255, 255, 255, .25);
            color: #fff;
        }

        .fp-tab-pane {
            display: none;
        }

        .fp-tab-pane.active {
            display: block;
        }

        /* ── MAIN CONTENT CONTAINER ── */
        .fp-content {
            max-width: 900px;
            margin: 0 auto;
        }

        /* ── PRODUCT GRID ── */
        .fp-product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(246px, 1fr));
            gap: 18px;
        }

        .fp-product-card {
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
            transition: transform .22s, box-shadow .22s;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            border: 1.5px solid transparent;
        }

        .fp-product-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(0, 0, 0, .12);
            border-color: #bbf7d0;
        }

        .fp-img-wrap {
            height: 178px;
            overflow: hidden;
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .fp-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .38s;
        }

        .fp-product-card:hover .fp-img-wrap img {
            transform: scale(1.08);
        }

        .fp-img-placeholder {
            font-size: 3rem;
            color: rgba(5, 150, 105, .18);
        }

        .fp-img-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(6, 95, 70, .45) 0%, transparent 55%);
            opacity: 0;
            transition: opacity .3s;
            display: flex;
            align-items: flex-end;
            padding: 14px;
        }

        .fp-product-card:hover .fp-img-overlay {
            opacity: 1;
        }

        .fp-img-overlay span {
            color: #fff;
            font-size: .78rem;
            font-weight: 600;
            background: rgba(255, 255, 255, .2);
            border-radius: 20px;
            padding: 3px 12px;
            backdrop-filter: blur(4px);
        }

        .fp-card-body {
            padding: 15px 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .fp-card-title {
            font-size: .94rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 5px;
        }

        .fp-card-desc {
            font-size: .79rem;
            color: #64748b;
            flex: 1;
            line-height: 1.5;
        }

        .fp-price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 13px;
            padding-top: 13px;
            border-top: 1px solid #f1f5f9;
        }

        .fp-price {
            font-size: 1.05rem;
            font-weight: 800;
            color: #059669;
        }

        .fp-price-date {
            font-size: .71rem;
            color: #cbd5e1;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ── REVIEW SECTION ── */
        .fp-review-summary {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1.5px solid #bbf7d0;
            border-radius: 20px;
            padding: 24px 28px;
            display: flex;
            gap: 32px;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .rsum-score {
            text-align: center;
            flex-shrink: 0;
        }

        .rsum-big {
            font-size: 4rem;
            font-weight: 800;
            color: #065f46;
            line-height: 1;
        }

        .rsum-stars {
            display: flex;
            gap: 4px;
            justify-content: center;
            margin: 6px 0 4px;
        }

        .rsum-stars i {
            color: #f59e0b;
            font-size: 1rem;
        }

        .rsum-count {
            font-size: .78rem;
            color: #6b9080;
        }

        .rsum-bars {
            flex: 1;
            min-width: 200px;
        }

        .rsum-bar-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 7px;
        }

        .rsum-bar-row:last-child {
            margin-bottom: 0;
        }

        .rsum-bar-lbl {
            font-size: .72rem;
            color: #6b9080;
            width: 12px;
            text-align: right;
            flex-shrink: 0;
        }

        .rsum-bar-track {
            flex: 1;
            height: 7px;
            background: #d1fae5;
            border-radius: 99px;
            overflow: hidden;
        }

        .rsum-bar-fill {
            height: 100%;
            border-radius: 99px;
            background: linear-gradient(90deg, #f59e0b, #f97316);
            transition: width .8s ease;
        }

        .rsum-bar-cnt {
            font-size: .7rem;
            color: #94a3b8;
            width: 24px;
            flex-shrink: 0;
        }

        /* Review cards */
        .fp-review-card {
            background: #fff;
            border-radius: 18px;
            padding: 22px 24px;
            margin-bottom: 14px;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .06);
            transition: transform .2s, box-shadow .2s;
            position: relative;
            overflow: hidden;
            border: 1.5px solid transparent;
        }

        .fp-review-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(5, 150, 105, .10);
            border-color: #d1fae5;
        }

        .fp-review-card::before {
            content: '\201C';
            position: absolute;
            top: -18px;
            right: 18px;
            font-size: 8rem;
            color: rgba(5, 150, 105, .05);
            font-family: Georgia, serif;
            line-height: 1;
            pointer-events: none;
            user-select: none;
        }

        .rv-avatar {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, #059669, #065f46);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(5, 150, 105, .28);
        }

        .rv-name {
            font-size: .9rem;
            font-weight: 700;
            color: #0f172a;
        }

        .rv-stars i.sf {
            color: #f59e0b;
            font-size: .85rem;
        }

        .rv-stars i.se {
            color: #e2e8f0;
            font-size: .85rem;
        }

        .rv-product {
            font-size: .72rem;
            color: #059669;
            font-weight: 600;
            margin-top: 2px;
        }

        .rv-date {
            font-size: .71rem;
            color: #94a3b8;
            background: #f8fafc;
            border-radius: 20px;
            padding: 4px 11px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .rv-text {
            font-size: .86rem;
            color: #475569;
            margin-top: 12px;
            line-height: 1.65;
            font-style: italic;
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px 16px;
            border-left: 3px solid #10b981;
        }

        /* ── ABOUT / SIDEBAR ── */
        .fp-about-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 20px;
        }

        @media(max-width:768px) {
            .fp-about-grid {
                grid-template-columns: 1fr;
            }
        }

        .fp-widget {
            background: #fff;
            border-radius: 18px;
            padding: 22px 24px;
            margin-bottom: 18px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
        }

        .fp-widget-title {
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #059669;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #d1fae5;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Bar rows */
        .fp-perf-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }

        .perf-label {
            font-size: .83rem;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .perf-value {
            font-size: .88rem;
            font-weight: 700;
            color: #0f172a;
        }

        .fp-bar-track {
            height: 8px;
            background: #f1f5f9;
            border-radius: 99px;
            overflow: hidden;
            margin-bottom: 18px;
        }

        .fp-bar-fill {
            height: 100%;
            border-radius: 99px;
            transition: width .8s ease;
        }

        /* Contact rows */
        .contact-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f8fafc;
        }

        .contact-row:last-child {
            border-bottom: none;
        }

        .contact-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #f0fdf4;
            color: #059669;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: .85rem;
        }

        .contact-lbl {
            font-size: .67rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .contact-val {
            font-size: .86rem;
            font-weight: 600;
            color: #0f172a;
        }

        .contact-val.na {
            color: #cbd5e1;
            font-style: italic;
            font-weight: 400;
        }

        /* Trust score widget */
        .fp-trust {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1.5px solid #bbf7d0;
            border-radius: 18px;
            padding: 20px 24px;
            text-align: center;
        }

        .trust-score {
            font-size: 2.8rem;
            font-weight: 800;
            color: #065f46;
            line-height: 1;
        }

        .trust-label {
            font-size: .75rem;
            color: #6b9080;
            margin-top: 4px;
        }

        .trust-meter {
            height: 10px;
            background: #d1fae5;
            border-radius: 99px;
            overflow: hidden;
            margin: 14px 0 8px;
        }

        .trust-fill {
            height: 100%;
            border-radius: 99px;
            background: linear-gradient(90deg, #10b981, #059669);
            transition: width .9s ease;
        }

        /* ── EMPTY STATE ── */
        .fp-empty {
            text-align: center;
            padding: 56px 20px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
        }

        .fp-empty-icon {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: #f0fdf4;
            color: #10b981;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 16px;
        }

        .fp-empty-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .fp-empty-sub {
            font-size: .83rem;
            color: #94a3b8;
        }

        @media(max-width:600px) {
            .fp-profile-card {
                padding: 20px 18px 18px;
                margin: -60px 12px 20px;
            }

            .fp-avatar {
                width: 80px;
                height: 80px;
                font-size: 1.6rem;
                border-radius: 18px;
            }

            .fp-name {
                font-size: 1.25rem;
            }

            .fp-tabs {
                margin: 0 12px 20px;
            }

            .fp-content {
                padding: 0 12px;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="fp-page">

        <!-- ══ HERO ══ -->
        <div class="fp-hero">
            <div class="fp-hero-pattern"></div>
            <div class="fp-hero-emoji"><i class="fas fa-seedling"></i></div>
        </div>

        <!-- ══ PROFILE CARD ══ -->
        <div class="fp-profile-card">
            <div class="d-flex align-items-start flex-wrap" style="gap:20px;">

                <!-- Avatar -->
                <div class="fp-avatar-wrap">
                    <?php if ($fp_avatar_url): ?>
                        <img src="<?php echo htmlspecialchars($fp_avatar_url); ?>" alt="Avatar"
                            style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid #fff;box-shadow:0 4px 14px rgba(17,153,142,.3);">
                    <?php else: ?>
                        <div class="fp-avatar"><?php echo htmlspecialchars($fp_initials); ?></div>
                    <?php endif; ?>
                    <div class="fp-avatar-badge"><i class="fas fa-check"></i></div>
                </div>

                <!-- Info -->
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center flex-wrap" style="gap:8px; margin-bottom:6px;">
                        <span class="fp-name"><?php echo htmlspecialchars($display_name); ?>
                            <?php if ($display_name !== $farmer['username']): ?>
                                <span style="font-size:.65em;color:#94a3b8;font-weight:500;margin-left:6px;">@<?php echo htmlspecialchars($farmer['username']); ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if ($seller_stars > 0): ?>
                            <span class="fp-seller-badge">
                                <i class="fas fa-star"></i>
                                <?php echo htmlspecialchars($seller_label); ?>
                            </span>
                        <?php else: ?>
                            <span class="fp-seller-badge" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-color:#86efac;color:#166534;">
                                <i class="fas fa-leaf" style="color:#22c55e;"></i> New Seller
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="fp-joined">
                        <i class="fas fa-calendar-alt"></i>
                        Member since <?php echo date('F Y', strtotime($farmer['created_at'] ?? date('Y-m-d'))); ?>
                    </div>
                    <?php if (!empty($farmer['location'])): ?>
                        <div class="fp-joined mt-1">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php if (!empty($farmer['latitude']) && !empty($farmer['longitude'])): ?>
                                <a href="#tab-about" onclick="switchTab('about', document.querySelectorAll('.fp-tab-btn')[2]); setTimeout(()=>{ document.getElementById('farmerMapSection').scrollIntoView({behavior:'smooth'}); }, 200); return false;" style="color:inherit;text-decoration:underline dotted;"><?php echo htmlspecialchars($farmer['location']); ?></a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($farmer['location']); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($farmer['bio'])): ?>
                        <p style="font-size:.81rem;color:#64748b;margin:8px 0 0;max-width:480px;line-height:1.55;"><?php echo nl2br(htmlspecialchars($farmer['bio'])); ?></p>
                    <?php endif; ?>

                    <!-- Message Button -->
                    <?php
                    $viewer_id   = $_SESSION['user_id'] ?? null;
                    $viewer_role = $_SESSION['role']    ?? null;
                    $is_own      = $viewer_id && (int)$viewer_id === $farmerId;
                    ?>
                    <?php if (!$is_own): ?>
                        <div style="margin-top:14px;">
                            <?php if ($viewer_id && $viewer_role === 'user'): ?>
                                <a href="<?php echo $base_url; ?>messages_chat.php?user=<?php echo $farmerId; ?>"
                                    class="fp-msg-btn">
                                    <i class="fas fa-paper-plane"></i> Message Farmer
                                </a>
                            <?php elseif (!$viewer_id): ?>
                                <a href="<?php echo $base_url; ?>index.php?auth=login"
                                    class="fp-msg-btn fp-msg-btn--ghost">
                                    <i class="fas fa-comment-dots"></i> Login to Message
                                </a>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top:10px;">
                            <?php if ($viewer_id): ?>
                                <button type="button"
                                    id="fpFollowBtn"
                                    data-farmer-id="<?php echo $farmerId; ?>"
                                    class="fp-follow-btn <?php echo $fp_is_following ? 'is-following' : ''; ?>"
                                    onclick="fpToggleFollow(this)">
                                    <i class="fas <?php echo $fp_is_following ? 'fa-user-check' : 'fa-user-plus'; ?>"></i>
                                    <span id="fpFollowText"><?php echo $fp_is_following ? 'Following Farmer' : 'Follow Farmer'; ?></span>
                                </button>
                                <div class="fp-follow-hint">
                                    <i class="fas fa-heart"></i> <span id="fpFollowCount"><?php echo number_format($fp_follower_count); ?></span> follower<?php echo $fp_follower_count === 1 ? '' : 's'; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($review_count > 0): ?>
                        <div class="d-flex align-items-center" style="gap:7px; margin-top:10px;">
                            <div class="fp-stars">
                                <?php
                                $full  = floor($avg_rating_value);
                                $half  = ($avg_rating_value - $full) >= 0.5 ? 1 : 0;
                                $empty = 5 - $full - $half;
                                for ($i = 0; $i < $full; $i++)  echo '<i class="fas fa-star sf"></i>';
                                if ($half)                echo '<i class="fas fa-star-half-alt sh"></i>';
                                for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star se"></i>';
                                ?>
                            </div>
                            <span style="font-size:.8rem;color:#64748b;">
                                <?php echo number_format($avg_rating_value, 1); ?>
                                &middot;
                                <?php echo (int)$review_count; ?> review<?php echo $review_count != 1 ? 's' : ''; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Stat pills -->
                    <div class="fp-stat-row">
                        <div class="fp-stat-pill">
                            <div class="fp-stat-icon b"><i class="fas fa-list-ul"></i></div>
                            <div>
                                <div class="stat-val"><?php echo (int)$total_listings; ?></div>
                                <div class="stat-lbl">Listings</div>
                            </div>
                        </div>
                        <div class="fp-stat-pill">
                            <div class="fp-stat-icon g"><i class="fas fa-hand-holding-usd"></i></div>
                            <div>
                                <div class="stat-val"><?php echo (int)$sold_count; ?></div>
                                <div class="stat-lbl">Sold</div>
                            </div>
                        </div>
                        <div class="fp-stat-pill">
                            <div class="fp-stat-icon a"><i class="fas fa-chart-line"></i></div>
                            <div>
                                <div class="stat-val"><?php echo $success_rate; ?>%</div>
                                <div class="stat-lbl">Success</div>
                            </div>
                        </div>

                        <div class="fp-rating-pair">
                            <div class="fp-rpill">
                                <div class="rp-lbl">Customer Rating</div>
                                <?php if ($review_count > 0): ?>
                                    <div class="rp-val"><?php echo number_format($avg_rating_value, 1); ?><span style="font-size:.7rem;font-weight:500;color:#94a3b8;">/5</span></div>
                                    <div class="rp-sub"><?php echo (int)$review_count; ?> review<?php echo $review_count != 1 ? 's' : ''; ?></div>
                                <?php else: ?>
                                    <div class="rp-val" style="font-size:.88rem;color:#94a3b8;">No reviews</div>
                                <?php endif; ?>
                            </div>
                            <div class="fp-rpill">
                                <div class="rp-lbl">
                                    Reputation Score
                                    <span title="Automatically calculated based on your sales, buyer ratings, and market engagement." style="cursor:help;font-size:.7rem;">&#x2139;</span>
                                </div>
                                <div class="rp-val"><?php echo number_format($fairness_rating, 1); ?><span style="font-size:.7rem;font-weight:500;color:#94a3b8;">/5</span></div>
                                <div class="rp-sub">Seller reputation</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ TABS ══ -->
        <div class="fp-tabs">
            <button class="fp-tab-btn active" onclick="switchTab('listings',this)">
                <i class="fas fa-store"></i> Listings
                <?php if ($total_listings > 0): ?>
                    <span class="tab-badge"><?php echo (int)$total_listings; ?></span>
                <?php endif; ?>
            </button>
            <button class="fp-tab-btn" onclick="switchTab('reviews',this)">
                <i class="fas fa-star"></i> Reviews
                <?php if ($review_count > 0): ?>
                    <span class="tab-badge"><?php echo (int)$review_count; ?></span>
                <?php endif; ?>
            </button>
            <button class="fp-tab-btn" onclick="switchTab('about',this)">
                <i class="fas fa-info-circle"></i> About
            </button>
        </div>

        <div class="fp-content">

            <!-- ══ LISTINGS TAB ══ -->
            <div id="tab-listings" class="fp-tab-pane active">
                <?php if ($listings->num_rows > 0): ?>
                    <div class="fp-product-grid">
                        <?php while ($p = $listings->fetch_assoc()): ?>
                            <div class="fp-product-card" onclick="window.location='<?php echo $base_url; ?>product_detail.php?id=<?php echo $p['id']; ?>'">
                                <div class="fp-img-wrap">
                                    <?php if (!empty($p['image'])): ?>
                                        <img src="../assets/images/<?php echo htmlspecialchars($p['image']); ?>"
                                            alt="<?php echo htmlspecialchars($p['product_name']); ?>">
                                    <?php else: ?>
                                        <span class="fp-img-placeholder"><i class="fas fa-leaf"></i></span>
                                    <?php endif; ?>
                                    <div class="fp-img-overlay"><span>View Details</span></div>
                                </div>
                                <div class="fp-card-body">
                                    <div class="fp-card-title"><?php echo htmlspecialchars($p['product_name']); ?></div>
                                    <div class="fp-card-desc"><?php echo htmlspecialchars(mb_strimwidth($p['description'], 0, 88, '...')); ?></div>
                                    <div class="fp-price-row">
                                        <div class="fp-price">&#x09F3;<?php echo number_format($p['price'], 2); ?></div>
                                        <div class="fp-price-date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('d M Y', strtotime($p['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="fp-empty">
                        <div class="fp-empty-icon"><i class="fas fa-box-open"></i></div>
                        <div class="fp-empty-title">No listings yet</div>
                        <div class="fp-empty-sub">This farmer hasn't published any products yet.</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ══ REVIEWS TAB ══ -->
            <div id="tab-reviews" class="fp-tab-pane">

                <?php if ($review_count > 0): ?>
                    <!-- Rating Summary -->
                    <div class="fp-review-summary">
                        <div class="rsum-score">
                            <div class="rsum-big"><?php echo number_format($avg_rating_value, 1); ?></div>
                            <div class="rsum-stars">
                                <?php
                                $f = floor($avg_rating_value);
                                $h = ($avg_rating_value - $f) >= .5 ? 1 : 0;
                                $e = 5 - $f - $h;
                                for ($i = 0; $i < $f; $i++) echo '<i class="fas fa-star"></i>';
                                if ($h) echo '<i class="fas fa-star-half-alt"></i>';
                                for ($i = 0; $i < $e; $i++) echo '<i class="far fa-star" style="color:#d1fae5;"></i>';
                                ?>
                            </div>
                            <div class="rsum-count"><?php echo (int)$review_count; ?> review<?php echo $review_count != 1 ? 's' : ''; ?></div>
                        </div>
                        <div class="rsum-bars">
                            <?php foreach ([5, 4, 3, 2, 1] as $s):
                                $cnt = $star_counts[$s] ?? 0;
                                $pct = $review_count > 0 ? round($cnt / $review_count * 100) : 0;
                            ?>
                                <div class="rsum-bar-row">
                                    <span class="rsum-bar-lbl"><?php echo $s; ?></span>
                                    <i class="fas fa-star" style="color:#f59e0b;font-size:.65rem;flex-shrink:0;"></i>
                                    <div class="rsum-bar-track">
                                        <div class="rsum-bar-fill" style="width:<?php echo $pct; ?>%;"></div>
                                    </div>
                                    <span class="rsum-bar-cnt"><?php echo $cnt; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($farmer_reviews && $farmer_reviews->num_rows > 0): ?>
                    <?php while ($r = $farmer_reviews->fetch_assoc()): $rf = (int)$r['rating']; ?>
                        <div class="fp-review-card">
                            <div class="d-flex align-items-start" style="gap:14px;">
                                <div class="rv-avatar"><?php echo strtoupper(substr($r['reviewer_name'], 0, 1)); ?></div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:8px;">
                                        <div>
                                            <div class="rv-name"><?php echo htmlspecialchars($r['reviewer_name']); ?></div>
                                            <div class="rv-stars" style="margin:3px 0;">
                                                <?php
                                                for ($i = 0; $i < $rf; $i++)   echo '<i class="fas fa-star sf"></i>';
                                                for ($i = $rf; $i < 5; $i++)   echo '<i class="far fa-star se"></i>';
                                                ?>
                                            </div>
                                            <div class="rv-product">
                                                <i class="fas fa-seedling" style="margin-right:4px;font-size:.65rem;"></i>
                                                <?php echo htmlspecialchars($r['product_name']); ?>
                                            </div>
                                        </div>
                                        <div class="rv-date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('d M Y', strtotime($r['created_at'])); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($r['review_text'])): ?>
                                        <div class="rv-text">&ldquo;<?php echo htmlspecialchars($r['review_text']); ?>&rdquo;</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="fp-empty">
                        <div class="fp-empty-icon"><i class="fas fa-comment-slash"></i></div>
                        <div class="fp-empty-title">No reviews yet</div>
                        <div class="fp-empty-sub">Be the first to review a product from this farmer.</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ══ ABOUT TAB ══ -->
            <div id="tab-about" class="fp-tab-pane">
                <div class="fp-about-grid">
                    <!-- Left: Performance -->
                    <div>
                        <div class="fp-widget">
                            <div class="fp-widget-title"><i class="fas fa-chart-bar"></i> Performance</div>

                            <div class="fp-perf-row">
                                <div class="perf-label"><i class="fas fa-list-ul" style="color:#2563eb;"></i> Total Listings</div>
                                <div class="perf-value"><?php echo (int)$total_listings; ?></div>
                            </div>
                            <div class="fp-perf-row">
                                <div class="perf-label"><i class="fas fa-hand-holding-usd" style="color:#059669;"></i> Products Sold</div>
                                <div class="perf-value"><?php echo (int)$sold_count; ?></div>
                            </div>

                            <div style="margin-bottom:6px;display:flex;justify-content:space-between;font-size:.78rem;color:#64748b;">
                                <span>Success Rate</span><span style="font-weight:700;color:#059669;"><?php echo $success_rate; ?>%</span>
                            </div>
                            <div class="fp-bar-track">
                                <div class="fp-bar-fill" style="width:<?php echo $success_rate; ?>%;background:linear-gradient(90deg,#10b981,#059669);"></div>
                            </div>

                            <div style="margin-bottom:6px;display:flex;justify-content:space-between;font-size:.78rem;color:#64748b;">
                                <span>Customer Rating</span>
                                <span style="font-weight:700;color:#f59e0b;"><?php echo $review_count > 0 ? number_format($avg_rating_value, 1) . '/5' : 'N/A'; ?></span>
                            </div>
                            <div class="fp-bar-track">
                                <div class="fp-bar-fill" style="width:<?php echo $review_count > 0 ? ($avg_rating_value / 5 * 100) : 0; ?>%;background:linear-gradient(90deg,#fbbf24,#f97316);"></div>
                            </div>

                            <div style="margin-bottom:6px;display:flex;justify-content:space-between;font-size:.78rem;color:#64748b;">
                                <span>Reputation Score</span>
                                <span style="font-weight:700;color:#059669;"><?php echo number_format($fairness_rating, 1); ?>/5</span>
                            </div>
                            <div class="fp-bar-track" style="margin-bottom:0;">
                                <div class="fp-bar-fill" style="width:<?php echo $fairness_rating / 5 * 100; ?>%;background:linear-gradient(90deg,#10b981,#059669);"></div>
                            </div>
                        </div>

                        <!-- Trust score -->
                        <div class="fp-trust">
                            <div class="trust-label" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#6b9080;margin-bottom:4px;">Overall Trust Score</div>
                            <?php
                            $trust = round(($success_rate * 0.4) + ($avg_rating_value / 5 * 100 * 0.4) + ($fairness_rating / 5 * 100 * 0.2));
                            $trust = max(0, min(100, $trust));
                            ?>
                            <div class="trust-score"><?php echo $trust; ?><span style="font-size:1.2rem;font-weight:500;color:#6b9080;">/100</span></div>
                            <div class="trust-meter">
                                <div class="trust-fill" style="width:<?php echo $trust; ?>%;"></div>
                            </div>
                            <div style="font-size:.75rem;color:#6b9080;">Based on sales, ratings &amp; fairness</div>
                        </div>
                    </div>

                    <!-- Right: Contact -->
                    <div>
                        <div class="fp-widget">
                            <div class="fp-widget-title"><i class="fas fa-address-card"></i> Farmer Info</div>
                            <div class="contact-row">
                                <div class="contact-icon"><i class="fas fa-user"></i></div>
                                <div>
                                    <div class="contact-lbl">Name</div>
                                    <div class="contact-val"><?php echo htmlspecialchars($farmer['username']); ?></div>
                                </div>
                            </div>
                            <div class="contact-row">
                                <div class="contact-icon"><i class="fas fa-calendar-check"></i></div>
                                <div>
                                    <div class="contact-lbl">Member Since</div>
                                    <div class="contact-val"><?php echo date('d M Y', strtotime($farmer['created_at'] ?? date('Y-m-d'))); ?></div>
                                </div>
                            </div>
                            <div class="contact-row">
                                <div class="contact-icon"><i class="fas fa-store"></i></div>
                                <div>
                                    <div class="contact-lbl">Active Listings</div>
                                    <div class="contact-val"><?php echo (int)$total_listings; ?> product<?php echo $total_listings != 1 ? 's' : ''; ?></div>
                                </div>
                            </div>
                            <div class="contact-row">
                                <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div>
                                    <div class="contact-lbl">Location</div>
                                    <?php if (!empty($farmer['location'])): ?>
                                        <div class="contact-val">
                                            <?php if (!empty($farmer['latitude']) && !empty($farmer['longitude'])): ?>
                                                <a href="#farmerMapSection" onclick="document.getElementById('farmerMapSection').scrollIntoView({behavior:'smooth'}); return false;" style="color:#059669;font-weight:600;text-decoration:none;">
                                                    <i class="fas fa-map-pin" style="font-size:.75rem;"></i>
                                                    <?php echo htmlspecialchars($farmer['location']); ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($farmer['location']); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="contact-val na">Not provided</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Badges widget -->
                        <div class="fp-widget">
                            <div class="fp-widget-title"><i class="fas fa-award"></i> Achievements</div>
                            <?php if ($total_listings >= 1): ?>
                                <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f8fafc;">
                                    <span style="width:34px;height:34px;border-radius:9px;background:#dbeafe;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;"><i class="fas fa-seedling"></i></span>
                                    <div>
                                        <div style="font-size:.85rem;font-weight:600;color:#0f172a;">First Listing</div>
                                        <div style="font-size:.72rem;color:#94a3b8;">Posted their first product</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($sold_count >= 1): ?>
                                <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f8fafc;">
                                    <span style="width:34px;height:34px;border-radius:9px;background:#d1fae5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;"><i class="fas fa-handshake"></i></span>
                                    <div>
                                        <div style="font-size:.85rem;font-weight:600;color:#0f172a;">First Sale</div>
                                        <div style="font-size:.72rem;color:#94a3b8;">Completed their first transaction</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($review_count >= 5 && $avg_rating_value >= 4.0): ?>
                                <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f8fafc;">
                                    <span style="width:34px;height:34px;border-radius:9px;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;"><i class="fas fa-star"></i></span>
                                    <div>
                                        <div style="font-size:.85rem;font-weight:600;color:#0f172a;">Top Rated</div>
                                        <div style="font-size:.72rem;color:#94a3b8;"><?php echo number_format($avg_rating_value, 1); ?>+ rating from <?php echo (int)$review_count; ?> reviews</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($total_listings < 1 && $sold_count < 1): ?>
                                <div style="font-size:.83rem;color:#cbd5e1;text-align:center;padding:12px 0;">No achievements yet</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Farm Location Map (full-width, only when lat/lng set) -->
                    <?php if (!empty($farmer['latitude']) && !empty($farmer['longitude'])): ?>
                        <div class="farmer-map-card" id="farmerMapSection">
                            <div class="farmer-map-head">
                                <div class="farmer-map-icon"><i class="fas fa-map-marked-alt"></i></div>
                                <div>
                                    <div class="farmer-map-title">Farm Location</div>
                                    <div class="farmer-map-subtitle">
                                        <?php echo htmlspecialchars($farmer['location'] ?? 'Pinned location'); ?>
                                    </div>
                                </div>
                            </div>
                            <div id="farmerLeafletMap"></div>
                            <div class="farmer-map-footer">
                                <div class="map-loc-text">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($farmer['location'] ?? ''); ?>
                                </div>
                                <a class="map-osm-link"
                                    href="https://www.openstreetmap.org/?mlat=<?php echo (float)$farmer['latitude']; ?>&mlon=<?php echo (float)$farmer['longitude']; ?>&zoom=14"
                                    target="_blank" rel="noopener">
                                    <i class="fas fa-external-link-alt"></i> Open in OpenStreetMap
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="farmer-map-card" id="farmerMapSection">
                            <div class="farmer-map-no-location">
                                <div><i class="fas fa-map-marked-alt"></i></div>
                                <p>This farmer hasn't pinned their location yet.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /fp-content -->
    </div><!-- /fp-page -->

    <!-- Leaflet map for farm location -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .farmer-map-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .08);
            border: 1.5px solid #e2e8f0;
            overflow: hidden;
            grid-column: 1 / -1;
            margin-top: 4px;
        }

        .farmer-map-head {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 22px 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .farmer-map-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            background: linear-gradient(135deg, #059669, #065f46);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .farmer-map-title {
            font-size: .95rem;
            font-weight: 700;
            color: #0f172a;
        }

        .farmer-map-subtitle {
            font-size: .75rem;
            color: #94a3b8;
            margin-top: 1px;
        }

        #farmerLeafletMap {
            height: 380px;
            width: 100%;
        }

        .farmer-map-footer {
            padding: 12px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            background: #f8fafc;
        }

        .farmer-map-footer .map-loc-text {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .82rem;
            color: #475569;
            font-weight: 500;
        }

        .farmer-map-footer .map-loc-text i {
            color: #059669;
        }

        .map-osm-link {
            font-size: .75rem;
            color: #94a3b8;
            text-decoration: none;
        }

        .map-osm-link:hover {
            color: #059669;
        }

        .farmer-map-no-location {
            padding: 40px 24px;
            text-align: center;
            color: #94a3b8;
        }

        .farmer-map-no-location i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            color: #cbd5e1;
        }

        .farmer-map-no-location p {
            font-size: .85rem;
            margin: 0;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function switchTab(name, btn) {
            document.querySelectorAll('.fp-tab-pane').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.fp-tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + name).classList.add('active');
            btn.classList.add('active');
            // Invalidate map size when About tab shown
            if (name === 'about' && window._farmerMap) {
                setTimeout(() => window._farmerMap.invalidateSize(), 100);
            }
        }

        function fpToggleFollow(btn) {
            const farmerId = btn.dataset.farmerId;
            fetch('<?php echo $base_url; ?>follow_farmer_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'farmer_id=' + encodeURIComponent(farmerId)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.login_required) {
                        window.location.href = '<?php echo $base_url; ?>index.php?auth=login';
                        return;
                    }
                    if (!data.success) return;

                    const label = document.getElementById('fpFollowText');
                    const count = document.getElementById('fpFollowCount');
                    if (data.following) {
                        btn.classList.add('is-following');
                        btn.querySelector('i').className = 'fas fa-user-check';
                        label.textContent = 'Following Farmer';
                    } else {
                        btn.classList.remove('is-following');
                        btn.querySelector('i').className = 'fas fa-user-plus';
                        label.textContent = 'Follow Farmer';
                    }
                    if (count) {
                        count.textContent = Number(data.followers || 0).toLocaleString();
                    }
                });
        }

        // ── Farm Location Map (read-only view) ──
        (function() {
            const lat = <?php echo !empty($farmer['latitude'])  ? (float)$farmer['latitude']  : 'null'; ?>;
            const lng = <?php echo !empty($farmer['longitude']) ? (float)$farmer['longitude'] : 'null'; ?>;

            if (!lat || !lng) return; // No coordinates — map section hidden

            const map = L.map('farmerLeafletMap', {
                scrollWheelZoom: false
            }).setView([lat, lng], 13);
            window._farmerMap = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            const farmName = <?php echo json_encode(!empty($farmer['farm_name']) ? $farmer['farm_name'] : $farmer['username']); ?>;
            const locText = <?php echo json_encode($farmer['location'] ?? ''); ?>;

            const greenIcon = L.divIcon({
                className: '',
                html: '<div style="width:44px;height:44px;background:linear-gradient(135deg,#059669,#065f46);border:4px solid #fff;border-radius:50% 50% 50% 0;transform:rotate(-45deg);box-shadow:0 6px 18px rgba(5,150,105,.5);"><span style="display:block;width:12px;height:12px;background:#fff;border-radius:50%;margin:12px auto;"></span></div>',
                iconSize: [44, 44],
                iconAnchor: [22, 44],
                popupAnchor: [0, -46]
            });

            const marker = L.marker([lat, lng], {
                icon: greenIcon
            }).addTo(map);
            marker.bindPopup(
                '<div style="font-family:Inter,sans-serif;min-width:160px;">' +
                '<div style="font-weight:800;font-size:.95rem;color:#065f46;margin-bottom:4px;">🌾 ' + farmName + '</div>' +
                (locText ? '<div style="font-size:.8rem;color:#475569;"><i class="fas fa-map-marker-alt" style="color:#059669;"></i> ' + locText + '</div>' : '') +
                '</div>'
            ).openPopup();

            // Enable scroll wheel zoom on click
            map.on('click', () => map.scrollWheelZoom.enable());
            map.on('mouseout', () => map.scrollWheelZoom.disable());
        })();
    </script>

    <?php include '../includes/footer.php'; ?>
</body>

</html>