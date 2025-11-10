<?php

require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

// Regenerate session ID for security
session_regenerate_id(true);

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css"> -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <!-- browser cache problem solution --- add version number for production and add echo time for development -->
    <style>
        body {
            background-color: #f8f9fa;
        }

        .dashboard-header {
            background: linear-gradient(45deg, #343a40, #6c757d);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }

        .dashboard-cards {
            margin-top: 30px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .card-text {
            min-height: 48px;
            flex: 1;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .card-header {
            font-size: 1.25rem;
            font-weight: bold;
        }

        .btn-block {
            width: 100%;
        }

        footer {
            margin-top: 50px;
            text-align: center;
            padding: 10px 0;
            background-color: #343a40;
            color: white;
        }

        .icon-large {
            font-size: 50px;
            margin-bottom: 15px;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-5">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1>Welcome, Admin</h1>
            <p>Manage your platform with ease and control.</p>
        </div>

        <!-- Dashboard Cards -->
        <div class="row dashboard-cards">
            <!-- Manage Users -->
            <div class="col-md-4 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="icon-large">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h5 class="card-title">Manage Users</h5>
                        <p class="card-text">View, edit, or delete user accounts.</p>
                        <a href="manage_users.php" class="btn btn-primary btn-block">Manage Users</a>
                    </div>
                </div>
            </div>

            <!-- Manage Posts -->
            <div class="col-md-4 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="icon-large">
                            <i class="bi bi-card-list"></i>
                        </div>
                        <h5 class="card-title">Manage Posts</h5>
                        <p class="card-text">Approve, edit, or delete user posts.</p>
                        <a href="manage_posts.php" class="btn btn-success btn-block">Manage Posts</a>
                    </div>
                </div>
            </div>

            <!-- Site Statistics -->
            <div class="col-md-4 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="icon-large">
                            <i class="bi bi-bar-chart-fill"></i>
                        </div>
                        <h5 class="card-title">View Statistics</h5>
                        <p class="card-text">Analyze platform data and insights.</p>
                        <a href="view_statistics.php" class="btn btn-warning btn-block">View Statistics</a>
                    </div>
                </div>
            </div>

            <!-- Update Market Price -->
            <div class="col-md-4 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="icon-large">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <h5 class="card-title">Update Market Price</h5>
                        <p class="card-text">Set market prices per product for automatic ratings.</p>
                        <a href="update_market_price.php" class="btn btn-info btn-block">Update Market Price</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Farmer Market Platform. All Rights Reserved.</p>
    </footer>

    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.js"></script>
</body>

</html>