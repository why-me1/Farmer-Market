<?php

require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $action = $_GET['action'];
    $user_id = intval($_GET['user_id']);

    if ($action == 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: manage_users.php");
        exit();
    }

    if ($action == 'promote') {
        $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: manage_users.php");
        exit();
    }
}
?>

<?php include '../includes/header.php'; ?>
<div class="container mt-5">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h2 class="mb-0">Manage Users</h2>
        </div>
        <div class="card-body">
            <table class="table table-striped table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Registered At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT id, username, role, created_at FROM users WHERE role != 'admin'");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($user = $result->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($user['role'])); ?></td>
                            <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                            <td>
                                <?php if ($user['role'] !== 'admin'): ?>
                                    <a href="?action=promote&user_id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-user-shield"></i> Promote as Admin
                                    </a>
                                <?php endif; ?>
                                <a href="?action=delete&user_id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?')">
                                    <i class="fas fa-trash"></i> Delete
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