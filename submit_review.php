<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/ratings.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'index.php?auth=login');
    exit();
}

// Get POST data and validate
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$user_id = $_SESSION['user_id']; // Get from session instead of POST
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';

// Validate data
if ($product_id <= 0 || $rating < 1 || $rating > 5 || empty($review_text)) {
    $redirect = $product_id > 0 ? "product_detail.php?id={$product_id}#review-section" : "index.php";
    header("Location: " . $redirect);
    exit();
}

// Insert the review into the database
$sql = "INSERT INTO reviews (product_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiis", $product_id, $user_id, $rating, $review_text);

if ($stmt->execute()) {
    // Recalculate farmer's reputation — BuyerRatings factor (40% weight) just changed
    $farmer_stmt = $conn->prepare("SELECT farmer_id FROM posts WHERE id = ? LIMIT 1");
    $farmer_stmt->bind_param("i", $product_id);
    $farmer_stmt->execute();
    $farmer_row = $farmer_stmt->get_result()->fetch_assoc();
    $farmer_stmt->close();
    if ($farmer_row && $farmer_row['farmer_id']) {
        calculate_farmer_reputation(
            $farmer_row['farmer_id'],
            'review_submitted',
            ['product_id' => (int)$product_id, 'reviewer_id' => (int)$user_id, 'rating' => (int)$rating]
        );
    }

    // Set success message in session
    $_SESSION['success_message'] = 'Review submitted successfully!';
    // Redirect back to the product page
    header("Location: product_detail.php?id={$product_id}#review-section");
    exit();
} else {
    error_log("Database error: " . $stmt->error);
    $_SESSION['error_message'] = 'Failed to submit review. Please try again.';
    header("Location: product_detail.php?id={$product_id}#review-section");
    exit();
}

$stmt->close();
$conn->close();
