<?php
session_start();
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get POST data and validate
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$user_id = $_SESSION['user_id']; // Get from session instead of POST
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';

// Validate data
if ($product_id <= 0 || $rating < 1 || $rating > 5 || empty($review_text)) {
    header("Location: index.php?error=invalid_data");
    exit();
}

// Insert the review into the database
$sql = "INSERT INTO reviews (product_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiis", $product_id, $user_id, $rating, $review_text);

if ($stmt->execute()) {
    // Set success message in session
    $_SESSION['success_message'] = 'Review submitted successfully!';
    // After the review is submitted, redirect back to the product page
    header("Location: index.php");
    exit();
} else {
    error_log("Database error: " . $stmt->error);
    $_SESSION['error_message'] = 'Failed to submit review. Please try again.';
    header("Location: index.php");
    exit();
}

$stmt->close();
$conn->close();
