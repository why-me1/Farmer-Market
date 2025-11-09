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

    // Update product status
    $stmt = $conn->prepare("UPDATE posts SET status = ? WHERE id = ? AND farmer_id = ?");
    $stmt->bind_param("sii", $status, $product_id, $farmer_id);
    $stmt->execute();
    $stmt->close();

    // Get product name and buyer info
    $stmt = $conn->prepare("SELECT p.product_name, c.user_id FROM posts p 
                          JOIN comments c ON p.id = c.post_id 
                          WHERE p.id = ? AND c.is_approved = 1");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->bind_result($product_name, $buyer_id);
    $stmt->fetch();
    $stmt->close();

    // Send delivery update notification to buyer
    if ($buyer_id && $product_name) {
        notifyBuyerDeliveryUpdate($buyer_id, $product_id, $product_name, $status);
    }

    header("Location: manage_orders.php");
    exit();
}

// Fetch sold products for this farmer
$stmt = $conn->prepare("SELECT p.*, c.user_id, u.username FROM posts p 
                       JOIN comments c ON p.id = c.post_id 
                       JOIN users u ON c.user_id = u.id
                       WHERE p.farmer_id = ? AND p.status = 'sold' AND c.is_approved = 1
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
    <title>Manage Orders</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css"> -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <!-- browser cache problem solution --- add version number for production and add echo time for development -->
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h2 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Manage Orders</h2>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows === 0): ?>
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>No sold products to manage.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Product</th>
                                    <th>Buyer</th>
                                    <th>Current Status</th>
                                    <th>Sold Date</th>
                                    <th>Update Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($order = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['product_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['description']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['username']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['status'] === 'delivered' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                        <td>
                                            <?php if ($order['status'] !== 'delivered'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="product_id" value="<?php echo $order['id']; ?>">
                                                    <input type="hidden" name="status" value="delivered">
                                                    <button type="submit" name="update_delivery" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check"></i> Mark as Delivered
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-success"><i class="fas fa-check-circle"></i> Delivered</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
</body>

</html>