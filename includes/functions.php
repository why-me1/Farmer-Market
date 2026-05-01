<?php
function check_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'index.php?auth=login');
        exit();
    }
}

function sanitize(string $data): string
{
    return htmlspecialchars(stripslashes(trim($data)));
}

function ensure_delivery_otp_schema(): void
{
    global $conn;

    $conn->query("CREATE TABLE IF NOT EXISTS `delivery_otps` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `post_id` INT NOT NULL,
        `buyer_id` INT NOT NULL,
        `farmer_id` INT NOT NULL,
        `otp_code` VARCHAR(10) NOT NULL,
        `is_used` TINYINT(1) NOT NULL DEFAULT 0,
        `expires_at` DATETIME NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_delivery_otp` (`post_id`, `buyer_id`, `farmer_id`),
        KEY `idx_delivery_otps_post` (`post_id`),
        KEY `idx_delivery_otps_buyer` (`buyer_id`),
        KEY `idx_delivery_otps_farmer` (`farmer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add delivery columns to posts table if they don't exist yet
    $conn->query("ALTER TABLE `posts`
        ADD COLUMN IF NOT EXISTS `delivery_type`    ENUM('local','courier') NULL DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `courier_company`  VARCHAR(120)            NULL DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `courier_tracking` VARCHAR(120)            NULL DEFAULT NULL");
}
