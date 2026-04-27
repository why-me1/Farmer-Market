<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ratings.php';
check_login();

$user_id = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'user';

$is_farmer = ($role === 'farmer');
$score_type = $is_farmer ? 'farmer_reputation' : 'buyer_reputation';

$breakdown_function = $is_farmer ? 'get_farmer_reputation_breakdown' : 'get_buyer_reputation_breakdown';
$insight = [
    'score' => get_user_automatic_rating($user_id),
    'factors' => [],
    'formula' => 'Formula unavailable'
];
if (function_exists($breakdown_function)) {
    $insight = call_user_func($breakdown_function, $user_id);
}

$history_rows = [];
if (function_exists('get_rating_change_history')) {
    $history_rows = call_user_func('get_rating_change_history', $user_id, $score_type, 20);
}

$current_score = (float)$insight['score'];
$current_pct = max(0, min(100, (int)round(($current_score / 5.0) * 100)));
$page_title = $is_farmer ? 'Seller Reputation Insights' : 'Buyer Reputation Insights';
$back_url = $is_farmer ? $base_url . 'farmer/dashboard.php' : $base_url . 'user/dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: radial-gradient(circle at top right, #ecfeff 0%, #f8fafc 45%, #eef2ff 100%);
        }

        .si-shell {
            max-width: 1100px;
            margin: 20px auto 34px;
            padding: 0 14px;
        }

        .si-hero {
            background: linear-gradient(130deg, #064e3b, #0f766e 55%, #0ea5e9);
            color: #fff;
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 14px 30px rgba(15, 118, 110, 0.22);
        }

        .si-hero small {
            text-transform: uppercase;
            letter-spacing: 0.12em;
            opacity: 0.8;
            font-weight: 700;
        }

        .si-hero h1 {
            font-size: 1.6rem;
            margin: 8px 0 10px;
            font-weight: 800;
        }

        .si-progress {
            margin-top: 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 999px;
            height: 10px;
            overflow: hidden;
        }

        .si-progress > span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, #bbf7d0, #fde68a);
        }

        .si-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 5px 18px rgba(2, 6, 23, 0.06);
        }

        .si-card-h {
            padding: 16px 18px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .si-card-h h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
        }

        .si-pill {
            border-radius: 999px;
            font-size: 0.76rem;
            padding: 5px 10px;
            font-weight: 700;
            background: #dbeafe;
            color: #1d4ed8;
        }

        .si-table-wrap {
            overflow-x: auto;
        }

        .si-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
        }

        .si-table thead th {
            background: #f8fafc;
            color: #475569;
            font-size: 0.74rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 11px 14px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .si-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f8fafc;
            font-size: 0.9rem;
            color: #334155;
            vertical-align: middle;
        }

        .si-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .si-note {
            color: #64748b;
            font-size: 0.82rem;
        }

        .si-signal {
            font-size: 0.75rem;
            border-radius: 999px;
            padding: 3px 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .sig-strong { background: #dcfce7; color: #166534; }
        .sig-neutral { background: #fef3c7; color: #92400e; }
        .sig-weak { background: #fee2e2; color: #991b1b; }

        .si-delta-up { color: #16a34a; font-weight: 700; }
        .si-delta-down { color: #dc2626; font-weight: 700; }

        @media (max-width: 768px) {
            .si-hero h1 {
                font-size: 1.28rem;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="si-shell">
        <div class="mb-3">
            <a href="<?php echo htmlspecialchars($back_url); ?>" style="display:inline-flex;align-items:center;gap:7px;font-size:.82rem;font-weight:700;color:#0f766e;text-decoration:none;background:#f0fdf9;border:1.5px solid #a7f3d0;border-radius:10px;padding:6px 14px;transition:background .2s;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        <section class="si-hero mb-4">
            <small>Score Transparency</small>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <div><strong>Current score:</strong> <?php echo number_format($current_score, 1); ?> / 5</div>
                <div><strong>Role:</strong> <?php echo $is_farmer ? 'Farmer' : 'Buyer'; ?></div>
            </div>
            <div class="si-progress"><span style="width: <?php echo $current_pct; ?>%;"></span></div>
            <div class="mt-2" style="font-size:.83rem;opacity:.9;">Formula: <?php echo htmlspecialchars($insight['formula']); ?></div>
        </section>

        <section class="si-card mb-4">
            <div class="si-card-h">
                <h2>Why your score is at this level</h2>
                <span class="si-pill">Live factor breakdown</span>
            </div>
            <div class="si-table-wrap">
                <table class="si-table">
                    <thead>
                        <tr>
                            <th>Factor</th>
                            <th>Weight</th>
                            <th>Factor score</th>
                            <th>Contribution</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($insight['factors'] as $factor): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:700;"><?php echo htmlspecialchars($factor['label']); ?></div>
                                    <div class="si-note"><?php echo htmlspecialchars($factor['note']); ?></div>
                                </td>
                                <td><?php echo (int)round($factor['weight'] * 100); ?>%</td>
                                <td><?php echo number_format((float)$factor['score_05'], 2); ?> / 5</td>
                                <td><?php echo number_format((float)$factor['weighted_05'], 2); ?> points</td>
                                <td>
                                    <span class="si-signal sig-<?php echo htmlspecialchars($factor['signal']); ?>">
                                        <?php echo htmlspecialchars($factor['signal']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="si-card">
            <div class="si-card-h">
                <h2>Why your score changed recently</h2>
                <span class="si-pill">Increase or decrease history</span>
            </div>
            <div class="si-table-wrap">
                <table class="si-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Event</th>
                            <th>Change</th>
                            <th>From -> To</th>
                            <th>Top factor at that time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history_rows)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No score increase/decrease records yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history_rows as $row): ?>
                                <?php
                                $breakdown = $row['breakdown'] ?? [];
                                $factors = $breakdown['factors'] ?? [];
                                $top_label = 'N/A';
                                $top_value = null;
                                if (!empty($factors)) {
                                    usort($factors, function ($a, $b) {
                                        return ($b['weighted_05'] ?? 0) <=> ($a['weighted_05'] ?? 0);
                                    });
                                    $top_label = $factors[0]['label'] ?? 'N/A';
                                    $top_value = isset($factors[0]['weighted_05']) ? (float)$factors[0]['weighted_05'] : null;
                                }
                                $delta = (float)$row['delta'];
                                $delta_class = $delta > 0 ? 'si-delta-up' : 'si-delta-down';
                                $delta_text = ($delta > 0 ? '+' : '') . number_format($delta, 1);
                                ?>
                                <tr>
                                    <td><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <div style="font-weight:700;"><?php echo htmlspecialchars($row['event_label'] ?? 'Score update'); ?></div>
                                        <?php if (!empty($row['reason'])): ?>
                                            <div class="si-note"><?php echo htmlspecialchars($row['reason']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?php echo $delta_class; ?>"><?php echo $delta_text; ?></td>
                                    <td><?php echo number_format((float)$row['old_score'], 1); ?> -> <?php echo number_format((float)$row['new_score'], 1); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($top_label); ?>
                                        <?php if ($top_value !== null): ?>
                                            <span class="si-note">(<?php echo number_format($top_value, 2); ?> pts)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>

</html>
