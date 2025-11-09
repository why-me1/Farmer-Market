<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

    // Check if the post belongs to the logged-in farmer
    $stmt = $conn->prepare("SELECT id FROM posts WHERE id = ? AND farmer_id = ?");
    $stmt->bind_param("ii", $post_id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Delete the post and associated comments
        $delete_comments = $conn->prepare("DELETE FROM comments WHERE post_id = ?");
        $delete_comments->bind_param("i", $post_id);
        $delete_comments->execute();

        $delete_post = $conn->prepare("DELETE FROM posts WHERE id = ?");
        $delete_post->bind_param("i", $post_id);
        $delete_post->execute();

        header("Location: view_posts.php?success=Post deleted successfully.");
        exit();
    } else {
        header("Location: view_posts.php?error=Unauthorized action.");
        exit();
    }
    $stmt->close();
}
