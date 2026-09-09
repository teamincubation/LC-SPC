<?php

declare(strict_types=1);

/**
 * Migration: m0002_create_campaigns_table
 * Purpose: Creates campaigns table for multi-year initiative grouping with soft delete.
 */
return new class {
    public function up(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `campaigns` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(191) NOT NULL,
            `slug` VARCHAR(191) NOT NULL,
            `theme` VARCHAR(255) NULL DEFAULT NULL,
            `description` TEXT NULL DEFAULT NULL,
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `status` ENUM('draft', 'active', 'completed', 'archived') NOT NULL DEFAULT 'draft',
            `created_by` INT UNSIGNED NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL DEFAULT NULL,
            UNIQUE KEY `uk_campaigns_slug` (`slug`),
            INDEX `idx_campaigns_status_dates` (`status`, `start_date`, `end_date`),
            INDEX `idx_campaigns_created_by` (`created_by`),
            CONSTRAINT `fk_campaigns_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $pdo->exec($sql);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `campaigns`;");
    }
};
