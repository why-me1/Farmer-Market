<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/notification_functions.php';
require_once '../includes/ratings.php';
check_login();

if ($_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];
$focus_post_id = isset($_GET['focus_post']) ? (int)$_GET['focus_post'] : 0;

// Keep order statuses in sync: if a winner is approved but post is still active,
// mark it as sold so it appears in manage orders and delivery workflow.
$sync_stmt = $conn->prepare("UPDATE posts p
                                                        JOIN comments c ON p.id = c.post_id
                                                        SET p.status = 'sold'
                                                        WHERE p.farmer_id = ?
                                                            AND c.is_approved = 1
                                                            AND p.status NOT IN ('sold', 'delivered')");
$sync_stmt->bind_param("i", $farmer_id);
$sync_stmt->execute();
$sync_stmt->close();

// No more direct POST delivery update — handled by delivery_handler.php via AJAX

// Handle farmer rating a buyer after delivery
if (isset($_POST['rate_buyer'])) {
    $product_id = intval($_POST['product_id']);
    $buyer_id   = intval($_POST['buyer_id']);
    $rating     = max(1, min(5, intval($_POST['rating'])));

    // Verify this post belongs to this farmer and is delivered
    $stmt = $conn->prepare("SELECT id FROM posts WHERE id = ? AND farmer_id = ? AND status = 'delivered'");
    $stmt->bind_param("ii", $product_id, $farmer_id);
    $stmt->execute();
    $valid = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($valid && $buyer_id && $rating) {
        add_farmer_buyer_rating($farmer_id, $buyer_id, $product_id, $rating);
    }
    header("Location: manage_orders.php");
    exit();
}

// Fetch sold products for this farmer (including delivered ones)
$stmt = $conn->prepare("SELECT p.*, c.user_id, u.username FROM posts p 
                       JOIN comments c ON p.id = c.post_id 
                       JOIN users u ON c.user_id = u.id
                       WHERE p.farmer_id = ? AND p.status IN ('sold', 'delivered') AND c.is_approved = 1
                       ORDER BY p.created_at DESC");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Pre-fetch all buyer ratings given by this farmer (post_id => rating)
$rated_stmt = $conn->prepare("SELECT post_id, rating FROM buyer_ratings WHERE farmer_id = ?");
$rated_stmt->bind_param("i", $farmer_id);
$rated_stmt->execute();
$rated_rows = $rated_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rated_stmt->close();
$already_rated = [];
foreach ($rated_rows as $rr) {
    $already_rated[(int)$rr['post_id']] = (int)$rr['rating'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders – Farmers' Marketplace</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6fb;
        }

        /* ── Page Hero ── */
        .page-hero {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border-radius: 16px;
            padding: 32px 36px;
            color: white;
            margin-bottom: 24px;
            box-shadow: 0 6px 24px rgba(17, 153, 142, .28);
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .page-hero-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, .2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .page-hero .hero-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            opacity: .8;
            margin-bottom: 4px;
        }

        .page-hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 22px;
            font-weight: 700;
            margin: 0;
        }

        .page-hero p {
            font-size: 13px;
            opacity: .85;
            margin: 4px 0 0;
        }

        /* ── Stats Strip ── */
        .mo-stats-strip {
            display: flex;
            gap: 14px;
            margin-bottom: 22px;
            flex-wrap: wrap;
            padding: 20px;
            background: linear-gradient(135deg, #065f46 0%, #059669 100%);
            border-radius: 16px;
            box-shadow: 0 4px 18px rgba(5, 100, 70, .25);
        }

        .mo-stat-chip {
            background: white;
            border: 1px solid #ebebeb;
            border-radius: 12px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
            flex: 1;
            min-width: 130px;
        }

        .mo-stat-chip-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
        }

        .mo-stat-chip-icon.teal {
            background: #e8f8ee;
            color: #11998e;
        }

        .mo-stat-chip-icon.yellow {
            background: #fff8e1;
            color: #e6a817;
        }

        .mo-stat-chip-val {
            font-family: 'Poppins', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
            line-height: 1;
        }

        .mo-stat-chip-label {
            font-size: 11px;
            color: #999;
            font-weight: 500;
            margin-top: 2px;
        }

        /* ── Orders Panel ── */
        .orders-panel {
            background: white;
            border-radius: 16px;
            border: 1px solid #ebebeb;
            overflow: hidden;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .07);
        }

        .panel-header {
            padding: 18px 24px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-header-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .panel-header h5 {
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
        }

        /* ── Order Row ── */
        .order-row {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 18px 24px;
            border-bottom: 1px solid #f5f5f5;
            transition: background .15s;
        }

        .order-row:last-child {
            border-bottom: none;
        }

        .order-row:hover {
            background: #fafffe;
        }

        .order-row-focus {
            background: #effaf5;
            border: 2px solid #38ef7d;
            box-shadow: 0 0 0 4px rgba(56, 239, 125, .12);
            border-radius: 14px;
        }

        .order-thumb {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
            border: 1px solid #ebebeb;
        }

        .order-thumb-placeholder {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            background: linear-gradient(135deg, #e8f8ee, #d4f7e0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #a8dcc0;
            flex-shrink: 0;
        }

        .order-info {
            flex: 1;
            min-width: 0;
        }

        .order-name {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .order-meta {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .order-meta-item {
            font-size: 12px;
            color: #999;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .order-meta-item i {
            color: #11998e;
            width: 12px;
            text-align: center;
        }

        .order-meta-item strong {
            color: #555;
        }

        .order-price {
            text-align: right;
            flex-shrink: 0;
            min-width: 90px;
        }

        .order-price-val {
            font-family: 'Poppins', sans-serif;
            font-size: 17px;
            font-weight: 700;
            color: #11998e;
        }

        .order-price-label {
            font-size: 11px;
            color: #bbb;
            margin-bottom: 2px;
        }

        /* ── Unified right-side column ── */
        .order-right {
            flex-shrink: 0;
            width: 220px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
            width: 100%;
            box-sizing: border-box;
        }

        .status-pill.sold {
            background: #fff8e1;
            color: #b87a00;
        }

        .status-pill.delivered {
            background: #e8f8ee;
            color: #0d6b5e;
        }

        .btn-deliver {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            border: none;
            border-radius: 9px;
            padding: 10px 0;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 3px 10px rgba(17, 153, 142, .3);
            width: 100%;
        }

        .btn-deliver:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(17, 153, 142, .42);
        }

        /* ── Delivery Type Selector ── */
        .delivery-type-card {
            background: #f8fffc;
            border: 1.5px solid #b2e8d6;
            border-radius: 12px;
            padding: 12px;
            margin-top: 4px;
        }

        .delivery-type-label {
            font-size: 10px;
            font-weight: 700;
            color: #0d6b5e;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .delivery-type-btns {
            display: flex;
            gap: 6px;
        }

        .btn-dtype {
            flex: 1;
            padding: 8px 4px;
            border: 1.5px solid #c9e8df;
            border-radius: 8px;
            background: white;
            font-size: 11px;
            font-weight: 700;
            color: #555;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
        }

        .btn-dtype i {
            font-size: 16px;
        }

        .btn-dtype:hover,
        .btn-dtype.active {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            border-color: transparent;
            box-shadow: 0 3px 10px rgba(17, 153, 142, .28);
        }

        /* OTP Panel */
        .otp-panel {
            background: #fffbeb;
            border: 1.5px solid #fde68a;
            border-radius: 10px;
            padding: 11px 12px;
            margin-top: 6px;
            display: none;
        }

        .otp-display {
            font-family: 'Poppins', monospace;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 6px;
            color: #b45309;
            text-align: center;
            background: white;
            border: 2px dashed #fbbf24;
            border-radius: 8px;
            padding: 8px 4px;
            margin: 8px 0;
        }

        .otp-hint {
            font-size: 10px;
            color: #92400e;
            text-align: center;
            margin-bottom: 8px;
        }

        .otp-input-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 6px;
        }

        .otp-input {
            width: 100%;
            border: 2px solid #fbbf24;
            border-radius: 8px;
            padding: 9px 10px;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 6px;
            text-align: center;
            outline: none;
            color: #1a1a2e;
            box-sizing: border-box;
            background: white;
        }

        .otp-input:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, .15);
        }

        .btn-verify-otp {
            width: 100%;
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 0;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 3px 10px rgba(245, 158, 11, .3);
            transition: opacity .15s, transform .1s;
        }

        .btn-verify-otp:hover {
            opacity: .88;
            transform: translateY(-1px);
        }

        /* Courier Panel */
        .courier-panel {
            background: #eff6ff;
            border: 1.5px solid #bfdbfe;
            border-radius: 10px;
            padding: 10px 12px;
            margin-top: 8px;
            display: none;
        }

        .courier-panel label {
            font-size: 10px;
            font-weight: 700;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: .06em;
            display: block;
            margin-bottom: 3px;
        }

        .courier-panel input {
            width: 100%;
            border: 1.5px solid #bfdbfe;
            border-radius: 7px;
            padding: 7px 9px;
            font-size: 12px;
            color: #1a1a2e;
            outline: none;
            margin-bottom: 6px;
        }

        .courier-panel input:focus {
            border-color: #3b82f6;
        }

        .btn-save-courier {
            width: 100%;
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 9px 0;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: opacity .15s, transform .1s;
        }

        .btn-save-courier:hover {
            opacity: .88;
            transform: translateY(-1px);
        }

        /* Alert messages */
        .delivery-msg {
            font-size: 11px;
            padding: 7px 10px;
            border-radius: 7px;
            margin-top: 6px;
            display: none;
            font-weight: 600;
        }

        .delivery-msg.success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .delivery-msg.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        /* Courier info display */
        .courier-info-card {
            background: #eff6ff;
            border: 1.5px solid #bfdbfe;
            border-radius: 10px;
            padding: 10px 12px;
            margin-top: 4px;
        }

        .courier-info-label {
            font-size: 10px;
            font-weight: 700;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .courier-info-val {
            font-size: 12px;
            font-weight: 700;
            color: #1a1a2e;
        }

        .courier-info-sub {
            font-size: 10px;
            color: #64748b;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 64px 30px;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e8f8ee;
            color: #11998e;
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
        }

        /* ── Star Rating Widget ── */
        .rating-card {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 10px 12px;
            margin-top: 8px;
            min-width: 150px;
        }

        .star-label {
            font-size: 10px;
            color: #92400e;
            font-weight: 700;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .08em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stars {
            display: flex;
            flex-direction: row-reverse;
            gap: 1px;
            justify-content: flex-end;
            margin-bottom: 8px;
        }

        .stars input {
            display: none;
        }

        .stars label {
            font-size: 28px;
            color: #d4d4d4;
            cursor: pointer;
            transition: color .1s, transform .1s;
            line-height: 1;
        }

        .stars label:hover {
            transform: scale(1.15);
        }

        .stars input:checked~label,
        .stars label:hover,
        .stars label:hover~label {
            color: #f59e0b;
        }

        .btn-rate {
            width: 100%;
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 7px 0;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            box-shadow: 0 2px 8px rgba(245, 158, 11, .35);
            transition: opacity .15s, transform .1s;
        }

        .btn-rate:hover {
            opacity: .88;
            transform: translateY(-1px);
        }

        .rated-card {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 8px 12px;
            margin-top: 8px;
            text-align: center;
        }

        .rated-stars {
            color: #f59e0b;
            font-size: 18px;
            letter-spacing: 2px;
            display: block;
        }

        .rated-label {
            font-size: 10px;
            color: #047857;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-top: 2px;
            display: block;
        }

        /* OTP Sent Notice (farmer side — no code shown) */
        .otp-sent-notice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #ecfdf5;
            border: 1.5px solid #a7f3d0;
            border-radius: 9px;
            padding: 9px 11px;
            margin-bottom: 8px;
            margin-top: 6px;
        }

        .otp-sent-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        /* Courier Tip */
        .courier-tip {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 7px;
            padding: 7px 10px;
            font-size: 10px;
            color: #1e40af;
            line-height: 1.5;
            margin-bottom: 8px;
            display: flex;
            gap: 6px;
            align-items: flex-start;
        }

        .courier-tip i {
            margin-top: 1px;
            flex-shrink: 0;
        }

        @media (max-width: 768px) {
            .order-row {
                flex-wrap: wrap;
                gap: 12px;
            }

            .order-price,
            .order-right {
                min-width: unset;
                text-align: left;
                width: 100%;
            }

            .page-hero {
                padding: 24px 18px;
            }

            .mo-stats-strip {
                gap: 10px;
                padding: 16px;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="main-container">
        <div class="container py-4" style="max-width: 1100px;">

            <?php
            // Count stats
            $total_orders    = $result->num_rows;
            $delivered_count = 0;
            $pending_count   = 0;
            $orders = [];
            while ($row = $result->fetch_assoc()) {
                $orders[] = $row;
                if ($row['status'] === 'delivered') $delivered_count++;
                else $pending_count++;
            }
            ?>

            <!-- Page Hero -->
            <div class="page-hero">
                <div class="page-hero-icon"><i class="fas fa-truck"></i></div>
                <div>
                    <div class="hero-label"><i class="fas fa-tractor mr-1"></i> Farmer Dashboard</div>
                    <h1>Manage Orders</h1>
                    <p>Track and update delivery status for your completed sales.</p>
                </div>
            </div>

            <!-- Stats Strip -->
            <div class="mo-stats-strip">
                <div class="mo-stat-chip">
                    <div class="mo-stat-chip-icon teal"><i class="fas fa-box"></i></div>
                    <div>
                        <div class="mo-stat-chip-val"><?php echo $total_orders; ?></div>
                        <div class="mo-stat-chip-label">Total Orders</div>
                    </div>
                </div>
                <div class="mo-stat-chip">
                    <div class="mo-stat-chip-icon yellow"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="mo-stat-chip-val"><?php echo $pending_count; ?></div>
                        <div class="mo-stat-chip-label">Pending Delivery</div>
                    </div>
                </div>
                <div class="mo-stat-chip">
                    <div class="mo-stat-chip-icon teal"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="mo-stat-chip-val"><?php echo $delivered_count; ?></div>
                        <div class="mo-stat-chip-label">Delivered</div>
                    </div>
                </div>
            </div>

            <!-- Orders Panel -->
            <div class="orders-panel">
                <div class="panel-header">
                    <div class="panel-header-icon"><i class="fas fa-truck"></i></div>
                    <h5>All Orders</h5>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-box-open"></i></div>
                        <h5>No orders yet</h5>
                        <p>Once a product is sold, it will appear here for delivery management.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="order-row <?php echo $focus_post_id === (int)$order['id'] ? 'order-row-focus' : ''; ?>" id="order-row-<?php echo (int)$order['id']; ?>">

                            <?php if (!empty($order['image'])): ?>
                                <img src="<?php echo $base_url; ?>assets/images/<?php echo htmlspecialchars($order['image']); ?>"
                                    class="order-thumb"
                                    alt="<?php echo htmlspecialchars($order['product_name']); ?>">
                            <?php else: ?>
                                <div class="order-thumb-placeholder"><i class="fas fa-seedling"></i></div>
                            <?php endif; ?>

                            <div class="order-info">
                                <div class="order-name"><?php echo htmlspecialchars($order['product_name']); ?></div>
                                <div class="order-meta">
                                    <div class="order-meta-item">
                                        <i class="fas fa-user"></i>
                                        <strong><?php echo htmlspecialchars($order['username']); ?></strong>
                                    </div>
                                    <div class="order-meta-item">
                                        <i class="fas fa-layer-group"></i>
                                        <?php echo htmlspecialchars($order['category'] ?? '—'); ?>
                                    </div>
                                    <div class="order-meta-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="order-price">
                                <div class="order-price-label">Price</div>
                                <div class="order-price-val">৳<?php echo number_format($order['price'], 2); ?></div>
                            </div>

                            <div class="order-right">
                                <?php
                                // Fetch delivery OTP record for this order
                                $otp_stmt = $conn->prepare("SELECT otp_code, is_used, expires_at FROM delivery_otps WHERE post_id = ? AND farmer_id = ? LIMIT 1");
                                $otp_stmt->bind_param("ii", $order['id'], $farmer_id);
                                $otp_stmt->execute();
                                $otp_record = $otp_stmt->get_result()->fetch_assoc();
                                $otp_stmt->close();
                                ?>
                                <?php if ($order['status'] !== 'delivered'): ?>
                                    <span class="status-pill sold">
                                        <i class="fas fa-clock" style="font-size:9px;"></i> Pending
                                    </span>

                                    <?php if (!empty($order['delivery_type']) && $order['delivery_type'] === 'local' && $otp_record && !$otp_record['is_used']): ?>
                                        <!-- OTP generated — farmer DOES NOT see it. Buyer sees it on their dashboard. -->
                                        <div class="otp-panel" style="display:block;" id="otpPanel_<?php echo $order['id']; ?>">
                                            <div class="otp-sent-notice">
                                                <div class="otp-sent-icon"><i class="fas fa-mobile-alt"></i></div>
                                                <div>
                                                    <div style="font-size:11px;font-weight:700;color:#065f46;">OTP sent to buyer!</div>
                                                    <div style="font-size:10px;color:#6b7280;">Buyer sees their OTP on their dashboard.<br>Ask them to tell you the code at delivery.</div>
                                                </div>
                                            </div>
                                            <div class="otp-hint" style="margin-top:8px;"><strong>Ask buyer for their OTP &amp; enter below:</strong></div>
                                            <div class="otp-input-row">
                                                <input type="text" class="otp-input" id="otpVerifyInput_<?php echo $order['id']; ?>"
                                                    maxlength="6" placeholder="______" autocomplete="off">
                                                <button class="btn-verify-otp" onclick="verifyOTP(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-check"></i> Confirm
                                                </button>
                                            </div>
                                            <div class="delivery-msg" id="otpMsg_<?php echo $order['id']; ?>"></div>
                                            <div class="otp-hint" style="margin-top:6px;color:#9ca3af;">Expires: <?php echo date('M j, g:i A', strtotime($otp_record['expires_at'])); ?></div>
                                        </div>

                                    <?php elseif (!empty($order['delivery_type']) && $order['delivery_type'] === 'courier'): ?>
                                        <!-- Courier info saved, waiting for delivery -->
                                        <div class="courier-info-card">
                                            <div class="courier-info-label"><i class="fas fa-shipping-fast"></i> Courier Dispatched</div>
                                            <div class="courier-info-val"><?php echo htmlspecialchars($order['courier_company'] ?? ''); ?></div>
                                            <div class="courier-info-sub">Tracking: <?php echo htmlspecialchars($order['courier_tracking'] ?? ''); ?></div>
                                        </div>

                                    <?php else: ?>
                                        <!-- Fresh order — show delivery type chooser -->
                                        <div class="delivery-type-card" id="deliveryCard_<?php echo $order['id']; ?>">
                                            <div class="delivery-type-label"><i class="fas fa-truck"></i> How are you delivering?</div>
                                            <div class="delivery-type-btns">
                                                <button class="btn-dtype" onclick="showDeliveryPanel(<?php echo $order['id']; ?>, 'local')">
                                                    <i class="fas fa-person-walking"></i>
                                                    Local
                                                </button>
                                                <button class="btn-dtype" onclick="showDeliveryPanel(<?php echo $order['id']; ?>, 'courier')">
                                                    <i class="fas fa-shipping-fast"></i>
                                                    Courier
                                                </button>
                                            </div>

                                            <!-- LOCAL: OTP panel -->
                                            <div class="otp-panel" id="otpPanel_<?php echo $order['id']; ?>">
                                                <div class="otp-hint"><i class="fas fa-mobile-alt"></i> A secret OTP will be sent to the buyer's dashboard. At delivery, ask them to read it to you.</div>
                                                <button class="btn-deliver" onclick="initiateLocal(<?php echo $order['id']; ?>)" id="otpGenerateBtn_<?php echo $order['id']; ?>">
                                                    <i class="fas fa-lock"></i> Generate OTP &amp; Initiate
                                                </button>
                                                <!-- After generation: only show verify row, NOT the OTP code -->
                                                <div id="otpSentNotice_<?php echo $order['id']; ?>" style="display:none;">
                                                    <div class="otp-sent-notice">
                                                        <div class="otp-sent-icon"><i class="fas fa-mobile-alt"></i></div>
                                                        <div>
                                                            <div style="font-size:11px;font-weight:700;color:#065f46;">OTP sent to buyer!</div>
                                                            <div style="font-size:10px;color:#6b7280;">Ask buyer to tell you their OTP code.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="otp-hint" style="margin-top:6px;display:none;" id="otpVerifyHint_<?php echo $order['id']; ?>">
                                                    <strong>Enter buyer's OTP to confirm delivery:</strong>
                                                </div>
                                                <div class="otp-input-row" id="otpVerifyRow_<?php echo $order['id']; ?>" style="display:none;">
                                                    <input type="text" class="otp-input" id="otpVerifyInput_<?php echo $order['id']; ?>"
                                                        maxlength="6" placeholder="______" autocomplete="off">
                                                    <button class="btn-verify-otp" onclick="verifyOTP(<?php echo $order['id']; ?>)">
                                                        <i class="fas fa-check"></i> Confirm
                                                    </button>
                                                </div>
                                                <div class="delivery-msg" id="otpMsg_<?php echo $order['id']; ?>"></div>
                                            </div>

                                            <!-- COURIER: tracking panel -->
                                            <div class="courier-panel" id="courierPanel_<?php echo $order['id']; ?>">
                                                <div class="courier-tip"><i class="fas fa-info-circle"></i> Drop your product at the courier office. They will give you a waybill/receipt containing the tracking number. Enter it below.</div>
                                                <label>Courier Company</label>
                                                <input type="text" id="courierCompany_<?php echo $order['id']; ?>" placeholder="e.g. Sundarban, Pathao, SA Paribahan">
                                                <label>Tracking No. (from waybill)</label>
                                                <input type="text" id="courierTracking_<?php echo $order['id']; ?>" placeholder="e.g. SB-2024-XXXXX">
                                                <button class="btn-save-courier" onclick="saveCourier(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-paper-plane"></i> Save &amp; Notify Buyer
                                                </button>
                                                <div class="delivery-msg" id="courierMsg_<?php echo $order['id']; ?>"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="status-pill delivered">
                                        <i class="fas fa-check" style="font-size:9px;"></i> Delivered
                                    </span>
                                    <?php if (isset($already_rated[$order['id']])): ?>
                                        <?php $given = $already_rated[$order['id']]; ?>
                                        <div class="rated-card">
                                            <span class="rated-stars"><?php echo str_repeat('★', $given); ?><span style="color:#d4d4d4;"><?php echo str_repeat('★', 5 - $given); ?></span></span>
                                            <span class="rated-label"><i class="fas fa-check" style="margin-right:3px;"></i>Buyer rated</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="rating-card">
                                            <form method="POST" class="star-rating-form">
                                                <input type="hidden" name="product_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="buyer_id" value="<?php echo $order['user_id']; ?>">
                                                <span class="star-label"><i class="fas fa-star"></i> Rate this buyer</span>
                                                <div class="stars">
                                                    <?php for ($s = 5; $s >= 1; $s--): ?>
                                                        <input type="radio"
                                                            id="star<?php echo $s . '_' . $order['id']; ?>"
                                                            name="rating"
                                                            value="<?php echo $s; ?>">
                                                        <label for="star<?php echo $s . '_' . $order['id']; ?>" title="<?php echo $s; ?> stars">&#9733;</label>
                                                    <?php endfor; ?>
                                                </div>
                                                <button type="submit" name="rate_buyer" class="btn-rate">
                                                    <i class="fas fa-star"></i> Submit
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        const HANDLER = '../delivery_handler.php';
        const FOCUS_POST_ID = <?php echo (int)$focus_post_id; ?>;

        document.addEventListener('DOMContentLoaded', function() {
            if (!FOCUS_POST_ID) return;
            const row = document.getElementById('order-row-' + FOCUS_POST_ID);
            if (row) {
                row.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });

        function showDeliveryPanel(postId, type) {
            const otpPanel = document.getElementById('otpPanel_' + postId);
            const courierPanel = document.getElementById('courierPanel_' + postId);
            const btns = document.querySelectorAll('#deliveryCard_' + postId + ' .btn-dtype');

            // Reset active states
            btns.forEach(b => b.classList.remove('active'));

            if (type === 'local') {
                btns[0].classList.add('active');
                if (otpPanel) {
                    otpPanel.style.display = 'block';
                }
                if (courierPanel) {
                    courierPanel.style.display = 'none';
                }
            } else {
                btns[1].classList.add('active');
                if (otpPanel) {
                    otpPanel.style.display = 'none';
                }
                if (courierPanel) {
                    courierPanel.style.display = 'block';
                }
            }
        }

        function initiateLocal(postId) {
            const fd = new FormData();
            fd.append('action', 'initiate_delivery');
            fd.append('post_id', postId);
            fd.append('delivery_type', 'local');

            fetch(HANDLER, {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        showMsg('otpMsg', postId, data.message, 'error');
                        return;
                    }
                    // Hide the generate button
                    const btn = document.getElementById('otpGenerateBtn_' + postId);
                    if (btn) btn.style.display = 'none';

                    // Show "OTP sent to buyer" notice — do NOT display the OTP code here
                    const notice = document.getElementById('otpSentNotice_' + postId);
                    if (notice) notice.style.display = 'block';

                    // Show the verify input so farmer can enter what buyer tells them
                    const hint = document.getElementById('otpVerifyHint_' + postId);
                    if (hint) hint.style.display = 'block';

                    const row = document.getElementById('otpVerifyRow_' + postId);
                    if (row) row.style.display = 'flex';
                })
                .catch(() => showMsg('otpMsg', postId, 'Network error. Please try again.', 'error'));
        }

        function verifyOTP(postId) {
            const input = document.getElementById('otpVerifyInput_' + postId);
            if (!input || !input.value.trim()) {
                showMsg('otpMsg', postId, 'Please enter the OTP.', 'error');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'verify_otp');
            fd.append('post_id', postId);
            fd.append('otp_code', input.value.trim());

            fetch(HANDLER, {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        showMsg('otpMsg', postId, data.message, 'error');
                        input.style.borderColor = '#ef4444';
                        return;
                    }
                    showMsg('otpMsg', postId, '✅ ' + data.message, 'success');
                    // Reload after a brief pause to refresh the order status
                    setTimeout(() => location.reload(), 1800);
                })
                .catch(() => showMsg('otpMsg', postId, 'Network error. Please try again.', 'error'));
        }

        function saveCourier(postId) {
            const company = document.getElementById('courierCompany_' + postId)?.value.trim();
            const tracking = document.getElementById('courierTracking_' + postId)?.value.trim();

            if (!company || !tracking) {
                showMsg('courierMsg', postId, 'Please fill in both fields.', 'error');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'initiate_delivery');
            fd.append('post_id', postId);
            fd.append('delivery_type', 'courier');
            fd.append('courier_company', company);
            fd.append('courier_tracking', tracking);

            fetch(HANDLER, {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        showMsg('courierMsg', postId, data.message, 'error');
                        return;
                    }
                    showMsg('courierMsg', postId, '✅ ' + data.message, 'success');
                    setTimeout(() => location.reload(), 1800);
                })
                .catch(() => showMsg('courierMsg', postId, 'Network error. Please try again.', 'error'));
        }

        function showMsg(prefix, postId, text, type) {
            const el = document.getElementById(prefix + '_' + postId);
            if (!el) return;
            el.textContent = text;
            el.className = 'delivery-msg ' + type;
            el.style.display = 'block';
        }
    </script>

</body>

</html>