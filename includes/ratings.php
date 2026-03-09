<?php

/**
 * Professional Marketplace Rating System
 *
 * Buyer Reputation Score (0–5 stars):
 *   BuyerScore = 5 × (0.35 × BidFairness
 *                   + 0.30 × PurchaseCompletion
 *                   + 0.20 × PaymentSpeed
 *                   + 0.15 × FarmerFeedback)
 *
 * Farmer Reputation Score (0–5 stars):
 *   FarmerScore = 5 × (0.40 × BuyerRatings
 *                    + 0.25 × SaleSuccessRate
 *                    + 0.20 × EngagementScore
 *                    + 0.15 × DeliveryReliability)
 */

if (!isset($conn)) {
    require_once __DIR__ . '/config.php';
}

// ─── Schema bootstrap ────────────────────────────────────────────────────────
function ratings_ensure_schema()
{
    global $conn;
    static $done = false;
    if ($done) return;
    $done = true;

    // Ensure automatic_rating column exists (0–5 scale)
    $res = $conn->query("SHOW COLUMNS FROM `users` LIKE 'automatic_rating'");
    if ($res && $res->num_rows == 0) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `automatic_rating` DECIMAL(3,1) NOT NULL DEFAULT 2.5");
    }

    // One-time migration: scale old 0–10 values down to 0–5
    $conn->query("UPDATE `users` SET `automatic_rating` = ROUND(`automatic_rating` / 2.0, 1) WHERE `automatic_rating` > 5.0");

    // market_prices table
    $conn->query("CREATE TABLE IF NOT EXISTS `market_prices` (
        `id`           INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `product_name` VARCHAR(255) NOT NULL UNIQUE,
        `market_price` DECIMAL(10,2) NOT NULL,
        `updated_by`   INT DEFAULT NULL,
        `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // buyer_ratings: farmers rate buyers (1–5) after a completed transaction
    $conn->query("CREATE TABLE IF NOT EXISTS `buyer_ratings` (
        `id`         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `farmer_id`  INT NOT NULL,
        `buyer_id`   INT NOT NULL,
        `post_id`    INT NOT NULL,
        `rating`     DECIMAL(2,1) NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_buyer_rating` (`farmer_id`, `buyer_id`, `post_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // transactions: track when a buyer paid after winning an auction
    $conn->query("CREATE TABLE IF NOT EXISTS `transactions` (
        `id`         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `post_id`    INT NOT NULL,
        `buyer_id`   INT NOT NULL,
        `farmer_id`  INT NOT NULL,
        `win_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `paid_at`    TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY `unique_transaction` (`post_id`, `buyer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ─── Shared helpers ──────────────────────────────────────────────────────────

/** Clamp a value to the 0–5 star range with 1 decimal. */
function clamp_rating($v)
{
    return round(max(0.0, min(5.0, (float)$v)), 1);
}

/** Read a user's stored reputation score. Returns 2.5 (neutral) if none set. */
function get_user_automatic_rating($user_id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT automatic_rating FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($row && $row['automatic_rating'] !== null) ? (float)$row['automatic_rating'] : 2.5;
}

/** Persist a reputation score (0–5) for a user. */
function save_user_rating($user_id, $score)
{
    global $conn;
    $score = clamp_rating($score);
    $stmt  = $conn->prepare("UPDATE users SET automatic_rating = ? WHERE id = ?");
    $stmt->bind_param("di", $score, $user_id);
    $stmt->execute();
    $stmt->close();
    return $score;
}

/** Legacy delta helper – kept for any direct callers outside this file. */
function update_user_rating($user_id, $delta)
{
    return save_user_rating($user_id, get_user_automatic_rating($user_id) + $delta);
}

// ─── Market price helpers (public API unchanged) ─────────────────────────────

function get_market_price_for_product($product_name)
{
    global $conn;
    $stmt = $conn->prepare("SELECT market_price FROM market_prices WHERE product_name = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("s", $product_name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (float)$row['market_price'] : null;
}

function set_market_price_for_product($product_name, $price, $admin_id = null)
{
    global $conn;
    $stmt = $conn->prepare("INSERT INTO market_prices (product_name, market_price, updated_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE market_price = VALUES(market_price),
                                updated_by   = VALUES(updated_by),
                                updated_at   = NOW()");
    $stmt->bind_param("sdi", $product_name, $price, $admin_id);
    $stmt->execute();
    $stmt->close();
}

// ═══════════════════════════════════════════════════════════════════════════════
// BUYER REPUTATION SCORE
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * BidFairness (0–1)
 * Score each bid by how close it is to the asking price, then average.
 *   Within 10% of asking price → 1.0
 *   Within 30%                 → 0.7
 *   Within 50%                 → 0.4
 *   More than 50% below        → 0.0
 * Returns 0.5 (neutral) when the buyer has placed no bids yet.
 */
function _buyer_bid_fairness($buyer_id)
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT CAST(c.comment_text AS DECIMAL(12,2)) AS bid, p.price AS asking
         FROM comments c
         JOIN posts p ON p.id = c.post_id
         WHERE c.user_id = ?
           AND c.comment_text REGEXP '^[0-9]+(\\.[0-9]+)?$'"
    );
    $stmt->bind_param("i", $buyer_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) return 0.5;

    $total = 0.0;
    foreach ($rows as $r) {
        $asking = (float)$r['asking'];
        $bid    = (float)$r['bid'];
        if ($asking <= 0) {
            $total += 0.5;
            continue;
        }

        // How far below the asking price is the bid? (positive = lower than asking)
        $pct = (($asking - $bid) / $asking) * 100.0;

        if ($pct <= 10)     $total += 1.0;
        elseif ($pct <= 30) $total += 0.7;
        elseif ($pct <= 50) $total += 0.4;
        else                $total += 0.0;
    }
    return $total / count($rows);
}

/**
 * PurchaseCompletion (0–1)
 * Delivered auctions / total auction wins.
 * Returns 0.5 (neutral) when buyer has never won anything.
 */
function _buyer_purchase_completion($buyer_id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ? AND is_approved = 1");
    $stmt->bind_param("i", $buyer_id);
    $stmt->execute();
    $stmt->bind_result($wins);
    $stmt->fetch();
    $stmt->close();

    if ($wins == 0) return 0.5;

    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM comments c
         JOIN posts p ON p.id = c.post_id
         WHERE c.user_id = ? AND c.is_approved = 1 AND p.status = 'delivered'"
    );
    $stmt->bind_param("i", $buyer_id);
    $stmt->execute();
    $stmt->bind_result($completed);
    $stmt->fetch();
    $stmt->close();

    return (float)$completed / (float)$wins;
}

/**
 * PaymentSpeed (0–1)
 * Average payment speed score drawn from the transactions table.
 *   Paid within  1 hour  → 1.0
 *   Paid within 12 hours → 0.7
 *   Paid after  12 hours → 0.4
 * Returns 0.5 (neutral) when no payment records exist.
 */
function _buyer_payment_speed($buyer_id)
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT TIMESTAMPDIFF(MINUTE, win_at, paid_at) AS minutes
         FROM transactions
         WHERE buyer_id = ? AND paid_at IS NOT NULL"
    );
    $stmt->bind_param("i", $buyer_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) return 0.5;

    $total = 0.0;
    foreach ($rows as $r) {
        $mins = (float)$r['minutes'];
        if ($mins <= 60)   $total += 1.0;
        elseif ($mins <= 720) $total += 0.7;
        else               $total += 0.4;
    }
    return $total / count($rows);
}

/**
 * FarmerFeedback (0–1)
 * Average farmer-to-buyer rating from buyer_ratings table, normalised to 0–1.
 * Returns 0.5 (neutral) when no feedback exists.
 */
function _buyer_farmer_feedback($buyer_id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT AVG(rating) AS avg_r FROM buyer_ratings WHERE buyer_id = ?");
    $stmt->bind_param("i", $buyer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || $row['avg_r'] === null) return 0.5;
    return (float)$row['avg_r'] / 5.0;
}

/**
 * Calculate and persist the Buyer Reputation Score (0–5 stars).
 *
 * Formula:
 *   BuyerScore = 5 × (0.35 × BidFairness
 *                   + 0.30 × PurchaseCompletion
 *                   + 0.20 × PaymentSpeed
 *                   + 0.15 × FarmerFeedback)
 */
function calculate_buyer_reputation($buyer_id)
{
    ratings_ensure_schema();

    $bid_fairness        = _buyer_bid_fairness($buyer_id);
    $purchase_completion = _buyer_purchase_completion($buyer_id);
    $payment_speed       = _buyer_payment_speed($buyer_id);
    $farmer_feedback     = _buyer_farmer_feedback($buyer_id);

    $score = 5.0 * (
        0.35 * $bid_fairness
        + 0.30 * $purchase_completion
        + 0.20 * $payment_speed
        + 0.15 * $farmer_feedback
    );

    return save_user_rating($buyer_id, $score);
}

// ═══════════════════════════════════════════════════════════════════════════════
// FARMER REPUTATION SCORE
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * BuyerRatings (0–1)
 * Average buyer review rating across all farmer products, normalised to 0–1.
 * Returns 0.5 (neutral) when no reviews exist.
 */
function _farmer_buyer_ratings($farmer_id)
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT AVG(r.rating) AS avg_r
         FROM reviews r
         JOIN posts p ON p.id = r.product_id
         WHERE p.farmer_id = ?"
    );
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || $row['avg_r'] === null) return 0.5;
    return (float)$row['avg_r'] / 5.0;
}

/**
 * SaleSuccessRate (0–1)
 * Products with status sold or delivered / total products listed.
 * Returns 0.5 (neutral) when no products exist.
 */
function _farmer_sale_success_rate($farmer_id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE farmer_id = ? AND is_approved = 1");
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    if ($total == 0) return 0.5;

    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM posts
         WHERE farmer_id = ? AND is_approved = 1 AND status IN ('sold', 'delivered')"
    );
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $stmt->bind_result($sold);
    $stmt->fetch();
    $stmt->close();

    return (float)$sold / (float)$total;
}

/**
 * EngagementScore (0–1)
 * Measures demand by counting unique bidders per post, then averaging.
 *   > 10 unique bidders → 1.0
 *   5–10 unique bidders → 0.7
 *   2–4  unique bidders → 0.4
 *   < 2  unique bidders → 0.1
 * Returns 0.1 when farmer has no posts with bids.
 */
function _farmer_engagement($farmer_id)
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT COUNT(DISTINCT c.user_id) AS unique_bidders
         FROM posts p
         JOIN comments c ON c.post_id = p.id
         WHERE p.farmer_id = ?
         GROUP BY p.id"
    );
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) return 0.1;

    $total = 0.0;
    foreach ($rows as $r) {
        $b = (int)$r['unique_bidders'];
        if ($b > 10)     $total += 1.0;
        elseif ($b >= 5) $total += 0.7;
        elseif ($b >= 2) $total += 0.4;
        else             $total += 0.1;
    }
    return $total / count($rows);
}

/**
 * DeliveryReliability (0–1)
 * Delivered posts / (sold + delivered posts).
 * Returns 0.5 (neutral) when farmer has no completed sales.
 */
function _farmer_delivery_reliability($farmer_id)
{
    global $conn;

    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM posts
         WHERE farmer_id = ? AND status IN ('sold', 'delivered')"
    );
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $stmt->bind_result($sold_total);
    $stmt->fetch();
    $stmt->close();

    if ($sold_total == 0) return 0.5;

    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM posts WHERE farmer_id = ? AND status = 'delivered'"
    );
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $stmt->bind_result($delivered);
    $stmt->fetch();
    $stmt->close();

    return (float)$delivered / (float)$sold_total;
}

/**
 * Calculate and persist the Farmer Reputation Score (0–5 stars).
 *
 * Formula:
 *   FarmerScore = 5 × (0.40 × BuyerRatings
 *                    + 0.25 × SaleSuccessRate
 *                    + 0.20 × EngagementScore
 *                    + 0.15 × DeliveryReliability)
 */
function calculate_farmer_reputation($farmer_id)
{
    ratings_ensure_schema();

    $buyer_ratings        = _farmer_buyer_ratings($farmer_id);
    $sale_success         = _farmer_sale_success_rate($farmer_id);
    $engagement           = _farmer_engagement($farmer_id);
    $delivery_reliability = _farmer_delivery_reliability($farmer_id);

    $score = 5.0 * (
        0.40 * $buyer_ratings
        + 0.25 * $sale_success
        + 0.20 * $engagement
        + 0.15 * $delivery_reliability
    );

    return save_user_rating($farmer_id, $score);
}

// ═══════════════════════════════════════════════════════════════════════════════
// TRANSACTION HELPERS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Record that a buyer won an auction. Call this when a bid is approved.
 * Creates a transaction row so payment speed can be tracked later.
 */
function record_auction_win($buyer_id, $post_id, $farmer_id)
{
    global $conn;
    ratings_ensure_schema();
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO transactions (post_id, buyer_id, farmer_id, win_at)
         VALUES (?, ?, ?, NOW())"
    );
    $stmt->bind_param("iii", $post_id, $buyer_id, $farmer_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Record that a buyer paid for a won auction, then recalculate their score.
 * Call this from your payment confirmation flow.
 */
function record_buyer_payment($buyer_id, $post_id)
{
    global $conn;
    ratings_ensure_schema();
    $stmt = $conn->prepare(
        "UPDATE transactions SET paid_at = NOW()
         WHERE buyer_id = ? AND post_id = ? AND paid_at IS NULL"
    );
    $stmt->bind_param("ii", $buyer_id, $post_id);
    $stmt->execute();
    $stmt->close();
    return calculate_buyer_reputation($buyer_id);
}

/**
 * Let a farmer rate a buyer (1–5 stars) after a completed transaction.
 * Persists the rating then recalculates the buyer's reputation score.
 */
function add_farmer_buyer_rating($farmer_id, $buyer_id, $post_id, $rating)
{
    global $conn;
    ratings_ensure_schema();
    $rating = max(1.0, min(5.0, (float)$rating));
    $stmt = $conn->prepare(
        "INSERT INTO buyer_ratings (farmer_id, buyer_id, post_id, rating)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating)"
    );
    $stmt->bind_param("iiid", $farmer_id, $buyer_id, $post_id, $rating);
    $stmt->execute();
    $stmt->close();
    return calculate_buyer_reputation($buyer_id);
}

// ═══════════════════════════════════════════════════════════════════════════════
// BACKWARD-COMPATIBLE HOOKS
// All old adjust_rating_for_* functions now trigger a full recalculation
// instead of one-shot delta adjustments.
// ═══════════════════════════════════════════════════════════════════════════════

function adjust_rating_for_bid($user_id, $bid_amount, $farmer_price)
{
    return calculate_buyer_reputation($user_id);
}

function adjust_rating_for_post($farmer_id, $post_price, $product_name)
{
    return calculate_farmer_reputation($farmer_id);
}

function adjust_rating_for_sale($farmer_id, $post_id, $final_bid_amount)
{
    global $conn;
    // Also record the auction win for the buyer
    $stmt = $conn->prepare(
        "SELECT user_id FROM comments WHERE post_id = ? AND is_approved = 1 LIMIT 1"
    );
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        record_auction_win($row['user_id'], $post_id, $farmer_id);
        calculate_buyer_reputation($row['user_id']);
    }

    return calculate_farmer_reputation($farmer_id);
}

function adjust_rating_for_unsold($farmer_id, $post_id)
{
    return calculate_farmer_reputation($farmer_id);
}

function adjust_rating_for_bidding_activity($farmer_id, $post_id, $bid_count)
{
    return calculate_farmer_reputation($farmer_id);
}

// Bootstrap schema on first include
ratings_ensure_schema();
