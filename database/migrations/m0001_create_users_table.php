<?php

declare(strict_types=1);

/**
 * Migration: m0001_create_users_table
 * Purpose: Creates administrative users table with tiered roles, security lockout, and soft delete.
 */
return new class {
    public function up(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(191) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` ENUM('super_admin', 'coordinator', 'staff', 'viewer') NOT NULL DEFAULT 'staff',
            `phone` VARCHAR(25) NULL DEFAULT NULL,
            `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
            `failed_logins` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `locked_until` DATETIME NULL DEFAULT NULL,
            `last_login_at` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL DEFAULT NULL,
            UNIQUE KEY `uk_users_email` (`email`),
            INDEX `idx_users_role_status` (`role`, `status`),
            INDEX `idx_users_deleted_at` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $pdo->exec($sql);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `users`;");
    }
};
