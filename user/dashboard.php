<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Query to get approved comments by the user along with product and farmer details
$stmt = $conn->prepare("
    SELECT DISTINCT posts.id AS post_id, 
                    posts.product_name, 
                    posts.description, 
                    posts.price, 
                    posts.created_at AS post_created_at,
                    users.username AS farmer_username,
                    comments.comment_text,
                    comments.is_approved
    FROM comments
    JOIN posts ON comments.post_id = posts.id
    JOIN users ON posts.farmer_id = users.id
    WHERE comments.user_id = ? AND comments.is_approved = 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css"> -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <!-- browser cache problem solution --- add version number for production and add echo time for development -->
    <style>
        body {
            background-color: #f5f7fa;
        }

        .welcome-banner {
            text-align: center;
            background: linear-gradient(135deg, #007bff, #00d4ff);
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .welcome-banner img {
            border-radius: 50%;
            margin-bottom: 10px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .badge-price {
            background-color: #ffc107;
            color: #212529;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .comment-section {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 15px;
        }

        .approved-comment {
            color: green;
            font-weight: bold;
        }

        .no-data {
            text-align: center;
            color: #6c757d;
            font-size: 1.3rem;
            margin-top: 40px;
        }

        .dark-mode {
            background-color: #121212;
            color: white;
        }

        .dark-mode .card {
            background-color: #1e1e1e;
            color: white;
        }

        .dark-mode .btn {
            background-color: white;
            color: black;
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <img src="../uploads/avatars/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="User Avatar" class="img-fluid" style="width: 100px; height: 100px;">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <p>Your activity dashboard. Manage your approved bids and products with ease.</p>
        <a href="../index.php" class="btn btn-light">Explore All Products</a>
    </div>

    <!-- Quick Stats -->
    <div class="container mb-4">
        <div class="row text-center">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h2>15</h2>
                        <p>Approved Comments</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h2>8</h2>
                        <p>Products Listed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h2>5</h2>
                        <p>Pending Comments</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approved Comments Section -->
    <div class="container">
        <h3 class="mb-4">Your Approved Comments</h3>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()):
        ?>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title">
                            <i class="fas fa-seedling"></i> <?php echo htmlspecialchars($row['product_name']); ?>
                        </h5>
                        <span class="badge badge-price"><?php echo number_format($row['price'], 2); ?>৳</span>
                    </div>
                    <p class="card-text mt-3">
                        <strong>Description:</strong> <?php echo htmlspecialchars($row['description']); ?><br>
                        <strong>Posted on:</strong> <?php echo htmlspecialchars($row['post_created_at']); ?><br>
                        <strong>Farmer:</strong> <?php echo htmlspecialchars($row['farmer_username']); ?>
                    </p>
                    <div class="comment-section">
                        <h6><i class="fas fa-comments"></i> Approved Comment:</h6>
                        <p class="approved-comment"><?php echo htmlspecialchars($row['comment_text']); ?></p>
                    </div>
                </div>
            </div>
        <?php
            endwhile;
        } else {
            echo "<div class='no-data'><i class='fas fa-exclamation-circle'></i> No approved comments found!</div>";
        }
        ?>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <!-- Dark Mode Toggle -->
    <div class="text-end mt-3 container">
        <button id="darkModeToggle" class="btn btn-sm btn-outline-secondary">Toggle Dark Mode</button>
    </div>

    <script>
        const toggle = document.getElementById('darkModeToggle');
        toggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
        });
    </script>

    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
