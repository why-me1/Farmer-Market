<?php
session_start();
include 'includes/db.php';
date_default_timezone_set('Asia/Dhaka');
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure wishlist table exists
$conn->query("CREATE TABLE IF NOT EXISTS `wishlist` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `post_id` INT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_wishlist` (`user_id`, `post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$category = isset($_GET['category']) ? sanitize($_GET['category']) : 'Vegetables';

$valid_categories = ['Vegetables', 'Fruits', 'Grains', 'Dairy', 'Eggs', 'Honey', 'Herbs', 'Root Vegetables'];
if (!in_array($category, $valid_categories)) {
    $category = 'Vegetables';
}

// Filter parameters
$sort          = (isset($_GET['sort']) && in_array($_GET['sort'], ['newest', 'price_asc', 'price_desc', 'most_bids'])) ? $_GET['sort'] : 'newest';
$status_filter = (isset($_GET['status']) && in_array($_GET['status'], ['all', 'live', 'upcoming', 'ended']))           ? $_GET['status'] : 'all';
$min_price     = (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) ? (float)$_GET['min_price'] : '';
$max_price     = (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) ? (float)$_GET['max_price'] : '';
$location_filter = isset($_GET['location']) ? sanitize($_GET['location']) : '';

$active_filter_count = 0;
if ($sort !== 'newest')       $active_filter_count++;
if ($status_filter !== 'all') $active_filter_count++;
if ($min_price !== '')        $active_filter_count++;
if ($max_price !== '')        $active_filter_count++;
if ($location_filter !== '')  $active_filter_count++;

// Category meta: icon + gradient colours
$cat_meta = [
    'Vegetables'      => ['icon' => 'fa-leaf',        'grad' => 'linear-gradient(135deg,#16a34a,#4ade80)', 'light' => '#dcfce7'],
    'Fruits'          => ['icon' => 'fa-apple-alt',   'grad' => 'linear-gradient(135deg,#dc2626,#f87171)', 'light' => '#fee2e2'],
    'Grains'          => ['icon' => 'fa-seedling',    'grad' => 'linear-gradient(135deg,#d97706,#fbbf24)', 'light' => '#fef3c7'],
    'Dairy'           => ['icon' => 'fa-droplet',     'grad' => 'linear-gradient(135deg,#2563eb,#60a5fa)', 'light' => '#dbeafe'],
    'Eggs'            => ['icon' => 'fa-egg',         'grad' => 'linear-gradient(135deg,#ca8a04,#fde047)', 'light' => '#fefce8'],
    'Honey'           => ['icon' => 'fa-fill-drip',   'grad' => 'linear-gradient(135deg,#b45309,#fb923c)', 'light' => '#fff7ed'],
    'Herbs'           => ['icon' => 'fa-spa',         'grad' => 'linear-gradient(135deg,#059669,#34d399)', 'light' => '#ecfdf5'],
    'Root Vegetables' => ['icon' => 'fa-carrot',      'grad' => 'linear-gradient(135deg,#ea580c,#fb923c)', 'light' => '#fff7ed'],
];
$meta  = $cat_meta[$category];
$icon  = $meta['icon'];
$grad  = $meta['grad'];
$light = $meta['light'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $category; ?> – Farmers' Marketplace</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        /* ── Filter Layout ── */
        .br-layout {
            display: flex;
            align-items: flex-start;
            gap: 24px;
            padding: 24px 0 48px;
        }

        .br-main {
            flex: 1;
            min-width: 0;
        }

        /* ── Sidebar ── */
        .br-sidebar {
            width: 240px;
            flex-shrink: 0;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 18px 16px;
            position: sticky;
            top: 130px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }

        .br-sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .br-sidebar-header h3 {
            font-size: .88rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .br-filter-section {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f1f5f9;
        }

        .br-filter-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .br-filter-title {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .br-filter-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px;
            border-radius: 8px;
            cursor: pointer;
            font-size: .82rem;
            color: #475569;
            transition: background .15s;
            margin-bottom: 2px;
        }

        .br-filter-option:hover {
            background: #f8fafc;
        }

        .br-filter-option input[type=radio] {
            accent-color: #16a34a;
            cursor: pointer;
            flex-shrink: 0;
        }

        .br-filter-option.is-active {
            background: #f0fdf4;
            color: #15803d;
            font-weight: 600;
        }

        .br-price-row {
            display: flex;
            gap: 6px;
            align-items: center;
            margin-top: 6px;
        }

        .br-price-row input {
            flex: 1;
            min-width: 0;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 8px;
            font-size: .78rem;
            outline: none;
            transition: border-color .15s;
        }

        .br-price-row input:focus {
            border-color: #16a34a;
        }

        .br-apply-btn {
            width: 100%;
            background: #16a34a;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px;
            font-size: .8rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: background .15s;
        }

        .br-apply-btn:hover {
            background: #15803d;
        }

        .br-clear-filters {
            display: block;
            text-align: center;
            font-size: .76rem;
            color: #ef4444;
            margin-top: 12px;
            text-decoration: none;
            font-weight: 500;
        }

        .br-clear-filters:hover {
            text-decoration: underline;
        }

        .br-filter-badge {
            background: #ef4444;
            color: #fff;
            font-size: .62rem;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 10px;
            margin-left: 4px;
            vertical-align: middle;
        }

        .filter-chips-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 0 0 14px;
        }

        .filter-chip-label {
            font-size: 12px;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-right: 2px;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: #f0fdf4;
            border: 1px solid rgba(5, 150, 105, 0.25);
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 600;
            color: #065f46;
            text-decoration: none;
        }

        .filter-chip .chip-remove {
            color: #059669;
            font-size: 10px;
            margin-left: 2px;
            opacity: 0.7;
        }

        .filter-chip:hover {
            background: #dcfce7;
            border-color: #059669;
            text-decoration: none;
            color: #065f46;
        }

        /* ── Wishlist heart button ── */
        .wl-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 5;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.92);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            color: #94a3b8;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: color .2s, transform .2s, background .2s;
        }

        .wl-btn:hover {
            transform: scale(1.15);
            background: #fff;
        }

        .wl-btn.saved {
            color: #ef4444;
        }

        .wl-btn .fa-heart {
            pointer-events: none;
        }

        /* ── Mobile toggle ── */
        .br-sidebar-toggle {
            display: none;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 14px;
            font-size: .82rem;
            font-weight: 600;
            color: #0f172a;
            cursor: pointer;
            margin-bottom: 12px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        }

        .br-sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 200;
        }

        .br-sidebar-backdrop.active {
            display: block;
        }

        @media(max-width:768px) {
            .br-layout {
                padding: 16px 0 36px;
            }

            .br-sidebar-toggle {
                display: flex;
            }

            .br-sidebar {
                position: fixed;
                top: 0;
                left: -260px;
                bottom: 0;
                z-index: 201;
                border-radius: 0;
                overflow-y: auto;
                transition: left .3s ease;
            }

            .br-sidebar.open {
                left: 0;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="br-wrapper">

        <!-- ===== HERO HEADER ===== -->
        <div class="br-hero" style="background:<?php echo $grad; ?>">
            <div class="br-hero-shapes"></div>
            <div class="br-hero-content">
                <div class="br-hero-icon">
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <div>
                    <h1 class="br-hero-title"><?php echo $category; ?></h1>
                    <p class="br-hero-sub">Browse fresh <?php echo strtolower($category); ?> from trusted local farmers</p>
                </div>
            </div>
        </div>

        <!-- ===== CATEGORY TABS ===== -->
        <div class="br-tabs-bar">
            <div class="br-tabs-scroll">
                <?php foreach ($valid_categories as $cat):
                    $c = $cat_meta[$cat];
                    $isActive = $cat === $category;
                ?>
                    <a href="browse.php?category=<?php echo urlencode($cat); ?><?php echo $location_filter !== '' ? '&location=' . urlencode($location_filter) : ''; ?>"
                        class="br-tab <?php echo $isActive ? 'br-tab-active' : ''; ?>"
                        title="<?php echo $cat; ?>">
                        <i class="fas <?php echo $c['icon']; ?> br-tab-icon"></i>
                        <span><?php echo $cat; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ===== PRODUCTS AREA ===== -->
        <div class="br-layout">

            <!-- ── SIDEBAR ── -->
            <aside class="br-sidebar" id="brSidebar">
                <form id="brFilterForm" method="GET" action="browse.php">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">

                    <div class="br-sidebar-header">
                        <h3><i class="fas fa-sliders-h" style="color:#16a34a;margin-right:6px;"></i>Filters</h3>
                        <?php if ($active_filter_count > 0): ?>
                            <a href="browse.php?category=<?php echo urlencode($category); ?>" class="br-clear-filters" style="margin:0;font-size:.72rem;">Clear all</a>
                        <?php endif; ?>
                    </div>

                    <!-- Sort By -->
                    <div class="br-filter-section">
                        <div class="br-filter-title">Sort By</div>
                        <?php
                        $sort_opts = [
                            'newest'     => ['fa-clock',       'Newest First'],
                            'price_asc'  => ['fa-arrow-up',    'Price: Low → High'],
                            'price_desc' => ['fa-arrow-down',  'Price: High → Low'],
                            'most_bids'  => ['fa-gavel',       'Most Bids'],
                        ];
                        foreach ($sort_opts as $val => $opt): ?>
                            <label class="br-filter-option <?php echo $sort === $val ? 'is-active' : ''; ?>">
                                <input type="radio" name="sort" value="<?php echo $val; ?>" <?php echo $sort === $val ? 'checked' : ''; ?>>
                                <i class="fas <?php echo $opt[0]; ?>" style="width:14px;text-align:center;font-size:.75rem;color:#64748b;"></i>
                                <?php echo $opt[1]; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Auction Status -->
                    <div class="br-filter-section">
                        <div class="br-filter-title">Auction Status</div>
                        <?php
                        $status_opts = [
                            'all'      => ['fa-th-large',        'All',       '#64748b'],
                            'live'     => ['fa-circle',          'Live Now',  '#ef4444'],
                            'upcoming' => ['fa-hourglass-start', 'Upcoming',  '#f59e0b'],
                            'ended'    => ['fa-flag-checkered',  'Ended',     '#94a3b8'],
                        ];
                        foreach ($status_opts as $val => $opt): ?>
                            <label class="br-filter-option <?php echo $status_filter === $val ? 'is-active' : ''; ?>">
                                <input type="radio" name="status" value="<?php echo $val; ?>" <?php echo $status_filter === $val ? 'checked' : ''; ?>>
                                <i class="fas <?php echo $opt[0]; ?>" style="width:14px;text-align:center;font-size:.65rem;color:<?php echo $opt[2]; ?>;"></i>
                                <?php echo $opt[1]; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Price Range -->
                    <div class="br-filter-section">
                        <div class="br-filter-title">Price Range (৳)</div>
                        <div class="br-price-row">
                            <input type="number" name="min_price" placeholder="Min" min="0" step="1"
                                value="<?php echo $min_price !== '' ? (int)$min_price : ''; ?>">
                            <span style="color:#cbd5e1;">&mdash;</span>
                            <input type="number" name="max_price" placeholder="Max" min="0" step="1"
                                value="<?php echo $max_price !== '' ? (int)$max_price : ''; ?>">
                        </div>
                    </div>

                    <!-- Farmer Location -->
                    <div class="br-filter-section">
                        <div class="br-filter-title">Farmer Location</div>
                        <div class="br-price-row">
                            <input type="text" name="location" placeholder="e.g. Dhaka, Chittagong"
                                value="<?php echo htmlspecialchars($location_filter); ?>">
                        </div>
                        <button type="submit" class="br-apply-btn"><i class="fas fa-check"></i> Apply</button>
                    </div>

                </form>
            </aside>

            <!-- Mobile backdrop -->
            <div class="br-sidebar-backdrop" id="brBackdrop"></div>

            <!-- ── MAIN CONTENT ── -->
            <div class="br-main">

                <!-- Mobile sidebar toggle -->
                <button class="br-sidebar-toggle" id="brSidebarToggle" type="button">
                    <i class="fas fa-sliders-h"></i> Filters
                    <?php if ($active_filter_count > 0): ?>
                        <span class="br-filter-badge"><?php echo $active_filter_count; ?></span>
                    <?php endif; ?>
                </button>

                <?php
                // Build dynamic query
                $where_clauses = ["posts.is_approved = 1", "posts.status IN ('active', 'sold', 'delivered')", "posts.category = ?"];
                $params = [$category];
                $types  = "s";

                if ($min_price !== '') {
                    $where_clauses[] = "posts.price >= ?";
                    $params[] = $min_price;
                    $types .= "d";
                }
                if ($max_price !== '') {
                    $where_clauses[] = "posts.price <= ?";
                    $params[] = $max_price;
                    $types .= "d";
                }
                if ($location_filter !== '') {
                    $where_clauses[] = "users.location LIKE ?";
                    $params[] = '%' . $location_filter . '%';
                    $types .= "s";
                }

                $status_condition = '';
                if ($status_filter === 'live') {
                    $status_condition = " AND posts.status = 'active' AND posts.auction_start_date <= NOW() AND posts.auction_end_date > NOW()";
                } elseif ($status_filter === 'upcoming') {
                    $status_condition = " AND posts.status = 'active' AND posts.auction_start_date > NOW()";
                } elseif ($status_filter === 'ended') {
                    $status_condition = " AND (posts.status IN ('sold', 'delivered') OR posts.auction_end_date <= NOW())";
                }

                if ($sort === 'price_asc')      $sort_sql = 'posts.price ASC';
                elseif ($sort === 'price_desc') $sort_sql = 'posts.price DESC';
                elseif ($sort === 'most_bids')  $sort_sql = 'total_bids DESC';
                else                            $sort_sql = 'posts.created_at DESC';

                $where_str = implode(' AND ', $where_clauses) . $status_condition;
                $sql = "SELECT posts.*, users.username,
                           (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as total_bids,
                          (SELECT MAX(comment_text) FROM comments WHERE post_id = posts.id) as max_bid,
                          EXISTS(SELECT 1 FROM comments WHERE post_id = posts.id AND is_approved = 1) as has_winner
                    FROM posts
                    JOIN users ON posts.farmer_id = users.id
                    WHERE {$where_str}
                    ORDER BY {$sort_sql}";
                $products_stmt = $conn->prepare($sql);
                $products_stmt->bind_param($types, ...$params);
                $products_stmt->execute();
                $products_result = $products_stmt->get_result();
                $total_products  = $products_result->num_rows;
                ?>

                <!-- Toolbar -->
                <div class="br-toolbar">
                    <span class="br-product-count">
                        <strong><?php echo $total_products; ?></strong>
                        product<?php echo $total_products != 1 ? 's' : ''; ?> found
                        <?php if ($active_filter_count > 0): ?>
                            <span style="color:#94a3b8;font-weight:400;font-size:.82rem;"> &middot; <?php echo $active_filter_count; ?> filter<?php echo $active_filter_count > 1 ? 's' : ''; ?> active</span>
                        <?php endif; ?>
                    </span>
                    <div class="br-search-inline">
                        <i class="fas fa-search"></i>
                        <input type="text" id="br-search" placeholder="Search <?php echo strtolower($category); ?>…">
                    </div>
                </div>

                <?php if ($active_filter_count > 0): ?>
                    <div class="filter-chips-row">
                        <span class="filter-chip-label"><i class="fas fa-filter"></i> Active:</span>
                        <?php if ($sort !== 'newest'): ?>
                            <a href="browse.php?category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status_filter); ?>&min_price=<?php echo $min_price !== '' ? (int)$min_price : ''; ?>&max_price=<?php echo $max_price !== '' ? (int)$max_price : ''; ?>&location=<?php echo urlencode($location_filter); ?>" class="filter-chip">
                                <i class="fas fa-sort"></i> Sort: <?php echo htmlspecialchars(str_replace('_', ' ', $sort)); ?> <span class="chip-remove"><i class="fas fa-times"></i></span>
                            </a>
                        <?php endif; ?>
                        <?php if ($status_filter !== 'all'): ?>
                            <a href="browse.php?category=<?php echo urlencode($category); ?>&sort=<?php echo urlencode($sort); ?>&min_price=<?php echo $min_price !== '' ? (int)$min_price : ''; ?>&max_price=<?php echo $max_price !== '' ? (int)$max_price : ''; ?>&location=<?php echo urlencode($location_filter); ?>" class="filter-chip">
                                <i class="fas fa-circle"></i> <?php echo htmlspecialchars(ucfirst($status_filter)); ?> <span class="chip-remove"><i class="fas fa-times"></i></span>
                            </a>
                        <?php endif; ?>
                        <?php if ($min_price !== '' || $max_price !== ''): ?>
                            <a href="browse.php?category=<?php echo urlencode($category); ?>&sort=<?php echo urlencode($sort); ?>&status=<?php echo urlencode($status_filter); ?>&location=<?php echo urlencode($location_filter); ?>" class="filter-chip">
                                <i class="fas fa-tag"></i> ৳<?php echo $min_price !== '' ? (int)$min_price : 0; ?> &ndash; ৳<?php echo $max_price !== '' ? (int)$max_price : 0; ?> <span class="chip-remove"><i class="fas fa-times"></i></span>
                            </a>
                        <?php endif; ?>
                        <?php if ($location_filter !== ''): ?>
                            <a href="browse.php?category=<?php echo urlencode($category); ?>&sort=<?php echo urlencode($sort); ?>&status=<?php echo urlencode($status_filter); ?>&min_price=<?php echo $min_price !== '' ? (int)$min_price : ''; ?>&max_price=<?php echo $max_price !== '' ? (int)$max_price : ''; ?>" class="filter-chip">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($location_filter); ?> <span class="chip-remove"><i class="fas fa-times"></i></span>
                            </a>
                        <?php endif; ?>
                        <a href="browse.php?category=<?php echo urlencode($category); ?>" class="filter-chip" style="background:#fff5f5;border-color:rgba(220,38,38,0.2);color:#b91c1c;">
                            <i class="fas fa-redo"></i> Clear all
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($total_products > 0): ?>

                    <div class="br-grid" id="br-grid">
                        <?php while ($post = $products_result->fetch_assoc()):
                            $post_id          = $post['id'];
                            $current_time     = time();
                            $auction_start    = strtotime($post['auction_start_date']);
                            $auction_end      = strtotime($post['auction_end_date']);
                            $is_sold          = (in_array($post['status'], ['sold', 'delivered'], true) || (int)$post['has_winner'] === 1);
                            $is_ended         = ($is_sold || $current_time >= $auction_end);
                            $is_live          = (!$is_ended && $current_time >= $auction_start);
                            $total_bids       = (int)$post['total_bids'];
                            $max_bid          = $post['max_bid'];
                            $initials         = strtoupper(substr($post['username'], 0, 2));
                            // Wishlist state
                            $is_wishlisted = false;
                            if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user') {
                                $wl_chk = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND post_id = ? LIMIT 1");
                                $wl_chk->bind_param("ii", $_SESSION['user_id'], $post_id);
                                $wl_chk->execute();
                                $is_wishlisted = $wl_chk->get_result()->num_rows > 0;
                                $wl_chk->close();
                            }
                        ?>
                            <a href="product_detail.php?id=<?php echo $post_id; ?>"
                                class="br-card"
                                data-name="<?php echo strtolower(htmlspecialchars($post['product_name'])); ?>">

                                <!-- Image -->
                                <div class="br-card-img-wrap">
                                    <?php if ($post['image']): ?>
                                        <img src="assets/images/<?php echo htmlspecialchars($post['image']); ?>"
                                            alt="<?php echo htmlspecialchars($post['product_name']); ?>"
                                            class="br-card-img">
                                    <?php else: ?>
                                        <div class="br-card-img-placeholder">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                    <?php endif; ?>

                                    <!-- View overlay on hover -->
                                    <div class="br-view-overlay">
                                        <span class="br-view-btn"><i class="fas fa-eye"></i> View Details</span>
                                    </div>

                                    <!-- Status overlay badge -->
                                    <?php if ($is_sold): ?>
                                        <div class="br-status-badge br-ended">
                                            <i class="fas fa-check-circle"></i> SOLD
                                        </div>
                                    <?php elseif ($is_ended): ?>
                                        <div class="br-status-badge br-ended">
                                            <i class="fas fa-flag-checkered"></i> Ended
                                        </div>
                                    <?php elseif ($is_live): ?>
                                        <div class="br-status-badge br-live">
                                            <span class="br-live-dot"></span> LIVE
                                        </div>
                                    <?php else: ?>
                                        <div class="br-status-badge br-upcoming">
                                            <i class="fas fa-hourglass-start"></i> Upcoming
                                        </div>
                                    <?php endif; ?>

                                    <!-- Bid count pill on image -->
                                    <div class="br-bids-pill">
                                        <i class="fas fa-gavel"></i> <?php echo $total_bids; ?> bid<?php echo $total_bids != 1 ? 's' : ''; ?>
                                    </div>

                                    <!-- Wishlist heart -->
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user' && !$is_ended): ?>
                                        <button class="wl-btn <?php echo $is_wishlisted ? 'saved' : ''; ?>"
                                            data-post-id="<?php echo $post_id; ?>"
                                            title="<?php echo $is_wishlisted ? 'Remove from wishlist' : 'Save to wishlist'; ?>"
                                            onclick="event.preventDefault();event.stopPropagation();toggleWishlist(this);">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <!-- Body -->
                                <div class="br-card-body">
                                    <h3 class="br-card-title"><?php echo htmlspecialchars($post['product_name']); ?></h3>

                                    <div class="br-card-price-row">
                                        <div>
                                            <span class="br-price-label">Starting at</span>
                                            <span class="br-price-val">৳<?php echo number_format($post['price'], 2); ?></span>
                                        </div>
                                        <?php if ($max_bid && $max_bid > $post['price']): ?>
                                            <div class="br-current-bid">
                                                <span class="br-cb-label">Current</span>
                                                <span class="br-cb-val">৳<?php echo number_format($max_bid, 2); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="br-card-qty">
                                        <i class="fas fa-balance-scale"></i>
                                        <?php echo htmlspecialchars($post['quantity']); ?> <?php echo htmlspecialchars($post['unit']); ?>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div class="br-card-footer">
                                    <div class="br-farmer-info" onclick="event.preventDefault();event.stopPropagation();window.location.href='farmer/profile.php?id=<?php echo $post['farmer_id']; ?>'" title="View <?php echo htmlspecialchars($post['username']); ?>'s profile">
                                        <span class="br-farmer-avatar"><?php echo $initials; ?></span>
                                        <span class="br-farmer-name"><?php echo htmlspecialchars($post['username']); ?></span>
                                        <i class="fas fa-external-link-alt br-farmer-link-icon"></i>
                                    </div>
                                    <?php if ($is_sold): ?>
                                        <div class="br-ended-pill">
                                            <i class="fas fa-trophy"></i>
                                            <span>Sold</span>
                                        </div>
                                    <?php elseif ($is_ended): ?>
                                        <div class="br-ended-pill">
                                            <i class="fas fa-gavel"></i>
                                            <span>Auction Ended</span>
                                        </div>
                                    <?php elseif ($is_live): ?>
                                        <div class="br-countdown" data-end="<?php echo $auction_end; ?>">
                                            <i class="fas fa-clock"></i>
                                            <span class="br-cd-label">Ends in</span>
                                            <span class="br-cd-text">–</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="br-starts-on" data-start="<?php echo $auction_start; ?>">
                                            <i class="fas fa-hourglass-start"></i>
                                            <span class="br-starts-label">Starts in</span>
                                            <span class="br-cd-text">–</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>

                    <!-- Empty search state (hidden by default) -->
                    <div class="br-no-results" id="br-no-results" style="display:none;">
                        <i class="fas fa-search-minus fa-3x"></i>
                        <p>No products match your search.</p>
                    </div>

                <?php else: ?>
                    <div class="br-empty-state">
                        <div class="br-empty-icon" style="background:<?php echo $light; ?>">
                            <i class="fas <?php echo $icon; ?> fa-3x" style="color:<?php echo 'var(--primary-color)'; ?>"></i>
                        </div>
                        <h3>No <?php echo $category; ?> listed yet</h3>
                        <p>Check back soon — farmers are adding new products every day!</p>
                        <a href="index.php" class="br-back-btn">
                            <i class="fas fa-home"></i> Back to Home
                        </a>
                    </div>
                <?php endif; ?>

                <?php $products_stmt->close(); ?>

            </div><!-- /br-main -->
        </div><!-- /br-layout -->
    </div><!-- /br-wrapper -->

    <script>
        (function() {
            // ── Live countdowns ──────────────────────────────────────────
            function pad(n) {
                return String(n).padStart(2, '0');
            }

            document.querySelectorAll('.br-countdown').forEach(function(el) {
                const end = parseInt(el.dataset.end, 10);
                const textEl = el.querySelector('.br-cd-text');

                function tick() {
                    const diff = end - Math.floor(Date.now() / 1000);
                    if (diff <= 0) {
                        // swap badge to Ended
                        const card = el.closest('.br-card');
                        if (card) {
                            const badge = card.querySelector('.br-status-badge');
                            if (badge) {
                                badge.className = 'br-status-badge br-ended';
                                badge.innerHTML = '<i class="fas fa-flag-checkered"></i> Ended';
                            }
                        }
                        // swap footer pill to Ended
                        el.outerHTML = '<div class="br-ended-pill"><i class="fas fa-gavel"></i><span>Auction Ended</span></div>';
                        return;
                    }
                    const d = Math.floor(diff / 86400);
                    const h = Math.floor((diff % 86400) / 3600);
                    const m = Math.floor((diff % 3600) / 60);
                    const s = diff % 60;
                    textEl.textContent = d > 0 ? `${d}d ${pad(h)}h ${pad(m)}m` :
                        h > 0 ? `${pad(h)}h ${pad(m)}m ${pad(s)}s` :
                        `${pad(m)}m ${pad(s)}s`;
                }
                tick();
                setInterval(tick, 1000);
            });

            // br-starts-on countdown (data-start)
            document.querySelectorAll('.br-starts-on[data-start]').forEach(function(el) {
                const start = parseInt(el.dataset.start, 10);
                const textEl = el.querySelector('.br-cd-text');

                function tick() {
                    const diff = start - Math.floor(Date.now() / 1000);
                    if (diff <= 0) {
                        textEl.textContent = 'Starting...';
                        return;
                    }
                    const d = Math.floor(diff / 86400);
                    const h = Math.floor((diff % 86400) / 3600);
                    const m = Math.floor((diff % 3600) / 60);
                    const s = diff % 60;
                    textEl.textContent = d > 0 ? `${d}d ${pad(h)}h ${pad(m)}m` :
                        h > 0 ? `${pad(h)}h ${pad(m)}m ${pad(s)}s` :
                        `${pad(m)}m ${pad(s)}s`;
                }
                tick();
                setInterval(tick, 1000);
            });

            // ── Inline search filter ─────────────────────────────────────
            const searchInput = document.getElementById('br-search');
            const grid = document.getElementById('br-grid');
            const noResults = document.getElementById('br-no-results');

            if (searchInput && grid) {
                searchInput.addEventListener('input', function() {
                    const q = this.value.trim().toLowerCase();
                    const cards = grid.querySelectorAll('.br-card');
                    let visible = 0;
                    cards.forEach(function(card) {
                        const match = card.dataset.name.includes(q);
                        card.style.display = match ? '' : 'none';
                        if (match) visible++;
                    });
                    if (noResults) noResults.style.display = visible === 0 ? 'flex' : 'none';
                });
            }

            // ── Staggered card entrance animation ────────────────────────
            document.querySelectorAll('.br-card').forEach(function(card, i) {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(function() {
                    card.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 60 + i * 60);
            });
            // ── Wishlist toggle ──────────────────────────────────────
            window.toggleWishlist = function(btn) {
                var postId = btn.dataset.postId;
                fetch('wishlist_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=toggle&post_id=' + postId
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(data) {
                        if (data.login_required) {
                            window.location.href = 'index.php?auth=login';
                            return;
                        }
                        if (data.success) {
                            if (data.saved) {
                                btn.classList.add('saved');
                                btn.title = 'Remove from wishlist';
                                showWlToast('♥ Saved to wishlist');
                            } else {
                                btn.classList.remove('saved');
                                btn.title = 'Save to wishlist';
                                showWlToast('Removed from wishlist');
                            }
                        }
                    });
            }

            window.showWlToast = function showWlToast(msg) {
                var t = document.getElementById('wlToast');
                if (!t) {
                    t = document.createElement('div');
                    t.id = 'wlToast';
                    t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(20px);background:#1e293b;color:#fff;padding:10px 20px;border-radius:50px;font-size:.82rem;font-weight:600;z-index:9999;opacity:0;transition:opacity .25s,transform .25s;pointer-events:none;';
                    document.body.appendChild(t);
                }
                t.textContent = msg;
                t.style.opacity = '1';
                t.style.transform = 'translateX(-50%) translateY(0)';
                clearTimeout(t._timer);
                t._timer = setTimeout(function() {
                    t.style.opacity = '0';
                    t.style.transform = 'translateX(-50%) translateY(20px)';
                }, 2200);
            }

            // ── Sidebar toggle (mobile) ──────────────────────────────────
            var sidebarToggle = document.getElementById('brSidebarToggle');
            var sidebar = document.getElementById('brSidebar');
            var backdrop = document.getElementById('brBackdrop');
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('open');
                    backdrop.classList.toggle('active');
                    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
                });
                backdrop.addEventListener('click', function() {
                    sidebar.classList.remove('open');
                    backdrop.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }

            // ── Auto-submit filter radios ─────────────────────────────────
            var filterForm = document.getElementById('brFilterForm');
            if (filterForm) {
                filterForm.querySelectorAll('input[type="radio"]').forEach(function(r) {
                    r.addEventListener('change', function() {
                        filterForm.submit();
                    });
                });
            }
        })();
    </script>

    <?php include 'includes/footer.php'; ?>
</body>

</html>