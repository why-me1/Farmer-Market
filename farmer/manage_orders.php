<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/notification_functions.php';
check_login();

if ($_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Handle delivery status update
if (isset($_POST['update_delivery']) && isset($_POST['product_id']) && isset($_POST['status'])) {
    $product_id = intval($_POST['product_id']);
    $status = sanitize($_POST['status']);

    // Get product name and buyer info BEFORE updating status
    $stmt = $conn->prepare("SELECT p.product_name, c.user_id FROM posts p 
                          JOIN comments c ON p.id = c.post_id 
                          WHERE p.id = ? AND p.farmer_id = ? AND c.is_approved = 1 
                          LIMIT 1");
    $stmt->bind_param("ii", $product_id, $farmer_id);
    $stmt->execute();
    $stmt->bind_result($product_name, $buyer_id);
    $stmt->fetch();
    $stmt->close();

    // Update product status
    $stmt = $conn->prepare("UPDATE posts SET status = ? WHERE id = ? AND farmer_id = ?");
    $stmt->bind_param("sii", $status, $product_id, $farmer_id);
    $success = $stmt->execute();
    $stmt->close();

    // Send delivery update notification to buyer
    if ($success && $buyer_id && $product_name) {
        notifyBuyerDeliveryUpdate($buyer_id, $product_id, $product_name, $status);
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

        .order-status {
            flex-shrink: 0;
            text-align: center;
            min-width: 110px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 20px;
            padding: 5px 13px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .status-pill.sold {
            background: #fff8e1;
            color: #b87a00;
        }

        .status-pill.delivered {
            background: #e8f8ee;
            color: #0d6b5e;
        }

        .order-action {
            flex-shrink: 0;
        }

        .btn-deliver {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            border: none;
            border-radius: 9px;
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 3px 10px rgba(17, 153, 142, .3);
            white-space: nowrap;
        }

        .btn-deliver:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(17, 153, 142, .42);
        }

        .delivered-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e8f8ee;
            color: #0d6b5e;
            border-radius: 9px;
            padding: 9px 14px;
            font-size: 13px;
            font-weight: 600;
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

        @media (max-width: 768px) {
            .order-row {
                flex-wrap: wrap;
                gap: 12px;
            }

            .order-price,
            .order-status,
            .order-action {
                min-width: unset;
                text-align: left;
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
                        <div class="order-row">

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

                            <div class="order-status">
                                <?php if ($order['status'] === 'delivered'): ?>
                                    <span class="status-pill delivered">
                                        <i class="fas fa-check" style="font-size:9px;"></i> Delivered
                                    </span>
                                <?php else: ?>
                                    <span class="status-pill sold">
                                        <i class="fas fa-clock" style="font-size:9px;"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="order-action">
                                <?php if ($order['status'] !== 'delivered'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="product_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="status" value="delivered">
                                        <button type="submit" name="update_delivery" class="btn-deliver">
                                            <i class="fas fa-truck"></i> Mark Delivered
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="delivered-badge">
                                        <i class="fas fa-check-circle"></i> Done
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

</body>

</html>