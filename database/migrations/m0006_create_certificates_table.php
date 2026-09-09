<?php

declare(strict_types=1);

/**
 * Migration: m0006_create_certificates_table
 * Purpose: Creates certificates table with 256-bit verification tokens, name snapshots, and revocation fields.
 */
return new class {
    public function up(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `certificates` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `certificate_number` VARCHAR(50) NOT NULL,
            `verification_token` VARCHAR(64) NOT NULL,
            `registration_id` BIGINT UNSIGNED NOT NULL,
            `recipient_name_snapshot` VARCHAR(150) NOT NULL,
            `type` ENUM('participation', 'volunteer', 'speaker', 'appreciation') NOT NULL DEFAULT 'participation',
            `issue_date` DATE NOT NULL,
            `status` ENUM('active', 'revoked') NOT NULL DEFAULT 'active',
            `issued_by` INT UNSIGNED NULL DEFAULT NULL,
            `revoked_at` DATETIME NULL DEFAULT NULL,
            `revoked_by` INT UNSIGNED NULL DEFAULT NULL,
            `revocation_reason` VARCHAR(255) NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_cert_number` (`certificate_number`),
            UNIQUE KEY `uk_cert_token` (`verification_token`),
            UNIQUE KEY `uk_cert_reg_type` (`registration_id`, `type`),
            INDEX `idx_cert_status` (`status`),
            INDEX `idx_cert_issued_by` (`issued_by`),
            INDEX `idx_cert_revoked_by` (`revoked_by`),
            CONSTRAINT `fk_cert_registration` FOREIGN KEY (`registration_id`) REFERENCES `event_registrations` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_cert_issued_by` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_cert_revoked_by` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $pdo->exec($sql);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `certificates`;");
    }
};
