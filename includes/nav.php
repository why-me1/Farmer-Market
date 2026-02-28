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

// Determine dashboard URL
$dashboard_url = '#';
if ($role === 'user')        $dashboard_url = $base_url . 'user/dashboard.php';
elseif ($role === 'farmer')  $dashboard_url = $base_url . 'farmer/dashboard.php';
elseif ($role === 'admin')   $dashboard_url = $base_url . 'admin/dashboard.php';

// User avatar initials
$avatar_initials = $username ? strtoupper(substr($username, 0, 2)) : '';

// Display label: show "Buyer" for role "user"
$display_role = $role === 'user' ? 'Buyer' : ucfirst($role);
?>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>
    /* ── Navbar Core ── */
    .fm-navbar {
        background: linear-gradient(135deg, #065f46 0%, #059669 60%, #10b981 100%);
        padding: 0;
        height: 64px;
        display: flex;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 1030;
        box-shadow: 0 2px 16px rgba(5, 150, 105, 0.25);
        transition: box-shadow 0.3s;
    }

    .fm-navbar.scrolled {
        box-shadow: 0 4px 24px rgba(5, 150, 105, 0.38);
    }

    .fm-navbar .container-fluid {
        display: flex;
        align-items: center;
        gap: 0;
        padding: 0 24px;
    }

    /* ── Brand ── */
    .fm-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none !important;
        flex-shrink: 0;
        margin-right: 28px;
    }

    .fm-brand:hover,
    .fm-brand:focus,
    .fm-brand:active {
        text-decoration: none !important;
    }

    .fm-brand-icon {
        width: 36px;
        height: 36px;
        background: rgba(255, 255, 255, 0.18);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #fff;
        backdrop-filter: blur(4px);
        transition: background 0.2s;
    }

    .fm-brand:hover .fm-brand-icon {
        background: rgba(255, 255, 255, 0.30);
    }

    .fm-brand-text {
        font-size: 1.05rem;
        font-weight: 700;
        color: #fff;
        letter-spacing: 0.3px;
        line-height: 1.2;
    }

    .fm-brand-text small {
        display: block;
        font-size: 0.62rem;
        font-weight: 400;
        opacity: 0.75;
        letter-spacing: 0.8px;
        text-transform: uppercase;
    }

    /* ── Nav Links (center) ── */
    .fm-nav-links {
        display: flex;
        align-items: center;
        gap: 2px;
        list-style: none;
        margin: 0;
        padding: 0;
        flex: 1;
    }

    .fm-nav-links .fm-nav-link {
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.875rem;
        font-weight: 500;
        padding: 6px 14px;
        border-radius: 8px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: background 0.18s, color 0.18s;
        white-space: nowrap;
    }

    .fm-nav-links .fm-nav-link:hover,
    .fm-nav-links .fm-nav-link.active {
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
    }

    .fm-nav-links .fm-nav-link i {
        font-size: 0.8rem;
        opacity: 0.8;
    }

    /* ── Right Controls ── */
    .fm-nav-right {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-left: auto;
        flex-shrink: 0;
    }

    /* ── Icon Buttons ── */
    .fm-icon-btn {
        position: relative;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: none;
        background: rgba(255, 255, 255, 0.10);
        color: rgba(255, 255, 255, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.18s, transform 0.15s;
        flex-shrink: 0;
    }

    .fm-icon-btn:hover {
        background: rgba(255, 255, 255, 0.22);
        color: #fff;
        transform: translateY(-1px);
        text-decoration: none;
    }

    /* ── Notification Badge ── */
    .fm-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        border-radius: 8px;
        background: #ef4444;
        color: #fff;
        font-size: 0.6rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        border: 2px solid transparent;
        animation: badgePop 0.3s ease;
    }

    @keyframes badgePop {
        0% {
            transform: scale(0);
        }

        70% {
            transform: scale(1.2);
        }

        100% {
            transform: scale(1);
        }
    }

    /* ── Bell ring animation on load when notifications exist ── */
    .bell-ring {
        animation: bellRing 0.6s ease 0.5s;
    }

    @keyframes bellRing {

        0%,
        100% {
            transform: rotate(0);
        }

        20% {
            transform: rotate(-15deg);
        }

        40% {
            transform: rotate(15deg);
        }

        60% {
            transform: rotate(-10deg);
        }

        80% {
            transform: rotate(10deg);
        }
    }

    /* ── Divider ── */
    .fm-divider {
        width: 1px;
        height: 26px;
        background: rgba(255, 255, 255, 0.2);
        margin: 0 6px;
        flex-shrink: 0;
    }

    /* ── Auth Buttons ── */
    .fm-btn-login {
        padding: 7px 16px;
        border-radius: 8px;
        font-size: 0.855rem;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.9);
        background: rgba(255, 255, 255, 0.12);
        border: 1.5px solid rgba(255, 255, 255, 0.28);
        text-decoration: none;
        transition: all 0.18s;
        white-space: nowrap;
    }

    .fm-btn-login:hover {
        background: rgba(255, 255, 255, 0.22);
        color: #fff;
        border-color: rgba(255, 255, 255, 0.5);
        text-decoration: none;
    }

    .fm-btn-register {
        padding: 7px 16px;
        border-radius: 8px;
        font-size: 0.855rem;
        font-weight: 600;
        color: #059669;
        background: #fff;
        border: 1.5px solid #fff;
        text-decoration: none;
        transition: all 0.18s;
        white-space: nowrap;
    }

    .fm-btn-register:hover {
        background: #f0fdf4;
        color: #047857;
        text-decoration: none;
    }

    /* ── User Dropdown ── */
    .fm-user-dropdown {
        position: relative;
    }

    .fm-user-trigger {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 12px 5px 5px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.12);
        border: 1.5px solid rgba(255, 255, 255, 0.22);
        cursor: pointer;
        transition: background 0.18s;
        user-select: none;
    }

    .fm-user-trigger:hover {
        background: rgba(255, 255, 255, 0.22);
    }

    .fm-avatar {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.25);
        color: #fff;
        font-size: 0.75rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        letter-spacing: 0.5px;
    }

    .fm-user-name {
        font-size: 0.855rem;
        font-weight: 600;
        color: #fff;
        max-width: 110px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .fm-user-role-tag {
        font-size: 0.62rem;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.65);
        text-transform: capitalize;
    }

    .fm-caret {
        font-size: 0.7rem;
        color: rgba(255, 255, 255, 0.65);
        transition: transform 0.2s;
    }

    .fm-user-dropdown.open .fm-caret {
        transform: rotate(180deg);
    }

    .fm-dropdown-menu {
        display: none;
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        min-width: 210px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.14), 0 2px 8px rgba(0, 0, 0, 0.06);
        overflow: hidden;
        animation: dropFade 0.2s ease;
        z-index: 9998;
    }

    @keyframes dropFade {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fm-user-dropdown.open .fm-dropdown-menu {
        display: block;
    }

    .fm-dropdown-header {
        padding: 14px 16px 10px;
        border-bottom: 1px solid #f0f0f0;
    }

    .fm-dropdown-header .dh-name {
        font-weight: 700;
        font-size: 0.9rem;
        color: #1a1a2e;
    }

    .fm-dropdown-header .dh-role {
        font-size: 0.75rem;
        color: #6b7280;
        text-transform: capitalize;
    }

    .fm-dropdown-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        font-size: 0.855rem;
        color: #374151;
        text-decoration: none;
        transition: background 0.15s;
        cursor: pointer;
    }

    .fm-dropdown-item:hover {
        background: #f0fdf4;
        color: #059669;
        text-decoration: none;
    }

    .fm-dropdown-item i {
        width: 16px;
        font-size: 0.85rem;
        color: #9ca3af;
        text-align: center;
        flex-shrink: 0;
    }

    .fm-dropdown-item:hover i {
        color: #059669;
    }

    .fm-dropdown-divider {
        height: 1px;
        background: #f0f0f0;
        margin: 4px 0;
    }

    .fm-dropdown-item.logout {
        color: #ef4444;
    }

    .fm-dropdown-item.logout:hover {
        background: #fff5f5;
        color: #dc2626;
    }

    .fm-dropdown-item.logout i {
        color: #ef4444;
    }

    /* ── Mobile Toggler ── */
    .fm-toggler {
        display: none;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.12);
        border: 1.5px solid rgba(255, 255, 255, 0.25);
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-direction: column;
        gap: 5px;
        padding: 10px;
        margin-left: auto;
        transition: background 0.18s;
    }

    .fm-toggler:hover {
        background: rgba(255, 255, 255, 0.22);
    }

    .fm-toggler span {
        display: block;
        width: 100%;
        height: 2px;
        background: #fff;
        border-radius: 2px;
        transition: all 0.3s;
    }

    .fm-toggler.open span:nth-child(1) {
        transform: translateY(7px) rotate(45deg);
    }

    .fm-toggler.open span:nth-child(2) {
        opacity: 0;
    }

    .fm-toggler.open span:nth-child(3) {
        transform: translateY(-7px) rotate(-45deg);
    }

    /* ── Mobile Menu ── */
    .fm-mobile-menu {
        display: none;
        position: fixed;
        top: 64px;
        left: 0;
        right: 0;
        background: #065f46;
        padding: 12px 0 20px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        z-index: 1029;
        max-height: calc(100vh - 64px);
        overflow-y: auto;
    }

    .fm-mobile-menu.open {
        display: block;
        animation: slideDown 0.25s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-12px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fm-mobile-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 24px;
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.92rem;
        font-weight: 500;
        text-decoration: none;
        transition: background 0.15s, color 0.15s;
    }

    .fm-mobile-link:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
        text-decoration: none;
    }

    .fm-mobile-link i {
        width: 20px;
        text-align: center;
        font-size: 0.9rem;
        opacity: 0.75;
    }

    .fm-mobile-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.12);
        margin: 8px 24px;
    }

    .fm-mobile-auth {
        display: flex;
        gap: 10px;
        padding: 10px 24px 0;
    }

    .fm-mobile-auth a {
        flex: 1;
        text-align: center;
        padding: 9px 0;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.18s;
    }

    .fm-mobile-auth .m-login {
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        border: 1.5px solid rgba(255, 255, 255, 0.28);
    }

    .fm-mobile-auth .m-register {
        background: #fff;
        color: #059669;
    }

    .fm-mobile-user {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 24px 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        margin-bottom: 4px;
    }

    .fm-mobile-avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
        font-size: 0.9rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .fm-mobile-user-info .mu-name {
        font-weight: 700;
        color: #fff;
        font-size: 0.92rem;
    }

    .fm-mobile-user-info .mu-role {
        font-size: 0.72rem;
        color: rgba(255, 255, 255, 0.6);
        text-transform: capitalize;
    }

    /* ── Search Overlay ── */
    .fm-search-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        z-index: 9999;
        align-items: flex-start;
        justify-content: center;
        padding-top: 90px;
        backdrop-filter: blur(3px);
        animation: fadeIn 0.25s ease;
    }

    .fm-search-overlay.visible {
        display: flex;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .fm-search-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
        width: 90%;
        max-width: 580px;
        max-height: 72vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: cardIn 0.25s ease;
    }

    @keyframes cardIn {
        from {
            opacity: 0;
            transform: translateY(-20px) scale(0.97);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .fm-search-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid #f0f0f0;
    }

    .fm-search-back {
        width: 36px;
        height: 36px;
        border: none;
        background: #f1f5f9;
        border-radius: 8px;
        color: #64748b;
        font-size: 0.9rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: background 0.15s, color 0.15s;
    }

    .fm-search-back:hover {
        background: #e2e8f0;
        color: #059669;
    }

    .fm-search-input-wrap {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        padding: 0 14px;
        transition: border-color 0.15s;
    }

    .fm-search-input-wrap:focus-within {
        border-color: #059669;
    }

    .fm-search-input-wrap i {
        color: #94a3b8;
        font-size: 0.9rem;
        flex-shrink: 0;
    }

    .fm-search-input {
        flex: 1;
        border: none;
        background: transparent;
        font-size: 0.95rem;
        color: #1e293b;
        padding: 11px 0;
        outline: none;
    }

    .fm-search-input::placeholder {
        color: #94a3b8;
    }

    .fm-search-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
    }

    .fm-search-label {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 1.2px;
        color: #94a3b8;
        text-transform: uppercase;
        margin-bottom: 10px;
    }

    .fm-search-chip {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        cursor: pointer;
        transition: background 0.15s;
    }

    .fm-search-chip:hover {
        background: #f0fdf4;
    }

    .fm-search-chip .chip-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: #f0fdf4;
        color: #059669;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
    }

    .fm-search-chip:hover .chip-icon {
        background: #dcfce7;
    }

    .fm-search-chip .chip-text {
        font-size: 0.9rem;
        color: #374151;
        font-weight: 500;
    }

    .fm-search-chip .chip-arrow {
        margin-left: auto;
        color: #d1d5db;
        font-size: 0.75rem;
    }

    /* ── Responsive ── */
    @media (max-width: 991px) {
        .fm-nav-links {
            display: none;
        }

        .fm-nav-right {
            display: none;
        }

        .fm-toggler {
            display: flex;
        }
    }

    @media (min-width: 992px) {
        .fm-toggler {
            display: none !important;
        }

        .fm-mobile-menu {
            display: none !important;
        }
    }
</style>

<!-- ═══ NAVBAR ═══ -->
<nav class="fm-navbar" id="fm-navbar">
    <div class="container-fluid">

        <!-- Brand -->
        <a class="fm-brand" href="<?php echo $base_url; ?>index.php">
            <div class="fm-brand-icon"><i class="fas fa-seedling"></i></div>
            <div class="fm-brand-text">
                Farmers' Market
                <small>Fresh from the field</small>
            </div>
        </a>

        <!-- Center Nav Links -->
        <ul class="fm-nav-links">
            <li><a class="fm-nav-link" href="<?php echo $base_url; ?>browse.php"><i class="fas fa-th-large"></i> Browse</a></li>
            <li><a class="fm-nav-link" href="<?php echo $base_url; ?>index.php#live-auctions"><i class="fas fa-gavel"></i> Live Auctions</a></li>
            <li><a class="fm-nav-link" href="<?php echo $base_url; ?>bidding_guide.php"><i class="fas fa-circle-info"></i> How it Works</a></li>
        </ul>

        <!-- Right Controls -->
        <div class="fm-nav-right">

            <!-- Search -->
            <button class="fm-icon-btn" id="navSearchIcon" title="Search">
                <i class="fas fa-search"></i>
            </button>

            <?php if ($role !== 'guest'): ?>
                <!-- Notifications -->
                <a class="fm-icon-btn" href="<?php echo $base_url; ?>notifications.php" title="Notifications">
                    <i class="fas fa-bell <?php echo $notification_count > 0 ? 'bell-ring' : ''; ?>"></i>
                    <?php if ($notification_count > 0): ?>
                        <span class="fm-badge" id="notifCount">
                            <?php echo $notification_count > 99 ? '99+' : $notification_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <?php if ($username): ?>
                <div class="fm-divider"></div>
                <!-- User Dropdown -->
                <div class="fm-user-dropdown" id="userDropdown">
                    <div class="fm-user-trigger" id="userDropdownTrigger">
                        <div class="fm-avatar"><?php echo htmlspecialchars($avatar_initials); ?></div>
                        <div>
                            <div class="fm-user-name"><?php echo htmlspecialchars($username); ?></div>
                            <div class="fm-user-role-tag"><?php echo htmlspecialchars($display_role); ?></div>
                        </div>
                        <i class="fas fa-chevron-down fm-caret"></i>
                    </div>
                    <div class="fm-dropdown-menu" id="userDropdownMenu">
                        <div class="fm-dropdown-header">
                            <div class="dh-name"><?php echo htmlspecialchars($username); ?></div>
                            <div class="dh-role"><?php echo htmlspecialchars($display_role); ?></div>
                        </div>
                        <?php if ($role !== 'guest'): ?>
                            <a class="fm-dropdown-item" href="<?php echo $dashboard_url; ?>">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                            <?php if ($role === 'farmer'): ?>
                                <a class="fm-dropdown-item" href="<?php echo $base_url; ?>farmer/profile.php">
                                    <i class="fas fa-user"></i> My Profile
                                </a>
                                <a class="fm-dropdown-item" href="<?php echo $base_url; ?>farmer/view_posts.php">
                                    <i class="fas fa-list"></i> My Listings
                                </a>
                            <?php elseif ($role === 'user'): ?>
                                <a class="fm-dropdown-item" href="<?php echo $base_url; ?>user/profile.php">
                                    <i class="fas fa-user"></i> My Profile
                                </a>
                            <?php endif; ?>
                            <a class="fm-dropdown-item" href="<?php echo $base_url; ?>notifications.php">
                                <i class="fas fa-bell"></i> Notifications
                                <?php if ($notification_count > 0): ?>
                                    <span style="margin-left:auto;background:#ef4444;color:#fff;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:10px;"><?php echo $notification_count; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                        <div class="fm-dropdown-divider"></div>
                        <a class="fm-dropdown-item logout" href="<?php echo $base_url; ?>logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <div class="fm-divider"></div>
                <a class="fm-btn-login" href="#" data-auth-modal="login">Login</a>
                <a class="fm-btn-register" href="#" data-auth-modal="signup">Sign Up</a>
            <?php endif; ?>
        </div>

        <!-- Mobile Toggler -->
        <button class="fm-toggler" id="mobileToggler" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<!-- ═══ MOBILE MENU ═══ -->
<div class="fm-mobile-menu" id="mobileMenu">
    <?php if ($username): ?>
        <div class="fm-mobile-user">
            <div class="fm-mobile-avatar"><?php echo htmlspecialchars($avatar_initials); ?></div>
            <div class="fm-mobile-user-info">
                <div class="mu-name"><?php echo htmlspecialchars($username); ?></div>
                <div class="mu-role"><?php echo htmlspecialchars($display_role); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <a class="fm-mobile-link" href="<?php echo $base_url; ?>browse.php"><i class="fas fa-th-large"></i> Browse</a>
    <a class="fm-mobile-link" href="<?php echo $base_url; ?>index.php#live-auctions"><i class="fas fa-gavel"></i> Live Auctions</a>
    <a class="fm-mobile-link" href="<?php echo $base_url; ?>bidding_guide.php"><i class="fas fa-circle-info"></i> How it Works</a>

    <?php if ($role !== 'guest'): ?>
        <div class="fm-mobile-divider"></div>
        <a class="fm-mobile-link" href="<?php echo $dashboard_url; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a class="fm-mobile-link" href="<?php echo $base_url; ?>notifications.php">
            <i class="fas fa-bell"></i> Notifications
            <?php if ($notification_count > 0): ?>
                <span style="margin-left:auto;background:#ef4444;color:#fff;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:10px;"><?php echo $notification_count; ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>

    <?php if ($username): ?>
        <div class="fm-mobile-divider"></div>
        <a class="fm-mobile-link" href="<?php echo $base_url; ?>logout.php" style="color:#fca5a5;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    <?php else: ?>
        <div class="fm-mobile-divider"></div>
        <div class="fm-mobile-auth">
            <a class="m-login" href="#" data-auth-modal="login">Login</a>
            <a class="m-register" href="#" data-auth-modal="signup">Sign Up</a>
        </div>
    <?php endif; ?>
</div>

<!-- ═══ SEARCH OVERLAY ═══ -->
<div class="fm-search-overlay" id="searchOverlay">
    <div class="fm-search-card">
        <div class="fm-search-head">
            <button class="fm-search-back" id="closeSearchOverlay"><i class="fas fa-arrow-left"></i></button>
            <div class="fm-search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" class="fm-search-input" id="overlaySearchInput"
                    placeholder="Search tomatoes, dairy, fresh apples…" autocomplete="off">
            </div>
        </div>
        <div class="fm-search-body">
            <div class="fm-search-label">Popular Searches</div>
            <div class="fm-search-chip" data-query="Organic Tomatoes">
                <div class="chip-icon"><i class="fas fa-apple-alt"></i></div>
                <span class="chip-text">Organic Tomatoes</span>
                <i class="fas fa-arrow-up-right chip-arrow"></i>
            </div>
            <div class="fm-search-chip" data-query="Fresh Apples">
                <div class="chip-icon"><i class="fas fa-leaf"></i></div>
                <span class="chip-text">Fresh Apples</span>
                <i class="fas fa-arrow-up-right chip-arrow"></i>
            </div>
            <div class="fm-search-chip" data-query="Local Dairy Products">
                <div class="chip-icon"><i class="fas fa-cheese"></i></div>
                <span class="chip-text">Local Dairy Products</span>
                <i class="fas fa-arrow-up-right chip-arrow"></i>
            </div>
            <div class="fm-search-chip" data-query="Organic Vegetables">
                <div class="chip-icon"><i class="fas fa-carrot"></i></div>
                <span class="chip-text">Organic Vegetables</span>
                <i class="fas fa-arrow-up-right chip-arrow"></i>
            </div>
        </div>
    </div>
</div>

<!-- AJAX Notifications + Interaction Script -->
<script>
    function fetchNotifications() {
        $.ajax({
            url: "<?php echo $base_url; ?>fetch_notifications.php",
            method: "GET",
            success: function(data) {
                try {
                    let result = JSON.parse(data);
                    let count = result.count || 0;
                    let badge = $("#notifCount");
                    if (count > 0) {
                        let displayCount = count > 99 ? '99+' : count;
                        if (badge.length === 0) {
                            $('a[href*="notifications.php"].fm-icon-btn').append(
                                '<span class="fm-badge" id="notifCount">' + displayCount + '</span>'
                            );
                        } else {
                            badge.text(displayCount).show();
                        }
                    } else {
                        badge.hide();
                    }
                } catch (e) {}
            }
        });
    }

    $(document).ready(function() {
        fetchNotifications();
        setInterval(fetchNotifications, 10000);

        // ── Sticky scroll shadow ──
        window.addEventListener('scroll', function() {
            const nav = document.getElementById('fm-navbar');
            if (nav) nav.classList.toggle('scrolled', window.scrollY > 10);
        });

        // ── User Dropdown ──
        const userDD = document.getElementById('userDropdown');
        const userTrigger = document.getElementById('userDropdownTrigger');
        if (userTrigger) {
            userTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                userDD.classList.toggle('open');
            });
            document.addEventListener('click', function() {
                userDD.classList.remove('open');
            });
        }

        // ── Mobile Menu ──
        const toggler = document.getElementById('mobileToggler');
        const mobileMenu = document.getElementById('mobileMenu');
        if (toggler) {
            toggler.addEventListener('click', function() {
                toggler.classList.toggle('open');
                mobileMenu.classList.toggle('open');
            });
        }

        // ── Search Overlay ──
        const searchIcon = document.getElementById('navSearchIcon');
        const searchOverlay = document.getElementById('searchOverlay');
        const closeBtn = document.getElementById('closeSearchOverlay');
        const searchInput = document.getElementById('overlaySearchInput');

        function openSearch() {
            searchOverlay.classList.add('visible');
            setTimeout(() => searchInput && searchInput.focus(), 100);
        }

        function closeSearch() {
            searchOverlay.classList.remove('visible');
        }

        if (searchIcon) searchIcon.addEventListener('click', openSearch);
        if (closeBtn) closeBtn.addEventListener('click', closeSearch);
        if (searchOverlay) {
            searchOverlay.addEventListener('click', function(e) {
                if (e.target === searchOverlay) closeSearch();
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSearch();
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                openSearch();
            }
        });

        // Search chips
        document.querySelectorAll('.fm-search-chip').forEach(chip => {
            chip.addEventListener('click', function() {
                const query = this.dataset.query;
                window.location.href = '<?php echo $base_url; ?>search.php?q=' + encodeURIComponent(query);
            });
        });

        // Search input submit
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const q = this.value.trim();
                    if (q) window.location.href = '<?php echo $base_url; ?>search.php?q=' + encodeURIComponent(q);
                }
            });
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<?php require_once __DIR__ . '/auth_modal.php'; ?>