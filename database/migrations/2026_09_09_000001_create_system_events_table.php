<?php

declare(strict_types=1);

return new class {
    public function up(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS `system_events` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `event_type` VARCHAR(64) NOT NULL,
                `message` VARCHAR(255) NOT NULL,
                `context` TEXT NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS `system_events` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `event_type` VARCHAR(64) NOT NULL,
                `message` VARCHAR(255) NOT NULL,
                `context` JSON NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_event_type` (`event_type`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        }

        $pdo->exec($sql);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `system_events`;");
    }
};
