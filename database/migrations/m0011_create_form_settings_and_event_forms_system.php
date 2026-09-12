<?php

declare(strict_types=1);

/**
 * Migration: m0011_create_form_settings_and_event_forms_system
 * Purpose: Establishes the dynamic form engine:
 *          - form_settings: Global configuration (mandatory location, +91 default, WhatsApp redirect)
 *          - event_forms: 1 Event = 1 Form with dedicated registration slug
 *          - form_fields: Field definitions (locked global mandatory fields + extensible custom fields)
 *          - Backfills forms and locked fields for existing events
 */
return new class {
    public function up(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `form_settings` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `mandatory_location_access` INTEGER NOT NULL DEFAULT 0,
                    `default_country_code` VARCHAR(10) NOT NULL DEFAULT '+91',
                    `default_country_iso` VARCHAR(5) NOT NULL DEFAULT 'IN',
                    `registration_success_message` TEXT NULL,
                    `whatsapp_group_url` VARCHAR(255) NULL,
                    `whatsapp_auto_redirect` INTEGER NOT NULL DEFAULT 0,
                    `whatsapp_countdown_seconds` INTEGER NOT NULL DEFAULT 5,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS `event_forms` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `event_id` INTEGER NOT NULL UNIQUE,
                    `form_title` VARCHAR(255) NOT NULL,
                    `slug` VARCHAR(191) NOT NULL UNIQUE,
                    `banner_path` VARCHAR(255) NULL,
                    `photo_upload_enabled` INTEGER NOT NULL DEFAULT 0,
                    `location_access_required` INTEGER NOT NULL DEFAULT 0,
                    `whatsapp_group_url` VARCHAR(255) NULL,
                    `whatsapp_auto_redirect` INTEGER NOT NULL DEFAULT 0,
                    `whatsapp_countdown_seconds` INTEGER NOT NULL DEFAULT 5,
                    `custom_success_message` TEXT NULL,
                    `status` VARCHAR(20) NOT NULL DEFAULT 'published',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE RESTRICT
                );

                CREATE TABLE IF NOT EXISTS `form_fields` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `form_id` INTEGER NOT NULL,
                    `field_key` VARCHAR(64) NOT NULL,
                    `field_label` VARCHAR(100) NOT NULL,
                    `field_type` VARCHAR(30) NOT NULL DEFAULT 'text',
                    `is_required` INTEGER NOT NULL DEFAULT 0,
                    `is_locked` INTEGER NOT NULL DEFAULT 0,
                    `sort_order` INTEGER NOT NULL DEFAULT 0,
                    `options_json` TEXT NULL,
                    `placeholder` VARCHAR(255) NULL,
                    `help_text` VARCHAR(255) NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (`form_id`, `field_key`),
                    FOREIGN KEY (`form_id`) REFERENCES `event_forms` (`id`) ON DELETE CASCADE
                );
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `form_settings` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `mandatory_location_access` TINYINT(1) NOT NULL DEFAULT 0,
                    `default_country_code` VARCHAR(10) NOT NULL DEFAULT '+91',
                    `default_country_iso` VARCHAR(5) NOT NULL DEFAULT 'IN',
                    `registration_success_message` TEXT NULL DEFAULT NULL,
                    `whatsapp_group_url` VARCHAR(255) NULL DEFAULT NULL,
                    `whatsapp_auto_redirect` TINYINT(1) NOT NULL DEFAULT 0,
                    `whatsapp_countdown_seconds` INT UNSIGNED NOT NULL DEFAULT 5,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `event_forms` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `event_id` INT UNSIGNED NOT NULL,
                    `form_title` VARCHAR(255) NOT NULL,
                    `slug` VARCHAR(191) NOT NULL,
                    `banner_path` VARCHAR(255) NULL DEFAULT NULL,
                    `photo_upload_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                    `location_access_required` TINYINT(1) NOT NULL DEFAULT 0,
                    `whatsapp_group_url` VARCHAR(255) NULL DEFAULT NULL,
                    `whatsapp_auto_redirect` TINYINT(1) NOT NULL DEFAULT 0,
                    `whatsapp_countdown_seconds` INT UNSIGNED NOT NULL DEFAULT 5,
                    `custom_success_message` TEXT NULL DEFAULT NULL,
                    `status` ENUM('draft', 'published', 'closed') NOT NULL DEFAULT 'published',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_event_forms_event` (`event_id`),
                    UNIQUE KEY `uk_event_forms_slug` (`slug`),
                    CONSTRAINT `fk_event_forms_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `form_fields` (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `form_id` INT UNSIGNED NOT NULL,
                    `field_key` VARCHAR(64) NOT NULL,
                    `field_label` VARCHAR(100) NOT NULL,
                    `field_type` ENUM('text', 'long_text', 'number', 'email', 'dropdown', 'radio', 'checkbox', 'date') NOT NULL DEFAULT 'text',
                    `is_required` TINYINT(1) NOT NULL DEFAULT 0,
                    `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `options_json` JSON NULL DEFAULT NULL,
                    `placeholder` VARCHAR(255) NULL DEFAULT NULL,
                    `help_text` VARCHAR(255) NULL DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_form_field_key` (`form_id`, `field_key`),
                    INDEX `idx_form_field_order` (`form_id`, `sort_order`),
                    CONSTRAINT `fk_form_fields_form` FOREIGN KEY (`form_id`) REFERENCES `event_forms` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }

        // Seed default Global Form Settings row if empty
        $count = (int) $pdo->query("SELECT COUNT(*) FROM `form_settings`")->fetchColumn();
        if ($count === 0) {
            $pdo->exec("
                INSERT INTO `form_settings` (
                    `id`, `mandatory_location_access`, `default_country_code`, `default_country_iso`, 
                    `registration_success_message`, `whatsapp_group_url`, `whatsapp_auto_redirect`, `whatsapp_countdown_seconds`
                ) VALUES (
                    1, 0, '+91', 'IN', 
                    'Thank you for registering! Your registration pass has been generated. Please present your pass QR at the event check-in.',
                    NULL, 0, 5
                );
            ");
        }

        // Backfill: Ensure every existing event has an event_forms record and locked fields
        $events = $pdo->query("SELECT `id`, `title`, `slug` FROM `events`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($events as $evt) {
            $checkStmt = $pdo->prepare("SELECT `id` FROM `event_forms` WHERE `event_id` = :eid LIMIT 1");
            $checkStmt->execute([':eid' => $evt['id']]);
            $existingFormId = $checkStmt->fetchColumn();

            if (!$existingFormId) {
                $insertForm = $pdo->prepare("
                    INSERT INTO `event_forms` (`event_id`, `form_title`, `slug`, `status`)
                    VALUES (:eid, :title, :slug, 'published')
                ");
                $insertForm->execute([
                    ':eid'   => $evt['id'],
                    ':title' => $evt['title'],
                    ':slug'  => $evt['slug'],
                ]);
                $existingFormId = $pdo->lastInsertId();
            }

            // Ensure locked global mandatory fields exist for this form
            $this->ensureDefaultFields($pdo, (int) $existingFormId);
        }
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `form_fields`;");
        $pdo->exec("DROP TABLE IF EXISTS `event_forms`;");
        $pdo->exec("DROP TABLE IF EXISTS `form_settings`;");
    }

    private function ensureDefaultFields(PDO $pdo, int $formId): void
    {
        $defaultFields = [
            [
                'field_key'   => 'full_name',
                'field_label' => 'Full Name',
                'field_type'  => 'text',
                'is_required' => 1,
                'is_locked'   => 1,
                'sort_order'  => 1,
                'placeholder' => 'Enter your full legal name',
            ],
            [
                'field_key'   => 'phone',
                'field_label' => 'WhatsApp / Mobile Number',
                'field_type'  => 'text',
                'is_required' => 1,
                'is_locked'   => 1,
                'sort_order'  => 2,
                'placeholder' => 'e.g. 9876543210',
            ],
            [
                'field_key'   => 'place',
                'field_label' => 'Place',
                'field_type'  => 'text',
                'is_required' => 0,
                'is_locked'   => 0,
                'sort_order'  => 3,
                'placeholder' => 'City or town of residence',
            ],
        ];

        foreach ($defaultFields as $df) {
            $stmt = $pdo->prepare("SELECT 1 FROM `form_fields` WHERE `form_id` = :fid AND `field_key` = :k LIMIT 1");
            $stmt->execute([':fid' => $formId, ':k' => $df['field_key']]);
            if (!$stmt->fetchColumn()) {
                $ins = $pdo->prepare("
                    INSERT INTO `form_fields` (`form_id`, `field_key`, `field_label`, `field_type`, `is_required`, `is_locked`, `sort_order`, `placeholder`)
                    VALUES (:fid, :k, :lbl, :t, :req, :lck, :ord, :ph)
                ");
                $ins->execute([
                    ':fid' => $formId,
                    ':k'   => $df['field_key'],
                    ':lbl' => $df['field_label'],
                    ':t'   => $df['field_type'],
                    ':req' => $df['is_required'],
                    ':lck' => $df['is_locked'],
                    ':ord' => $df['sort_order'],
                    ':ph'  => $df['placeholder'],
                ]);
            }
        }
    }
};
