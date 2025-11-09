<?php

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/notification_functions.php';
check_login();

if ($_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

if (isset($_GET['action']) && isset($_GET['comment_id'])) {
    $action = $_GET['action'];
    $comment_id = intval($_GET['comment_id']);

    if ($action == 'approve') {
        // Approve comment
        $stmt = $conn->prepare("UPDATE comments SET is_approved = 1 WHERE id = ?");
        $stmt->bind_param("i", $comment_id);
        $stmt->execute();
        $stmt->close();

        // Get post id associated with the comment
        $stmt = $conn->prepare("SELECT post_id, user_id FROM comments WHERE id = ?");
        $stmt->bind_param("i", $comment_id);
        $stmt->execute();
        $stmt->bind_result($post_id, $user_id);
        $stmt->fetch();
        $stmt->close();

        // Get product name and update post status
        $stmt = $conn->prepare("SELECT product_name FROM posts WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->bind_result($product_name);
        $stmt->fetch();
        $stmt->close();

        // Update post status to sold
        $stmt = $conn->prepare("UPDATE posts SET status = 'sold' WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();

        // Send notifications
        $farmer_name = getUsername($farmer_id);
        $buyer_name = getUsername($user_id);

        // Notify farmer about sale
        notifyFarmerProductSold($farmer_id, $post_id, $buyer_name, $product_name);

        // Notify buyer about winning bid
        notifyBuyerWonBid($user_id, $post_id, $product_name);

        // Delete all comments for the post
        $stmt = $conn->prepare("DELETE FROM comments WHERE post_id = ? AND is_approved = 0");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();
        // Optional: Update post approval status if needed
        $stmt = $conn->prepare("UPDATE posts SET is_approved = is_approved WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();

        header("Location: manage_comments.php");
        exit();
    }
}

// Fetch comments for this farmer
$stmt = $conn->prepare("SELECT comments.*, users.username, posts.product_name FROM comments 
                        JOIN users ON comments.user_id = users.id 
                        JOIN posts ON comments.post_id = posts.id
                        WHERE posts.farmer_id = ? AND comments.is_approved = 0");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Comments</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css"> -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <!-- browser cache problem solution --- add version number for production and add echo time for development -->
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h2 class="mb-0">Manage Comments</h2>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows === 0): ?>
                    <div class="alert alert-info" role="alert">
                        No comments to review.
                    </div>
                <?php else: ?>
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Comment ID</th>
                                <th>Post</th>
                                <th>User</th>
                                <th>Comment</th>
                                <th>Submitted At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($comment = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($comment['id']); ?></td>
                                    <td><?php echo htmlspecialchars($comment['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($comment['username']); ?></td>
                                    <td><?php echo htmlspecialchars($comment['comment_text']); ?></td>
                                    <td><?php echo htmlspecialchars($comment['created_at']); ?></td>
                                    <td>
                                        <a href="?action=approve&comment_id=<?php echo $comment['id']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <!-- Include Bootstrap JS and FontAwesome for icons -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
</body>

</html>