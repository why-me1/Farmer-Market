<?php
// session_start(); // Ensure session starts
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// Include configuration and function files
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ratings.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure user is logged in
check_login();

// Check if user is a farmer
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}

// Initialize variables
$errors = [];
$product_name = $category = $description = $image = "";
$price = 0.0;
$quantity = 0.0;
$unit = "kg";
$auction_start_date = "";
$auction_end_date = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize input
    $product_name = sanitize($_POST['product_name']);
    $category = sanitize($_POST['category']);
    $description = sanitize($_POST['description']);
    $price = floatval($_POST['price']);
    $quantity = floatval($_POST['quantity']);
    $unit = sanitize($_POST['unit']);
    $auction_start_date = sanitize($_POST['auction_start_date']);
    $auction_end_date = sanitize($_POST['auction_end_date']);

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $image = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], '../assets/images/' . $image);
        } else {
            $errors[] = "Invalid image format. Allowed formats: JPG, JPEG, PNG, GIF.";
        }
    }

    // Validate required fields
    if (empty($product_name) || empty($category) || empty($description) || empty($price) || empty($quantity) || empty($unit) || empty($auction_start_date) || empty($auction_end_date)) {
        $errors[] = "All fields are required.";
    }

    // Validate quantity is positive
    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than 0.";
    }

    // Validate auction dates
    $start_date = strtotime($auction_start_date);
    $end_date = strtotime($auction_end_date);

    if ($start_date === false || $end_date === false) {
        $errors[] = "Invalid auction dates.";
    } elseif ($start_date >= $end_date) {
        $errors[] = "Auction end date must be after start date.";
    }

    // Insert into database if no errors
    if (empty($errors)) {
        $farmer_id = $_SESSION['user_id'];

        $stmt = $conn->prepare("INSERT INTO posts (farmer_id, product_name, category, description, price, quantity, unit, auction_start_date, auction_end_date, image, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $stmt->bind_param("isssddssss", $farmer_id, $product_name, $category, $description, $price, $quantity, $unit, $auction_start_date, $auction_end_date, $image);


        if ($stmt->execute()) {
            // Adjust farmer automatic rating based on posted price vs market price for this product
            adjust_rating_for_post($farmer_id, $price, $product_name);

            header("Location: dashboard.php");
            exit();
        } else {
            $errors[] = "Failed to create post. Error: " . $conn->error;
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Post</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css"> -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <!-- browser cache problem solution --- add version number for production and add echo time for development -->
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h2>Create New Post</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error) {
                            echo "<p>$error</p>";
                        } ?>
                    </div>
                <?php endif; ?>

                <form action="create_post.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="product_name">Product Name</label>
                        <input type="text" name="product_name" id="product_name" class="form-control" placeholder="Enter product name" required>
                    </div>


                    <div class="form-group">
                        <label for="category">Category</label>
                        <select name="category" class="form-control" required>
                            <option value="Vegetables">Vegetables</option>
                            <option value="Fruits">Fruits</option>
                            <option value="Dairy">Dairy</option>
                            <option value="Grains">Grains</option>
                            <option value="Meat">Meat</option>
                            <option value="Fish">Fish</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="5" placeholder="Enter product description" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="price">Price (৳)</label>
                        <input type="number" name="price" id="price" class="form-control" step="0.01" placeholder="Enter price" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="quantity">Quantity</label>
                                <input type="number" name="quantity" id="quantity" class="form-control" step="0.01" placeholder="Enter quantity" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="unit">Unit</label>
                                <select name="unit" id="unit" class="form-control" required>
                                    <option value="kg">Kilogram (kg)</option>
                                    <option value="g">Gram (g)</option>
                                    <option value="L">Liter (L)</option>
                                    <option value="ml">Milliliter (ml)</option>
                                    <option value="pcs">Pieces (pcs)</option>
                                    <option value="dozen">Dozen</option>
                                    <option value="bundle">Bundle</option>
                                    <option value="box">Box</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="auction_start_date">Auction Start Date & Time</label>
                                <input type="datetime-local" name="auction_start_date" id="auction_start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="auction_end_date">Auction End Date & Time</label>
                                <input type="datetime-local" name="auction_end_date" id="auction_end_date" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="image">Product Image</label>
                        <input type="file" name="image" id="image" class="form-control-file">
                    </div>

                    <button type="submit" class="btn btn-primary">Submit for Approval</button>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <!-- Include Bootstrap JS and Popper.js -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>