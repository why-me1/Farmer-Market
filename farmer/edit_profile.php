<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];
$errors    = [];
$success   = [];

// ─── Ensure extended columns exist ───────────────────────────────────────────
$conn->query("ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `full_name`       VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `email`           VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `phone`           VARCHAR(20)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `location`        VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `bio`             TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `profile_picture` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `farm_name`       VARCHAR(100) DEFAULT NULL");

// ─── Fetch current farmer data ────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT username, full_name, email, phone, location, bio, profile_picture, farm_name FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$farmer = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ─── Handle form submission ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'profile';

    // ── Profile info update ──────────────────────────────────────────────────
    if ($action === 'profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email']     ?? '');
        $phone     = trim($_POST['phone']     ?? '');
        $location  = trim($_POST['location']  ?? '');
        $bio       = trim($_POST['bio']       ?? '');
        $farm_name = trim($_POST['farm_name'] ?? '');

        if (mb_strlen($full_name) > 100) $errors[] = 'Full name is too long (max 100 characters).';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if (mb_strlen($phone) > 20) $errors[] = 'Phone number is too long.';
        if (mb_strlen($location) > 255) $errors[] = 'Location is too long.';
        if (mb_strlen($bio) > 1000) $errors[] = 'Bio must be under 1000 characters.';
        if (mb_strlen($farm_name) > 100) $errors[] = 'Farm name is too long (max 100 characters).';

        // Email uniqueness check
        if ($email !== '' && empty($errors)) {
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->bind_param("si", $email, $farmer_id);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) $errors[] = 'That email address is already in use.';
            $chk->close();
        }

        if (empty($errors)) {
            $upd = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, location=?, bio=?, farm_name=? WHERE id=?");
            $upd->bind_param("ssssssi", $full_name, $email, $phone, $location, $bio, $farm_name, $farmer_id);
            if ($upd->execute()) {
                $success[] = 'Profile updated successfully!';
                $farmer['full_name'] = $full_name;
                $farmer['email']     = $email;
                $farmer['phone']     = $phone;
                $farmer['location']  = $location;
                $farmer['bio']       = $bio;
                $farmer['farm_name'] = $farm_name;
            } else {
                $errors[] = 'Database error. Please try again.';
            }
            $upd->close();
        }
    }

    // ── Avatar / profile picture upload ─────────────────────────────────────
    if ($action === 'avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['avatar'];
            $maxSize = 2 * 1024 * 1024;
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo   = finfo_open(FILEINFO_MIME_TYPE);
            $mime    = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if ($file['size'] > $maxSize) {
                $errors[] = 'Image must be under 2 MB.';
            } elseif (!in_array($mime, $allowed)) {
                $errors[] = 'Only JPEG, PNG, GIF, and WebP images are allowed.';
            } else {
                $uploadDir = dirname(__DIR__) . '/assets/images/avatars/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'farmer_' . $farmer_id . '_' . time() . '.' . strtolower($ext);
                $dest     = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    if (!empty($farmer['profile_picture'])) {
                        $old = $uploadDir . basename($farmer['profile_picture']);
                        if (file_exists($old)) @unlink($old);
                    }
                    $picPath = 'assets/images/avatars/' . $filename;
                    $upd = $conn->prepare("UPDATE users SET profile_picture=? WHERE id=?");
                    $upd->bind_param("si", $picPath, $farmer_id);
                    $upd->execute();
                    $upd->close();
                    $farmer['profile_picture'] = $picPath;
                    $success[] = 'Profile picture updated!';
                } else {
                    $errors[] = 'Failed to upload image. Please try again.';
                }
            }
        } else {
            $errors[] = 'Please select an image file to upload.';
        }
    }

    // ── Remove avatar ────────────────────────────────────────────────────────
    if ($action === 'remove_avatar') {
        if (!empty($farmer['profile_picture'])) {
            $old = dirname(__DIR__) . '/' . $farmer['profile_picture'];
            if (file_exists($old)) @unlink($old);
            $upd = $conn->prepare("UPDATE users SET profile_picture=NULL WHERE id=?");
            $upd->bind_param("i", $farmer_id);
            $upd->execute();
            $upd->close();
            $farmer['profile_picture'] = null;
        }
        $success[] = 'Profile picture removed.';
    }

    // ── Password change ──────────────────────────────────────────────────────
    if ($action === 'password') {
        $current = $_POST['current_password']  ?? '';
        $newpass = $_POST['new_password']       ?? '';
        $confirm = $_POST['confirm_password']   ?? '';

        $ph = $conn->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $ph->bind_param("i", $farmer_id);
        $ph->execute();
        $ph->bind_result($hash);
        $ph->fetch();
        $ph->close();

        if (!password_verify($current, $hash))  $errors[] = 'Current password is incorrect.';
        elseif (strlen($newpass) < 8)           $errors[] = 'New password must be at least 8 characters.';
        elseif ($newpass !== $confirm)          $errors[] = 'New passwords do not match.';
        else {
            $newHash = password_hash($newpass, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $upd->bind_param("si", $newHash, $farmer_id);
            if ($upd->execute()) $success[] = 'Password changed successfully!';
            else                 $errors[]  = 'Database error. Please try again.';
            $upd->close();
        }
    }
}

// ─── Avatar helpers ───────────────────────────────────────────────────────────
$initials   = strtoupper(substr($farmer['username'], 0, 2));
$has_avatar = !empty($farmer['profile_picture']) && file_exists(dirname(__DIR__) . '/' . $farmer['profile_picture']);
$avatar_url = $has_avatar ? $base_url . $farmer['profile_picture'] : null;
$bio_len    = mb_strlen($farmer['bio'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile &mdash; Farmers' Marketplace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(160deg, #ecfdf5 0%, #f1f5f9 40%, #f0fdf4 100%);
            min-height: 100vh;
        }

        .ep-hero {
            background: linear-gradient(135deg, #0d6e5e 0%, #11998e 50%, #38ef7d 100%);
            border-radius: 20px;
            padding: 36px 40px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(17, 153, 142, .35);
            margin-bottom: 28px;
        }

        .ep-hero::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .07);
            top: -80px;
            right: -60px;
        }

        .ep-hero-inner {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .ep-hero-back {
            background: rgba(255, 255, 255, .18);
            border: 1px solid rgba(255, 255, 255, .3);
            color: #fff;
            border-radius: 10px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background .2s;
        }

        .ep-hero-back:hover {
            background: rgba(255, 255, 255, .28);
            color: #fff;
        }

        .ep-hero-text h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(20px, 3vw, 28px);
            font-weight: 800;
            margin: 0 0 4px;
        }

        .ep-hero-text p {
            font-size: 13.5px;
            opacity: .8;
            margin: 0;
        }

        .ep-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .08);
            border: 1px solid #edf0f6;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .ep-card-head {
            padding: 18px 26px;
            border-bottom: 1px solid #f1f4f8;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ep-card-head .ch-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .ch-icon.green {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: #fff;
        }

        .ch-icon.amber {
            background: linear-gradient(135deg, #f7971e, #ffd200);
            color: #fff;
        }

        .ch-icon.purple {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }

        .ep-card-head h5 {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
        }

        .ep-card-head .ch-sub {
            font-size: 12px;
            color: #aab3bd;
            font-weight: 400;
        }

        .ep-card-body {
            padding: 26px;
        }

        .ep-avatar-wrap {
            display: flex;
            align-items: center;
            gap: 22px;
            flex-wrap: wrap;
            padding: 22px 26px;
        }

        .ep-avatar-img {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 18px rgba(17, 153, 142, .35);
            flex-shrink: 0;
        }

        .ep-avatar-placeholder {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 800;
            color: #fff;
            border: 4px solid #fff;
            box-shadow: 0 4px 18px rgba(17, 153, 142, .35);
            flex-shrink: 0;
        }

        .ep-avatar-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn-upload {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .2s;
        }

        .btn-upload:hover {
            opacity: .88;
        }

        .btn-remove {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 7px 14px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
            text-decoration: none;
        }

        .btn-remove:hover {
            background: #fee2e2;
        }

        .ep-avatar-hint {
            font-size: 12px;
            color: #aab3bd;
            margin-top: 4px;
        }

        .ep-form-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
            display: block;
        }

        .ep-form-control {
            width: 100%;
            border: 1.5px solid #e4e8f0;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #1a1a2e;
            background: #fafbff;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }

        .ep-form-control:focus {
            border-color: #11998e;
            box-shadow: 0 0 0 3px rgba(17, 153, 142, .15);
            background: #fff;
        }

        textarea.ep-form-control {
            resize: vertical;
            min-height: 100px;
        }

        .ep-form-hint {
            font-size: 11.5px;
            color: #aab3bd;
            margin-top: 4px;
        }

        .ep-char-count {
            font-size: 11.5px;
            color: #aab3bd;
            text-align: right;
        }

        .ep-btn-save {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: #fff;
            border: none;
            border-radius: 11px;
            padding: 11px 28px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 4px 14px rgba(17, 153, 142, .35);
        }

        .ep-btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(17, 153, 142, .45);
        }

        .ep-alert {
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 13.5px;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
        }

        .ep-alert.success {
            background: #e8faf3;
            color: #0b6e52;
            border: 1px solid #a7f3d0;
        }

        .ep-alert.error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .ep-alert i {
            margin-top: 1px;
            flex-shrink: 0;
        }

        .ep-nav-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #edf0f6;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .06);
            overflow: hidden;
            position: sticky;
            top: 90px;
        }

        .ep-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-bottom: 1px solid #f1f4f8;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            transition: background .15s;
            font-size: 13.5px;
            font-weight: 600;
            color: #4a5568;
        }

        .ep-nav-item:last-child {
            border-bottom: none;
        }

        .ep-nav-item:hover,
        .ep-nav-item.active {
            background: #f0fdf4;
            color: #11998e;
        }

        .ep-nav-item .ni-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
            background: #dcfce7;
            color: #11998e;
        }

        .ep-nav-item.active .ni-icon,
        .ep-nav-item:hover .ni-icon {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: #fff;
        }

        .ep-divider {
            height: 1px;
            background: #f1f4f8;
            margin: 20px 0;
        }

        .pw-strength-bar {
            height: 6px;
            border-radius: 20px;
            background: #e4e8f0;
            overflow: hidden;
            margin-top: 6px;
        }

        .pw-strength-fill {
            height: 100%;
            border-radius: 20px;
            transition: width .3s, background .3s;
            width: 0;
        }

        .pw-strength-text {
            font-size: 11.5px;
            margin-top: 4px;
            font-weight: 600;
        }

        /* Farm name highlight */
        .farm-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #dcfce7;
            color: #0b6e52;
            border-radius: 8px;
            padding: 3px 10px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="main-container">
        <div class="container py-4" style="max-width:1100px;">

            <!-- Hero -->
            <div class="ep-hero">
                <div class="ep-hero-inner">
                    <a href="dashboard.php" class="ep-hero-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
                    <div class="ep-hero-text">
                        <h1><i class="fas fa-user-edit me-2"></i>Edit Profile</h1>
                        <p>Update your farmer profile, farm details, and account settings.</p>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (!empty($success)): ?>
                <div class="ep-alert success mb-3">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo implode('<br>', array_map('htmlspecialchars', $success)); ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="ep-alert error mb-3">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Sidebar -->
                <div class="col-lg-3">
                    <div class="ep-nav-card">
                        <a href="#section-avatar" class="ep-nav-item active" onclick="scrollTo('section-avatar')">
                            <span class="ni-icon"><i class="fas fa-camera"></i></span> Photo
                        </a>
                        <a href="#section-profile" class="ep-nav-item" onclick="scrollTo('section-profile')">
                            <span class="ni-icon"><i class="fas fa-tractor"></i></span> Farm &amp; Info
                        </a>
                        <a href="#section-password" class="ep-nav-item" onclick="scrollTo('section-password')">
                            <span class="ni-icon"><i class="fas fa-lock"></i></span> Password
                        </a>
                        <a href="dashboard.php" class="ep-nav-item">
                            <span class="ni-icon"><i class="fas fa-th-large"></i></span> Back to Dashboard
                        </a>
                        <a href="profile.php?id=<?php echo $farmer_id; ?>" class="ep-nav-item">
                            <span class="ni-icon"><i class="fas fa-eye"></i></span> View Public Profile
                        </a>
                    </div>
                </div>

                <!-- Main content -->
                <div class="col-lg-9">

                    <!-- ── Profile Picture ─────────────────────────────────── -->
                    <div class="ep-card" id="section-avatar">
                        <div class="ep-card-head">
                            <span class="ch-icon green"><i class="fas fa-camera"></i></span>
                            <div>
                                <h5>Profile Photo <span class="ch-sub">— Optional</span></h5>
                            </div>
                        </div>
                        <div class="ep-avatar-wrap">
                            <?php if ($avatar_url): ?>
                                <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="ep-avatar-img">
                            <?php else: ?>
                                <div class="ep-avatar-placeholder"><?php echo $initials; ?></div>
                            <?php endif; ?>

                            <div>
                                <form method="POST" enctype="multipart/form-data" style="display:inline;">
                                    <input type="hidden" name="action" value="avatar">
                                    <label class="ep-avatar-actions">
                                        <span class="btn-upload" onclick="document.getElementById('avatarInput').click()">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <?php echo $avatar_url ? 'Change Photo' : 'Upload Photo'; ?>
                                        </span>
                                    </label>
                                    <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display:none;"
                                        onchange="this.closest('form').submit()">
                                </form>
                                <?php if ($avatar_url): ?>
                                    <form method="POST" style="display:inline; margin-top:8px;">
                                        <input type="hidden" name="action" value="remove_avatar">
                                        <button type="submit" class="ep-avatar-actions mt-2">
                                            <span class="btn-remove"><i class="fas fa-trash-alt"></i> Remove Photo</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <p class="ep-avatar-hint mt-2">JPEG, PNG, GIF, or WebP &bull; Max 2 MB</p>
                            </div>
                        </div>
                    </div>

                    <!-- ── Farm & Personal Information ────────────────────── -->
                    <div class="ep-card" id="section-profile">
                        <div class="ep-card-head">
                            <span class="ch-icon green"><i class="fas fa-tractor"></i></span>
                            <div>
                                <h5>Farm &amp; Personal Information</h5>
                            </div>
                        </div>
                        <div class="ep-card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="profile">

                                <div class="row g-3">
                                    <!-- Username (readonly) -->
                                    <div class="col-md-6">
                                        <label class="ep-form-label">Username</label>
                                        <input type="text" class="ep-form-control" value="<?php echo htmlspecialchars($farmer['username']); ?>" disabled>
                                        <p class="ep-form-hint">Username cannot be changed.</p>
                                    </div>
                                    <!-- Full Name -->
                                    <div class="col-md-6">
                                        <label class="ep-form-label" for="full_name">Full Name</label>
                                        <input type="text" id="full_name" name="full_name" class="ep-form-control"
                                            maxlength="100"
                                            value="<?php echo htmlspecialchars($farmer['full_name'] ?? ''); ?>"
                                            placeholder="Your full name">
                                    </div>
                                    <!-- Farm Name -->
                                    <div class="col-md-6">
                                        <label class="ep-form-label" for="farm_name">
                                            Farm Name <span class="farm-badge"><i class="fas fa-seedling"></i> Farmer</span>
                                        </label>
                                        <input type="text" id="farm_name" name="farm_name" class="ep-form-control"
                                            maxlength="100"
                                            value="<?php echo htmlspecialchars($farmer['farm_name'] ?? ''); ?>"
                                            placeholder="e.g. Green Valley Farm">
                                    </div>
                                    <!-- Location -->
                                    <div class="col-md-6">
                                        <label class="ep-form-label" for="location">Farm Location</label>
                                        <input type="text" id="location" name="location" class="ep-form-control"
                                            maxlength="255"
                                            value="<?php echo htmlspecialchars($farmer['location'] ?? ''); ?>"
                                            placeholder="City, Region, Country">
                                    </div>
                                    <!-- Email -->
                                    <div class="col-md-6">
                                        <label class="ep-form-label" for="email">Email Address</label>
                                        <input type="email" id="email" name="email" class="ep-form-control"
                                            value="<?php echo htmlspecialchars($farmer['email'] ?? ''); ?>"
                                            placeholder="you@example.com">
                                    </div>
                                    <!-- Phone -->
                                    <div class="col-md-6">
                                        <label class="ep-form-label" for="phone">Phone Number</label>
                                        <input type="tel" id="phone" name="phone" class="ep-form-control"
                                            maxlength="20"
                                            value="<?php echo htmlspecialchars($farmer['phone'] ?? ''); ?>"
                                            placeholder="+1 234 567 890">
                                    </div>
                                    <!-- Bio -->
                                    <div class="col-12">
                                        <label class="ep-form-label" for="bio">About Your Farm</label>
                                        <textarea id="bio" name="bio" class="ep-form-control"
                                            maxlength="1000"
                                            placeholder="Tell buyers about your farm, products, and practices…"><?php echo htmlspecialchars($farmer['bio'] ?? ''); ?></textarea>
                                        <div class="d-flex justify-content-between">
                                            <p class="ep-form-hint">Max 1000 characters.</p>
                                            <span class="ep-char-count" id="bioCount"><?php echo $bio_len; ?>/1000</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="ep-divider"></div>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="ep-btn-save">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ── Change Password ─────────────────────────────────── -->
                    <div class="ep-card" id="section-password">
                        <div class="ep-card-head">
                            <span class="ch-icon amber"><i class="fas fa-lock"></i></span>
                            <div>
                                <h5>Change Password</h5>
                            </div>
                        </div>
                        <div class="ep-card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="password">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="ep-form-label" for="current_password">Current Password</label>
                                        <div style="position:relative;">
                                            <input type="password" id="current_password" name="current_password"
                                                class="ep-form-control" placeholder="Enter current password"
                                                autocomplete="current-password">
                                            <button type="button" onclick="togglePw('current_password',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#aab3bd;cursor:pointer;font-size:15px;"><i class="fas fa-eye"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="ep-form-label" for="new_password">New Password</label>
                                        <div style="position:relative;">
                                            <input type="password" id="new_password" name="new_password"
                                                class="ep-form-control" placeholder="Min. 8 characters"
                                                autocomplete="new-password" oninput="checkStrength(this.value)">
                                            <button type="button" onclick="togglePw('new_password',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#aab3bd;cursor:pointer;font-size:15px;"><i class="fas fa-eye"></i></button>
                                        </div>
                                        <div class="pw-strength-bar">
                                            <div class="pw-strength-fill" id="pwFill"></div>
                                        </div>
                                        <div class="pw-strength-text" id="pwText"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="ep-form-label" for="confirm_password">Confirm New Password</label>
                                        <div style="position:relative;">
                                            <input type="password" id="confirm_password" name="confirm_password"
                                                class="ep-form-control" placeholder="Repeat new password"
                                                autocomplete="new-password">
                                            <button type="button" onclick="togglePw('confirm_password',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#aab3bd;cursor:pointer;font-size:15px;"><i class="fas fa-eye"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="ep-divider"></div>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="ep-btn-save" style="background:linear-gradient(135deg,#f7971e,#ffd200);box-shadow:0 4px 14px rgba(247,151,30,.35);">
                                        <i class="fas fa-key"></i> Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const bioEl = document.getElementById('bio');
        const bioCount = document.getElementById('bioCount');
        if (bioEl) bioEl.addEventListener('input', () => {
            bioCount.textContent = bioEl.value.length + '/1000';
        });

        function togglePw(id, btn) {
            const inp = document.getElementById(id);
            const isText = inp.type === 'text';
            inp.type = isText ? 'password' : 'text';
            btn.innerHTML = isText ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        }

        function checkStrength(val) {
            const fill = document.getElementById('pwFill');
            const text = document.getElementById('pwText');
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            const levels = [{
                    pct: '0%',
                    color: '#e4e8f0',
                    label: ''
                },
                {
                    pct: '25%',
                    color: '#ef4444',
                    label: '🔴 Weak'
                },
                {
                    pct: '50%',
                    color: '#f59e0b',
                    label: '🟡 Fair'
                },
                {
                    pct: '75%',
                    color: '#3b82f6',
                    label: '🔵 Good'
                },
                {
                    pct: '100%',
                    color: '#22c55e',
                    label: '🟢 Strong'
                },
            ];
            const l = levels[score] || levels[0];
            fill.style.width = l.pct;
            fill.style.background = l.color;
            text.textContent = l.label;
            text.style.color = l.color;
        }

        function scrollTo(id) {
            const el = document.getElementById(id);
            if (el) el.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
        const sections = ['section-avatar', 'section-profile', 'section-password'];
        const navItems = document.querySelectorAll('.ep-nav-item');
        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(id => {
                const sec = document.getElementById(id);
                if (sec && sec.getBoundingClientRect().top < 180) current = id;
            });
            navItems.forEach(a => {
                const href = a.getAttribute('href');
                a.classList.toggle('active', href === '#' + current);
            });
        });
    </script>
</body>

</html>