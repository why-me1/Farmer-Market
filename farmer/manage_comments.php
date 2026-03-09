<?php

/**
 * Bid Activity – Read-only view for farmers.
 * Farmers can see every bid placed on their products. Winners are determined
 * automatically when the auction ends (highest bid wins). Farmers have no
 * ability to manually approve or reject bids.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
check_login();

if ($_SESSION['role'] !== 'farmer') {
    header("Location: ../index.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Fetch all posts this farmer owns, along with bid stats and the auto-winner
$stmt = $conn->prepare("
    SELECT
        p.id,
        p.product_name,
        p.price          AS asking_price,
        p.status,
        p.auction_start_date,
        p.auction_end_date,
        p.is_approved    AS post_approved,
        COUNT(c.id)      AS total_bids,
        MAX(CAST(c.comment_text AS DECIMAL(12,2))) AS highest_bid,
        (SELECT u2.username FROM comments c2 JOIN users u2 ON u2.id = c2.user_id
             WHERE c2.post_id = p.id AND c2.is_approved = 1 LIMIT 1) AS winner_username,
        (SELECT CAST(c3.comment_text AS DECIMAL(12,2)) FROM comments c3
             WHERE c3.post_id = p.id AND c3.is_approved = 1 LIMIT 1) AS winning_bid
    FROM posts p
    LEFT JOIN comments c ON c.post_id = p.id
    WHERE p.farmer_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$now = time();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bid Activity – Farmers' Marketplace</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6fb;
        }

        .page-hero {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border-radius: 16px;
            padding: 28px 32px;
            color: #fff;
            margin-bottom: 24px;
            box-shadow: 0 6px 24px rgba(79, 70, 229, .28);
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .page-hero-icon {
            width: 52px;
            height: 52px;
            background: rgba(255, 255, 255, .18);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .page-hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }

        .page-hero p {
            font-size: 13px;
            opacity: .85;
            margin: 4px 0 0;
        }

        .notice-bar {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 10px;
            padding: 12px 18px;
            font-size: 13px;
            color: #92400e;
            margin-bottom: 22px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .notice-bar i {
            color: #d97706;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .ba-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .ba-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .ba-product-name {
            font-weight: 700;
            font-size: 15px;
            color: #1e293b;
            flex: 1;
            min-width: 150px;
        }

        .ba-asking {
            font-size: 12px;
            color: #64748b;
            background: #f8fafc;
            border-radius: 6px;
            padding: 3px 9px;
        }

        .badge-status {
            font-size: 11px;
            font-weight: 600;
            border-radius: 20px;
            padding: 4px 11px;
        }

        .badge-live {
            background: #dcfce7;
            color: #166534;
        }

        .badge-ended {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-sold {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-delivered {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-pending {
            background: #f3f4f6;
            color: #374151;
        }

        .ba-winner-bar {
            background: linear-gradient(90deg, #f0fdf4 0%, #dcfce7 100%);
            border-bottom: 1px solid #bbf7d0;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .ba-winner-bar .wi-label {
            font-size: 12px;
            font-weight: 700;
            color: #065f46;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .ba-winner-bar .wi-name {
            font-weight: 700;
            color: #1e293b;
        }

        .ba-winner-bar .wi-amt {
            font-weight: 700;
            color: #059669;
        }

        .ba-winner-bar .wi-auto {
            font-size: 11px;
            color: #6b7280;
            background: #e5e7eb;
            border-radius: 20px;
            padding: 2px 8px;
            margin-left: auto;
        }

        .ba-no-winner {
            background: #fafafa;
            border-bottom: 1px solid #f1f5f9;
            padding: 11px 20px;
            font-size: 13px;
            color: #94a3b8;
            font-style: italic;
        }

        .ba-bids-toggle {
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .ba-bids-count {
            font-size: 13px;
            color: #64748b;
        }

        .btn-toggle {
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 5px 14px;
            font-size: 12px;
            color: #6366f1;
            cursor: pointer;
            transition: background .15s;
        }

        .btn-toggle:hover {
            background: #f0f0ff;
        }

        .ba-bids-table {
            width: 100%;
            border-collapse: collapse;
        }

        .ba-bids-table th {
            background: #f8fafc;
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: 8px 16px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        .ba-bids-table td {
            padding: 9px 16px;
            font-size: 13px;
            color: #374151;
            border-bottom: 1px solid #f8fafc;
        }

        .ba-bids-table tr:last-child td {
            border-bottom: none;
        }

        .ba-bids-table tr.winner-row td {
            background: #f0fdf4;
            font-weight: 600;
        }

        .ba-bids-table .amt {
            font-weight: 700;
            color: #059669;
        }

        .winner-crown {
            color: #f59e0b;
            margin-right: 4px;
        }

        .ba-empty {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .ba-empty i {
            font-size: 42px;
            margin-bottom: 14px;
            display: block;
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="container mt-4 mb-5">

        <div class="page-hero">
            <div class="page-hero-icon"><i class="fas fa-gavel"></i></div>
            <div>
                <div style="font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;opacity:.8;margin-bottom:4px;">Auction Overview</div>
                <h1>Bid Activity</h1>
                <p>See all bids on your products. Winners are selected automatically — the highest bid wins when the auction ends.</p>
            </div>
        </div>

        <div class="notice-bar">
            <i class="fas fa-info-circle"></i>
            <span>
                <strong>Automatic auction system:</strong> You don't need to do anything here.
                When your auction timer expires, the highest bidder wins automatically.
                Head to <a href="manage_orders.php" style="color:#92400e;font-weight:700;">Manage Orders</a>
                to mark items as delivered once you've dispatched them.
            </span>
        </div>

        <?php if (empty($posts)): ?>
            <div class="ba-card">
                <div class="ba-empty">
                    <i class="fas fa-box-open"></i>
                    <div style="font-size:16px;font-weight:600;color:#475569;margin-bottom:6px;">No products listed yet</div>
                    <div>Create your first listing and buyers will start bidding.</div>
                    <a href="create_post.php" class="btn btn-primary mt-3" style="border-radius:10px;padding:8px 22px;">
                        <i class="fas fa-plus" style="margin-right:6px;"></i> Create Listing
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $p):
                $end   = $p['auction_end_date']   ? strtotime($p['auction_end_date'])   : null;
                $start = $p['auction_start_date'] ? strtotime($p['auction_start_date']) : null;

                if ($p['status'] === 'delivered') {
                    $badge = '<span class="badge-status badge-delivered">Delivered</span>';
                } elseif ($p['status'] === 'sold') {
                    $badge = '<span class="badge-status badge-sold">Sold – Pending Delivery</span>';
                } elseif (!$p['post_approved']) {
                    $badge = '<span class="badge-status badge-pending">Awaiting Admin Approval</span>';
                } elseif ($end && $now >= $end) {
                    $badge = '<span class="badge-status badge-ended">Auction Ended</span>';
                } elseif ($start && $now >= $start && (!$end || $now < $end)) {
                    $badge = '<span class="badge-status badge-live"><i class="fas fa-circle" style="font-size:7px;margin-right:4px;"></i>Live</span>';
                } else {
                    $badge = '<span class="badge-status badge-pending">Scheduled</span>';
                }

                // Fetch individual bids for this product, sorted highest first
                $bid_stmt = $conn->prepare("
                SELECT c.id, c.comment_text, c.created_at, c.is_approved, u.username
                FROM comments c
                JOIN users u ON u.id = c.user_id
                WHERE c.post_id = ?
                ORDER BY CAST(c.comment_text AS DECIMAL(12,2)) DESC
            ");
                $bid_stmt->bind_param("i", $p['id']);
                $bid_stmt->execute();
                $bids = $bid_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $bid_stmt->close();
            ?>
                <div class="ba-card">
                    <div class="ba-card-header">
                        <div>
                            <div class="ba-product-name"><?php echo htmlspecialchars($p['product_name']); ?></div>
                            <?php if ($p['auction_end_date']): ?>
                                <div style="font-size:11px;color:#94a3b8;margin-top:3px;">
                                    <i class="far fa-clock"></i>
                                    Ends: <?php echo date('d M Y, h:i A', strtotime($p['auction_end_date'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <span class="ba-asking">Starting: ৳<?php echo number_format($p['asking_price'], 2); ?></span>
                        <?php echo $badge; ?>
                        <a href="../product_detail.php?id=<?php echo $p['id']; ?>"
                            class="btn btn-sm ml-auto"
                            style="background:#f0f0ff;color:#6366f1;border:none;border-radius:8px;font-size:12px;font-weight:600;padding:5px 14px;">
                            <i class="fas fa-external-link-alt" style="font-size:10px;"></i> View
                        </a>
                    </div>

                    <?php if ($p['winner_username']): ?>
                        <div class="ba-winner-bar">
                            <i class="fas fa-trophy" style="color:#f59e0b;"></i>
                            <span class="wi-label">Winner</span>
                            <span class="wi-name"><?php echo htmlspecialchars($p['winner_username']); ?></span>
                            &mdash;
                            <span class="wi-amt">৳<?php echo number_format((float)$p['winning_bid'], 2); ?></span>
                            <span class="wi-auto"><i class="fas fa-robot" style="font-size:10px;margin-right:3px;"></i> Auto-selected</span>
                        </div>
                    <?php elseif ($p['total_bids'] > 0 && $end && $now >= $end): ?>
                        <div class="ba-no-winner">
                            Auction ended without a winner (minimum bid or bid count not met).
                        </div>
                    <?php elseif ($p['total_bids'] == 0): ?>
                        <div class="ba-no-winner">No bids yet.</div>
                    <?php else: ?>
                        <div class="ba-no-winner">
                            <i class="fas fa-hourglass-half" style="margin-right:6px;color:#d97706;"></i>
                            Auction in progress — <?php echo (int)$p['total_bids']; ?> bid<?php echo $p['total_bids'] != 1 ? 's' : ''; ?> so far.
                            Current highest: <strong>৳<?php echo $p['highest_bid'] ? number_format((float)$p['highest_bid'], 2) : '—'; ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($bids)): ?>
                        <div class="ba-bids-toggle">
                            <span class="ba-bids-count"><?php echo count($bids); ?> bid<?php echo count($bids) != 1 ? 's' : ''; ?></span>
                            <button class="btn-toggle" onclick="toggleBids(this)" data-target="bids-<?php echo $p['id']; ?>">
                                <i class="fas fa-chevron-down" style="font-size:10px;margin-right:4px;"></i> Show bids
                            </button>
                        </div>
                        <div id="bids-<?php echo $p['id']; ?>" style="display:none;">
                            <table class="ba-bids-table">
                                <thead>
                                    <tr>
                                        <th>Bidder</th>
                                        <th>Bid Amount</th>
                                        <th>Placed At</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bids as $b): ?>
                                        <tr <?php echo $b['is_approved'] ? 'class="winner-row"' : ''; ?>>
                                            <td>
                                                <?php if ($b['is_approved']): ?>
                                                    <i class="fas fa-crown winner-crown"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($b['username']); ?>
                                            </td>
                                            <td class="amt">৳<?php echo number_format((float)$b['comment_text'], 2); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($b['created_at'])); ?></td>
                                            <td>
                                                <?php if ($b['is_approved']): ?>
                                                    <span style="color:#059669;font-weight:700;font-size:12px;"><i class="fas fa-check-circle"></i> Winner</span>
                                                <?php else: ?>
                                                    <span style="color:#94a3b8;font-size:12px;">Outbid</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function toggleBids(btn) {
            var target = document.getElementById(btn.getAttribute('data-target'));
            if (target.style.display === 'none') {
                target.style.display = 'block';
                btn.innerHTML = '<i class="fas fa-chevron-up" style="font-size:10px;margin-right:4px;"></i> Hide bids';
            } else {
                target.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-chevron-down" style="font-size:10px;margin-right:4px;"></i> Show bids';
            }
        }
    </script>
</body>

</html>