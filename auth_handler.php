<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

ensure_user_moderation_schema();

// ── LOGIN ──────────────────────────────────────────────────────────────────
if ($action === 'login') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh and try again.']);
        exit();
    }

    $lockout_duration = 30;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
        exit();
    }

    $stmt = $conn->prepare("SELECT id, password, role, failed_attempts, last_attempt, is_banned FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($id, $hashed_password, $role, $failed_attempts, $last_attempt, $is_banned);

    if ($stmt->fetch()) {
        $stmt->close();
        $time_since_last_attempt = $last_attempt ? time() - strtotime($last_attempt) : PHP_INT_MAX;

        if ($failed_attempts >= 3 && $time_since_last_attempt < $lockout_duration) {
            $remaining = $lockout_duration - $time_since_last_attempt;
            echo json_encode(['success' => false, 'message' => "Account locked. Try again in {$remaining} seconds."]);
            exit();
        }

        if ((int) $is_banned === 1) {
            echo json_encode(['success' => false, 'message' => 'This account has been banned by the administrator.']);
            exit();
        }

        if ($hashed_password && password_verify($password, $hashed_password)) {
            $_SESSION['user_id']  = $id;
            $_SESSION['username'] = $username;
            $_SESSION['role']     = $role;

            // Reset failed attempts
            $upd = $conn->prepare("UPDATE users SET failed_attempts = 0, last_attempt = NULL WHERE id = ?");
            $upd->bind_param("i", $id);
            $upd->execute();
            $upd->close();

            // Build redirect URL
            $redirect = '';
            $requested = filter_var($_POST['redirect'] ?? '', FILTER_SANITIZE_URL);
            // Only allow relative redirects
            if (!empty($requested) && strpos($requested, '://') === false && strpos($requested, '//') !== 0) {
                if ($role !== 'admin') {
                    $redirect = $requested;
                }
            }
            if (empty($redirect)) {
                $redirect = match ($role) {
                    'admin'  => 'admin/dashboard.php',
                    'farmer' => 'farmer/dashboard.php',
                    default  => '', // reload current page
                };
            }

            echo json_encode(['success' => true, 'redirect' => $redirect]);
        } else {
            $failed_attempts++;
            $current_time = date("Y-m-d H:i:s");
            $upd = $conn->prepare("UPDATE users SET failed_attempts = ?, last_attempt = ? WHERE id = ?");
            $upd->bind_param("isi", $failed_attempts, $current_time, $id);
            $upd->execute();
            $upd->close();

            $msg = ($failed_attempts >= 3)
                ? "Account locked due to too many failed attempts. Try again in {$lockout_duration} seconds."
                : "Incorrect username or password.";
            echo json_encode(['success' => false, 'message' => $msg]);
        }
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Incorrect username or password.']);
    }
    exit();
}

// ── REGISTER ───────────────────────────────────────────────────────────────
if ($action === 'register') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = sanitize($_POST['role'] ?? '');

    if (empty($username) || empty($password) || empty($role)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit();
    }

    if (!in_array($role, ['user', 'farmer'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
        exit();
    }

    // Check duplicate username
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Username already exists. Please choose another.']);
        exit();
    }
    $stmt->close();

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $ins    = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $ins->bind_param("sss", $username, $hashed, $role);

    if ($ins->execute()) {
        $ins->close();
        echo json_encode(['success' => true, 'message' => 'Account created! You can now log in.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }
    exit();
}

// ── REFRESH CSRF ───────────────────────────────────────────────────────────
if ($action === 'get_csrf') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    echo json_encode(['token' => $_SESSION['csrf_token']]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
