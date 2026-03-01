<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// ── Handle delete individual image ──────────────────────────────────
if (isset($_GET['delete_image']) && isset($_GET['edit'])) {
    $del_img_id    = (int)$_GET['delete_image'];
    $del_post_id   = (int)$_GET['edit'];
    $del_stmt = $conn->prepare(
        "SELECT pi.filename, pi.is_primary FROM post_images pi
         JOIN posts p ON p.id = pi.post_id
         WHERE pi.id = ? AND p.farmer_id = ? AND pi.post_id = ?"
    );
    $del_stmt->bind_param("iii", $del_img_id, $farmer_id, $del_post_id);
    $del_stmt->execute();
    $del_row = $del_stmt->get_result()->fetch_assoc();
    $del_stmt->close();
    if ($del_row) {
        $dp = '../assets/images/' . $del_row['filename'];
        if (file_exists($dp)) unlink($dp);
        $conn->query("DELETE FROM post_images WHERE id = $del_img_id");
        if ($del_row['is_primary']) {
            $nx = $conn->prepare("SELECT id, filename FROM post_images WHERE post_id = ? ORDER BY sort_order ASC LIMIT 1");
            $nx->bind_param("i", $del_post_id);
            $nx->execute();
            $nx_row = $nx->get_result()->fetch_assoc();
            $nx->close();
            if ($nx_row) {
                $conn->query("UPDATE post_images SET is_primary = 1 WHERE id = " . (int)$nx_row['id']);
                $esc = $conn->real_escape_string($nx_row['filename']);
                $conn->query("UPDATE posts SET image = '$esc' WHERE id = $del_post_id AND farmer_id = $farmer_id");
            } else {
                $conn->query("UPDATE posts SET image = '' WHERE id = $del_post_id AND farmer_id = $farmer_id");
            }
        }
    }
    header("Location: view_posts.php?edit=$del_post_id");
    exit();
}

// Handle update post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_post'])) {
    $post_id      = (int)$_POST['post_id'];
    $product_name = trim($_POST['product_name']);
    $description  = trim($_POST['description']);
    $price        = floatval($_POST['price']);

    $update_stmt = $conn->prepare("UPDATE posts SET product_name = ?, description = ?, price = ? WHERE id = ? AND farmer_id = ?");
    $update_stmt->bind_param("ssdii", $product_name, $description, $price, $post_id, $farmer_id);
    $update_stmt->execute();
    $update_stmt->close();

    // Handle multiple new images
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $allowed  = ['jpg', 'jpeg', 'png', 'gif'];
        $fc = count($_FILES['images']['name']);
        // Get current max sort_order
        $mo_r = $conn->query("SELECT MAX(sort_order) AS mx FROM post_images WHERE post_id = $post_id");
        $max_ord = (($mo_r->fetch_assoc()['mx']) ?? -1) + 1;
        // Check if any images already exist
        $cnt_r = $conn->query("SELECT COUNT(*) AS cnt FROM post_images WHERE post_id = $post_id");
        $has_imgs = (int)($cnt_r->fetch_assoc()['cnt']) > 0;
        $img_ins2 = $conn->prepare("INSERT INTO post_images (post_id, filename, is_primary, sort_order) VALUES (?, ?, ?, ?)");
        $first_new2 = true;
        for ($i = 0; $i < $fc; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $ext2 = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext2, $allowed) && $_FILES['images']['size'][$i] <= 5 * 1024 * 1024) {
                    $fn2 = uniqid() . '.' . $ext2;
                    move_uploaded_file($_FILES['images']['tmp_name'][$i], '../assets/images/' . $fn2);
                    $is_prim2 = (!$has_imgs && $first_new2) ? 1 : 0;
                    $img_ins2->bind_param("isii", $post_id, $fn2, $is_prim2, $max_ord);
                    $img_ins2->execute();
                    if (!$has_imgs && $first_new2) {
                        $esc2 = $conn->real_escape_string($fn2);
                        $conn->query("UPDATE posts SET image = '$esc2' WHERE id = $post_id AND farmer_id = $farmer_id");
                    }
                    $max_ord++;
                    $first_new2 = false;
                    $has_imgs = true;
                }
            }
        }
        $img_ins2->close();
    }

    header("Location: view_posts.php");
    exit();
}

$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Prepare SQL with filter
$sql = "
    SELECT posts.*, 
           CASE 
               WHEN (SELECT COUNT(*) 
                     FROM comments 
                     WHERE comments.post_id = posts.id AND comments.is_approved = 1) > 0 THEN 'Sold' 
               ELSE 'Active' 
           END AS status
    FROM posts
    WHERE posts.farmer_id = ?
";

if ($filter === 'sold') {
    $sql .= " HAVING status = 'Sold'";
} elseif ($filter === 'active') {
    $sql .= " HAVING status = 'Active'";
}

$sql .= " ORDER BY posts.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Listings – Farmers' Marketplace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6fb;
        }

        /* ── Page Hero ── */
        .page-hero {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border-radius: 16px;
            padding: 32px 36px;
            color: white;
            margin-bottom: 24px;
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

        /* ── Filter Bar ── */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 14px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
            border: 1px solid #ebebeb;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: space-between;
        }

        .filter-pills {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .filter-pill {
            padding: 7px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            border: 1.5px solid #e0e0e0;
            background: #fafafa;
            color: #666;
            text-decoration: none !important;
            transition: all .2s;
            cursor: pointer;
        }

        .filter-pill:hover {
            border-color: #11998e;
            color: #11998e;
            background: #f0fdf8;
        }

        .filter-pill.active {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            border-color: transparent;
            box-shadow: 0 3px 10px rgba(17, 153, 142, .3);
        }

        .btn-new-listing {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            border: none;
            border-radius: 9px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all .2s;
            box-shadow: 0 3px 10px rgba(17, 153, 142, .3);
            white-space: nowrap;
        }

        .btn-new-listing:hover {
            transform: translateY(-1px);
            color: white;
            box-shadow: 0 6px 16px rgba(17, 153, 142, .4);
        }

        /* ── Listing Cards Grid ── */
        .listings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .listing-card {
            background: white;
            border-radius: 14px;
            border: 1px solid #ebebeb;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .07);
            transition: transform .2s, box-shadow .2s;
            display: flex;
            flex-direction: column;
        }

        .listing-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 26px rgba(0, 0, 0, .12);
        }

        .listing-card-img {
            width: 100%;
            height: 190px;
            object-fit: cover;
            border-bottom: 1px solid #f0f0f0;
        }

        .listing-card-img-placeholder {
            width: 100%;
            height: 190px;
            background: linear-gradient(135deg, #e8f8ee, #d4f7e0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 52px;
            color: #a8dcc0;
            border-bottom: 1px solid #f0f0f0;
        }

        .listing-card-body {
            padding: 16px 18px;
            flex: 1;
        }

        .listing-card-title {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .listing-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 10px;
        }

        .listing-meta-row {
            font-size: 12px;
            color: #888;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .listing-meta-row i {
            color: #11998e;
            width: 13px;
            text-align: center;
        }

        .listing-meta-row strong {
            color: #444;
        }

        .listing-price {
            font-family: 'Poppins', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #11998e;
            margin-bottom: 10px;
        }

        .listing-price span {
            font-size: 12px;
            color: #aaa;
            font-weight: 400;
            margin-left: 2px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 20px;
            padding: 4px 11px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .status-pill.active {
            background: #e8f8ee;
            color: #0d6b5e;
        }

        .status-pill.sold {
            background: #fde8e8;
            color: #c0392b;
        }

        .listing-card-footer {
            padding: 12px 18px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 8px;
        }

        .btn-card-action {
            flex: 1;
            padding: 9px 0;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            text-decoration: none !important;
        }

        .btn-card-edit {
            background: #eef0ff;
            color: #667eea;
        }

        .btn-card-edit:hover {
            background: #667eea;
            color: white;
        }

        .btn-card-delete {
            background: #fde8e8;
            color: #e74c3c;
        }

        .btn-card-delete:hover {
            background: #e74c3c;
            color: white;
        }

        /* ── Inline Edit Form ── */
        .edit-form-panel {
            background: #f8fffe;
            border: 1.5px dashed #11998e;
            border-radius: 12px;
            padding: 18px;
            margin: 0;
        }

        .edit-form-title {
            font-size: 13px;
            font-weight: 700;
            color: #11998e;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: .8px;
        }

        .edit-input {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #cce8e4;
            border-radius: 8px;
            font-size: 13px;
            background: white;
            transition: all .2s;
            font-family: 'Inter', sans-serif;
        }

        .edit-input:focus {
            outline: none;
            border-color: #11998e;
            box-shadow: 0 0 0 3px rgba(17, 153, 142, .12);
        }

        textarea.edit-input {
            min-height: 80px;
            resize: vertical;
        }

        .edit-label {
            font-size: 12px;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            display: block;
        }

        .btn-save {
            background: #11998e;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
        }

        .btn-save:hover {
            background: #0e8076;
        }

        .btn-cancel-edit {
            background: #f0f0f0;
            color: #555;
            border: none;
            border-radius: 8px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            text-decoration: none !important;
        }

        .btn-cancel-edit:hover {
            background: #e0e0e0;
            color: #333;
        }

        /* ── Edit Image Upload ── */
        .edit-img-upload {
            border: 2px dashed #cce8e4;
            border-radius: 10px;
            padding: 12px 14px;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
            background: white;
            position: relative;
            overflow: hidden;
        }

        .edit-img-upload:hover {
            border-color: #11998e;
            background: #f0fdf8;
        }

        .edit-img-upload input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .edit-upload-txt {
            font-size: 12px;
            color: #888;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .edit-upload-txt i {
            color: #11998e;
            font-size: 15px;
        }

        /* existing images grid */
        .existing-imgs-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }

        .existing-img-item {
            position: relative;
            width: 72px;
            height: 72px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #d0e8e0;
            flex-shrink: 0;
        }

        .existing-img-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .img-primary-tag {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(17, 153, 142, .85);
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            text-align: center;
            padding: 2px 0;
            text-transform: uppercase;
            letter-spacing: .6px;
        }

        .img-delete-btn {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(200, 0, 0, .82);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            text-decoration: none;
            transition: background .15s;
        }

        .img-delete-btn:hover {
            background: #c00;
            color: #fff;
        }

        .edit-previews-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            justify-content: center;
        }

        .edit-previews-grid img {
            width: 72px;
            height: 72px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #11998e;
        }

        .edit-img-current {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f0fdf8;
            border: 1px solid #cce8e4;
            border-radius: 8px;
            padding: 7px 10px;
            margin-bottom: 8px;
        }

        .edit-img-current img {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #d0e8e0;
            flex-shrink: 0;
        }

        .edit-img-current span {
            font-size: 11px;
            color: #555;
        }

        .edit-img-preview {
            max-width: 100%;
            max-height: 110px;
            border-radius: 8px;
            display: none;
            margin-top: 10px;
            object-fit: cover;
            border: 1px solid #d0e0e8;
        }

        /* ── Empty State ── */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 30px;
            background: white;
            border-radius: 14px;
            border: 1px solid #ebebeb;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e8f8ee;
            color: #11998e;
            font-size: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
        }

        .empty-state h5 {
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: #aaa;
            margin-bottom: 20px;
        }

        @media (max-width: 576px) {
            .page-hero {
                padding: 24px 18px;
            }

            .listings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="main-container">
        <div class="container py-4" style="max-width: 1200px;">

            <!-- Page Hero -->
            <div class="page-hero">
                <div class="page-hero-icon"><i class="fas fa-layer-group"></i></div>
                <div>
                    <div class="hero-label"><i class="fas fa-tractor me-1"></i> Farmer Dashboard</div>
                    <h1>My Listings</h1>
                    <p>Manage, edit, and track all your auction products in one place.</p>
                </div>
            </div>

            <!-- Filter & Actions Bar -->
            <div class="filter-bar">
                <div class="filter-pills">
                    <a href="?status=all" class="filter-pill <?php echo ($filter === 'all')    ? 'active' : ''; ?>"><i class="fas fa-th-large me-1"></i> All</a>
                    <a href="?status=active" class="filter-pill <?php echo ($filter === 'active') ? 'active' : ''; ?>"><i class="fas fa-bolt me-1"></i> Active</a>
                    <a href="?status=sold" class="filter-pill <?php echo ($filter === 'sold')   ? 'active' : ''; ?>"><i class="fas fa-check-double me-1"></i> Sold</a>
                </div>
                <a href="create_post.php" class="btn-new-listing">
                    <i class="fas fa-plus"></i> New Listing
                </a>
            </div>

            <!-- Listings Grid -->
            <div class="listings-grid">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($post = $result->fetch_assoc()): ?>
                        <div class="listing-card">
                            <?php if ($post['image']): ?>
                                <img src="../assets/images/<?php echo htmlspecialchars($post['image']); ?>"
                                    class="listing-card-img"
                                    alt="<?php echo htmlspecialchars($post['product_name']); ?>">
                            <?php else: ?>
                                <div class="listing-card-img-placeholder"><i class="fas fa-seedling"></i></div>
                            <?php endif; ?>

                            <div class="listing-card-body">
                                <?php if ($edit_id === (int)$post['id']): ?>
                                    <!-- ── Edit Mode ── -->
                                    <form action="" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <div class="edit-form-panel">
                                            <div class="edit-form-title"><i class="fas fa-pen"></i> Editing Listing</div>

                                            <div class="mb-2">
                                                <label class="edit-label">Product Name</label>
                                                <input type="text" name="product_name" class="edit-input"
                                                    value="<?php echo htmlspecialchars($post['product_name']); ?>" required>
                                            </div>
                                            <div class="mb-2">
                                                <label class="edit-label">Description</label>
                                                <textarea name="description" class="edit-input" required><?php echo htmlspecialchars($post['description']); ?></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="edit-label">Price (৳)</label>
                                                <input type="number" name="price" class="edit-input"
                                                    value="<?php echo $post['price']; ?>" step="0.01" required>
                                            </div>

                                            <!-- Images management -->
                                            <div class="mb-3">
                                                <label class="edit-label">Product Photos <span style="font-weight:400;color:#aaa;">(add more or remove existing)</span></label>
                                                <?php
                                                $ei_stmt = $conn->prepare("SELECT id, filename, is_primary FROM post_images WHERE post_id = ? ORDER BY is_primary DESC, sort_order ASC");
                                                $ei_stmt->bind_param("i", $post['id']);
                                                $ei_stmt->execute();
                                                $ei_rows = $ei_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                                $ei_stmt->close();
                                                ?>
                                                <?php if (!empty($ei_rows)): ?>
                                                    <div class="existing-imgs-grid">
                                                        <?php foreach ($ei_rows as $ei): ?>
                                                            <div class="existing-img-item">
                                                                <img src="../assets/images/<?php echo htmlspecialchars($ei['filename']); ?>" alt="">
                                                                <?php if ($ei['is_primary']): ?>
                                                                    <span class="img-primary-tag">Cover</span>
                                                                <?php endif; ?>
                                                                <a href="?delete_image=<?php echo $ei['id']; ?>&edit=<?php echo $post['id']; ?>"
                                                                    class="img-delete-btn"
                                                                    onclick="return confirm('Remove this image?')"
                                                                    title="Remove">
                                                                    <i class="fas fa-times"></i>
                                                                </a>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php elseif (!empty($post['image'])): ?>
                                                    <div class="edit-img-current">
                                                        <img src="../assets/images/<?php echo htmlspecialchars($post['image']); ?>"
                                                            style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid #d0e8e0;"
                                                            alt="Current image">
                                                        <span style="font-size:11px;color:#555;">Legacy photo — upload new ones to replace</span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="edit-img-upload" id="editUploadArea-<?php echo $post['id']; ?>">
                                                    <input type="file" name="images[]" multiple
                                                        accept="image/jpg,image/jpeg,image/png,image/gif"
                                                        onchange="previewEditImages(this,<?php echo $post['id']; ?>)">
                                                    <div class="edit-upload-txt">
                                                        <i class="fas fa-cloud-upload-alt"></i>
                                                        <span id="editUploadLbl-<?php echo $post['id']; ?>">Click or drag to add more photos</span>
                                                    </div>
                                                    <div id="editImgPreviews-<?php echo $post['id']; ?>" class="edit-previews-grid"></div>
                                                </div>
                                            </div>

                                            <div style="display:flex;gap:8px;">
                                                <button type="submit" name="update_post" class="btn-save">
                                                    <i class="fas fa-check me-1"></i> Save
                                                </button>
                                                <a href="view_posts.php" class="btn-cancel-edit">Cancel</a>
                                            </div>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <!-- ── View Mode ── -->
                                    <div class="listing-price">
                                        ৳<?php echo number_format($post['price'], 2); ?>
                                    </div>
                                    <div class="listing-card-title"><?php echo htmlspecialchars($post['product_name']); ?></div>
                                    <div class="listing-meta">
                                        <div class="listing-meta-row">
                                            <i class="fas fa-align-left"></i>
                                            <span><?php echo mb_strimwidth(htmlspecialchars($post['description']), 0, 70, '…'); ?></span>
                                        </div>
                                        <div class="listing-meta-row">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span>Listed <?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                                        </div>
                                        <div class="listing-meta-row">
                                            <i class="fas fa-circle" style="font-size:8px;"></i>
                                            <span class="status-pill <?php echo strtolower($post['status']); ?>">
                                                <?php if (strtolower($post['status']) === 'active'): ?>
                                                    <i class="fas fa-bolt" style="font-size:9px;"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-check" style="font-size:9px;"></i>
                                                <?php endif; ?>
                                                <?php echo $post['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($edit_id !== (int)$post['id']): ?>
                                <div class="listing-card-footer">
                                    <a href="?edit=<?php echo $post['id']; ?>" class="btn-card-action btn-card-edit">
                                        <i class="fas fa-pen"></i> Edit
                                    </a>
                                    <form action="delete_post.php" method="POST" style="flex:1;" onsubmit="return confirm('Delete this listing?');">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <button type="submit" class="btn-card-action btn-card-delete" style="width:100%;">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-layer-group"></i></div>
                        <h5>No listings found</h5>
                        <p>
                            <?php echo $filter !== 'all' ? "No $filter listings yet. Try a different filter." : "You haven't created any listings yet."; ?>
                        </p>
                        <a href="create_post.php" class="btn-new-listing" style="display:inline-flex;">
                            <i class="fas fa-plus"></i> Create Your First Listing
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewEditImages(input, postId) {
            const grid = document.getElementById('editImgPreviews-' + postId);
            const lbl = document.getElementById('editUploadLbl-' + postId);
            if (!grid) return;
            grid.innerHTML = '';
            if (!input.files || !input.files.length) return;
            const count = Math.min(input.files.length, 10);
            if (lbl) lbl.textContent = count + ' photo' + (count > 1 ? 's' : '') + ' to add';
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

        // Drag-over highlight for edit upload areas
        document.querySelectorAll('.edit-img-upload').forEach(function(area) {
            area.addEventListener('dragover', function(e) {
                e.preventDefault();
                area.style.borderColor = '#11998e';
                area.style.background = '#f0fdf8';
            });
            area.addEventListener('dragleave', function() {
                area.style.borderColor = '';
                area.style.background = '';
            });
            area.addEventListener('drop', function(e) {
                e.preventDefault();
                area.style.borderColor = '';
                area.style.background = '';
                const fileInput = area.querySelector('input[type=file]');
                if (fileInput && e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            });
        });
    </script>
</body>

</html>