<?php
session_start();
include 'includes/db.php'; // Database connection
date_default_timezone_set('Asia/Dhaka');
require_once 'includes/config.php';
require_once 'includes/functions.php';
check_login();

// Get search parameters
$search_query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$category_filter = isset($_GET['category']) ? sanitize($_GET['category']) : '';
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
$count_query = "SELECT COUNT(*) as total FROM posts WHERE $where_clause";
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 36px 32px;
            margin-bottom: 28px;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(102, 126, 234, 0.32);
        }

        .search-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-header h1 i {
            animation: bounce 1.2s infinite;
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        .search-header .search-info {
            font-size: 15px;
            opacity: 0.92;
            font-weight: 500;
            line-height: 1.5;
        }

        /* ── Filters Sidebar ── */
        .filters-sidebar {
            background: white;
            padding: 22px 20px;
            border-radius: 12px;
            height: fit-content;
            position: sticky;
            top: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #ebebeb;
        }

        .filters-sidebar>h5 {
            font-size: 15px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid #f0f0f0;
        }

        .filter-section {
            margin-bottom: 22px;
            padding-bottom: 18px;
            border-bottom: 1px solid #f0f0f0;
        }

        .filter-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .filter-title {
            font-weight: 700;
            font-size: 12px;
            margin-bottom: 12px;
            color: #888;
            display: flex;
            align-items: center;
            gap: 7px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .filter-title i {
            color: #667eea;
            font-size: 13px;
        }

        .filter-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 0;
            margin-bottom: 0;
        }

        .filter-item+.filter-item {
            border-top: 1px solid #fafafa;
        }

        .filter-item input[type="radio"] {
            flex-shrink: 0;
            cursor: pointer;
            width: 16px;
            height: 16px;
            accent-color: #667eea;
            margin: 0;
        }

        .filter-item label {
            margin-bottom: 0;
            cursor: pointer;
            flex: 1;
            font-size: 14px;
            color: #555;
            transition: color 0.2s;
            line-height: 1.4;
        }

        .filter-item label:hover,
        .filter-item input[type="radio"]:checked+label {
            color: #667eea;
            font-weight: 600;
        }

        /* ── Price Range ── */
        .price-range-container {
            margin-top: 4px;
        }

        .price-input-group {
            display: flex;
            gap: 8px;
            margin-bottom: 0;
        }

        .price-input-group input {
            width: 100%;
            padding: 9px 10px;
            border: 1.5px solid #e0e0e0;
            border-radius: 7px;
            font-size: 13px;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fafafa;
        }

        .price-input-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
        }

        /* ── Filter Buttons ── */
        .btn-apply-filters {
            width: 100%;
            padding: 11px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 18px;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }

        .btn-apply-filters:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(102, 126, 234, 0.4);
        }

        .clear-filters-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background: transparent;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #777;
            transition: all 0.25s;
            margin-top: 8px;
            text-align: center;
            text-decoration: none !important;
        }

        .clear-filters-btn:hover {
            background: #f5f5f5;
            border-color: #ccc;
            color: #444;
            text-decoration: none !important;
        }

        /* ── Results Section ── */
        .results-section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.07);
            border: 1px solid #ebebeb;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f0f0f0;
        }

        .results-count {
            font-size: 14px;
            color: #777;
            font-weight: 500;
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
            border-color: #667eea;
            background: white;
        }

        /* ── Products Grid ── */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 14px;
            }

            .search-header {
                padding: 26px 18px;
            }

            .search-header h1 {
                font-size: 22px;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.3s;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 4px 14px rgba(102, 126, 234, 0.3);
            text-decoration: none !important;
            display: inline-block;
            text-align: center;
        }

        .load-more-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
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
            background: #f0f8ff;
            border-left: 4px solid #667eea;
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 18px;
            font-weight: 500;
            font-size: 14px;
        }

        .search-suggestion strong {
            color: #667eea;
            font-weight: 700;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container-fluid" style="max-width: 1400px; margin: 0 auto;">
        <div class="search-results-container">
            <!-- Search Header -->
            <div class="search-header">
                <div class="search-header-content">
                    <h1><i class="fas fa-search me-2"></i>Search Results</h1>
                    <div class="search-info">
                        <?php if (!empty($search_query)): ?>
                            <strong>"<?php echo htmlspecialchars($search_query); ?>"</strong>
                            <?php if ($total_results > 0): ?>
                                — Found <?php echo $total_results; ?> result<?php echo $total_results !== 1 ? 's' : ''; ?>
                            <?php else: ?>
                                — No results found
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Filters Sidebar -->
                <div class="col-lg-3">
                    <div class="filters-sidebar">
                        <h5 class="mb-4" style="font-weight: 700; color: #333;">
                            <i class="fas fa-filter"></i> Filters
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
                                        <i class="fas fa-circle-notch fa-spin" style="color: #28a745; font-size: 8px;"></i> Live
                                    </label>
                                </div>
                                <div class="filter-item">
                                    <input type="radio" id="status_upcoming" name="status" value="upcoming"
                                        <?php echo $status_filter === 'upcoming' ? 'checked' : ''; ?>>
                                    <label for="status_upcoming">
                                        <i class="fas fa-hourglass-start" style="color: #ffc107;"></i> Upcoming
                                    </label>
                                </div>
                                <div class="filter-item">
                                    <input type="radio" id="status_ending" name="status" value="ending_soon"
                                        <?php echo $status_filter === 'ending_soon' ? 'checked' : ''; ?>>
                                    <label for="status_ending">
                                        <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i> Ending Soon
                                    </label>
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
                                    📊 Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $per_page, $total_results); ?> of <?php echo $total_results; ?> results
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
                                                    <h6 class="card-title"><?php echo htmlspecialchars($post['product_name']); ?></h6>

                                                    <div class="card-text small mb-2">
                                                        <p class="text-muted mb-1">
                                                            <span class="badge badge-info"><?php echo htmlspecialchars($post['category']); ?></span>
                                                        </p>
                                                        <p class="font-weight-bold text-success mb-1">
                                                            ৳ <?php echo number_format($post['price'], 2); ?>
                                                        </p>
                                                    </div>

                                                    <div class="card-meta">
                                                        <?php if ($is_live): ?>
                                                            <span class="badge badge-success">
                                                                <i class="fas fa-circle-notch fa-spin"></i> LIVE
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">UPCOMING</span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="card-footer-info small text-muted mt-2">
                                                        <span><i class="fas fa-gavel"></i> <?php echo (int)$post['total_bids']; ?> bid<?php echo $post['total_bids'] !== 1 ? 's' : ''; ?></span>
                                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($post['username']); ?></span>
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
                                        <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo urlencode($category_filter); ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&status=<?php echo urlencode($status_filter); ?>&page=<?php echo $page + 1; ?>"
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
            height: 170px;
            overflow: hidden;
            background: #f5f5f5;
            border-radius: 8px 8px 0 0;
        }

        .product-image-search img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.35s ease;
        }

        .product-card-search .card-link:hover .product-image-search img {
            transform: scale(1.06);
        }

        .product-card-search .card {
            border: 1px solid #ebebeb;
            border-radius: 10px;
            overflow: hidden;
            transition: box-shadow 0.25s ease, transform 0.25s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
            height: 100%;
        }

        .product-card-search .card:hover {
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.13);
            transform: translateY(-4px);
        }

        .product-card-search .card-body {
            padding: 14px 16px 12px;
        }

        .product-card-search .card-title {
            font-size: 14px;
            font-weight: 600;
            color: #2d2d2d;
            margin-bottom: 10px;
            line-height: 1.45;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-card-search .card-text {
            margin-bottom: 4px;
        }

        .product-card-search .card-meta {
            margin: 10px 0 6px;
        }

        .product-card-search .card-footer-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            margin-top: 6px;
            border-top: 1px solid #f0f0f0;
            font-size: 12px;
            color: #888;
            gap: 6px;
        }

        .product-card-search .card-footer-info span {
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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