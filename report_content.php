<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/moderation_functions.php';
check_login();

global $conn;

$reporter_id = (int) ($_SESSION['user_id'] ?? 0);
$target_type = strtolower(trim((string) ($_GET['target_type'] ?? $_POST['target_type'] ?? '')));
$target_id = (int) ($_GET['target_id'] ?? $_POST['target_id'] ?? 0);
$reported_user_id = isset($_GET['reported_user_id']) ? (int) $_GET['reported_user_id'] : (int) ($_POST['reported_user_id'] ?? 0);
$back = trim((string) ($_GET['back'] ?? $_POST['back'] ?? 'index.php'));

$is_safe_back = $back !== '' && strpos($back, '://') === false && strpos($back, "\n") === false && strpos($back, "\r") === false;

if (!in_array($target_type, ['post', 'comment', 'user'], true) || $target_id <= 0) {
    header('Location: ' . ($is_safe_back ? $back : 'index.php'));
    exit();
}

ensureModerationSchema();

$target_summary = moderation_enrich_report([
    'target_type' => $target_type,
    'target_id' => $target_id,
    'status' => 'pending',
]);
$target_summary = $target_summary['summary'];

$reason = '';
$details = '';
$toast = '';
$toast_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $details = trim((string) ($_POST['details'] ?? ''));

    if ($reason === '') {
        $toast = 'Please select a reason.';
        $toast_type = 'danger';
    } else {
        $ok = submitContentReport($reporter_id, $target_type, $target_id, $reported_user_id > 0 ? $reported_user_id : null, $reason, $details);
        if ($ok) {
            $toast = 'Report submitted. An admin will review it shortly.';
            $toast_type = 'success';
            if ($is_safe_back) {
                header('Location: ' . $back . (strpos($back, '?') === false ? '?' : '&') . 'reported=1');
                exit();
            }
        } else {
            $toast = 'Unable to submit the report.';
            $toast_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Content &mdash; Farmer Market</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            min-height: 100vh;
        }

        .report-shell {
            max-width: 760px;
            margin: 0 auto;
            padding: 48px 16px 60px;
        }

        .report-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .08);
            overflow: hidden;
        }

        .report-hero {
            background: linear-gradient(135deg, #7c3aed, #4f46e5);
            color: #fff;
            padding: 28px 30px;
        }

        .report-hero h1 {
            font-size: 1.55rem;
            font-weight: 800;
            margin: 0 0 6px;
        }

        .report-hero p {
            margin: 0;
            opacity: .9;
            font-size: .92rem;
        }

        .report-body {
            padding: 26px 30px 30px;
        }

        .target-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 22px;
        }

        .target-box h5 {
            margin: 0 0 4px;
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
        }

        .target-box p {
            margin: 0;
            color: #64748b;
            font-size: .85rem;
        }

        .form-label-custom {
            font-size: .78rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .45px;
            margin-bottom: 6px;
            display: block;
        }

        .form-ctrl {
            width: 100%;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 11px 14px;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: border-color .18s, box-shadow .18s;
        }

        .form-ctrl:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, .12);
        }

        .btn-submit {
            background: linear-gradient(135deg, #7c3aed, #4f46e5);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 11px 18px;
            width: 100%;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .toast-wrap {
            position: fixed;
            top: 20px;
            right: 24px;
            z-index: 9999;
            display: <?php echo $toast !== '' ? 'block' : 'none'; ?>;
        }

        .toast-msg {
            background: #0f172a;
            color: #fff;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: .83rem;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .18);
        }

        .toast-msg.success {
            border-left: 4px solid #22c55e;
        }

        .toast-msg.danger {
            border-left: 4px solid #ef4444;
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>
    <div class="report-shell">
        <div class="report-card">
            <div class="report-hero">
                <h1>Report Content</h1>
                <p>Send a report to the moderation queue. Admins can hide, approve, warn, or ban from one place.</p>
            </div>
            <div class="report-body">
                <div class="target-box">
                    <h5><?php echo htmlspecialchars($target_summary['title']); ?></h5>
                    <p><?php echo htmlspecialchars($target_summary['subtitle'] ?: ucfirst($target_type) . ' report'); ?></p>
                </div>

                <form method="POST" action="report_content.php">
                    <input type="hidden" name="target_type" value="<?php echo htmlspecialchars($target_type); ?>">
                    <input type="hidden" name="target_id" value="<?php echo (int) $target_id; ?>">
                    <input type="hidden" name="reported_user_id" value="<?php echo (int) $reported_user_id; ?>">
                    <input type="hidden" name="back" value="<?php echo htmlspecialchars($back); ?>">

                    <div class="mb-3">
                        <label class="form-label-custom" for="reason">Reason</label>
                        <select name="reason" id="reason" class="form-ctrl" required>
                            <option value="">Select a reason</option>
                            <option value="spam" <?php echo $reason === 'spam' ? 'selected' : ''; ?>>Spam or misleading content</option>
                            <option value="harassment" <?php echo $reason === 'harassment' ? 'selected' : ''; ?>>Harassment or abuse</option>
                            <option value="fraud" <?php echo $reason === 'fraud' ? 'selected' : ''; ?>>Fraud or scam</option>
                            <option value="offensive" <?php echo $reason === 'offensive' ? 'selected' : ''; ?>>Offensive or inappropriate</option>
                            <option value="other" <?php echo $reason === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-custom" for="details">Details</label>
                        <textarea name="details" id="details" class="form-ctrl" rows="5" placeholder="Add any extra details for the admin team..."><?php echo htmlspecialchars($details); ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="bi bi-send-fill"></i> Submit Report
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="toast-wrap">
        <div class="toast-msg <?php echo htmlspecialchars($toast_type); ?>">
            <i class="bi bi-info-circle-fill"></i>
            <span><?php echo htmlspecialchars($toast); ?></span>
        </div>
    </div>
</body>

</html>