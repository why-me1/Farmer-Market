<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

$errors = [];
$lockout_duration = 30; // Lockout time in seconds

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token. Please refresh the page and try again.";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT id, password, role, failed_attempts, last_attempt FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($id, $hashed_password, $role, $failed_attempts, $last_attempt);

        if ($stmt->fetch()) {
            $stmt->close();
            $time_since_last_attempt = time() - strtotime($last_attempt);

            if ($failed_attempts >= 3 && $time_since_last_attempt < $lockout_duration) {
                $errors[] = "Account locked. Try again after " . ($lockout_duration - $time_since_last_attempt) . " seconds.";
            } else {
                if (password_verify($password, $hashed_password)) {
                    $_SESSION['user_id'] = $id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;

                    // Reset failed attempts
                    $stmt = $conn->prepare("UPDATE users SET failed_attempts = 0, last_attempt = NULL WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $stmt->close();

                    // Redirect based on role
                    $redirect_url = match ($role) {
                        'admin' => 'admin/dashboard.php',
                        'farmer' => 'farmer/dashboard.php',
                        default => 'index.php',
                    };
                    header("Location: $redirect_url");
                    exit();
                } else {
                    $failed_attempts++;
                    $current_time = date("Y-m-d H:i:s");

                    $stmt = $conn->prepare("UPDATE users SET failed_attempts = ?, last_attempt = ? WHERE id = ?");
                    $stmt->bind_param("isi", $failed_attempts, $current_time, $id);
                    $stmt->execute();
                    $stmt->close();

                    if ($failed_attempts >= 3) {
                        $errors[] = "Account locked due to multiple failed attempts. Try again after $lockout_duration seconds.";
                    } else {
                        $errors[] = "Incorrect username or password.";
                    }
                }
            }
        } else {
            $errors[] = "Incorrect username or password.";
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
                                        <i class="fas fa-leaf"></i>
                                    </div>
                                    <h2>Farmer Market</h2>
                                    <p>Connect with local farmers and get fresh, organic produce delivered to your doorstep.</p>
                                    <div class="login-features">
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span>Fresh Produce Daily</span>
                                        </div>
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span>Direct from Farmers</span>
                                        </div>
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span>Secure Bidding System</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side: Login Form -->
                        <div class="col-lg-6">
                            <div class="login-form-section">
                                <div class="login-form-header">
                                    <h2>Welcome Back</h2>
                                    <p>Sign in to your account</p>
                                </div>

                                <?php if (isset($_GET['register']) && $_GET['register'] == 'success'): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle me-2"></i>Registration successful. Please login.
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <?php foreach ($errors as $error) echo "<p class='mb-0'>$error</p>"; ?>
                                    </div>
                                <?php endif; ?>

                                <form id="loginForm" action="login.php" method="POST" class="login-form needs-validation" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                    <div class="form-group">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-user"></i> Username
                                        </label>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                        <div class="form-line"></div>
                                        <div class="invalid-feedback">
                                            Please enter your username.
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock"></i> Password
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <div class="form-line"></div>
                                        <div class="invalid-feedback">
                                            Please enter your password.
                                        </div>
                                    </div>

                                    <div class="form-options">
                                        <div class="remember-me">
                                            <input type="checkbox" id="remember" name="remember">
                                            <label for="remember">Remember me</label>
                                        </div>
                                        <a href="#" class="forgot-password">Forgot Password?</a>
                                    </div>

                                    <button type="submit" class="login-btn">
                                        <span>Sign In</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                </form>

                                <div class="login-footer">
                                    <p>Don't have an account? <a href="register.php" class="register-link">Create one here</a></p>
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
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var timerElement = document.getElementById('timer');
        var loginForm = document.getElementById('loginForm');

        if (timerElement) {
            var remainingTime = parseInt(timerElement.textContent);
            var interval = setInterval(function() {
                remainingTime--;
                if (remainingTime <= 0) {
                    clearInterval(interval);
                    timerElement.textContent = '0';
                    loginForm.querySelector('button').disabled = false;
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', 'scripts/clear_message.php', true);
                    xhr.send();
                } else {
                    timerElement.textContent = remainingTime;
                }
            }, 1000);
            loginForm.querySelector('button').disabled = true;
        }
    });
</script>