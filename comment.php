<?php

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/notification_functions.php';
session_start();



if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $post_id = intval($_POST['post_id']);
    $comment_text = floatval($_POST['comment_text']); // Convert to float for proper numeric comparison
    $user_id = $_SESSION['user_id'];

    // Get the starting price for this post
    $price_stmt = $conn->prepare("SELECT price FROM posts WHERE id = ?");
    $price_stmt->bind_param("i", $post_id);
    $price_stmt->execute();
    $price_stmt->bind_result($starting_price);
    $price_stmt->fetch();
    $price_stmt->close();

    // Get the current highest bid for this post
    $highest_bid_stmt = $conn->prepare("SELECT MAX(CAST(comment_text AS DECIMAL(10,2))) as max_bid FROM comments WHERE post_id = ?");
    $highest_bid_stmt->bind_param("i", $post_id);
    $highest_bid_stmt->execute();
    $highest_bid_stmt->bind_result($current_highest_bid);
    $highest_bid_stmt->fetch();
    $highest_bid_stmt->close();

    // Validation: Bid must be higher than starting price
    if ($comment_text <= $starting_price) {
        $_SESSION['error_message'] = "Your bid must be higher than the starting price of " . number_format($starting_price, 2) . "৳";
        header("Location: index.php");
        exit();
    }

    // Validation: If there are existing bids, new bid must be higher than current highest bid
    if ($current_highest_bid !== null && $comment_text <= $current_highest_bid) {
        $_SESSION['error_message'] = "Your bid must be higher than the current highest bid of " . number_format($current_highest_bid, 2) . "৳";
        header("Location: index.php");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, comment_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $post_id, $user_id, $comment_text);
    if ($stmt->execute()) {

        $post_stmt = $conn->prepare("SELECT farmer_id, product_name FROM posts WHERE id = ?");
        $post_stmt->bind_param("i", $post_id);
        $post_stmt->execute();
        $post_stmt->bind_result($farmer_id, $product_name);
        $post_stmt->fetch();
        $post_stmt->close();

        // Send notification to farmer about new bid
        if ($farmer_id && $product_name) {
            $buyer_name = getUsername($user_id);
            notifyFarmerBidPlaced($farmer_id, $post_id, $buyer_name, $comment_text, $product_name);
        }

        // Check for outbid notifications - notify other bidders
        $stmt = $conn->prepare("SELECT DISTINCT user_id FROM comments WHERE post_id = ? AND user_id != ? AND is_approved = 0");
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            notifyBuyerOutbid($row['user_id'], $post_id, $product_name);
        }
        $stmt->close();

        $_SESSION['success_message'] = "Your bid of " . number_format($comment_text, 2) . "৳ has been placed successfully!";
        header("Location: index.php");
        exit();
    } else {
        echo "Failed to add comment.";
    }
    $stmt->close();
} else {
    header("Location: login.php");
    exit();
}
