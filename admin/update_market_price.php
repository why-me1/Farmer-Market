<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ratings.php';
check_login();

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_name']) && isset($_POST['market_price'])) {
    $product_name = trim($_POST['product_name']);
    $price = floatval($_POST['market_price']);

    if ($product_name !== '' && $price > 0) {
        set_market_price_for_product($product_name, $price, $_SESSION['user_id']);
        $message = "Market price updated for product: {$product_name}";
    } else {
        $message = "Please provide a valid product name and price.";
    }
}

// Fetch distinct product names from posts
$products = [];
$query = "SELECT DISTINCT product_name FROM posts WHERE product_name IS NOT NULL AND product_name != '' ORDER BY product_name";
$res = $conn->query($query);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $products[] = $row['product_name'];
    }
} else {
    $message = "Error fetching products: " . $conn->error;
}

// Debug: Check if we have products
if (empty($products)) {
    $count_query = "SELECT COUNT(*) as total FROM posts";
    $count_res = $conn->query($count_query);
    if ($count_res) {
        $count_row = $count_res->fetch_assoc();
        if ($count_row['total'] == 0) {
            $message = "No products found in the database. Please add some products first.";
        } else {
            $message = "Found {$count_row['total']} posts but no valid product names.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Market Price</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container mt-5">
        <h2>Update Market Price</h2>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <p>Set the current market price for each product. This will be used to calculate automatic ratings for farmers when they post products.</p>
        <p class="text-muted">Found <strong><?php echo count($products); ?></strong> unique product(s) in the system.</p>

        <?php if (!empty($products)): ?>
            <form method="POST" action="update_market_price.php">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="product_name">Product Name</label>
                        <select name="product_name" id="product_name" class="form-control" required>
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products as $p): ?>
                                <?php $mp = get_market_price_for_product($p); ?>
                                <option value="<?php echo htmlspecialchars($p); ?>">
                                    <?php echo htmlspecialchars($p); ?>
                                    <?php echo ($mp !== null) ? ' (Current: ' . number_format($mp, 2) . '৳)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="market_price">Market Price (৳)</label>
                        <input type="number" step="0.01" min="0.01" name="market_price" id="market_price" class="form-control" required>
                    </div>
                    <div class="col-md-2 mb-3 align-self-end">
                        <button type="submit" class="btn btn-primary w-100">Update</button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> No products available. Please add products first before setting market prices.
            </div>
        <?php endif; ?>

        <hr />
        <h4>Existing Market Prices</h4>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Market Price (৳)</th>
                    <th>Updated At</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $mpres = $conn->query("SELECT product_name, market_price, updated_at FROM market_prices ORDER BY product_name");
                if ($mpres && $mpres->num_rows > 0) {
                    while ($row = $mpres->fetch_assoc()) {
                        echo '<tr><td>' . htmlspecialchars($row['product_name']) . '</td><td>' . number_format($row['market_price'], 2) . '৳</td><td>' . $row['updated_at'] . '</td></tr>';
                    }
                } else {
                    echo '<tr><td colspan="3" class="text-center text-muted">No market prices set yet</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

</html>