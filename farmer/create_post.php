<?php
// session_start(); // Ensure session starts
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// Include configuration and function files
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ratings.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure user is logged in
check_login();

// Check if user is a farmer
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}

// Initialize variables
$errors = [];
$product_name = $category = $description = "";
$price = 0.0;
$quantity = 0.0;
$unit = "kg";
$auction_start_date = "";
$auction_end_date = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize input
    $product_name = sanitize($_POST['product_name']);
    $category = sanitize($_POST['category']);
    $description = sanitize($_POST['description']);
    $price = floatval($_POST['price']);
    $quantity = floatval($_POST['quantity']);
    $unit = sanitize($_POST['unit']);
    $auction_start_date = sanitize($_POST['auction_start_date']);
    $auction_end_date = sanitize($_POST['auction_end_date']);

    // Handle multiple image uploads
    $saved_images = [];
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $file_count = count($_FILES['images']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed) && $_FILES['images']['size'][$i] <= 5 * 1024 * 1024) {
                    $fname = uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['images']['tmp_name'][$i], '../assets/images/' . $fname);
                    $saved_images[] = $fname;
                } else {
                    $errors[] = "Image #" . ($i + 1) . ": invalid format or exceeds 5 MB (JPG, JPEG, PNG, GIF allowed).";
                }
            }
        }
    }
    $cover_image = !empty($saved_images) ? $saved_images[0] : '';

    // Validate required fields
    if (empty($product_name) || empty($category) || empty($description) || empty($price) || empty($quantity) || empty($unit) || empty($auction_start_date) || empty($auction_end_date)) {
        $errors[] = "All fields are required.";
    }

    // Validate quantity is positive
    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than 0.";
    }

    // Validate auction dates
    $start_date = strtotime($auction_start_date);
    $end_date = strtotime($auction_end_date);

    if ($start_date === false || $end_date === false) {
        $errors[] = "Invalid auction dates.";
    } elseif ($start_date >= $end_date) {
        $errors[] = "Auction end date must be after start date.";
    }

    // Insert into database if no errors
    if (empty($errors)) {
        $farmer_id = $_SESSION['user_id'];

        $stmt = $conn->prepare("INSERT INTO posts (farmer_id, product_name, category, description, price, quantity, unit, auction_start_date, auction_end_date, image, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssddssss", $farmer_id, $product_name, $category, $description, $price, $quantity, $unit, $auction_start_date, $auction_end_date, $cover_image);

        if ($stmt->execute()) {
            $new_post_id = $stmt->insert_id;

            // Save each image to post_images table
            if (!empty($saved_images)) {
                $img_ins = $conn->prepare("INSERT INTO post_images (post_id, filename, is_primary, sort_order) VALUES (?, ?, ?, ?)");
                foreach ($saved_images as $idx => $fname) {
                    $is_primary = ($idx === 0) ? 1 : 0;
                    $img_ins->bind_param("isii", $new_post_id, $fname, $is_primary, $idx);
                    $img_ins->execute();
                }
                $img_ins->close();
            }

            // Adjust farmer automatic rating
            adjust_rating_for_post($farmer_id, $price, $product_name);

            header("Location: dashboard.php");
            exit();
        } else {
            $errors[] = "Failed to create post. Error: " . $conn->error;
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Listing – Farmers' Marketplace</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6fb;
        }

        /* ── Page Header ── */
        .page-hero {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border-radius: 16px;
            padding: 32px 36px;
            color: white;
            margin-bottom: 28px;
            box-shadow: 0 6px 24px rgba(17, 153, 142, .28);
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .page-hero-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, .2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .page-hero .hero-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            opacity: .8;
            margin-bottom: 4px;
        }

        .page-hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 22px;
            font-weight: 700;
            margin: 0;
        }

        .page-hero p {
            font-size: 13px;
            opacity: .85;
            margin: 4px 0 0;
        }

        /* ── Form Layout ── */
        .form-panel {
            background: white;
            border-radius: 16px;
            border: 1px solid #ebebeb;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .07);
            overflow: hidden;
        }

        .form-section {
            padding: 24px 28px;
            border-bottom: 1px solid #f0f0f0;
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .form-section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #11998e;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .form-section-title i {
            font-size: 13px;
        }

        /* ── Inputs ── */
        .form-label-custom {
            font-size: 13px;
            font-weight: 600;
            color: #444;
            margin-bottom: 6px;
            display: block;
        }

        .form-input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #e0e0e0;
            border-radius: 9px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            background: #fafafa;
            transition: all .2s;
            color: #333;
        }

        .form-input:focus {
            outline: none;
            border-color: #11998e;
            background: white;
            box-shadow: 0 0 0 3px rgba(17, 153, 142, .12);
        }

        textarea.form-input {
            resize: vertical;
            min-height: 110px;
        }

        select.form-input {
            cursor: pointer;
        }

        .input-icon-wrap {
            position: relative;
        }

        .input-icon-wrap .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 14px;
            pointer-events: none;
        }

        .input-icon-wrap .form-input {
            padding-left: 36px;
        }

        /* ── Image Upload ── */
        .image-upload-area {
            border: 2px dashed #d0d0d0;
            border-radius: 12px;
            padding: 32px 20px;
            text-align: center;
            cursor: pointer;
            transition: all .25s;
            background: #fafafa;
            position: relative;
        }

        .image-upload-area:hover,
        .image-upload-area.dragover {
            border-color: #11998e;
            background: #f0fdf8;
        }

        .image-upload-area input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .upload-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #e8f8ee;
            color: #11998e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin: 0 auto 12px;
        }

        .upload-label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .upload-hint {
            font-size: 12px;
            color: #aaa;
        }

        .preview-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
            justify-content: center;
        }

        .preview-grid img {
            width: 90px;
            height: 90px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .1);
            border: 2px solid #11998e;
        }

        /* ── Error Alert ── */
        .error-alert {
            background: #fff0f0;
            border: 1px solid #ffd0d0;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 20px;
        }

        .error-alert .error-title {
            font-size: 13px;
            font-weight: 700;
            color: #c0392b;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .error-alert ul {
            margin: 0;
            padding-left: 18px;
        }

        .error-alert ul li {
            font-size: 13px;
            color: #c0392b;
        }

        /* ── Submit Button ── */
        .btn-submit {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 13px 32px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all .25s;
            box-shadow: 0 4px 14px rgba(17, 153, 142, .35);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(17, 153, 142, .45);
        }

        .btn-back {
            background: white;
            color: #555;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all .2s;
            text-decoration: none !important;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .btn-back:hover {
            background: #f5f5f5;
            color: #333;
            text-decoration: none !important;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 576px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="main-container">
        <div class="container py-4" style="max-width: 800px;">

            <!-- Page Hero -->
            <div class="page-hero">
                <div class="page-hero-icon"><i class="fas fa-plus"></i></div>
                <div>
                    <div class="hero-label"><i class="fas fa-tractor me-1"></i> Farmer Dashboard</div>
                    <h1>Create New Listing</h1>
                    <p>Fill in the details below to list your product for auction.</p>
                </div>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="error-alert">
                    <div class="error-title"><i class="fas fa-exclamation-circle"></i> Please fix the following errors:</div>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Form Panel -->
            <div class="form-panel">
                <form action="create_post.php" method="POST" enctype="multipart/form-data">

                    <!-- Product Info -->
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-seedling"></i> Product Information</div>
                        <div class="mb-3">
                            <label class="form-label-custom" for="product_name">Product Name</label>
                            <div class="input-icon-wrap">
                                <i class="fas fa-box input-icon"></i>
                                <input type="text" name="product_name" id="product_name" class="form-input"
                                    placeholder="e.g. Fresh Organic Tomatoes"
                                    value="<?php echo htmlspecialchars($product_name); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom" for="category">Category</label>
                            <div class="input-icon-wrap">
                                <i class="fas fa-th-large input-icon"></i>
                                <select name="category" id="category" class="form-input" required>
                                    <?php
                                    $cats = ['Vegetables', 'Fruits', 'Dairy', 'Grains', 'Meat', 'Fish', 'Eggs', 'Honey', 'Herbs', 'Root Vegetables'];
                                    foreach ($cats as $c):
                                    ?>
                                        <option value="<?php echo $c; ?>" <?php echo $category === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label-custom" for="description">Description</label>
                            <textarea name="description" id="description" class="form-input"
                                placeholder="Describe your product — quality, origin, freshness..."
                                required><?php echo htmlspecialchars($description); ?></textarea>
                        </div>
                    </div>

                    <!-- Pricing & Quantity -->
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-tag"></i> Pricing & Quantity</div>
                        <div class="form-grid-2 mb-0">
                            <div>
                                <label class="form-label-custom" for="price">Starting Price (৳)</label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-taka-sign input-icon" style="font-size:13px;font-weight:700;">৳</i>
                                    <input type="number" name="price" id="price" class="form-input"
                                        step="0.01" min="0" placeholder="0.00"
                                        value="<?php echo $price ?: ''; ?>" required>
                                </div>
                            </div>
                            <div>
                                <label class="form-label-custom">Quantity &amp; Unit</label>
                                <div style="display:flex;gap:8px;">
                                    <input type="number" name="quantity" id="quantity" class="form-input"
                                        style="flex:1;" step="0.01" min="0" placeholder="0"
                                        value="<?php echo $quantity ?: ''; ?>" required>
                                    <select name="unit" id="unit" class="form-input" style="width:130px;" required>
                                        <?php
                                        $units = ['kg' => 'kg', 'g' => 'g', 'L' => 'L', 'ml' => 'ml', 'pcs' => 'pcs', 'dozen' => 'dozen', 'bundle' => 'bundle', 'box' => 'box'];
                                        foreach ($units as $val => $label):
                                        ?>
                                            <option value="<?php echo $val; ?>" <?php echo $unit === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Auction Schedule -->
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-calendar-alt"></i> Auction Schedule</div>
                        <div class="form-grid-2">
                            <div>
                                <label class="form-label-custom" for="auction_start_date">Start Date & Time</label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-play-circle input-icon"></i>
                                    <input type="datetime-local" name="auction_start_date" id="auction_start_date"
                                        class="form-input"
                                        value="<?php echo htmlspecialchars($auction_start_date); ?>" required>
                                </div>
                            </div>
                            <div>
                                <label class="form-label-custom" for="auction_end_date">End Date & Time</label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-stop-circle input-icon"></i>
                                    <input type="datetime-local" name="auction_end_date" id="auction_end_date"
                                        class="form-input"
                                        value="<?php echo htmlspecialchars($auction_end_date); ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Images -->
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-images"></i> Product Photos</div>
                        <div class="image-upload-area" id="uploadArea">
                            <input type="file" name="images[]" id="images" accept="image/jpg,image/jpeg,image/png,image/gif"
                                multiple onchange="previewImages(this)">
                            <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                            <div class="upload-label" id="uploadLabel">Click or drag &amp; drop to upload</div>
                            <div class="upload-hint">Up to 10 photos &middot; JPG, PNG, GIF &middot; max 5 MB each &mdash; optional but recommended</div>
                            <div class="preview-grid" id="previewGrid"></div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="form-section" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit for Approval
                        </button>
                        <a href="dashboard.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>

                </form>
            </div>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function previewImages(input) {
            const grid = document.getElementById('previewGrid');
            const label = document.getElementById('uploadLabel');
            grid.innerHTML = '';
            if (!input.files || !input.files.length) return;
            const count = Math.min(input.files.length, 10);
            label.textContent = count + ' photo' + (count > 1 ? 's' : '') + ' selected';
            for (let i = 0; i < count; i++) {
                const reader = new FileReader();
                reader.onload = e => {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    grid.appendChild(img);
                };
                reader.readAsDataURL(input.files[i]);
            }
        }

        // Drag & drop highlight
        const area = document.getElementById('uploadArea');
        area.addEventListener('dragover', e => {
            e.preventDefault();
            area.classList.add('dragover');
        });
        area.addEventListener('dragleave', () => area.classList.remove('dragover'));
        area.addEventListener('drop', e => {
            e.preventDefault();
            area.classList.remove('dragover');
            const fi = document.getElementById('images');
            if (fi && e.dataTransfer.files.length) {
                fi.files = e.dataTransfer.files;
                fi.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>

</html>