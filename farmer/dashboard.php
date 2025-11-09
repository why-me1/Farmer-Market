<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css"> -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <!-- browser cache problem solution --- add version number for production and add echo time for development -->
    <style>
        body {
            background-color: #f5f7fa;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #007bff, #00d4ff);
            color: white;
            text-align: center;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .dashboard-header p {
            font-size: 1.2rem;
            margin-bottom: 20px;
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

        .card-title {
            font-weight: bold;
            color: #007bff;
        }

        .btn-primary {
            background-color: #007bff;
            border: none;
            padding: 10px 20px;
            font-size: 1rem;
            border-radius: 5px;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
            border: none;
            padding: 10px 20px;
            font-size: 1rem;
            border-radius: 5px;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="container mt-4">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1>Welcome, Farmer!</h1>
            <p>Manage your products, interact with customers, and grow your business with ease.</p>
        </div>

        <!-- Dashboard Options -->
        <div class="row">
            <!-- Create New Post -->
            <div class="col-md-4">
                <div class="card text-center mb-4">
                    <div class="card-body">
                        <i class="fas fa-plus-circle fa-3x mb-3" style="color: #007bff;"></i>
                        <h5 class="card-title">Create New Post</h5>
                        <p class="card-text">List your products to start receiving bids from customers.</p>
                        <a href="create_post.php" class="btn btn-primary">Create Post</a>
                    </div>
                </div>
            </div>

            <!-- Manage Comments -->
            <!-- <div class="col-md-4">
                <div class="card text-center mb-4">
                    <div class="card-body">
                        <i class="fas fa-comments fa-3x mb-3" style="color: #6c757d;"></i>
                        <h5 class="card-title">Manage Comments</h5>
                        <p class="card-text">Review and approve comments or bids from your customers.</p>
                        <a href="manage_comments.php" class="btn btn-secondary">Manage Comments</a>
                    </div>
                </div>
            </div> -->

            <!-- View Posts -->
            <div class="col-md-4">
                <div class="card text-center mb-4">
                    <div class="card-body">
                        <i class="fas fa-list fa-3x mb-3" style="color: #ffc107;"></i>
                        <h5 class="card-title">View Your Posts</h5>
                        <p class="card-text">See all your products and track their progress.</p>
                        <a href="view_posts.php" class="btn btn-warning text-white">View Posts</a>
                    </div>
                </div>
            </div>

            <!-- Manage Orders -->
            <div class="col-md-4">
                <div class="card text-center mb-4">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-3x mb-3" style="color: #28a745;"></i>
                        <h5 class="card-title">Manage Orders</h5>
                        <p class="card-text">Update delivery status for your sold products.</p>
                        <a href="manage_orders.php" class="btn btn-success">Manage Orders</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <!-- Include Bootstrap JS and FontAwesome -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>