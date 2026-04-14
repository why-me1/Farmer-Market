<?php

require_once 'db.php';

function discoveryEnsureFollowTable()
{
    global $conn;

    $conn->query("CREATE TABLE IF NOT EXISTS `farmer_follows` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `farmer_id` INT NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_follow` (`user_id`, `farmer_id`),
        INDEX `idx_user_id` (`user_id`),
        INDEX `idx_farmer_id` (`farmer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function discoveryNormalizeIdList($rawValue)
{
    if (!is_string($rawValue) || trim($rawValue) === '') {
        return [];
    }

    $parts = array_map('trim', explode(',', $rawValue));
    $ids = [];

    foreach ($parts as $part) {
        if ($part !== '' && ctype_digit($part)) {
            $ids[] = (int)$part;
        }
    }

    return array_values(array_unique($ids));
}

function discoveryGetRecentlyViewedPostIds($limit = 6)
{
    $ids = discoveryNormalizeIdList($_COOKIE['recently_viewed_posts'] ?? '');
    if ($limit > 0) {
        $ids = array_slice($ids, 0, $limit);
    }

    return $ids;
}

function discoveryTrackRecentlyViewedPost($postId, $limit = 6)
{
    $postId = (int)$postId;
    if ($postId <= 0) {
        return;
    }

    $ids = discoveryNormalizeIdList($_COOKIE['recently_viewed_posts'] ?? '');
    $ids = array_values(array_filter($ids, function ($id) use ($postId) {
        return $id !== $postId;
    }));
    array_unshift($ids, $postId);
    $ids = array_slice(array_values(array_unique($ids)), 0, max(1, $limit));

    if (!headers_sent()) {
        setcookie('recently_viewed_posts', implode(',', $ids), time() + (86400 * 30), '/');
    }
}

function discoveryFetchPostsByIds($ids, $limit = null)
{
    global $conn;

    $ids = array_values(array_filter(array_map('intval', (array)$ids), function ($id) {
        return $id > 0;
    }));

    if (empty($ids)) {
        return [];
    }

    if ($limit !== null && $limit > 0) {
        $ids = array_slice($ids, 0, $limit);
    }

    $idList = implode(',', $ids);

    $sql = "SELECT posts.*, users.username,
                   (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) AS total_bids,
                   (SELECT COUNT(*) FROM reviews WHERE product_id = posts.id) AS total_reviews,
                   (SELECT MAX(CAST(comment_text AS DECIMAL(12,2))) FROM comments WHERE post_id = posts.id) AS highest_bid
            FROM posts
            JOIN users ON posts.farmer_id = users.id
            WHERE posts.id IN ($idList)
              AND posts.is_approved = 1
            ORDER BY FIELD(posts.id, $idList)";

    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function discoveryIsFollowingFarmer($userId, $farmerId)
{
    global $conn;

    discoveryEnsureFollowTable();

    $stmt = $conn->prepare("SELECT id FROM farmer_follows WHERE user_id = ? AND farmer_id = ? LIMIT 1");
    $stmt->bind_param("ii", $userId, $farmerId);
    $stmt->execute();
    $isFollowing = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $isFollowing;
}

function discoveryGetFarmerFollowerCount($farmerId)
{
    global $conn;

    discoveryEnsureFollowTable();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM farmer_follows WHERE farmer_id = ?");
    $stmt->bind_param("i", $farmerId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return (int)$count;
}

function discoveryGetFarmerFollowerIds($farmerId)
{
    global $conn;

    discoveryEnsureFollowTable();

    $stmt = $conn->prepare("SELECT user_id FROM farmer_follows WHERE farmer_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $farmerId);
    $stmt->execute();
    $result = $stmt->get_result();

    $followerIds = [];
    while ($row = $result->fetch_assoc()) {
        $followerIds[] = (int)$row['user_id'];
    }

    $stmt->close();

    return $followerIds;
}

function discoveryToggleFarmerFollow($userId, $farmerId)
{
    global $conn;

    discoveryEnsureFollowTable();

    if (discoveryIsFollowingFarmer($userId, $farmerId)) {
        $stmt = $conn->prepare("DELETE FROM farmer_follows WHERE user_id = ? AND farmer_id = ?");
        $stmt->bind_param("ii", $userId, $farmerId);
        $stmt->execute();
        $stmt->close();

        return ['following' => false, 'followers' => discoveryGetFarmerFollowerCount($farmerId)];
    }

    $stmt = $conn->prepare("INSERT INTO farmer_follows (user_id, farmer_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $userId, $farmerId);
    $stmt->execute();
    $stmt->close();

    return ['following' => true, 'followers' => discoveryGetFarmerFollowerCount($farmerId)];
}

function discoveryGetTrendingProducts($limit = 8)
{
    global $conn;

    $limit = max(1, (int)$limit);

    $sql = "SELECT posts.*, users.username,
                   COUNT(DISTINCT comments.id) AS total_bids,
                   COUNT(DISTINCT reviews.id) AS total_reviews,
                   MAX(CAST(comments.comment_text AS DECIMAL(12,2))) AS highest_bid,
                   (
                        (COUNT(DISTINCT comments.id) * 2)
                        + (COUNT(DISTINCT reviews.id) * 3)
                        + CASE
                            WHEN posts.auction_start_date <= NOW() AND posts.auction_end_date > NOW() THEN 6
                            WHEN posts.auction_end_date <= NOW() THEN 1
                            ELSE 2
                          END
                   ) AS trending_score
            FROM posts
            JOIN users ON posts.farmer_id = users.id
            LEFT JOIN comments ON comments.post_id = posts.id AND comments.is_approved = 1
            LEFT JOIN reviews ON reviews.product_id = posts.id
            WHERE posts.is_approved = 1
              AND posts.status = 'active'
            GROUP BY posts.id
            ORDER BY trending_score DESC, posts.created_at DESC
            LIMIT {$limit}";

    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function discoveryGetSimilarProducts($postId, $category, $farmerId, $limit = 4)
{
    global $conn;

    $postId = (int)$postId;
    $farmerId = (int)$farmerId;
    $limit = max(1, (int)$limit);
    $category = trim((string)$category);

    if ($category === '') {
        return [];
    }

    $categoryEscaped = $conn->real_escape_string($category);

    $sql = "SELECT posts.*, users.username,
                   COUNT(DISTINCT comments.id) AS total_bids,
                   MAX(CAST(comments.comment_text AS DECIMAL(12,2))) AS highest_bid,
                   COUNT(DISTINCT reviews.id) AS total_reviews
            FROM posts
            JOIN users ON posts.farmer_id = users.id
            LEFT JOIN comments ON comments.post_id = posts.id AND comments.is_approved = 1
            LEFT JOIN reviews ON reviews.product_id = posts.id
            WHERE posts.is_approved = 1
              AND posts.status = 'active'
              AND posts.category = '{$categoryEscaped}'
              AND posts.id <> {$postId}
            GROUP BY posts.id
            ORDER BY
                CASE WHEN posts.farmer_id = {$farmerId} THEN 0 ELSE 1 END,
                total_bids DESC,
                total_reviews DESC,
                posts.created_at DESC
            LIMIT {$limit}";

    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function discoveryGetRecentlyViewedProducts($limit = 6)
{
    $ids = discoveryGetRecentlyViewedPostIds($limit);
    return discoveryFetchPostsByIds($ids, $limit);
}
