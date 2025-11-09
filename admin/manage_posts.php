<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['action']) && isset($_GET['post_id'])) {
    $action = $_GET['action'];
    $post_id = intval($_GET['post_id']);

    if ($action == 'approve') {
        // Approve the post without expiry date
        $stmt = $conn->prepare("UPDATE posts SET is_approved = 1 WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();

        // Get farmer_id for potential notification purposes
        $stmt = $conn->prepare("SELECT farmer_id FROM posts WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->bind_result($farmer_id);
        $stmt->fetch();
        $stmt->close();

        // Uncomment the notification system if implemented
        // try {
        //     send_notification($conn, $farmer_id, $post_id, null, 'post_approved');
        // } catch (Exception $e) {
        //     echo "Error: " . $e->getMessage();
        // }

        header("Location: manage_posts.php");
        exit();
    }

    if ($action == 'reject') {
        // Delete the post if rejected
        $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();
        header("Location: manage_posts.php");
        exit();
    }
}
?>

<?php include '../includes/header.php'; ?>
<div class="container mt-5">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h2 class="mb-0">Manage Posts</h2>
        </div>
        <div class="card-body">
            <table class="table table-striped table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Farmer</th>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Submitted At</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch all posts pending approval
                    $stmt = $conn->prepare("SELECT posts.*, users.username FROM posts JOIN users ON posts.farmer_id = users.id WHERE posts.is_approved = 0");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($post = $result->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($post['id']); ?></td>
                            <td><?php echo htmlspecialchars($post['username']); ?></td>
                            <td><?php echo htmlspecialchars($post['product_name']); ?></td>
                            <td>৳<?php echo number_format($post['price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($post['created_at']); ?></td>
                            <td><?php echo $post['is_approved'] ? 'Approved' : 'Pending'; ?></td>
                            <td>
                                <a href="?action=approve&post_id=<?php echo $post['id']; ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                                <a href="?action=reject&post_id=<?php echo $post['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to reject this post?')">
                                    <i class="fas fa-times"></i> Reject
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
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
