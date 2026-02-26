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

// Valid categories
$valid_categories = ['Vegetables', 'Fruits', 'Grains', 'Dairy', 'Eggs', 'Honey', 'Herbs', 'Root Vegetables', 'Fish'];

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
        .search-results-container {
            min-height: calc(100vh - 200px);
            padding: 30px 0;
        }

        .search-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            margin-bottom: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .search-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .search-header h1 i {
            margin-right: 15px;
            animation: bounce 1s infinite;
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
            font-size: 16px;
            opacity: 0.95;
            font-weight: 500;
        }

        .filters-sidebar {
            background: white;
            padding: 25px;
            border-radius: 12px;
            height: fit-content;
            position: sticky;
            top: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
        }

        .filter-section {
            margin-bottom: 28px;
            padding-bottom: 22px;
            border-bottom: 2px solid #f5f5f5;
        }

        .filter-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .filter-title {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 15px;
            color: #333;
            display: flex;
            align-items: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-title i {
            margin-right: 10px;
            color: #667eea;
            font-size: 18px;
        }

        .filter-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .filter-item input[type="checkbox"],
        .filter-item input[type="radio"] {
            margin-right: 12px;
            cursor: pointer;
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .filter-item label {
            margin-bottom: 0;
            cursor: pointer;
            flex: 1;
            font-size: 15px;
            color: #555;
            transition: color 0.2s;
        }

        .filter-item label:hover {
            color: #667eea;
        }

        .price-range-container {
            margin-top: 15px;
        }

        .price-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .price-input-group input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e8e8e8;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .price-input-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .clear-filters-btn {
            width: 100%;
            padding: 12px;
            background: #f8f9fa;
            border: 2px solid #e8e8e8;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            color: #555;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .clear-filters-btn:hover {
            background: #e8e8e8;
            border-color: #ddd;
            color: #333;
        }

        .results-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f5f5f5;
        }

        .results-count {
            font-size: 16px;
            color: #666;
            font-weight: 600;
        }

        .sort-dropdown {
            padding: 10px 15px;
            border: 2px solid #e8e8e8;
            border-radius: 6px;
            font-size: 15px;
            cursor: pointer;
            background: white;
            color: #333;
            transition: border-color 0.2s;
            min-width: 200px;
        }

        .sort-dropdown:hover,
        .sort-dropdown:focus {
            outline: none;
            border-color: #667eea;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 22px;
            margin-bottom: 40px;
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }

            .search-header {
                padding: 30px 20px;
            }

            .search-header h1 {
                font-size: 24px;
            }

            .results-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .sort-dropdown {
                width: 100%;
                min-width: unset;
            }
        }

        .no-results {
            text-align: center;
            padding: 80px 40px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
        }

        .no-results i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 25px;
            display: block;
        }

        .no-results h3 {
            color: #555;
            margin-bottom: 15px;
            font-size: 24px;
            font-weight: 700;
        }

        .no-results p {
            color: #999;
            font-size: 16px;
            margin-bottom: 25px;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #f5f5f5;
        }

        .load-more-btn {
            padding: 14px 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .load-more-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .load-more-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .search-suggestion {
            background: #f0f8ff;
            border-left: 5px solid #667eea;
            padding: 18px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
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
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
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
                                <?php
                                $conn->query("SELECT DISTINCT category FROM posts WHERE is_approved = 1 AND status = 'active' AND category IS NOT NULL AND category != '' ORDER BY category");
                                $cats_result = $conn->query("SELECT DISTINCT category FROM posts WHERE is_approved = 1 AND status = 'active' AND category IS NOT NULL AND category != '' ORDER BY category");
                                while ($cat = $cats_result->fetch_assoc()):
                                ?>
                                    <div class="filter-item">
                                        <input type="checkbox" id="cat_<?php echo str_replace(' ', '_', $cat['category']); ?>"
                                            name="category" value="<?php echo htmlspecialchars($cat['category']); ?>"
                                            <?php echo $category_filter === $cat['category'] ? 'checked' : ''; ?>>
                                        <label for="cat_<?php echo str_replace(' ', '_', $cat['category']); ?>">
                                            <?php echo htmlspecialchars($cat['category']); ?>
                                        </label>
                                    </div>
                                <?php endwhile; ?>
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
                            <button type="submit" class="btn btn-primary btn-block mb-2">
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
        .product-image-search {
            width: 100%;
            height: 150px;
            overflow: hidden;
            background: #f5f5f5;
            border-radius: 4px 4px 0 0;
        }

        .product-image-search img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .product-card-search .card-link:hover .product-image-search img {
            transform: scale(1.05);
        }

        .product-card-search .card {
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .product-card-search .card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            transform: translateY(-3px);
        }

        .product-card-search .card-body {
            padding: 12px;
        }

        .product-card-search .card-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-card-search .card-meta {
            margin: 8px 0;
        }

        .product-card-search .card-footer-info {
            display: flex;
            justify-content: space-between;
            padding-top: 8px;
            border-top: 1px solid #eee;
        }

        .card-link {
            text-decoration: none;
            color: inherit;
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

        // Filter form handling
        document.getElementById('filterForm').addEventListener('change', function() {
            // Auto-submit when radio buttons change (status)
            if (event.target.type === 'radio') {
                this.submit();
            }
        });
    </script>

    <?php include 'includes/footer.php'; ?>
</body>

</html>