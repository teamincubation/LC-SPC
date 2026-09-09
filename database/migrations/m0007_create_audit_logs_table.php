<?php

declare(strict_types=1);

/**
 * Migration: m0007_create_audit_logs_table
 * Purpose: Creates append-only security audit log table for tracking administrative and forensic actions.
 */
return new class {
    public function up(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `actor_id` INT UNSIGNED NULL DEFAULT NULL,
            `actor_type` ENUM('admin', 'system', 'anonymous') NOT NULL DEFAULT 'admin',
            `action` VARCHAR(100) NOT NULL,
            `entity_type` VARCHAR(50) NOT NULL,
            `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` VARCHAR(255) NULL DEFAULT NULL,
            `metadata` JSON NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_audit_actor_created` (`actor_id`, `created_at`),
            INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
            INDEX `idx_audit_action_created` (`action`, `created_at`),
            CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $pdo->exec($sql);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `audit_logs`;");
    }
};
