<?php

/**
 * Automatic Rating System
 * Manages user and farmer automatic ratings based on bidding behavior and price fairness
 * Market prices are keyed by product_name (not category)
 */

// Ensure we have a DB connection available
if (!isset($conn)) {
    require_once __DIR__ . '/config.php';
}

// Ensure required schema exists
function ratings_ensure_schema()
{
    global $conn;

    // Add automatic_rating column if missing
    $res = $conn->query("SHOW COLUMNS FROM `users` LIKE 'automatic_rating'");
    if ($res && $res->num_rows == 0) {
        $sql = "ALTER TABLE `users` ADD COLUMN `automatic_rating` DECIMAL(3,1) NOT NULL DEFAULT 5.0";
        if (!$conn->query($sql)) {
            error_log("Failed to add automatic_rating column: " . $conn->error);
        }
    }

    // Create market_prices table if not exists (keyed by product_name)
    $create = "CREATE TABLE IF NOT EXISTS `market_prices` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `product_name` VARCHAR(255) NOT NULL UNIQUE,
        `market_price` DECIMAL(10,2) NOT NULL,
        `updated_by` INT DEFAULT NULL,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if (!$conn->query($create)) {
        error_log("Failed to create market_prices table: " . $conn->error);
    }
}

// Clamp rating between 0 and 10 with one decimal
function clamp_rating($r)
{
    if ($r < 0) return 0.0;
    if ($r > 10) return 10.0;
    return round($r, 1);
}

function get_user_automatic_rating($user_id)
{
    global $conn;
    ratings_ensure_schema();

    $stmt = $conn->prepare("SELECT automatic_rating FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row || $row['automatic_rating'] === null) return 5.0;
    return (float)$row['automatic_rating'];
}

function update_user_rating($user_id, $delta)
{
    global $conn;
    ratings_ensure_schema();

    $current = get_user_automatic_rating($user_id);
    $new = clamp_rating($current + $delta);

    $stmt = $conn->prepare("UPDATE users SET automatic_rating = ? WHERE id = ?");
    $stmt->bind_param("di", $new, $user_id);
    $stmt->execute();
    $stmt->close();

    return $new;
}

function get_market_price_for_product($product_name)
{
    global $conn;
    ratings_ensure_schema();

    // Check if table exists first
    $check = $conn->query("SHOW TABLES LIKE 'market_prices'");
    if (!$check || $check->num_rows == 0) {
        error_log("market_prices table does not exist");
        return null;
    }

    $stmt = $conn->prepare("SELECT market_price FROM market_prices WHERE product_name = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        return null;
    }

    $stmt->bind_param("s", $product_name);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if ($row) return (float)$row['market_price'];
    return null;
}

function set_market_price_for_product($product_name, $price, $admin_id = null)
{
    global $conn;
    ratings_ensure_schema();

    $stmt = $conn->prepare("INSERT INTO market_prices (product_name, market_price, updated_by) VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE market_price = VALUES(market_price), updated_by = VALUES(updated_by), updated_at = NOW()");
    $stmt->bind_param("sdi", $product_name, $price, $admin_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Adjust user automatic rating after a bid
 * Rules:
 * - Bid >50% lower than farmer price: -0.5
 * - Bid within ±10% of farmer price: +0.3
 * - Bid between 10%–50% lower: +0.1
 */
function adjust_rating_for_bid($user_id, $bid_amount, $farmer_price)
{
    if ($farmer_price <= 0) return null;

    $pct = (($bid_amount - $farmer_price) / max(0.0001, $farmer_price)) * 100.0;

    $delta = 0.0;
    if ($pct <= -50.0) {
        // Bid is >50% lower
        $delta = -0.5;
    } elseif ($pct >= -10.0 && $pct <= 10.0) {
        // Bid within ±10%
        $delta = 0.3;
    } elseif ($pct < -10.0 && $pct > -50.0) {
        // Bid between 10%–50% lower
        $delta = 0.1;
    }

    if ($delta != 0.0) {
        return update_user_rating($user_id, $delta);
    }

    return get_user_automatic_rating($user_id);
}

/**
 * Adjust farmer automatic rating after posting a product
 * Rules:
 * - Product price deviates >±30% from market price: -0.5
 * - Product price within ±30% (fair): +0.2
 */
function adjust_rating_for_post($farmer_id, $post_price, $product_name)
{
    $market = get_market_price_for_product($product_name);
    if ($market === null || $market <= 0) return null;

    $pct = (($post_price - $market) / max(0.0001, $market)) * 100.0;

    $delta = 0.0;
    if (abs($pct) > 30.0) {
        // Deviates >30%
        $delta = -0.5;
    } else {
        // Within ±30% (fair)
        $delta = 0.2;
    }

    if ($delta != 0.0) {
        return update_user_rating($farmer_id, $delta);
    }
    return get_user_automatic_rating($farmer_id);
}

/**
 * Adjust farmer rating when product is sold
 * Considers:
 * - How fast the product sold (time from creation to sale)
 * - Price fairness compared to market price
 * 
 * Rules:
 * - Sold within 24 hours: +0.3 (quick sale, good pricing)
 * - Sold within 24-72 hours: +0.1 (reasonable time)
 * - Sold after 7+ days: -0.2 (too long, poor pricing)
 * - Sold with final bid close to market price: +0.2 (fair pricing)
 */
function adjust_rating_for_sale($farmer_id, $post_id, $final_bid_amount)
{
    global $conn;

    // Get post details
    $stmt = $conn->prepare("SELECT product_name, price, UNIX_TIMESTAMP(created_at) as created_time, 
                            UNIX_TIMESTAMP(NOW()) as now_time FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $post = $result->fetch_assoc();
    $stmt->close();

    if (!$post) return null;

    $time_to_sell = ($post['now_time'] - $post['created_time']) / 3600; // hours
    $delta = 0.0;

    // Time-based rating
    if ($time_to_sell <= 24) {
        // Sold within 24 hours - excellent
        $delta += 0.3;
    } elseif ($time_to_sell <= 72) {
        // Sold within 3 days - good
        $delta += 0.1;
    } elseif ($time_to_sell >= 168) {
        // Took more than 7 days - poor pricing or unattractive product
        $delta -= 0.2;
    }

    // Check final price vs market price
    $market = get_market_price_for_product($post['product_name']);
    if ($market !== null && $market > 0) {
        $pct_diff = abs((($final_bid_amount - $market) / $market) * 100);

        if ($pct_diff <= 20) {
            // Final bid within ±20% of market price - fair pricing
            $delta += 0.2;
        }
    }

    if ($delta != 0.0) {
        return update_user_rating($farmer_id, $delta);
    }
    return get_user_automatic_rating($farmer_id);
}

/**
 * Adjust farmer rating when product remains unsold after bidding ends
 * This happens when:
 * - 5 bids placed but highest bid is below asking price
 * - Product expires without meeting reserve price
 * 
 * Rules:
 * - Product remains unsold: -0.4 (overpriced or unrealistic expectations)
 * - Multiple unsold products in short time: additional -0.1 per unsold item
 */
function adjust_rating_for_unsold($farmer_id, $post_id)
{
    global $conn;

    $delta = -0.4; // Base penalty for unsold product

    // Check how many products this farmer has unsold in last 30 days
    $stmt = $conn->prepare("SELECT COUNT(*) as unsold_count FROM posts 
                            WHERE farmer_id = ? 
                            AND status = 'active' 
                            AND expiry_date IS NOT NULL 
                            AND expiry_date < UNIX_TIMESTAMP(NOW())
                            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $unsold_count = $row['unsold_count'] ?? 0;

    // Additional penalty for multiple unsold products
    if ($unsold_count > 1) {
        $delta -= (($unsold_count - 1) * 0.1);
    }

    return update_user_rating($farmer_id, $delta);
}

/**
 * Adjust farmer rating based on bidding activity
 * Considers how engaged buyers are with the product
 * 
 * Rules:
 * - Product gets 10+ bids quickly: +0.2 (attractive pricing/product)
 * - Product gets only 5 bids in long time: -0.1 (barely met minimum, poor interest)
 */
function adjust_rating_for_bidding_activity($farmer_id, $post_id, $bid_count)
{
    global $conn;

    // Get post creation time and first/last bid times
    $stmt = $conn->prepare("SELECT UNIX_TIMESTAMP(p.created_at) as post_time,
                            UNIX_TIMESTAMP(MIN(c.created_at)) as first_bid_time,
                            UNIX_TIMESTAMP(MAX(c.created_at)) as last_bid_time
                            FROM posts p
                            LEFT JOIN comments c ON p.id = c.post_id
                            WHERE p.id = ?
                            GROUP BY p.id");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if (!$data || !$data['first_bid_time']) return null;

    $time_to_first_bid = ($data['first_bid_time'] - $data['post_time']) / 3600; // hours
    $bidding_duration = ($data['last_bid_time'] - $data['first_bid_time']) / 3600; // hours

    $delta = 0.0;

    // High engagement - many bids
    if ($bid_count >= 10) {
        $delta += 0.2;
    }

    // Low engagement - barely met minimum and took long time
    if ($bid_count == 5 && $time_to_first_bid > 48) {
        $delta -= 0.1;
    }

    if ($delta != 0.0) {
        return update_user_rating($farmer_id, $delta);
    }
    return get_user_automatic_rating($farmer_id);
}

// Ensure schema on include
ratings_ensure_schema();
