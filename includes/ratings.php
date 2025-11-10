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

// Ensure schema on include
ratings_ensure_schema();
