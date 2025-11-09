<!-- navbar is for the login and registration page -->

<?php
require_once 'config.php';
require_once 'functions.php';

$notification_count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($notification_count);
    $stmt->fetch();
    $stmt->close();
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Market</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css"> -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <!-- browser cache problem solution --- add version number for production and add echo time for development -->

</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-success text-white shadow-sm">
        <a class="navbar-brand text-white font-weight-bold text-uppercase" href="../index.php">Farmer Market</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link text-white font-weight-bold" href="../index.php">Home</a>
                </li>

                <?php if ($role !== 'guest'): ?>
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold"
                            href="<?php echo ($role === 'user') ? 'index.php' : (($role === 'farmer') ? '../farmer/dashboard.php' : '../admin/dashboard.php'); ?>">
                            Dashboard
                            <?php if ($notification_count > 0): ?>
                                <span class="badge badge-pill badge-primary"><?php echo $notification_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['username'])): ?>
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold" href="#">Welcome, <?php echo $_SESSION['username']; ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold" href="../logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold" href="register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Include Bootstrap JS and Popper.js -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>