<?php

declare(strict_types=1);

/**
 * Migration: m0005_create_event_registrations_table
 * Purpose: Creates event registrations junction table with pass codes, attendance status, and check-in tracking.
 */
return new class {
    public function up(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `event_registrations` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `registration_code` VARCHAR(40) NOT NULL,
            `event_id` INT UNSIGNED NOT NULL,
            `participant_id` BIGINT UNSIGNED NOT NULL,
            `status` ENUM('pending', 'confirmed', 'waitlisted', 'cancelled') NOT NULL DEFAULT 'confirmed',
            `attendance_status` ENUM('unmarked', 'attended', 'absent', 'excused') NOT NULL DEFAULT 'unmarked',
            `checked_in_at` DATETIME NULL DEFAULT NULL,
            `checked_in_by` INT UNSIGNED NULL DEFAULT NULL,
            `check_in_method` ENUM('admin_manual', 'qr_scan', 'self_verified') NULL DEFAULT NULL,
            `admin_notes` VARCHAR(255) NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_event_reg_code` (`registration_code`),
            UNIQUE KEY `uk_event_reg_unique_enrollment` (`event_id`, `participant_id`),
            INDEX `idx_event_reg_attendance` (`event_id`, `attendance_status`),
            INDEX `idx_event_reg_participant` (`participant_id`),
            INDEX `idx_event_reg_checked_in_by` (`checked_in_by`),
            CONSTRAINT `fk_event_reg_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_event_reg_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_event_reg_staff` FOREIGN KEY (`checked_in_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $pdo->exec($sql);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `event_registrations`;");
    }
};
