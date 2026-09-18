<?php

declare(strict_types=1);

/**
 * Migration: m0014_create_certificate_platform_v3_tables
 * Purpose: Creates decoupled, additive tables for LC-SPC Certificate Platform V3:
 *          - certificate_settings
 *          - v3_certificate_templates
 *          - v3_certificate_batches
 *          - v3_certificates
 *          - certificate_fonts
 *          - Seeds canonical V3 permissions and default settings.
 *
 * NOTE: Strictly additive. Legacy tables (events, campaigns, registrations, old certificates)
 *       are completely preserved with zero data modification or dropping.
 */
return new class {
    public function up(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            // SQLite schema for fallback / unit testing
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `certificate_settings` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
                    `setting_value` TEXT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS `v3_certificate_templates` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `name` VARCHAR(191) NOT NULL,
                    `description` TEXT NULL,
                    `certificate_type` VARCHAR(50) NOT NULL DEFAULT 'participation',
                    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                    `background_image_path` VARCHAR(255) NULL,
                    `seal_image_path` VARCHAR(255) NULL,
                    `signature1_image_path` VARCHAR(255) NULL,
                    `signature1_name` VARCHAR(100) NULL,
                    `signature1_designation` VARCHAR(100) NULL,
                    `signature2_image_path` VARCHAR(255) NULL,
                    `signature2_name` VARCHAR(100) NULL,
                    `signature2_designation` VARCHAR(100) NULL,
                    `layout_config` TEXT NOT NULL,
                    `required_variables` TEXT NOT NULL,
                    `created_by` INTEGER NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS `v3_certificate_batches` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `batch_code` VARCHAR(64) NOT NULL UNIQUE,
                    `template_id` INTEGER NOT NULL,
                    `total_records` INTEGER NOT NULL DEFAULT 0,
                    `valid_records` INTEGER NOT NULL DEFAULT 0,
                    `invalid_records` INTEGER NOT NULL DEFAULT 0,
                    `generated_count` INTEGER NOT NULL DEFAULT 0,
                    `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
                    `csv_filename` VARCHAR(255) NULL,
                    `csv_data_path` VARCHAR(255) NULL,
                    `error_summary` TEXT NULL,
                    `generated_by` INTEGER NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`template_id`) REFERENCES `v3_certificate_templates` (`id`) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS `v3_certificates` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `certificate_id` VARCHAR(50) NOT NULL UNIQUE,
                    `verification_token` VARCHAR(64) NOT NULL UNIQUE,
                    `batch_id` INTEGER NULL,
                    `template_id` INTEGER NOT NULL,
                    `name` VARCHAR(150) NOT NULL,
                    `phone` VARCHAR(25) NOT NULL,
                    `phone_normalized` VARCHAR(25) NOT NULL,
                    `email` VARCHAR(191) NULL,
                    `event_title` VARCHAR(191) NULL,
                    `event_type` VARCHAR(50) NULL,
                    `date` VARCHAR(50) NULL,
                    `place` VARCHAR(150) NULL,
                    `certificate_data_json` TEXT NOT NULL,
                    `pdf_path` VARCHAR(255) NULL,
                    `image_path` VARCHAR(255) NULL,
                    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                    `invalidated_at` DATETIME NULL,
                    `invalidated_by` INTEGER NULL,
                    `invalidation_reason` VARCHAR(255) NULL,
                    `revoked_at` DATETIME NULL,
                    `revoked_by` INTEGER NULL,
                    `deleted_at` DATETIME NULL,
                    `created_by` INTEGER NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`template_id`) REFERENCES `v3_certificate_templates` (`id`) ON DELETE RESTRICT,
                    FOREIGN KEY (`batch_id`) REFERENCES `v3_certificate_batches` (`id`) ON DELETE SET NULL
                );

                CREATE INDEX IF NOT EXISTS `idx_v3_cert_phone_norm` ON `v3_certificates` (`phone_normalized`);
                CREATE INDEX IF NOT EXISTS `idx_v3_cert_status` ON `v3_certificates` (`status`);
                CREATE INDEX IF NOT EXISTS `idx_v3_cert_template` ON `v3_certificates` (`template_id`);
                CREATE INDEX IF NOT EXISTS `idx_v3_cert_batch` ON `v3_certificates` (`batch_id`);

                CREATE TABLE IF NOT EXISTS `certificate_fonts` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `name` VARCHAR(100) NOT NULL,
                    `font_family` VARCHAR(100) NOT NULL,
                    `file_path` VARCHAR(255) NOT NULL,
                    `file_size` INTEGER NOT NULL,
                    `format` VARCHAR(10) NOT NULL,
                    `is_active` INTEGER NOT NULL DEFAULT 1,
                    `created_by` INTEGER NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
            ");
        } else {
            // MySQL / MariaDB Execution:
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `certificate_settings` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `setting_key` VARCHAR(100) NOT NULL,
                    `setting_value` TEXT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_cert_settings_key` (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `v3_certificate_templates` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(191) NOT NULL,
                    `description` TEXT NULL DEFAULT NULL,
                    `certificate_type` VARCHAR(50) NOT NULL DEFAULT 'participation',
                    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                    `background_image_path` VARCHAR(255) NULL DEFAULT NULL,
                    `seal_image_path` VARCHAR(255) NULL DEFAULT NULL,
                    `signature1_image_path` VARCHAR(255) NULL DEFAULT NULL,
                    `signature1_name` VARCHAR(100) NULL DEFAULT NULL,
                    `signature1_designation` VARCHAR(100) NULL DEFAULT NULL,
                    `signature2_image_path` VARCHAR(255) NULL DEFAULT NULL,
                    `signature2_name` VARCHAR(100) NULL DEFAULT NULL,
                    `signature2_designation` VARCHAR(100) NULL DEFAULT NULL,
                    `layout_config` JSON NOT NULL,
                    `required_variables` JSON NOT NULL,
                    `created_by` INT UNSIGNED NULL DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_v3_template_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `v3_certificate_batches` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `batch_code` VARCHAR(64) NOT NULL,
                    `template_id` INT UNSIGNED NOT NULL,
                    `total_records` INT UNSIGNED NOT NULL DEFAULT 0,
                    `valid_records` INT UNSIGNED NOT NULL DEFAULT 0,
                    `invalid_records` INT UNSIGNED NOT NULL DEFAULT 0,
                    `generated_count` INT UNSIGNED NOT NULL DEFAULT 0,
                    `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
                    `csv_filename` VARCHAR(255) NULL DEFAULT NULL,
                    `csv_data_path` VARCHAR(255) NULL DEFAULT NULL,
                    `error_summary` TEXT NULL DEFAULT NULL,
                    `generated_by` INT UNSIGNED NULL DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_v3_batches_code` (`batch_code`),
                    INDEX `idx_v3_batches_template` (`template_id`),
                    INDEX `idx_v3_batches_status` (`status`),
                    CONSTRAINT `fk_v3_batches_template` FOREIGN KEY (`template_id`) REFERENCES `v3_certificate_templates` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `v3_certificates` (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `certificate_id` VARCHAR(50) NOT NULL,
                    `verification_token` VARCHAR(64) NOT NULL,
                    `batch_id` INT UNSIGNED NULL DEFAULT NULL,
                    `template_id` INT UNSIGNED NOT NULL,
                    `name` VARCHAR(150) NOT NULL,
                    `phone` VARCHAR(25) NOT NULL,
                    `phone_normalized` VARCHAR(25) NOT NULL,
                    `email` VARCHAR(191) NULL DEFAULT NULL,
                    `event_title` VARCHAR(191) NULL DEFAULT NULL,
                    `event_type` VARCHAR(50) NULL DEFAULT NULL,
                    `date` VARCHAR(50) NULL DEFAULT NULL,
                    `place` VARCHAR(150) NULL DEFAULT NULL,
                    `certificate_data_json` JSON NOT NULL,
                    `pdf_path` VARCHAR(255) NULL DEFAULT NULL,
                    `image_path` VARCHAR(255) NULL DEFAULT NULL,
                    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                    `invalidated_at` DATETIME NULL DEFAULT NULL,
                    `invalidated_by` INT UNSIGNED NULL DEFAULT NULL,
                    `invalidation_reason` VARCHAR(255) NULL DEFAULT NULL,
                    `revoked_at` DATETIME NULL DEFAULT NULL,
                    `revoked_by` INT UNSIGNED NULL DEFAULT NULL,
                    `deleted_at` DATETIME NULL DEFAULT NULL,
                    `created_by` INT UNSIGNED NULL DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_v3_cert_id` (`certificate_id`),
                    UNIQUE KEY `uk_v3_cert_token` (`verification_token`),
                    INDEX `idx_v3_cert_phone_norm` (`phone_normalized`),
                    INDEX `idx_v3_cert_name` (`name`),
                    INDEX `idx_v3_cert_status` (`status`),
                    INDEX `idx_v3_cert_date` (`date`),
                    INDEX `idx_v3_cert_template` (`template_id`),
                    INDEX `idx_v3_cert_batch` (`batch_id`),
                    INDEX `idx_v3_cert_deleted` (`deleted_at`),
                    CONSTRAINT `fk_v3_cert_template` FOREIGN KEY (`template_id`) REFERENCES `v3_certificate_templates` (`id`) ON DELETE RESTRICT,
                    CONSTRAINT `fk_v3_cert_batch` FOREIGN KEY (`batch_id`) REFERENCES `v3_certificate_batches` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `certificate_fonts` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL,
                    `font_family` VARCHAR(100) NOT NULL,
                    `file_path` VARCHAR(255) NOT NULL,
                    `file_size` INT UNSIGNED NOT NULL,
                    `format` VARCHAR(10) NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_by` INT UNSIGNED NULL DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_cert_font_name` (`name`),
                    INDEX `idx_cert_font_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }

        // Seed default certificate settings
        $settings = [
            'cert_id_prefix'                     => 'LC',
            'cert_id_include_year'               => '1',
            'cert_id_separator'                  => '-',
            'cert_id_segment_count'              => '2',
            'cert_id_segment_length'             => '4',
            'cert_id_charset'                    => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ',
            'output_format'                      => 'pdf_image',
            'canvas_width'                       => '2480',
            'canvas_height'                      => '1754',
            'dpi'                                => '300',
            'default_font'                       => 'arial',
            'public_search_phone_enabled'        => '1',
            'public_rate_limit_max_attempts'     => '10',
            'public_rate_limit_lockout_seconds'  => '900',
        ];

        $setSql = ($driver === 'sqlite')
            ? "INSERT OR IGNORE INTO `certificate_settings` (`setting_key`, `setting_value`) VALUES (:k, :v)"
            : "INSERT IGNORE INTO `certificate_settings` (`setting_key`, `setting_value`) VALUES (:k, :v)";
        $stmtSet = $pdo->prepare($setSql);
        foreach ($settings as $k => $v) {
            $stmtSet->execute([':k' => $k, ':v' => $v]);
        }

        // Seed canonical V3 permissions if permissions table exists
        $hasPermissionsTable = false;
        try {
            $checkStmt = $pdo->query("SELECT 1 FROM `permissions` LIMIT 1");
            $hasPermissionsTable = ($checkStmt !== false);
        } catch (Throwable) {
            $hasPermissionsTable = false;
        }

        if ($hasPermissionsTable) {
            $permissions = [
                ['certificate_settings.view',   'certificate_settings',   'view',      'View certificate platform configuration and settings'],
                ['certificate_settings.manage', 'certificate_settings',   'manage',    'Manage certificate ID patterns, fonts, and platform settings'],
                ['certificate_templates.view',   'certificate_templates',  'view',      'View certificate templates and layout configurations'],
                ['certificate_templates.create', 'certificate_templates',  'create',    'Create new certificate templates'],
                ['certificate_templates.edit',   'certificate_templates',  'edit',      'Modify certificate templates and visual designs'],
                ['certificate_templates.delete', 'certificate_templates',  'delete',    'Deactivate or remove certificate templates'],
                ['certificate_templates.manage', 'certificate_templates',  'manage',    'Full control over certificate template lifecycle'],
                ['certificates.generate',        'certificates',           'generate',  'Upload CSV and generate automated certificate batches'],
                ['certificates.view',            'certificates',           'view',      'View certificate registry, recipient data, and logs'],
                ['certificates.download',        'certificates',           'download',  'Download official PDF and high-resolution certificate images'],
                ['certificates.invalidate',      'certificates',           'invalidate','Invalidate or revoke issued credentials with audit trail'],
                ['certificates.delete',          'certificates',           'delete',    'Archive or soft-delete certificates (Super Admin)'],
                ['certificates.export',          'certificates',           'export',    'Export certificate registries and generation batch reports'],
            ];

            $permSql = ($driver === 'sqlite')
                ? "INSERT OR IGNORE INTO `permissions` (`name`, `module`, `action`, `description`) VALUES (:name, :module, :action, :desc)"
                : "INSERT IGNORE INTO `permissions` (`name`, `module`, `action`, `description`) VALUES (:name, :module, :action, :desc)";
            $stmtPerm = $pdo->prepare($permSql);

            foreach ($permissions as $p) {
                $stmtPerm->execute([
                    ':name'   => $p[0],
                    ':module' => $p[1],
                    ':action' => $p[2],
                    ':desc'   => $p[3],
                ]);
            }
        }
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `v3_certificates`;");
        $pdo->exec("DROP TABLE IF EXISTS `v3_certificate_batches`;");
        $pdo->exec("DROP TABLE IF EXISTS `v3_certificate_templates`;");
        $pdo->exec("DROP TABLE IF EXISTS `certificate_fonts`;");
        $pdo->exec("DROP TABLE IF EXISTS `certificate_settings`;");

        try {
            $pdo->exec("DELETE FROM `permissions` WHERE `module` IN ('certificate_settings', 'certificate_templates') OR (`module` = 'certificates' AND `action` IN ('generate', 'invalidate'));");
        } catch (Throwable) {
            // Non-critical if permissions table absent
        }
    }
};
