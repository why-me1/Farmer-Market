<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Handle update post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_post'])) {
    $post_id = (int)$_POST['post_id'];
    $product_name = trim($_POST['product_name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);

    $update_stmt = $conn->prepare("UPDATE posts SET product_name = ?, description = ?, price = ? WHERE id = ? AND farmer_id = ?");
    $update_stmt->bind_param("ssdii", $product_name, $description, $price, $post_id, $farmer_id);
    $update_stmt->execute();

    header("Location: view_posts.php");
    exit();
}

$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Prepare SQL with filter
$sql = "
    SELECT posts.*, 
           CASE 
               WHEN (SELECT COUNT(*) 
                     FROM comments 
                     WHERE comments.post_id = posts.id AND comments.is_approved = 1) > 0 THEN 'Sold' 
               ELSE 'Active' 
           END AS status
    FROM posts
    WHERE posts.farmer_id = ?
";

if ($filter === 'sold') {
    $sql .= " HAVING status = 'Sold'";
} elseif ($filter === 'active') {
    $sql .= " HAVING status = 'Active'";
}

$sql .= " ORDER BY posts.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View Posts</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css"> -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <!-- browser cache problem solution --- add version number for production and add echo time for development -->
    <style>
        body {
            background-color: #f8f9fa;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .status-active {
            color: green;
            font-weight: bold;
        }

        .status-sold {
            color: red;
            font-weight: bold;
        }

        .btn-delete {
            background-color: #dc3545;
            border: none;
        }

        .btn-delete:hover {
            background-color: #c82333;
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="container mt-5">
        <h2 class="mb-4">Your Posts</h2>

        <!-- Filter Dropdown -->
        <form method="GET" class="mb-4">
            <label for="status" class="form-label">Filter by Status:</label>
            <select name="status" id="status" class="form-select w-auto d-inline-block" onchange="this.form.submit()">
                <option value="all" <?php if ($filter === 'all') echo 'selected'; ?>>All</option>
                <option value="active" <?php if ($filter === 'active') echo 'selected'; ?>>Active (Unsold)</option>
                <option value="sold" <?php if ($filter === 'sold') echo 'selected'; ?>>Sold</option>
            </select>
        </form>

        <div class="row">
            <?php
            if ($result->num_rows > 0) {
                while ($post = $result->fetch_assoc()):
            ?>
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <?php if ($post['image']): ?>
                                <img src="../assets/images/<?php echo htmlspecialchars($post['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($post['product_name']); ?>" style="height: 200px; object-fit: cover;">
                            <?php endif; ?>
                            <div class="card-body">
                                <?php if ($edit_id === (int)$post['id']): ?>
                                    <!-- Edit Mode -->
                                    <form action="" method="POST">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">

                                        <div class="mb-2">
                                            <label>Product Name</label>
                                            <input type="text" name="product_name" class="form-control" value="<?php echo htmlspecialchars($post['product_name']); ?>" required>
                                        </div>

                                        <div class="mb-2">
                                            <label>Description</label>
                                            <textarea name="description" class="form-control" required><?php echo htmlspecialchars($post['description']); ?></textarea>
                                        </div>

                                        <div class="mb-2">
                                            <label>Price (৳)</label>
                                            <input type="number" name="price" class="form-control" value="<?php echo $post['price']; ?>" required>
                                        </div>

                                        <button type="submit" name="update_post" class="btn btn-success btn-sm">Update</button>
                                        <a href="view_posts.php" class="btn btn-secondary btn-sm">Cancel</a>
                                    </form>
                                <?php else: ?>
                                    <!-- View Mode -->
                                    <h5 class="card-title"><?php echo htmlspecialchars($post['product_name']); ?></h5>
                                    <p class="card-text"><strong>Description:</strong> <?php echo htmlspecialchars($post['description']); ?></p>
                                    <p class="card-text"><strong>Price:</strong> <?php echo number_format($post['price'], 2); ?>৳</p>
                                    <p class="card-text"><strong>Status:</strong>
                                        <span class="status-<?php echo strtolower($post['status']); ?>"><?php echo $post['status']; ?></span>
                                    </p>
                                    <p class="card-text"><small class="text-muted">Posted on: <?php echo htmlspecialchars($post['created_at']); ?></small></p>

                                    <a href="?edit=<?php echo $post['id']; ?>" class="btn btn-primary btn-sm">Edit</a>

                                    <form action="delete_post.php" method="POST" class="mt-2">
                                        <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['id']); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete Post</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
            <?php
                endwhile;
            } else {
                echo "<p class='alert alert-light text-center'>No posts found.</p>";
            }
            ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>