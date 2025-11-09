<!-- nav is for rest of the website -->

<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'functions.php';

// Default values
$notification_count = 0;
$username = $_SESSION['username'] ?? null;
$role = $_SESSION['role'] ?? 'guest';

// Fetch unread notifications count
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($notification_count);
    $stmt->fetch();
    $stmt->close();
}

// Define base URL
$base_url = "http://localhost/DEMO/";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Market</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css"> -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <!-- browser cache problem solution --- add version number for production and add echo time for development -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light primary-gradient text-white shadow-sm">
        <a class="navbar-brand font-weight-bold text-white ml-3" href="<?php echo $base_url; ?>index.php">
            <i class="fas fa-seedling me-2"></i>Farmer Market
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link text-white font-weight-bold" href="<?php echo $base_url; ?>index.php">Home</a>
                </li>
                <!-- Dropdown Menu for 'How It Works' -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white font-weight-bold" href="#" id="howItWorksDropdown"
                        role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        How It Works
                    </a>
                    <div class="dropdown-menu" aria-labelledby="howItWorksDropdown">
                        <a class="dropdown-item" href="<?php echo $base_url; ?>bidding_guide.php">How Bidding Works</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>how_to_sell.php">How to Sell</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>how_to_buy.php">How to Buy</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>delivery_info.php">Delivery System</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>faq.php">FAQs</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>terms.php">Terms & Conditions</a>
                    </div>
                </li>

                <?php if ($role !== 'guest'): ?>
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold"
                            href="<?php
                                    if ($role === 'user') echo $base_url . 'index.php';
                                    elseif ($role === 'farmer') echo $base_url . 'farmer/dashboard.php';
                                    else echo $base_url . 'admin/dashboard.php';
                                    ?>">
                            Dashboard
                        </a>
                    </li>

                    <!-- View All Notifications Link -->
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold" href="<?php echo $base_url; ?>notifications.php">
                            <i class="fas fa-bell me-1"></i>All Notifications
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($username): ?>
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold" href="#">Welcome, <?php echo htmlspecialchars($username); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold" href="<?php echo $base_url; ?>logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold" href="<?php echo $base_url; ?>login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold" href="<?php echo $base_url; ?>register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- AJAX Script for Real-Time Notifications -->
    <script>
        function fetchNotifications() {
            $.ajax({
                url: "<?php echo $base_url; ?>fetch_notifications.php",
                method: "GET",
                success: function(data) {
                    let result = JSON.parse(data);
                    $("#notifCount").text(result.count);
                    let notifList = $("#notifList");
                    notifList.empty();

                    if (result.notifications.length === 0) {
                        notifList.append('<p class="dropdown-item text-muted">No new notifications</p>');
                    } else {
                        result.notifications.forEach(notif => {
                            notifList.append('<a class="dropdown-item" href="<?php echo $base_url; ?>' + notif.link + '">' + notif.message + '</a>');
                        });
                    }
                }
            });
        }

        $(document).ready(function() {
            fetchNotifications();
            setInterval(fetchNotifications, 10000); // Refresh notifications every 10 seconds
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>