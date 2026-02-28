<?php
session_start();
include 'includes/db.php';
date_default_timezone_set('Asia/Dhaka');
require_once 'includes/config.php';
require_once 'includes/functions.php';
check_login();

$category = isset($_GET['category']) ? sanitize($_GET['category']) : 'Vegetables';

$valid_categories = ['Vegetables', 'Fruits', 'Grains', 'Dairy', 'Eggs', 'Honey', 'Herbs', 'Root Vegetables'];
if (!in_array($category, $valid_categories)) {
    $category = 'Vegetables';
}

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
                    <a href="browse.php?category=<?php echo urlencode($cat); ?>"
                        class="br-tab <?php echo $isActive ? 'br-tab-active' : ''; ?>"
                        title="<?php echo $cat; ?>">
                        <i class="fas <?php echo $c['icon']; ?> br-tab-icon"></i>
                        <span><?php echo $cat; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ===== PRODUCTS AREA ===== -->
        <div class="br-content">

            <?php
            $products_stmt = $conn->prepare("SELECT posts.*, users.username,
                                           (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as total_bids,
                                           (SELECT MAX(comment_text) FROM comments WHERE post_id = posts.id) as max_bid
                                           FROM posts
                                           JOIN users ON posts.farmer_id = users.id
                                           WHERE posts.is_approved = 1 AND posts.status = 'active'
                                           AND posts.category = ?
                                           ORDER BY posts.price ASC");
            $products_stmt->bind_param("s", $category);
            $products_stmt->execute();
            $products_result = $products_stmt->get_result();
            $total_products  = $products_result->num_rows;
            ?>

            <!-- Toolbar -->
            <div class="br-toolbar">
                <span class="br-product-count">
                    <strong><?php echo $total_products; ?></strong>
                    product<?php echo $total_products != 1 ? 's' : ''; ?> found
                </span>
                <div class="br-search-inline">
                    <i class="fas fa-search"></i>
                    <input type="text" id="br-search" placeholder="Search <?php echo strtolower($category); ?>…">
                </div>
            </div>

            <?php if ($total_products > 0): ?>

                <div class="br-grid" id="br-grid">
                    <?php while ($post = $products_result->fetch_assoc()):
                        $post_id          = $post['id'];
                        $current_time     = time();
                        $auction_start    = strtotime($post['auction_start_date']);
                        $auction_end      = strtotime($post['auction_end_date']);
                        $is_ended         = ($current_time >= $auction_end);
                        $is_live          = (!$is_ended && $current_time >= $auction_start);
                        $total_bids       = (int)$post['total_bids'];
                        $max_bid          = $post['max_bid'];
                        $initials         = strtoupper(substr($post['username'], 0, 2));
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
                                <?php if ($is_ended): ?>
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
                                <?php if ($is_ended): ?>
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

        </div><!-- /br-content -->
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
        })();
    </script>

    <?php include 'includes/footer.php'; ?>
</body>

</html>