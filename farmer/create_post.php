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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize input
    $product_name = sanitize($_POST['product_name']);
    $category = sanitize($_POST['category']);
    $description = sanitize($_POST['description']);
    $price = floatval($_POST['price']);

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
    if (empty($product_name) || empty($category) || empty($description) || empty($price)) {
        $errors[] = "All fields except image are required.";
    }

    // Insert into database if no errors
    if (empty($errors)) {
        $farmer_id = $_SESSION['user_id'];

        // $stmt = $conn->prepare("INSERT INTO posts (farmer_id, product_name, category, description, price, image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt = $conn->prepare("INSERT INTO posts (farmer_id, product_name, category, description, price, image, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())");


        // $stmt->bind_param("issdss", $farmer_id, $product_name, $category, $description, $price, $image);
        $stmt->bind_param("isssds", $farmer_id, $product_name, $category, $description, $price, $image);


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