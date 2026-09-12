<?php

declare(strict_types=1);

/**
 * Migration: m0013_create_certificate_templates_and_v2
 * Purpose: Creates certificate_templates table supporting custom backgrounds,
 *          organisation seal, up to two signatures, dynamic text variables,
 *          and pixel/percentage layout configurations.
 */
return new class {
    public function up(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `certificate_templates` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `event_id` INTEGER NOT NULL UNIQUE,
                    `background_image_path` VARCHAR(255) NULL,
                    `seal_image_path` VARCHAR(255) NULL,
                    `signature1_image_path` VARCHAR(255) NULL,
                    `signature1_name` VARCHAR(100) NULL,
                    `signature1_designation` VARCHAR(100) NULL,
                    `signature2_image_path` VARCHAR(255) NULL,
                    `signature2_name` VARCHAR(100) NULL,
                    `signature2_designation` VARCHAR(100) NULL,
                    `layout_config` TEXT NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
                );
            ");
            return;
        }

        // MySQL / MariaDB Execution:
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `certificate_templates` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `event_id` INT UNSIGNED NOT NULL,
                `background_image_path` VARCHAR(255) NULL DEFAULT NULL,
                `seal_image_path` VARCHAR(255) NULL DEFAULT NULL,
                `signature1_image_path` VARCHAR(255) NULL DEFAULT NULL,
                `signature1_name` VARCHAR(100) NULL DEFAULT NULL,
                `signature1_designation` VARCHAR(100) NULL DEFAULT NULL,
                `signature2_image_path` VARCHAR(255) NULL DEFAULT NULL,
                `signature2_name` VARCHAR(100) NULL DEFAULT NULL,
                `signature2_designation` VARCHAR(100) NULL DEFAULT NULL,
                `layout_config` JSON NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_cert_template_event` (`event_id`),
                CONSTRAINT `fk_cert_template_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `certificate_templates`;");
    }
};
