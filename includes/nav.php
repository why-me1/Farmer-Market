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
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<nav class="navbar navbar-expand-lg navbar-light primary-gradient text-white shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand font-weight-bold text-white" href="<?php echo $base_url; ?>index.php">
            <i class="fas fa-seedling me-2"></i>Farmers' Marketplace
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto align-items-center">
                <!-- Search -->
                <li class="nav-item">
                    <a class="nav-link text-white font-weight-bold cursor-pointer" id="navSearchIcon" title="Search">
                        <i class="fas fa-search fa-lg"></i>
                    </a>
                </li>

                <?php if ($role !== 'guest'): ?>
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold"
                            href="<?php
                                    if ($role === 'user') echo $base_url . 'user/dashboard.php';
                                    elseif ($role === 'farmer') echo $base_url . 'farmer/dashboard.php';
                                    else echo $base_url . 'admin/dashboard.php';
                                    ?>">
                            Dashboard
                        </a>
                    </li>

                    <!-- Notifications -->
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold position-relative" href="<?php echo $base_url; ?>notifications.php" title="Notifications">
                            <i class="fas fa-bell fa-lg"></i>
                            <?php if ($notification_count > 0): ?>
                                <span class="badge badge-danger badge-pill position-absolute" id="notifCount"
                                    style="top: 2px; right: -4px; font-size: 0.65rem; min-width: 18px; height: 18px; padding: 2px 5px; line-height: 14px;">
                                    <?php echo $notification_count > 99 ? '99+' : $notification_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($username): ?>
                    <li class="nav-item">
                        <a class="nav-link text-white font-weight-bold" href="#"
                            style="opacity:0.85; font-size:0.95rem;">
                            <i class="fas fa-user-circle mr-1"></i>Welcome, <?php echo htmlspecialchars($username); ?>
                        </a>
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
    </div>
</nav>

<!-- Search Overlay Modal -->
<div id="searchOverlay" class="search-overlay" style="display: none;">
    <div class="search-overlay-content">
        <div class="search-overlay-header">
            <button class="search-back-btn" id="closeSearchOverlay">
                <i class="fas fa-arrow-left"></i>
            </button>
            <input type="text" id="overlaySearchInput" class="search-overlay-input" placeholder="Search for organic tomatoes, fresh apples, local dairy...">
        </div>

        <div class="search-overlay-body">
            <div class="popular-searches">
                <h5>POPULAR SEARCHES</h5>
                <div class="search-item">
                    <i class="fas fa-tomato"></i>
                    <span>Organic Tomatoes</span>
                </div>
                <div class="search-item">
                    <i class="fas fa-apple-alt"></i>
                    <span>Fresh Apples</span>
                </div>
                <div class="search-item">
                    <i class="fas fa-glass-alt"></i>
                    <span>Local Dairy Products</span>
                </div>
                <div class="search-item">
                    <i class="fas fa-leaf"></i>
                    <span>Organic Vegetables</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .cursor-pointer {
        cursor: pointer;
    }

    .search-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding-top: 80px;
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @keyframes slideDown {
        from {
            transform: translateY(-30px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .search-overlay-content {
        display: flex;
        flex-direction: column;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        width: 90%;
        max-width: 600px;
        max-height: 70vh;
        animation: slideDown 0.3s ease-in-out;
        overflow: hidden;
    }

    .search-overlay-header {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e0e0e0;
    }

    .search-back-btn {
        background: none;
        border: none;
        font-size: 20px;
        color: #333;
        cursor: pointer;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        flex-shrink: 0;
    }

    .search-back-btn:hover {
        color: #28a745;
    }

    .search-overlay-input {
        flex: 1;
        border: none;
        font-size: 16px;
        padding: 10px 0;
        outline: none;
        color: #666;
    }

    .search-overlay-input::placeholder {
        color: #aaa;
    }

    .search-overlay-body {
        flex: 1;
        overflow-y: auto;
        padding: 30px 20px;
    }

    .popular-searches {
        margin-top: 20px;
    }

    .popular-searches h5 {
        color: #999;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 1px;
        margin-bottom: 20px;
        text-transform: uppercase;
    }

    .search-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px 0;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: all 0.2s ease;
    }


    .search-item:hover {
        background: #f8f9fa;
        padding-left: 10px;
        color: #28a745;
    }

    .search-item i {
        font-size: 20px;
        color: #28a745;
        width: 30px;
    }

    .search-item span {
        font-size: 16px;
        color: #333;
    }

    @media (max-width: 768px) {
        .search-overlay-header {
            padding: 15px;
        }

        .search-overlay-input {
            font-size: 14px;
        }

        .search-item {
            padding: 12px 0;
        }
    }
</style>

<!-- AJAX Script for Real-Time Notifications -->
<script>
    function fetchNotifications() {
        $.ajax({
            url: "<?php echo $base_url; ?>fetch_notifications.php",
            method: "GET",
            success: function(data) {
                let result = JSON.parse(data);
                let count = result.count || 0;

                // Update or create the badge
                let badge = $("#notifCount");
                if (count > 0) {
                    let displayCount = count > 99 ? '99+' : count;
                    if (badge.length === 0) {
                        // Create badge if it doesn't exist
                        $('a[href*="notifications.php"]').append(
                            '<span class="badge badge-danger badge-pill position-absolute" id="notifCount" ' +
                            'style="top: 5px; right: -5px; font-size: 0.65rem; min-width: 18px; height: 18px; padding: 2px 5px; line-height: 14px;">' +
                            displayCount + '</span>'
                        );
                    } else {
                        // Update existing badge
                        badge.text(displayCount).show();
                    }
                } else {
                    // Hide badge if no notifications
                    if (badge.length > 0) {
                        badge.hide();
                    }
                }

                let notifList = $("#notifList");
                if (notifList.length > 0) {
                    notifList.empty();

                    if (result.notifications.length === 0) {
                        notifList.append('<p class="dropdown-item text-muted">No new notifications</p>');
                    } else {
                        result.notifications.forEach(notif => {
                            notifList.append('<a class="dropdown-item" href="<?php echo $base_url; ?>' + notif.link + '">' + notif.message + '</a>');
                        });
                    }
                }
            }
        });
    }

    $(document).ready(function() {
        fetchNotifications();
        setInterval(fetchNotifications, 10000); // Refresh notifications every 10 seconds

        // Search overlay handler
        const searchIcon = document.getElementById('navSearchIcon');
        const searchOverlay = document.getElementById('searchOverlay');
        const closeBtn = document.getElementById('closeSearchOverlay');
        const overlaySearchInput = document.getElementById('overlaySearchInput');
        const searchItems = document.querySelectorAll('.search-item');

        // Open search overlay
        searchIcon.addEventListener('click', function() {
            searchOverlay.style.display = 'flex';
            overlaySearchInput.focus();
        });

        // Close search overlay
        closeBtn.addEventListener('click', function() {
            searchOverlay.style.display = 'none';
        });

        // Close overlay when clicking outside the card
        searchOverlay.addEventListener('click', function(e) {
            if (e.target === searchOverlay) {
                searchOverlay.style.display = 'none';
            }
        });

        // Handle search item clicks
        searchItems.forEach(item => {
            item.addEventListener('click', function() {
                const query = this.querySelector('span').textContent;
                window.location.href = '<?php echo $base_url; ?>search.php?q=' + encodeURIComponent(query);
            });
        });

        // Handle overlay search input submission
        overlaySearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query) {
                    window.location.href = '<?php echo $base_url; ?>search.php?q=' + encodeURIComponent(query);
                }
            }
        });

        // Close overlay when pressing Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                searchOverlay.style.display = 'none';
            }
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>