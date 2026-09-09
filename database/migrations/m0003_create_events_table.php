<?php

declare(strict_types=1);

/**
 * Migration: m0003_create_events_table
 * Purpose: Creates events table with scheduling, venue details, capacity, and status.
 */
return new class {
    public function up(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `events` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `campaign_id` INT UNSIGNED NOT NULL,
            `coordinator_id` INT UNSIGNED NULL DEFAULT NULL,
            `title` VARCHAR(191) NOT NULL,
            `slug` VARCHAR(191) NOT NULL,
            `category` ENUM('workshop', 'listening_circle', 'training', 'seminar', 'pledge_drive') NOT NULL DEFAULT 'workshop',
            `description` TEXT NULL DEFAULT NULL,
            `format` ENUM('in_person', 'online', 'hybrid') NOT NULL DEFAULT 'in_person',
            `venue_name` VARCHAR(255) NULL DEFAULT NULL,
            `venue_address` TEXT NULL DEFAULT NULL,
            `online_meeting_url` VARCHAR(255) NULL DEFAULT NULL,
            `start_time` DATETIME NOT NULL,
            `end_time` DATETIME NOT NULL,
            `capacity` INT UNSIGNED NOT NULL DEFAULT 0,
            `registration_deadline` DATETIME NULL DEFAULT NULL,
            `requires_approval` TINYINT(1) NOT NULL DEFAULT 0,
            `status` ENUM('draft', 'published', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL DEFAULT NULL,
            UNIQUE KEY `uk_events_campaign_slug` (`campaign_id`, `slug`),
            INDEX `idx_events_status_schedule` (`status`, `start_time`, `end_time`),
            INDEX `idx_events_category` (`category`),
            INDEX `idx_events_coordinator` (`coordinator_id`),
            CONSTRAINT `fk_events_campaign_id` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_events_coordinator_id` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $pdo->exec($sql);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `events`;");
    }
};
