<?php
session_start();
include 'includes/db.php'; // Database connection
date_default_timezone_set('Asia/Dhaka');
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get search parameters
$search_query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$category_filter = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$location_filter = isset($_GET['location']) ? sanitize($_GET['location']) : '';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 100000;
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Fetch valid categories from the database for validation
$valid_categories = [];
$valid_cats_result = $conn->query("SELECT DISTINCT category FROM posts WHERE is_approved = 1 AND status = 'active' AND category IS NOT NULL AND category != '' ORDER BY category");
if ($valid_cats_result) {
    while ($vc = $valid_cats_result->fetch_assoc()) {
        $valid_categories[] = $vc['category'];
    }
}

// Build search query
$where_conditions = ["posts.is_approved = 1"];

// Add search terms to all fields
if (!empty($search_query)) {
    $search_terms = array_filter(array_map('trim', explode(' ', $search_query)));
    $search_conditions = [];

    foreach ($search_terms as $term) {
        $term_escaped = $conn->real_escape_string($term);
        // Search with LIKE and MATCH AGAINST for better results
        $search_conditions[] = "(
            posts.product_name LIKE '%$term_escaped%' OR
            posts.category LIKE '%$term_escaped%' OR
            posts.description LIKE '%$term_escaped%'
        )";
    }

    if (!empty($search_conditions)) {
        $where_conditions[] = "(" . implode(" AND ", $search_conditions) . ")";
    }
}

// Add category filter
if (!empty($category_filter) && in_array($category_filter, $valid_categories)) {
    $category_escaped = $conn->real_escape_string($category_filter);
    $where_conditions[] = "posts.category = '$category_escaped'";
}

// Add location filter (farmer location)
if (!empty($location_filter)) {
    $location_escaped = $conn->real_escape_string($location_filter);
    $where_conditions[] = "users.location LIKE '%$location_escaped%'";
}

// Add price filter
$where_conditions[] = "posts.price BETWEEN $min_price AND $max_price";

// Add status filter
if (!empty($status_filter)) {
    if ($status_filter === 'live') {
        $where_conditions[] = "(posts.status = 'active' AND posts.auction_start_date <= NOW() AND posts.auction_end_date > NOW())";
    } elseif ($status_filter === 'upcoming') {
        $where_conditions[] = "(posts.status = 'active' AND posts.auction_start_date > NOW())";
    } elseif ($status_filter === 'ending_soon') {
        $where_conditions[] = "(posts.status = 'active' AND posts.auction_end_date > NOW() AND posts.auction_end_date <= DATE_ADD(NOW(), INTERVAL 24 HOUR))";
    }
} else {
    // Default: show active auctions
    $where_conditions[] = "posts.status = 'active'";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) as total FROM posts
                JOIN users ON posts.farmer_id = users.id
                WHERE $where_clause";
$count_result = $conn->query($count_query);
$total_results = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_results / $per_page);

// Get products
$query = "SELECT posts.*, users.username,
          (SELECT COUNT(*) FROM comments WHERE post_id = posts.id AND is_approved = 1) as total_bids,
          (SELECT MAX(CAST(comment_text AS DECIMAL(10,2))) FROM comments WHERE post_id = posts.id AND is_approved = 1) as highest_bid
          FROM posts 
          JOIN users ON posts.farmer_id = users.id 
          WHERE $where_clause
          ORDER BY posts.created_at DESC
          LIMIT $per_page OFFSET $offset";

$products_result = $conn->query($query);

// Get all categories for filter
$categories_query = "SELECT DISTINCT category FROM posts WHERE is_approved = 1 AND status = 'active' ORDER BY category";
$categories_result = $conn->query($categories_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - Farmers' Marketplace</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        /* ── Page Layout ── */
        .search-results-container {
            min-height: calc(100vh - 200px);
            padding: 30px 16px;
        }

        /* ── Search Header ── */
        .search-header {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            padding: 28px 32px;
            margin-bottom: 24px;
            border-radius: 20px;
            box-shadow: 0 8px 28px rgba(5, 150, 105, 0.30);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }

        .search-header-left h1 {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.3px;
        }

        .search-header-left .search-info {
            font-size: 13.5px;
            opacity: 0.88;
            font-weight: 500;
            line-height: 1.5;
        }

        .search-header-left .search-info strong {
            background: rgba(255, 255, 255, 0.22);
            padding: 1px 8px;
            border-radius: 6px;
            font-weight: 700;
        }

        .search-header-right {
            flex: 0 0 auto;
            width: 340px;
            max-width: 100%;
        }

        .header-search-form {
            display: flex;
            background: rgba(255, 255, 255, 0.18);
            border: 1.5px solid rgba(255, 255, 255, 0.35);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.25s ease;
        }

        .header-search-form:focus-within {
            background: rgba(255, 255, 255, 0.26);
            border-color: rgba(255, 255, 255, 0.65);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.15);
        }

        .header-search-input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            padding: 11px 16px;
            color: white;
            font-size: 14px;
            font-weight: 500;
        }

        .header-search-input::placeholder {
            color: rgba(255, 255, 255, 0.65);
        }

        .header-search-btn {
            background: rgba(255, 255, 255, 0.22);
            border: none;
            border-left: 1.5px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 11px 16px;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 14px;
        }

        .header-search-btn:hover {
            background: rgba(255, 255, 255, 0.32);
        }

        /* ── Filter Chips ── */
        .filter-chips-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
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

        /* ── Filters Sidebar ── */
        .filters-sidebar {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 18px 16px;
            position: sticky;
            top: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }

        .filters-sidebar>h5 {
            font-size: .88rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 14px;
            padding-bottom: 0;
            border-bottom: none;
        }

        .filter-section {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f1f5f9;
        }

        .filter-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .filter-title {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #94a3b8;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .filter-title i {
            color: #64748b;
            font-size: .75rem;
        }

        .filter-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px;
            border-radius: 8px;
            margin-bottom: 2px;
            transition: background .15s;
        }

        .filter-item:hover {
            background: #f8fafc;
        }

        .filter-item input[type="radio"] {
            flex-shrink: 0;
            cursor: pointer;
            accent-color: #16a34a;
            margin: 0;
        }

        .filter-item label {
            margin-bottom: 0;
            cursor: pointer;
            flex: 1;
            font-size: .82rem;
            color: #475569;
            line-height: 1.4;
        }

        .filter-item input[type="radio"]:checked+label {
            color: #15803d;
            font-weight: 600;
        }

        /* ── Price Range ── */
        .price-input-group {
            display: flex;
            gap: 6px;
            align-items: center;
            margin-top: 6px;
        }

        .price-input-group input {
            width: 100%;
            min-width: 0;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 8px;
            font-size: .78rem;
            outline: none;
            transition: border-color .15s;
            background: #fff;
        }

        .price-input-group input:focus {
            border-color: #16a34a;
        }

        /* ── Filter Buttons ── */
        .btn-apply-filters {
            width: 100%;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            padding: 8px;
            font-size: .8rem;
            font-weight: 600;
            transition: background .15s;
            margin-top: 8px;
            box-shadow: none;
        }

        .btn-apply-filters:hover {
            background: #15803d;
        }

        .clear-filters-btn {
            display: block;
            width: 100%;
            padding: 8px;
            background: transparent;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            font-size: .76rem;
            font-weight: 500;
            color: #ef4444;
            transition: all .15s;
            margin-top: 8px;
            text-align: center;
            text-decoration: none !important;
        }

        .clear-filters-btn:hover {
            background: #fff1f2;
            border-color: #fecdd3;
            color: #dc2626;
            text-decoration: none !important;
        }

        /* ── Results Section ── */
        .results-section {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.07);
            border: 1px solid #ebebeb;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f0fdf4;
        }

        .results-count {
            font-size: 13.5px;
            color: #6b7280;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .results-count .count-badge {
            background: #f0fdf4;
            color: #059669;
            border: 1px solid rgba(5, 150, 105, 0.2);
            border-radius: 999px;
            font-weight: 700;
            font-size: 12px;
            padding: 2px 10px;
        }

        .sort-dropdown {
            padding: 9px 14px;
            border: 1.5px solid #e0e0e0;
            border-radius: 7px;
            font-size: 14px;
            cursor: pointer;
            background: #fafafa;
            color: #333;
            transition: border-color 0.2s;
            min-width: 190px;
        }

        .sort-dropdown:hover,
        .sort-dropdown:focus {
            outline: none;
            border-color: #059669;
            background: white;
        }

        /* ── Products Grid ── */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 32px;
            padding: 4px 4px 16px;
            overflow: visible;
        }

        @media (max-width: 1100px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 14px;
            }

            .search-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 22px 18px;
            }

            .search-header-right {
                width: 100%;
            }

            .search-header-left h1 {
                font-size: 20px;
            }

            .results-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .sort-dropdown {
                width: 100%;
                min-width: unset;
            }
        }

        /* ── No Results ── */
        .no-results {
            text-align: center;
            padding: 70px 30px;
            background: #fafafa;
            border-radius: 12px;
        }

        .no-results i {
            font-size: 72px;
            color: #ddd;
            margin-bottom: 22px;
            display: block;
        }

        .no-results h3 {
            color: #555;
            margin-bottom: 12px;
            font-size: 22px;
            font-weight: 700;
        }

        .no-results p {
            color: #999;
            font-size: 15px;
            margin-bottom: 22px;
        }

        /* ── Load More ── */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #f0f0f0;
        }

        .load-more-btn {
            padding: 13px 48px;
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.3s;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 4px 14px rgba(5, 150, 105, 0.28);
            text-decoration: none !important;
            display: inline-block;
            text-align: center;
        }

        .load-more-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(5, 150, 105, 0.38);
            color: white;
            text-decoration: none !important;
        }

        .load-more-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* ── Misc ── */
        .search-suggestion {
            background: #f0fdf4;
            border-left: 4px solid #059669;
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 18px;
            font-weight: 500;
            font-size: 14px;
        }

        .search-suggestion strong {
            color: #059669;
            font-weight: 700;
        }

        .btn-primary {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            border: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(5, 150, 105, 0.36);
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container-fluid" style="max-width: 1400px; margin: 0 auto;">
        <div class="search-results-container">
            <!-- Search Header -->
            <div class="search-header">
                <div class="search-header-left">
                    <h1><i class="fas fa-seedling"></i> Search Results</h1>
                    <div class="search-info">
                        <?php if (!empty($search_query)): ?>
                            <strong>"<?php echo htmlspecialchars($search_query); ?>"</strong>
                            <?php if ($total_results > 0): ?>
                                &nbsp;&mdash; <?php echo $total_results; ?> result<?php echo $total_results !== 1 ? 's' : ''; ?> found
                            <?php else: ?>
                                &nbsp;&mdash; No results found
                            <?php endif; ?>
                        <?php else: ?>
                            Browse all available products
                        <?php endif; ?>
                    </div>
                </div>
                <div class="search-header-right">
                    <form method="GET" action="search.php" class="header-search-form">
                        <input type="text" name="q" class="header-search-input"
                            placeholder="Search products..."
                            value="<?php echo htmlspecialchars($search_query); ?>">
                        <?php if (!empty($category_filter)): ?>
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                        <?php endif; ?>
                        <?php if (!empty($location_filter)): ?>
                            <input type="hidden" name="location" value="<?php echo htmlspecialchars($location_filter); ?>">
                        <?php endif; ?>
                        <?php if (!empty($status_filter)): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                        <?php endif; ?>
                        <button type="submit" class="header-search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>

            <?php
            $has_active_filters = !empty($category_filter) || !empty($location_filter) || $min_price > 0 || $max_price < 100000 || !empty($status_filter);
            if ($has_active_filters):
            ?>
                <div class="filter-chips-row">
                    <span class="filter-chip-label"><i class="fas fa-filter"></i> Active:</span>
                    <?php if (!empty($category_filter)): ?>
                        <a href="search.php?q=<?php echo urlencode($search_query); ?>&location=<?php echo urlencode($location_filter); ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&status=<?php echo urlencode($status_filter); ?>" class="filter-chip">
                            <i class="fas fa-th"></i> <?php echo htmlspecialchars($category_filter); ?> <span class="chip-remove"><i class="fas fa-times"></i></span>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($location_filter)): ?>
                        <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo urlencode($category_filter); ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&status=<?php echo urlencode($status_filter); ?>" class="filter-chip">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($location_filter); ?> <span class="chip-remove"><i class="fas fa-times"></i></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($min_price > 0 || $max_price < 100000): ?>
                        <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo urlencode($category_filter); ?>&location=<?php echo urlencode($location_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="filter-chip">
                            <i class="fas fa-tag"></i> ৳<?php echo $min_price; ?> &ndash; ৳<?php echo $max_price; ?> <span class="chip-remove"><i class="fas fa-times"></i></span>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($status_filter)): ?>
                        <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo urlencode($category_filter); ?>&location=<?php echo urlencode($location_filter); ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>" class="filter-chip">
                            <i class="fas fa-circle"></i> <?php echo ucfirst(str_replace('_', ' ', $status_filter)); ?> <span class="chip-remove"><i class="fas fa-times"></i></span>
                        </a>
                    <?php endif; ?>
                    <a href="search.php?q=<?php echo urlencode($search_query); ?>" class="filter-chip" style="background:#fff5f5;border-color:rgba(220,38,38,0.2);color:#b91c1c;">
                        <i class="fas fa-redo"></i> Clear all
                    </a>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Filters Sidebar -->
                <div class="col-lg-3">
                    <div class="filters-sidebar">
                        <h5 class="mb-4" style="font-weight: 700; color: #333; display:flex; align-items:center; gap:8px;">
                            <span style="background:#f0fdf4;border:1px solid rgba(5,150,105,0.2);border-radius:8px;padding:5px 8px;color:#059669;"><i class="fas fa-sliders-h"></i></span> Filters
                        </h5>

                        <form id="filterForm" method="GET" action="search.php">
                            <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">

                            <!-- Category Filter -->
                            <div class="filter-section">
                                <div class="filter-title">
                                    <i class="fas fa-th"></i> Category
                                </div>
                                <div class="filter-item">
                                    <input type="radio" id="cat_all" name="category" value=""
                                        <?php echo empty($category_filter) ? 'checked' : ''; ?>>
                                    <label for="cat_all">All Categories</label>
                                </div>
                                <?php foreach ($valid_categories as $cat_name): ?>
                                    <div class="filter-item">
                                        <input type="radio" id="cat_<?php echo str_replace(' ', '_', $cat_name); ?>"
                                            name="category" value="<?php echo htmlspecialchars($cat_name); ?>"
                                            <?php echo $category_filter === $cat_name ? 'checked' : ''; ?>>
                                        <label for="cat_<?php echo str_replace(' ', '_', $cat_name); ?>">
                                            <?php echo htmlspecialchars($cat_name); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Price Filter -->
                            <div class="filter-section">
                                <div class="filter-title">
                                    <i class="fas fa-tag"></i> Price Range
                                </div>
                                <div class="price-range-container">
                                    <div class="price-input-group">
                                        <input type="number" name="min_price" placeholder="Min"
                                            value="<?php echo $min_price; ?>" min="0">
                                        <input type="number" name="max_price" placeholder="Max"
                                            value="<?php echo $max_price; ?>" min="0">
                                    </div>
                                </div>
                            </div>

                            <!-- Status Filter -->
                            <div class="filter-section">
                                <div class="filter-title">
                                    <i class="fas fa-circle"></i> Status
                                </div>
                                <div class="filter-item">
                                    <input type="radio" id="status_all" name="status" value=""
                                        <?php echo empty($status_filter) ? 'checked' : ''; ?>>
                                    <label for="status_all">All</label>
                                </div>
                                <div class="filter-item">
                                    <input type="radio" id="status_live" name="status" value="live"
                                        <?php echo $status_filter === 'live' ? 'checked' : ''; ?>>
                                    <label for="status_live">
                                        <i class="fas fa-broadcast-tower" style="color:#059669;font-size:11px;"></i> Live
                                    </label>
                                </div>
                                <div class="filter-item">
                                    <input type="radio" id="status_upcoming" name="status" value="upcoming"
                                        <?php echo $status_filter === 'upcoming' ? 'checked' : ''; ?>>
                                    <label for="status_upcoming">
                                        <i class="fas fa-hourglass-half" style="color:#d97706;font-size:11px;"></i> Upcoming
                                    </label>
                                </div>
                                <div class="filter-item">
                                    <input type="radio" id="status_ending" name="status" value="ending_soon"
                                        <?php echo $status_filter === 'ending_soon' ? 'checked' : ''; ?>>
                                    <label for="status_ending">
                                        <i class="fas fa-fire" style="color:#dc2626;font-size:11px;"></i> Ending Soon
                                    </label>
                                </div>
                            </div>

                            <!-- Location Filter -->
                            <div class="filter-section">
                                <div class="filter-title">
                                    <i class="fas fa-map-marker-alt"></i> Location
                                </div>
                                <div class="price-input-group">
                                    <input type="text" name="location" placeholder="e.g. Dhaka, Sylhet"
                                        value="<?php echo htmlspecialchars($location_filter); ?>">
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <button type="submit" class="btn-apply-filters">
                                <i class="fas fa-check me-2"></i> Apply Filters
                            </button>
                            <a href="search.php<?php echo !empty($search_query) ? '?q=' . urlencode($search_query) : ''; ?>"
                                class="clear-filters-btn">
                                <i class="fas fa-redo me-2"></i> Clear Filters
                            </a>
                        </form>
                    </div>
                </div>

                <!-- Results Section -->
                <div class="col-lg-9">
                    <div class="results-section">
                        <?php if ($total_results > 0): ?>
                            <div class="results-header">
                                <div class="results-count">
                                    <span class="count-badge"><?php echo $total_results; ?></span>
                                    product<?php echo $total_results !== 1 ? 's' : ''; ?> found
                                    <?php if (!empty($search_query)): ?>
                                        for &ldquo;<strong style="color:#111"><?php echo htmlspecialchars($search_query); ?></strong>&rdquo;
                                    <?php endif; ?>
                                </div>
                                <select class="sort-dropdown" id="sortSelect">
                                    <option value="newest">Newest First</option>
                                    <option value="price_low">Price: Low to High</option>
                                    <option value="price_high">Price: High to Low</option>
                                </select>
                            </div>

                            <div class="products-grid" id="productsContainer">
                                <?php
                                while ($post = $products_result->fetch_assoc()):
                                    $post_id = $post['id'];
                                    $current_time = time();
                                    $auction_start_time = strtotime($post['auction_start_date']);
                                    $auction_end_time = strtotime($post['auction_end_date']);

                                    $is_live = false;
                                    if ($current_time >= $auction_start_time && $current_time < $auction_end_time) {
                                        $is_live = true;
                                    }
                                ?>
                                    <div class="product-card-search" data-price="<?php echo $post['price']; ?>"
                                        data-name="<?php echo strtolower(htmlspecialchars($post['product_name'])); ?>">
                                        <a href="product_detail.php?id=<?php echo $post_id; ?>" class="card-link">
                                            <div class="card h-100">
                                                <?php if ($post['image']): ?>
                                                    <div class="product-image-search">
                                                        <img src="assets/images/<?php echo htmlspecialchars($post['image']); ?>"
                                                            alt="<?php echo htmlspecialchars($post['product_name']); ?>">
                                                    </div>
                                                <?php endif; ?>

                                                <div class="card-body">
                                                    <span class="search-card-category">
                                                        <?php echo htmlspecialchars($post['category']); ?>
                                                    </span>
                                                    <h6 class="card-title"><?php echo htmlspecialchars($post['product_name']); ?></h6>

                                                    <div class="search-card-price">
                                                        <span class="price-val">৳ <?php echo number_format($post['price'], 2); ?></span>
                                                        <span class="qty-val"><?php echo htmlspecialchars($post['quantity']); ?> <?php echo htmlspecialchars($post['unit']); ?></span>
                                                    </div>

                                                    <div class="mb-1">
                                                        <?php if ($is_live): ?>
                                                            <span class="search-badge-live">
                                                                <i class="fas fa-circle-notch fa-spin"></i> LIVE
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="search-badge-upcoming">
                                                                <i class="fas fa-hourglass-start"></i> Upcoming
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="search-card-meta">
                                                        <div class="meta-bids"><i class="fas fa-gavel"></i><?php echo (int)$post['total_bids']; ?> bid<?php echo $post['total_bids'] != 1 ? 's' : ''; ?></div>
                                                        <div class="meta-farmer"><i class="fas fa-user"></i><a href="farmer/profile.php?id=<?php echo (int)$post['farmer_id']; ?>" class="search-farmer-link" onclick="event.stopPropagation();"><?php echo htmlspecialchars($post['username']); ?></a></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endwhile; ?>
                            </div>

                            <!-- Load More / Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="pagination-container">
                                    <?php if ($page < $total_pages): ?>
                                        <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo urlencode($category_filter); ?>&location=<?php echo urlencode($location_filter); ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&status=<?php echo urlencode($status_filter); ?>&page=<?php echo $page + 1; ?>"
                                            class="load-more-btn">
                                            <i class="fas fa-plus me-2"></i> Load More (Page <?php echo $page + 1; ?> of <?php echo $total_pages; ?>)
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-results">
                                <i class="fas fa-search"></i>
                                <h3>No products found</h3>
                                <p><?php echo !empty($search_query) ? "Try different search terms or adjust your filters" : "Browse by category or search for products"; ?></p>
                                <a href="index.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-home me-2"></i> Back to Home
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* ── Product Cards ── */
        .card-link,
        .card-link:hover,
        .card-link:focus,
        .card-link:active,
        .card-link:visited {
            text-decoration: none !important;
            color: inherit;
        }

        .product-image-search {
            width: 100%;
            height: 190px;
            overflow: hidden;
            background: #f5f5f5;
            border-radius: 14px 14px 0 0;
        }

        .product-image-search img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .product-card-search .card-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .product-card-search .card-link:hover .product-image-search img,
        .product-card-search:hover .product-image-search img {
            transform: scale(1.06);
        }

        .product-card-search .card {
            border: 1.5px solid #f0f0f0;
            border-radius: 16px;
            overflow: hidden;
            transition: box-shadow 0.28s ease, transform 0.28s ease, border-color 0.28s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
            height: 100%;
            background: #fff;
        }

        .product-card-search:hover .card {
            box-shadow: 0 16px 40px rgba(5, 150, 105, 0.18);
            transform: translateY(-6px);
            border-color: #059669;
        }

        .product-card-search {
            cursor: pointer;
        }

        .product-card-search .card-body {
            padding: 0.85rem 1rem 0.6rem;
        }

        .product-card-search .card-title {
            font-size: 0.97rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.65rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Category pill */
        .search-card-category {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            background: #f0fdf4;
            color: #065f46;
            border: 1px solid rgba(5, 150, 105, 0.15);
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
            margin-bottom: 0.45rem;
        }

        /* Price block */
        .search-card-price {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 7px 10px;
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border-radius: 10px;
            border: 1px solid rgba(5, 150, 105, 0.1);
            margin-bottom: 0.5rem;
        }

        .search-card-price .price-val {
            font-size: 1rem;
            font-weight: 700;
            color: #059669;
        }

        .search-card-price .qty-val {
            font-size: 0.78rem;
            color: #6b7280;
            font-weight: 500;
        }

        /* Status badges */
        .search-badge-live {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #065f46;
            border: 1px solid rgba(5, 150, 105, 0.2);
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .search-badge-upcoming {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #1e40af;
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        /* Meta row */
        .search-card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.55rem;
            margin-top: 0.55rem;
            border-top: 1px solid #f3f4f6;
            font-size: 0.78rem;
            color: #6b7280;
        }

        .meta-bids,
        .meta-farmer {
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .search-card-meta i {
            color: #059669;
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .search-farmer-link {
            color: #059669;
            font-weight: 600;
            text-decoration: none;
        }

        .search-farmer-link:hover {
            color: #047857;
            text-decoration: underline;
        }
    </style>

    <script>
        // Sorting functionality
        document.getElementById('sortSelect').addEventListener('change', function() {
            const cards = Array.from(document.querySelectorAll('.product-card-search'));

            if (this.value === 'price_low') {
                cards.sort((a, b) => parseFloat(a.dataset.price) - parseFloat(b.dataset.price));
            } else if (this.value === 'price_high') {
                cards.sort((a, b) => parseFloat(b.dataset.price) - parseFloat(a.dataset.price));
            }

            const container = document.getElementById('productsContainer');
            cards.forEach(card => container.appendChild(card));
        });

        // Filter form handling — auto-submit on radio button change (category & status)
        document.getElementById('filterForm').addEventListener('change', function(event) {
            if (event.target.type === 'radio') {
                this.submit();
            }
        });
    </script>

    <?php include 'includes/footer.php'; ?>
</body>

</html>