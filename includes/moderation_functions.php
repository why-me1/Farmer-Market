<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notification_functions.php';

function ensureModerationSchema(): void
{
    global $conn;

    ensureNotificationSchema();

    $conn->query("CREATE TABLE IF NOT EXISTS `content_reports` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `reporter_id` INT NOT NULL,
        `target_type` VARCHAR(20) NOT NULL,
        `target_id` INT NOT NULL,
        `reported_user_id` INT DEFAULT NULL,
        `reason` VARCHAR(120) NOT NULL,
        `details` TEXT DEFAULT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `admin_action` VARCHAR(20) DEFAULT NULL,
        `admin_id` INT DEFAULT NULL,
        `admin_notes` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `reviewed_at` DATETIME DEFAULT NULL,
        KEY `idx_reports_status` (`status`),
        KEY `idx_reports_target` (`target_type`, `target_id`),
        KEY `idx_reports_reporter` (`reporter_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("ALTER TABLE `users`
        ADD COLUMN IF NOT EXISTS `is_banned` TINYINT(1) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `warning_count` INT NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `last_warning_at` DATETIME DEFAULT NULL");

    $conn->query("ALTER TABLE `posts`
        ADD COLUMN IF NOT EXISTS `is_hidden` TINYINT(1) NOT NULL DEFAULT 0");

    $conn->query("ALTER TABLE `comments`
        ADD COLUMN IF NOT EXISTS `is_hidden` TINYINT(1) NOT NULL DEFAULT 0");
}

function submitContentReport(int $reporter_id, string $target_type, int $target_id, ?int $reported_user_id, string $reason, string $details = ''): bool
{
    global $conn;

    ensureModerationSchema();

    $target_type = strtolower(trim($target_type));
    $reason = trim($reason);
    $details = trim($details);

    if (!in_array($target_type, ['post', 'comment', 'user'], true) || $target_id <= 0 || $reason === '') {
        return false;
    }

    $duplicate = $conn->prepare("SELECT id FROM content_reports WHERE reporter_id = ? AND target_type = ? AND target_id = ? AND reason = ? AND status = 'pending' LIMIT 1");
    $duplicate->bind_param("isis", $reporter_id, $target_type, $target_id, $reason);
    $duplicate->execute();
    $duplicate->store_result();
    if ($duplicate->num_rows > 0) {
        $duplicate->close();
        return true;
    }
    $duplicate->close();

    $stmt = $conn->prepare("INSERT INTO content_reports (reporter_id, target_type, target_id, reported_user_id, reason, details) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isiiss", $reporter_id, $target_type, $target_id, $reported_user_id, $reason, $details);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

function moderation_enrich_report(array $report): array
{
    global $conn;

    $summary = [
        'title' => 'Unknown item',
        'subtitle' => '',
        'link' => '#',
        'type_label' => ucfirst($report['target_type'] ?? 'item'),
        'status_label' => ucfirst($report['status'] ?? 'pending'),
    ];

    $target_type = $report['target_type'] ?? '';
    $target_id = (int) ($report['target_id'] ?? 0);

    if ($target_type === 'post' && $target_id > 0) {
        $stmt = $conn->prepare("SELECT p.product_name, p.is_approved, p.is_hidden, p.status, u.username AS owner_name FROM posts p JOIN users u ON u.id = p.farmer_id WHERE p.id = ? LIMIT 1");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $summary['title'] = $row['product_name'];
            $summary['subtitle'] = 'Post by ' . $row['owner_name'] . ' • ' . ucfirst((string) $row['status']);
            $summary['link'] = '../product_detail.php?id=' . $target_id;
        }
    } elseif ($target_type === 'comment' && $target_id > 0) {
        $stmt = $conn->prepare("SELECT c.comment_text, c.is_approved, p.product_name, u.username AS bidder_name, p.id AS post_id FROM comments c JOIN posts p ON p.id = c.post_id JOIN users u ON u.id = c.user_id WHERE c.id = ? LIMIT 1");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $summary['title'] = 'Bid on ' . $row['product_name'];
            $summary['subtitle'] = 'By ' . $row['bidder_name'] . ' • ' . number_format((float) $row['comment_text'], 0) . '৳';
            $summary['link'] = '../product_detail.php?id=' . (int) $row['post_id'];
        }
    } elseif ($target_type === 'user' && $target_id > 0) {
        $stmt = $conn->prepare("SELECT username, full_name, role, is_banned, warning_count FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $summary['title'] = !empty($row['full_name']) ? $row['full_name'] : $row['username'];
            $summary['subtitle'] = ucfirst($row['role']) . ' • ' . ((int) $row['warning_count']) . ' warning' . ((int) $row['warning_count'] === 1 ? '' : 's');
            $summary['link'] = '../user/profile.php?id=' . $target_id;
        }
    }

    $report['summary'] = $summary;
    return $report;
}

function getModerationReports(string $status = 'pending'): array
{
    global $conn;

    ensureModerationSchema();

    $status = strtolower(trim($status));
    $allowed = ['pending', 'resolved', 'dismissed', 'all'];
    if (!in_array($status, $allowed, true)) {
        $status = 'pending';
    }

    $sql = "SELECT r.*, u.username AS reporter_name, ru.username AS reported_name
            FROM content_reports r
            JOIN users u ON u.id = r.reporter_id
            LEFT JOIN users ru ON ru.id = r.reported_user_id";

    if ($status !== 'all') {
        $sql .= " WHERE r.status = ? ORDER BY r.created_at DESC LIMIT 100";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $status);
    } else {
        $sql .= " ORDER BY r.created_at DESC LIMIT 100";
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map('moderation_enrich_report', $rows);
}

function applyModerationAction(int $report_id, int $admin_id, string $action, string $notes = ''): bool
{
    global $conn;

    ensureModerationSchema();

    $action = strtolower(trim($action));
    $notes = trim($notes);

    $stmt = $conn->prepare("SELECT * FROM content_reports WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$report) {
        return false;
    }

    $target_type = $report['target_type'];
    $target_id = (int) $report['target_id'];
    $reported_user_id = !empty($report['reported_user_id']) ? (int) $report['reported_user_id'] : null;

    $resolved = false;

    if ($target_type === 'post') {
        if ($action === 'hide') {
            $upd = $conn->prepare("UPDATE posts SET is_hidden = 1, is_approved = 0 WHERE id = ?");
            $upd->bind_param("i", $target_id);
            $resolved = $upd->execute();
            $upd->close();
        } elseif ($action === 'approve') {
            $upd = $conn->prepare("UPDATE posts SET is_hidden = 0, is_approved = 1 WHERE id = ?");
            $upd->bind_param("i", $target_id);
            $resolved = $upd->execute();
            $upd->close();
        }
    } elseif ($target_type === 'comment') {
        if ($action === 'hide') {
            $upd = $conn->prepare("UPDATE comments SET is_hidden = 1 WHERE id = ?");
            $upd->bind_param("i", $target_id);
            $resolved = $upd->execute();
            $upd->close();
        } elseif ($action === 'approve') {
            $upd = $conn->prepare("UPDATE comments SET is_hidden = 0 WHERE id = ?");
            $upd->bind_param("i", $target_id);
            $resolved = $upd->execute();
            $upd->close();
        }
    } elseif ($target_type === 'user' && $reported_user_id) {
        if ($action === 'warn') {
            $upd = $conn->prepare("UPDATE users SET warning_count = warning_count + 1, last_warning_at = NOW() WHERE id = ?");
            $upd->bind_param("i", $reported_user_id);
            $resolved = $upd->execute();
            $upd->close();
            createNotification($reported_user_id, null, null, 'account_warning');
        } elseif ($action === 'ban') {
            $upd = $conn->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");
            $upd->bind_param("i", $reported_user_id);
            $resolved = $upd->execute();
            $upd->close();
            createNotification($reported_user_id, null, null, 'account_banned');
        } elseif ($action === 'approve') {
            $resolved = true;
        }
    }

    if (!$resolved) {
        return false;
    }

    $new_status = $action === 'approve' ? 'dismissed' : 'resolved';
    $upd_report = $conn->prepare("UPDATE content_reports SET status = ?, admin_action = ?, admin_id = ?, admin_notes = ?, reviewed_at = NOW() WHERE id = ?");
    $upd_report->bind_param("ssiss", $new_status, $action, $admin_id, $notes, $report_id);
    $ok = $upd_report->execute();
    $upd_report->close();

    return $ok;
}
