<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = sanitize($_POST['password']);
    $role = sanitize($_POST['role']);

    if (empty($username) || empty($password) || empty($role)) {
        $errors[] = "All fields are required.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Username already exists.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed_password, $role);
        if ($stmt->execute()) {
            header("Location: login.php?register=success");
            exit();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
        $stmt->close();
    }
}
?>

<?php include 'includes/header.php'; ?>
<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<!-- <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css"> -->
<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
<!-- browser cache problem solution --- add version number for production and add echo time for development -->




<div class="login-wrapper">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-lg-10">
                <div class="login-card">
                    <div class="row no-gutters">
                        <!-- Left Side: Image Section -->
                        <div class="col-lg-6 d-none d-lg-block">
                            <div class="login-image-section">
                                <div class="login-image-content">
                                    <div class="login-icon-container">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <h2>Join Farmer Market</h2>
                                    <p>Create your account and start connecting with local farmers. Get fresh produce delivered to your doorstep.</p>
                                    <div class="login-features">
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span>Easy Registration</span>
                                        </div>
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span>Secure Platform</span>
                                        </div>
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span>Start Bidding Today</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side: Registration Form -->
                        <div class="col-lg-6">
                            <div class="login-form-section">
                                <div class="login-form-header">
                                    <h2>Create Account</h2>
                                    <p>Sign up to get started</p>
                                </div>

                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <?php foreach ($errors as $error) echo "<p class='mb-0'>$error</p>"; ?>
                                    </div>
                                <?php endif; ?>

                                <form action="register.php" method="POST" class="login-form">
                                    <div class="form-group">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-user"></i> Username
                                        </label>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                        <div class="invalid-feedback">
                                            Please enter a username.
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock"></i> Password
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <div class="invalid-feedback">
                                            Please enter a password.
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="role" class="form-label">
                                            <i class="fas fa-user-tag"></i> Register As
                                        </label>
                                        <select name="role" class="form-control register-select" id="role" required>
                                            <option value="">Select your role</option>
                                            <option value="user">User</option>
                                            <option value="farmer">Farmer</option>
                                        </select>
                                        <div class="invalid-feedback">
                                            Please select a role.
                                        </div>
                                    </div>

                                    <button type="submit" class="login-btn">
                                        <span>Create Account</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                </form>

                                <div class="login-footer">
                                    <p>Already have an account? <a href="login.php" class="register-link">Login here</a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>