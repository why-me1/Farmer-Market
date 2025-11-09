<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Fetch statistics dynamically
$stats = [
    'total_users' => 0,
    'total_posts' => 0,
    'total_comments' => 0,
    'total_sales' => 0.00
];

$queries = [
    'total_users' => "SELECT COUNT(*) AS total FROM users",
    'total_posts' => "SELECT COUNT(*) AS total FROM posts",
    'total_comments' => "SELECT COUNT(*) AS total FROM comments",
    'total_sales' => "SELECT SUM(price) AS total FROM posts WHERE is_approved = 1"
];

foreach ($queries as $key => $query) {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats[$key] = $result['total'] ?? ($key === 'total_sales' ? 0.00 : 0);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - View Statistics</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css"> -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <!-- browser cache problem solution --- add version number for production and add echo time for development -->
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="main-container">
        <div class="admin-dashboard-page">
            <!-- <h1 class="text-center text-gradient mb-5">Admin Dashboard</h1> -->
            <h2 class="text-center mb-4" style="color: var(--primary-color);">View Statistics</h2>
            
            <div class="row statistics-cards">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card stat-card-users">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h5 class="stat-label">Total Users</h5>
                            <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card stat-card-posts">
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h5 class="stat-label">Total Posts</h5>
                            <div class="stat-value"><?php echo $stats['total_posts']; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card stat-card-comments">
                        <div class="stat-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="stat-content">
                            <h5 class="stat-label">Total Comments</h5>
                            <div class="stat-value"><?php echo $stats['total_comments']; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card stat-card-sales">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h5 class="stat-label">Total Sales</h5>
                            <div class="stat-value"><?php echo number_format($stats['total_sales'], 2); ?>৳</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
