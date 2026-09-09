<?php

declare(strict_types=1);

/**
 * Migration: m0004_create_participants_table
 * Purpose: Creates participants table with canonical identity, non-unique optional email, and consent timestamps.
 */
return new class {
    public function up(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `participants` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `full_name` VARCHAR(150) NOT NULL,
            `email` VARCHAR(191) NULL DEFAULT NULL,
            `phone` VARCHAR(25) NULL DEFAULT NULL,
            `category` ENUM('student', 'professional', 'community', 'other') NOT NULL DEFAULT 'community',
            `organization_name` VARCHAR(191) NULL DEFAULT NULL,
            `agreed_guidelines_at` DATETIME NOT NULL,
            `privacy_consent_at` DATETIME NOT NULL,
            `status` ENUM('active', 'flagged', 'blocked') NOT NULL DEFAULT 'active',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_participants_email` (`email`),
            INDEX `idx_participants_category` (`category`),
            INDEX `idx_participants_name` (`full_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $pdo->exec($sql);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `participants`;");
    }
};
