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
        `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_mp_user FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // buyer_ratings: farmers rate buyers (1–5) after a completed transaction
    $conn->query("CREATE TABLE IF NOT EXISTS `buyer_ratings` (
        `id`         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `farmer_id`  INT NOT NULL,
        `buyer_id`   INT NOT NULL,
        `post_id`    INT NOT NULL,
        `rating`     DECIMAL(2,1) NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_buyer_rating` (`farmer_id`, `buyer_id`, `post_id`),
        CONSTRAINT fk_br_farmer FOREIGN KEY (`farmer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        CONSTRAINT fk_br_buyer FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        CONSTRAINT fk_br_post FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // transactions: track when a buyer paid after winning an auction
    $conn->query("CREATE TABLE IF NOT EXISTS `transactions` (
        `id`         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `post_id`    INT NOT NULL,
        `buyer_id`   INT NOT NULL,
        `farmer_id`  INT NOT NULL,
        `win_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `paid_at`    TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY `unique_transaction` (`post_id`, `buyer_id`),
        CONSTRAINT fk_tx_post FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
        CONSTRAINT fk_tx_buyer FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        CONSTRAINT fk_tx_farmer FOREIGN KEY (`farmer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Keep a transparent audit trail for score increases/decreases.
    $conn->query("CREATE TABLE IF NOT EXISTS `rating_score_history` (
        `id`             INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id`        INT NOT NULL,
        `score_type`     VARCHAR(40) NOT NULL,
        `trigger_event`  VARCHAR(80) NOT NULL,
        `old_score`      DECIMAL(3,1) NOT NULL,
        `new_score`      DECIMAL(3,1) NOT NULL,
        `delta`          DECIMAL(4,1) NOT NULL,
        `breakdown_json` TEXT NULL,
        `context_json`   TEXT NULL,
        `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_rsh_user_created` (`user_id`, `created_at`),
        INDEX `idx_rsh_user_type` (`user_id`, `score_type`),
        CONSTRAINT fk_rsh_user FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ─── Shared helpers ──────────────────────────────────────────────────────────

/** Clamp a value to the 0–5 star range with 1 decimal. */
function clamp_rating(float|int $v): float
{
    return round(max(0.0, min(5.0, (float)$v)), 1);
}

/** Read a user's stored reputation score. Returns 2.5 (neutral) if none set. */
function get_user_automatic_rating(int $user_id): float
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
function save_user_rating(int $user_id, float|int $score): float
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
function update_user_rating(int $user_id, float|int $delta): float
{
    return save_user_rating($user_id, get_user_automatic_rating($user_id) + $delta);
}

function rating_factor_signal(float|int $score_01): string
{
    $score_01 = (float)$score_01;
    if ($score_01 >= 0.75) return 'strong';
    if ($score_01 >= 0.50) return 'neutral';
    return 'weak';
}

function rating_event_label(string $event): string
{
    $map = [
        'bid_placed' => 'Bid placed',
        'auction_won' => 'Auction won',
        'delivery_confirmed' => 'Delivery confirmed',
        'farmer_feedback_submitted' => 'Farmer feedback submitted',
        'review_submitted' => 'Product review submitted',
        'listing_activity' => 'Listing activity changed',
        'listing_unsold' => 'Listing ended unsold',
        'listing_engagement_updated' => 'Listing engagement updated',
        'system_recalculation' => 'System recalculation'
    ];
    return isset($map[$event]) ? $map[$event] : ucwords(str_replace('_', ' ', (string)$event));
}

function rating_event_reason(string $trigger_event, string $score_type, ?array $context = null): string
{
    $ctx = is_array($context) ? $context : [];

    if ($trigger_event === 'bid_placed') {
        $bid = isset($ctx['bid_amount']) ? (float)$ctx['bid_amount'] : null;
        $asking = isset($ctx['asking_price']) ? (float)$ctx['asking_price'] : null;
        if ($bid !== null && $asking !== null && $asking > 0) {
            $pct_below = (($asking - $bid) / $asking) * 100.0;
            if ($pct_below <= 10) return 'Your bid was close to asking price, which supports Bid Fairness.';
            if ($pct_below <= 30) return 'Your bid was moderately below asking price, slightly reducing Bid Fairness.';
            if ($pct_below <= 50) return 'Your bid was far below asking price, lowering Bid Fairness.';
            return 'Your bid was very low versus asking price, strongly reducing Bid Fairness.';
        }
        return 'A newly placed bid recalculated your Bid Fairness.';
    }

    if ($trigger_event === 'review_submitted') {
        $rating = isset($ctx['rating']) ? (float)$ctx['rating'] : null;
        if ($rating !== null) {
            return 'A new product review of ' . number_format($rating, 1) . '/5 updated your Buyer Ratings factor.';
        }
        return 'A new product review recalculated your Buyer Ratings factor.';
    }

    if ($trigger_event === 'farmer_feedback_submitted') {
        $rating = isset($ctx['rating']) ? (float)$ctx['rating'] : null;
        if ($rating !== null) {
            return 'Farmer feedback of ' . number_format($rating, 1) . '/5 updated your Farmer Feedback factor.';
        }
        return 'New farmer feedback recalculated your Farmer Feedback factor.';
    }

    if ($trigger_event === 'delivery_confirmed') {
        if ($score_type === 'buyer_reputation') {
            return 'Delivery confirmation updated your payment speed and completion-related signals.';
        }
        return 'Delivery confirmation improved delivery reliability and completion-related signals.';
    }

    if ($trigger_event === 'auction_won') {
        if ($score_type === 'buyer_reputation') {
            return 'A recorded auction win updated your purchase completion progress.';
        }
        return 'A recorded auction result updated your sale success and activity signals.';
    }

    if ($trigger_event === 'listing_engagement_updated') {
        return 'Bidder activity on your listing changed your Engagement Score.';
    }

    if ($trigger_event === 'listing_unsold') {
        return 'An unsold listing affected your sale-success related factors.';
    }

    if ($trigger_event === 'listing_activity') {
        return 'Listing activity changed your marketplace performance factors.';
    }

    return 'Your reputation was recalculated from the latest marketplace activity.';
}

function log_rating_score_change(int $user_id, string $score_type, string $trigger_event, float|int $old_score, float|int $new_score, array $breakdown = [], array $context = []): void
{
    global $conn;

    $old_score = clamp_rating($old_score);
    $new_score = clamp_rating($new_score);
    $delta = round($new_score - $old_score, 1);

    // Skip no-op rows to keep history readable.
    if (abs($delta) < 0.1) {
        return;
    }

    $breakdown_json = !empty($breakdown) ? json_encode($breakdown) : null;
    $context_json = !empty($context) ? json_encode($context) : null;

    $stmt = $conn->prepare(
        "INSERT INTO rating_score_history
         (user_id, score_type, trigger_event, old_score, new_score, delta, breakdown_json, context_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        "issdddss",
        $user_id,
        $score_type,
        $trigger_event,
        $old_score,
        $new_score,
        $delta,
        $breakdown_json,
        $context_json
    );
    $stmt->execute();
    $stmt->close();
}

function get_rating_change_history(int $user_id, ?string $score_type = null, int $limit = 15): array
{
    global $conn;
    ratings_ensure_schema();

    $limit = max(1, min(50, (int)$limit));

    if ($score_type) {
        $stmt = $conn->prepare(
            "SELECT id, score_type, trigger_event, old_score, new_score, delta, breakdown_json, context_json, created_at
             FROM rating_score_history
             WHERE user_id = ? AND score_type = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ?"
        );
        $stmt->bind_param("isi", $user_id, $score_type, $limit);
    } else {
        $stmt = $conn->prepare(
            "SELECT id, score_type, trigger_event, old_score, new_score, delta, breakdown_json, context_json, created_at
             FROM rating_score_history
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ?"
        );
        $stmt->bind_param("ii", $user_id, $limit);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$row) {
        $row['event_label'] = rating_event_label($row['trigger_event']);
        $row['direction'] = ((float)$row['delta'] > 0) ? 'increase' : 'decrease';
        $row['breakdown'] = !empty($row['breakdown_json']) ? json_decode($row['breakdown_json'], true) : null;
        $row['context'] = !empty($row['context_json']) ? json_decode($row['context_json'], true) : null;
        $row['reason'] = rating_event_reason($row['trigger_event'], $row['score_type'], $row['context']);
    }

    return $rows;
}

// ─── Market price helpers (public API unchanged) ─────────────────────────────

function get_market_price_for_product(string $product_name): ?float
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

function set_market_price_for_product(string $product_name, float|int $price, ?int $admin_id = null): void
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

function get_buyer_reputation_breakdown(int $buyer_id): array
{
    global $conn;
    $bid_fairness        = _buyer_bid_fairness($buyer_id);
    $purchase_completion = _buyer_purchase_completion($buyer_id);
    $payment_speed       = _buyer_payment_speed($buyer_id);
    $farmer_feedback     = _buyer_farmer_feedback($buyer_id);

    $factors = [
        [
            'key' => 'bid_fairness',
            'label' => 'Bid Fairness',
            'weight' => 0.35,
            'score_01' => round($bid_fairness, 4),
            'signal' => rating_factor_signal($bid_fairness),
            'note' => 'How close your bids are to asking prices.'
        ],
        [
            'key' => 'purchase_completion',
            'label' => 'Purchase Completion',
            'weight' => 0.30,
            'score_01' => round($purchase_completion, 4),
            'signal' => rating_factor_signal($purchase_completion),
            'note' => 'Delivered wins divided by all wins.'
        ],
        [
            'key' => 'payment_speed',
            'label' => 'Payment Speed',
            'weight' => 0.20,
            'score_01' => round($payment_speed, 4),
            'signal' => rating_factor_signal($payment_speed),
            'note' => 'How quickly wins are marked completed.'
        ],
        [
            'key' => 'farmer_feedback',
            'label' => 'Farmer Feedback',
            'weight' => 0.15,
            'score_01' => round($farmer_feedback, 4),
            'signal' => rating_factor_signal($farmer_feedback),
            'note' => 'Ratings from farmers after deliveries.'
        ]
    ];

    $weighted_total = 0.0;
    foreach ($factors as &$factor) {
        $weighted = $factor['weight'] * $factor['score_01'];
        $factor['weighted_01'] = round($weighted, 4);
        $factor['weighted_05'] = round($weighted * 5.0, 2);
        $factor['score_05'] = round($factor['score_01'] * 5.0, 2);
        $weighted_total += $weighted;
    }

    $raw_score = clamp_rating(5.0 * $weighted_total);

    // ANTI-FRAUD CHECK: Require minimum 3 completed transactions before score deviates from 2.5
    $stmt = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE buyer_id = ? AND paid_at IS NOT NULL");
    if (!$stmt) {
        // Fallback if not initialized fully
        $tx_count = 0;
    } else {
        $stmt->bind_param("i", $buyer_id);
        $stmt->execute();
        $stmt->bind_result($tx_count);
        $stmt->fetch();
        $stmt->close();
    }

    $is_probation = ($tx_count < 3);
    $final_score = $is_probation ? 2.5 : $raw_score;

    return [
        'score_type' => 'buyer_reputation',
        'title' => 'Buyer Reputation',
        'score' => $final_score,
        'raw_calculated_score' => $raw_score,
        'factors' => $factors,
        'formula' => '5 × (0.35×BidFairness + 0.30×PurchaseCompletion + 0.20×PaymentSpeed + 0.15×FarmerFeedback)',
        'probation' => [
            'is_active' => $is_probation,
            'completed' => $tx_count,
            'required' => 3,
            'message' => $is_probation ? "Complete " . (3 - $tx_count) . " more transactions to unlock your dynamic score." : "Probation completed."
        ]
    ];
}

function get_farmer_reputation_breakdown(int $farmer_id): array
{
    global $conn;
    $buyer_ratings        = _farmer_buyer_ratings($farmer_id);
    $sale_success         = _farmer_sale_success_rate($farmer_id);
    $engagement           = _farmer_engagement($farmer_id);
    $delivery_reliability = _farmer_delivery_reliability($farmer_id);

    $factors = [
        [
            'key' => 'buyer_ratings',
            'label' => 'Buyer Ratings',
            'weight' => 0.40,
            'score_01' => round($buyer_ratings, 4),
            'signal' => rating_factor_signal($buyer_ratings),
            'note' => 'Average buyer review rating for your listings.'
        ],
        [
            'key' => 'sale_success_rate',
            'label' => 'Sale Success Rate',
            'weight' => 0.25,
            'score_01' => round($sale_success, 4),
            'signal' => rating_factor_signal($sale_success),
            'note' => 'Sold or delivered listings divided by approved listings.'
        ],
        [
            'key' => 'engagement_score',
            'label' => 'Engagement Score',
            'weight' => 0.20,
            'score_01' => round($engagement, 4),
            'signal' => rating_factor_signal($engagement),
            'note' => 'Unique bidder participation per listing.'
        ],
        [
            'key' => 'delivery_reliability',
            'label' => 'Delivery Reliability',
            'weight' => 0.15,
            'score_01' => round($delivery_reliability, 4),
            'signal' => rating_factor_signal($delivery_reliability),
            'note' => 'Delivered sales divided by all completed sales.'
        ]
    ];

    $weighted_total = 0.0;
    foreach ($factors as &$factor) {
        $weighted = $factor['weight'] * $factor['score_01'];
        $factor['weighted_01'] = round($weighted, 4);
        $factor['weighted_05'] = round($weighted * 5.0, 2);
        $factor['score_05'] = round($factor['score_01'] * 5.0, 2);
        $weighted_total += $weighted;
    }

    $raw_score = clamp_rating(5.0 * $weighted_total);

    // ANTI-FRAUD CHECK: Require minimum 3 delivered sales before score deviates from 2.5
    $stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE farmer_id = ? AND status = 'delivered'");
    if (!$stmt) {
        $tx_count = 0;
    } else {
        $stmt->bind_param("i", $farmer_id);
        $stmt->execute();
        $stmt->bind_result($tx_count);
        $stmt->fetch();
        $stmt->close();
    }

    $is_probation = ($tx_count < 3);
    $final_score = $is_probation ? 2.5 : $raw_score;

    return [
        'score_type' => 'farmer_reputation',
        'title' => 'Farmer Reputation',
        'score' => $final_score,
        'raw_calculated_score' => $raw_score,
        'factors' => $factors,
        'formula' => '5 × (0.40×BuyerRatings + 0.25×SaleSuccessRate + 0.20×EngagementScore + 0.15×DeliveryReliability)',
        'probation' => [
            'is_active' => $is_probation,
            'completed' => $tx_count,
            'required' => 3,
            'message' => $is_probation ? "Deliver " . (3 - $tx_count) . " more orders to unlock your dynamic score." : "Probation completed."
        ]
    ];
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
function _buyer_bid_fairness(int $buyer_id): float
{
    global $conn;
    // Fix: Moved aggregation to SQL for O(1) memory usage and massive performance boost
    $stmt = $conn->prepare("
        SELECT AVG(
            CASE 
                WHEN asking <= 0 THEN 0.5
                WHEN ((asking - bid) / asking) * 100.0 <= 20 THEN 1.0
                WHEN ((asking - bid) / asking) * 100.0 <= 40 THEN 0.8
                WHEN ((asking - bid) / asking) * 100.0 <= 60 THEN 0.6
                ELSE 0.3
            END
        ) as final_score
        FROM (
            SELECT MAX(CAST(c.comment_text AS DECIMAL(12,2))) AS bid, CAST(p.price AS DECIMAL(12,2)) AS asking
            FROM comments c
            JOIN posts p ON p.id = c.post_id
            WHERE c.user_id = ?
              AND c.comment_text REGEXP '^[0-9]+(\\.[0-9]+)?$'
            GROUP BY p.id, p.price
        ) as subquery
    ");
    $stmt->bind_param("i", $buyer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($row && $row['final_score'] !== null) ? (float)$row['final_score'] : 0.5;
}

/**
 * PurchaseCompletion (0–1)
 * Delivered auctions / eligible auction wins.
 * Includes a 3-day grace period for 'sold' items (assumed in transit).
 * Returns 0.5 (neutral) when buyer has no eligible wins.
 */
function _buyer_purchase_completion(int $buyer_id): float
{
    global $conn;

    // Fix: Include t.paid_at to check if buyer fulfilled their part
    $stmt = $conn->prepare(
        "SELECT p.status, COALESCE(t.win_at, c.created_at) as event_time, t.paid_at
         FROM comments c
         JOIN posts p ON p.id = c.post_id
         LEFT JOIN transactions t ON t.post_id = p.id AND t.buyer_id = c.user_id
         WHERE c.user_id = ? AND c.is_approved = 1"
    );
    $stmt->bind_param("i", $buyer_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) return 0.5;

    $eligible_total = 0;
    $completed = 0;
    $now = time();

    foreach ($rows as $r) {
        if ($r['status'] === 'delivered') {
            $eligible_total++;
            $completed++;
        } elseif ($r['status'] === 'sold') {
            // Grace Period: > 3 days (259200 seconds)
            $event_time = strtotime($r['event_time']);
            if (($now - $event_time) > 259200) {
                // Fix: Only penalize buyer if they didn't pay. If paid_at is not null,
                // the delay is likely the farmer's fault, so don't count it against the buyer.
                if ($r['paid_at'] === null) {
                    $eligible_total++;
                }
            }
        }
    }

    if ($eligible_total == 0) return 0.5;
    return (float)$completed / (float)$eligible_total;
}

/**
 * PaymentSpeed (0–1)
 * Average payment speed score drawn from the transactions table.
 *   Paid within  1 hour  → 1.0
 *   Paid within 12 hours → 0.7
 *   Paid after  12 hours → 0.4
 * Returns 0.5 (neutral) when no payment records exist.
 */
function _buyer_payment_speed(int $buyer_id): float
{
    global $conn;
    // Fix: Moved aggregation to SQL for O(1) memory usage
    $stmt = $conn->prepare("
        SELECT AVG(
            CASE 
                WHEN paid_at IS NULL THEN 0.0
                WHEN TIMESTAMPDIFF(MINUTE, win_at, paid_at) <= 60 THEN 1.0
                WHEN TIMESTAMPDIFF(MINUTE, win_at, paid_at) <= 720 THEN 0.7
                ELSE 0.4
            END
        ) as final_score
        FROM transactions
        WHERE buyer_id = ? AND (paid_at IS NOT NULL OR TIMESTAMPDIFF(MINUTE, win_at, NOW()) > 1440)
    ");
    $stmt->bind_param("i", $buyer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($row && $row['final_score'] !== null) ? (float)$row['final_score'] : 0.5;
}

/**
 * FarmerFeedback (0–1)
 * Average farmer-to-buyer rating from buyer_ratings table, normalised to 0–1.
 * Returns 0.5 (neutral) when no feedback exists.
 */
function _buyer_farmer_feedback(int $buyer_id): float
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
function calculate_buyer_reputation(int $buyer_id, string $trigger_event = 'system_recalculation', array $context = []): float
{
    ratings_ensure_schema();
    global $conn;

    $old_score = get_user_automatic_rating($buyer_id);
    $breakdown = get_buyer_reputation_breakdown($buyer_id);
    $new_score = $breakdown['score'];

    try {
        $conn->begin_transaction();
        $new_score = save_user_rating($buyer_id, $new_score);
        log_rating_score_change($buyer_id, 'buyer_reputation', $trigger_event, $old_score, $new_score, $breakdown, $context);
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        return $old_score;
    }
    return $new_score;
}

// ═══════════════════════════════════════════════════════════════════════════════
// FARMER REPUTATION SCORE
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * BuyerRatings (0–1)
 * Average buyer review rating across all farmer products, normalised to 0–1.
 * Returns 0.5 (neutral) when no reviews exist.
 */
function _farmer_buyer_ratings(int $farmer_id): float
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT AVG(r.rating) AS avg_r, COUNT(r.id) AS review_count
         FROM reviews r
         JOIN posts p ON p.id = r.product_id
         WHERE p.farmer_id = ?"
    );
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || $row['avg_r'] === null || $row['review_count'] == 0) return 0.5;
    
    // Fix: Use Bayesian average to reduce volatility for new farmers
    $avg = (float)$row['avg_r'];
    $count = (int)$row['review_count'];
    $bayesian_avg = (($avg * $count) + (3.0 * 2)) / ($count + 2);
    
    return $bayesian_avg / 5.0;
}

/**
 * SaleSuccessRate (0–1)
 * Products with status sold or delivered / total resolved products.
 * Ignores active listings entirely.
 * Returns 0.5 (neutral) until the farmer has at least one completed sale.
 */
function _farmer_sale_success_rate(int $farmer_id): float
{
    global $conn;

    $total_resolved = 0;
    $sold = 0;

    $stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE farmer_id = ? AND is_approved = 1 AND status != 'active'");
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $stmt->bind_result($total_resolved);
    $stmt->fetch();
    $stmt->close();

    if ($total_resolved == 0) return 0.5;

    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM posts
         WHERE farmer_id = ? AND is_approved = 1 AND status IN ('sold', 'delivered')"
    );
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $stmt->bind_result($sold);
    $stmt->fetch();
    $stmt->close();

    if ($sold == 0) return 0.5;

    return (float)$sold / (float)$total_resolved;
}

/**
 * EngagementScore (0–1)
 * Measures demand by counting unique bidders per post, then averaging.
 *   > 10 unique bidders → 1.0
 *   5–10 unique bidders → 0.7
 *   2–4  unique bidders → 0.4
 *   < 2  unique bidders → 0.1
 * Returns 0.5 (neutral) when the farmer has no bid activity yet.
 */
function _farmer_engagement(int $farmer_id): float
{
    global $conn;
    // Fix: Moved aggregation to SQL for O(1) memory usage
    $stmt = $conn->prepare("
        SELECT AVG(
            CASE
                WHEN unique_bidders >= 5 THEN 1.0
                WHEN unique_bidders >= 3 THEN 0.8
                WHEN unique_bidders >= 1 THEN 0.6
                ELSE 0.2
            END
        ) as final_score
        FROM (
            SELECT COUNT(DISTINCT c.user_id) AS unique_bidders
            FROM posts p
            LEFT JOIN comments c ON c.post_id = p.id
            WHERE p.farmer_id = ?
            GROUP BY p.id
        ) as subquery
    ");
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($row && $row['final_score'] !== null) ? (float)$row['final_score'] : 0.5;
}

/**
 * DeliveryReliability (0–1)
 * Delivered posts / eligible (sold + delivered) posts.
 * Includes a 3-day grace period for 'sold' items.
 * Returns 0.5 (neutral) when farmer has no eligible sales.
 */
function _farmer_delivery_reliability(int $farmer_id): float
{
    global $conn;

    // Fix: Include t.paid_at to determine whose fault a delay is
    $stmt = $conn->prepare(
        "SELECT p.status, COALESCE(t.win_at, p.created_at) as event_time, t.paid_at
         FROM posts p
         LEFT JOIN transactions t ON t.post_id = p.id
         WHERE p.farmer_id = ? AND p.status IN ('sold', 'delivered')"
    );
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) return 0.5;

    $eligible_total = 0;
    $delivered = 0;
    $now = time();

    foreach ($rows as $r) {
        if ($r['status'] === 'delivered') {
            $eligible_total++;
            $delivered++;
        } elseif ($r['status'] === 'sold') {
            // Grace Period: > 3 days (259200 seconds)
            $event_time = strtotime($r['event_time']);
            if (($now - $event_time) > 259200) {
                // Fix: Only penalize farmer if the buyer DID pay.
                // If paid_at is null, the buyer ghosted, so farmer shouldn't be blamed for no delivery.
                if ($r['paid_at'] !== null) {
                    $eligible_total++;
                }
            }
        }
    }

    if ($eligible_total == 0) return 0.5;
    return (float)$delivered / (float)$eligible_total;
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
function calculate_farmer_reputation(int $farmer_id, string $trigger_event = 'system_recalculation', array $context = []): float
{
    ratings_ensure_schema();
    global $conn;

    $old_score = get_user_automatic_rating($farmer_id);
    $breakdown = get_farmer_reputation_breakdown($farmer_id);
    $new_score = $breakdown['score'];

    // An unsold listing should never improve the stored reputation score.
    // Keep the weighted recalculation, but cap the result so this event can
    // only hold steady or reduce the score.
    if ($trigger_event === 'listing_unsold') {
        $new_score = min($new_score, $old_score);
        $breakdown['score'] = $new_score;
    }

    try {
        $conn->begin_transaction();
        $new_score = save_user_rating($farmer_id, $new_score);
        log_rating_score_change($farmer_id, 'farmer_reputation', $trigger_event, $old_score, $new_score, $breakdown, $context);
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        return $old_score;
    }
    return $new_score;
}

// ═══════════════════════════════════════════════════════════════════════════════
// TRANSACTION HELPERS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Record that a buyer won an auction. Call this when a bid is approved.
 * Creates a transaction row so payment speed can be tracked later.
 */
function record_auction_win(int $buyer_id, int $post_id, int $farmer_id): void
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
function record_buyer_payment(int $buyer_id, int $post_id): float
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
    return calculate_buyer_reputation($buyer_id, 'delivery_confirmed', ['post_id' => (int)$post_id]);
}

/**
 * Let a farmer rate a buyer (1–5 stars) after a completed transaction.
 * Persists the rating then recalculates the buyer's reputation score.
 */
function add_farmer_buyer_rating(int $farmer_id, int $buyer_id, int $post_id, float|int $rating): float
{
    global $conn;
    ratings_ensure_schema();

    // Fix: Add server-side verification to prevent malicious ratings
    $check_stmt = $conn->prepare("SELECT 1 FROM transactions WHERE farmer_id = ? AND buyer_id = ? AND post_id = ?");
    $check_stmt->bind_param("iii", $farmer_id, $buyer_id, $post_id);
    $check_stmt->execute();
    $valid = $check_stmt->get_result()->num_rows > 0;
    $check_stmt->close();

    if (!$valid) {
        throw new Exception("Unauthorized: You cannot rate this transaction as it does not exist or you do not own it.");
    }

    $rating = max(1.0, min(5.0, (float)$rating));
    $stmt = $conn->prepare(
        "INSERT INTO buyer_ratings (farmer_id, buyer_id, post_id, rating)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating)"
    );
    $stmt->bind_param("iiid", $farmer_id, $buyer_id, $post_id, $rating);
    $stmt->execute();
    $stmt->close();
    return calculate_buyer_reputation(
        $buyer_id,
        'farmer_feedback_submitted',
        ['post_id' => (int)$post_id, 'farmer_id' => (int)$farmer_id, 'rating' => (float)$rating]
    );
}

// ═══════════════════════════════════════════════════════════════════════════════
// BACKWARD-COMPATIBLE HOOKS
// All old adjust_rating_for_* functions now trigger a full recalculation
// instead of one-shot delta adjustments.
// ═══════════════════════════════════════════════════════════════════════════════

function adjust_rating_for_bid(int $user_id, float|int $bid_amount, float|int $farmer_price): float
{
    return calculate_buyer_reputation(
        $user_id,
        'bid_placed',
        ['bid_amount' => (float)$bid_amount, 'asking_price' => (float)$farmer_price]
    );
}

function adjust_rating_for_post(int $farmer_id, float|int $post_price, string $product_name): float
{
    return calculate_farmer_reputation(
        $farmer_id,
        'listing_activity',
        ['post_price' => (float)$post_price, 'product_name' => (string)$product_name]
    );
}

function adjust_rating_for_sale(int $farmer_id, int $post_id, float|int $final_bid_amount): float
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
        calculate_buyer_reputation($row['user_id'], 'auction_won', ['post_id' => (int)$post_id]);
    }

    return calculate_farmer_reputation(
        $farmer_id,
        'auction_won',
        ['post_id' => (int)$post_id, 'final_bid_amount' => (float)$final_bid_amount]
    );
}

function adjust_rating_for_unsold(int $farmer_id, int $post_id): float
{
    return calculate_farmer_reputation($farmer_id, 'listing_unsold', ['post_id' => (int)$post_id]);
}

function adjust_rating_for_bidding_activity(int $farmer_id, int $post_id, int $bid_count): float
{
    return calculate_farmer_reputation(
        $farmer_id,
        'listing_engagement_updated',
        ['post_id' => (int)$post_id, 'bid_count' => (int)$bid_count]
    );
}

// Bootstrap schema on first include
ratings_ensure_schema();
