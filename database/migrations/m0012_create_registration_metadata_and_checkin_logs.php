<?php

declare(strict_types=1);

/**
 * Migration: m0012_create_registration_metadata_and_checkin_logs
 * Purpose: Adds background technical metadata collection and geofence check-in tracking:
 *          - registration_metadata table (IP, device, OS, browser, ISP, AS Name)
 *          - event_registrations extensions (custom_data, phone_normalized, photo_path, checkin_coordinates)
 *          - Unique constraint per (event_id, phone_normalized) preventing duplicate event enrollments
 *          - Backfills phone_normalized from existing participants
 */
return new class {
    public function up(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `registration_metadata` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `registration_id` INTEGER NOT NULL UNIQUE,
                    `ip_address` VARCHAR(45) NOT NULL,
                    `device_type` VARCHAR(50) NULL,
                    `operating_system` VARCHAR(50) NULL,
                    `browser` VARCHAR(50) NULL,
                    `isp` VARCHAR(100) NULL,
                    `as_name` VARCHAR(100) NULL,
                    `country` VARCHAR(50) NULL,
                    `city` VARCHAR(100) NULL,
                    `raw_user_agent` VARCHAR(255) NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`registration_id`) REFERENCES `event_registrations` (`id`) ON DELETE CASCADE
                );
            ");

            // Check columns in event_registrations
            $cols = $this->getSqliteColumns($pdo, 'event_registrations');
            if (!in_array('form_id', $cols, true)) {
                $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `form_id` INTEGER NULL;");
            }
            if (!in_array('custom_data', $cols, true)) {
                $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `custom_data` TEXT NULL;");
            }
            if (!in_array('phone_normalized', $cols, true)) {
                $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `phone_normalized` VARCHAR(30) NULL;");
            }
            if (!in_array('country_code', $cols, true)) {
                $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `country_code` VARCHAR(10) NOT NULL DEFAULT '+91';");
            }
            if (!in_array('photo_path', $cols, true)) {
                $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `photo_path` VARCHAR(255) NULL;");
            }
            if (!in_array('checkin_latitude', $cols, true)) {
                $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `checkin_latitude` DECIMAL(10, 8) NULL;");
            }
            if (!in_array('checkin_longitude', $cols, true)) {
                $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `checkin_longitude` DECIMAL(11, 8) NULL;");
            }
            if (!in_array('checkin_distance_meters', $cols, true)) {
                $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `checkin_distance_meters` DECIMAL(10, 2) NULL;");
            }
            if (!in_array('checkin_geofence_verified', $cols, true)) {
                $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `checkin_geofence_verified` INTEGER NOT NULL DEFAULT 0;");
            }

            // Backfill phone_normalized
            $this->backfillPhones($pdo);

            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS `uk_event_phone_unique` ON `event_registrations` (`event_id`, `phone_normalized`);");
            return;
        }

        // MySQL / MariaDB Execution:
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `registration_metadata` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `registration_id` BIGINT UNSIGNED NOT NULL,
                `ip_address` VARCHAR(45) NOT NULL,
                `device_type` VARCHAR(50) NULL DEFAULT NULL,
                `operating_system` VARCHAR(50) NULL DEFAULT NULL,
                `browser` VARCHAR(50) NULL DEFAULT NULL,
                `isp` VARCHAR(100) NULL DEFAULT NULL,
                `as_name` VARCHAR(100) NULL DEFAULT NULL,
                `country` VARCHAR(50) NULL DEFAULT NULL,
                `city` VARCHAR(100) NULL DEFAULT NULL,
                `raw_user_agent` VARCHAR(255) NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_reg_meta_registration` (`registration_id`),
                INDEX `idx_reg_meta_ip` (`ip_address`),
                INDEX `idx_reg_meta_device` (`device_type`, `browser`),
                CONSTRAINT `fk_reg_meta_registration` FOREIGN KEY (`registration_id`) REFERENCES `event_registrations` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        if (!$this->hasColumn($pdo, 'event_registrations', 'form_id')) {
            $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `form_id` INT UNSIGNED NULL DEFAULT NULL AFTER `event_id`;");
            $pdo->exec("ALTER TABLE `event_registrations` ADD CONSTRAINT `fk_event_reg_form` FOREIGN KEY (`form_id`) REFERENCES `event_forms` (`id`) ON DELETE SET NULL;");
        }
        if (!$this->hasColumn($pdo, 'event_registrations', 'custom_data')) {
            $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `custom_data` JSON NULL DEFAULT NULL AFTER `participant_id`;");
        }
        if (!$this->hasColumn($pdo, 'event_registrations', 'phone_normalized')) {
            $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `phone_normalized` VARCHAR(30) NULL DEFAULT NULL AFTER `custom_data`;");
        }
        if (!$this->hasColumn($pdo, 'event_registrations', 'country_code')) {
            $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `country_code` VARCHAR(10) NOT NULL DEFAULT '+91' AFTER `phone_normalized`;");
        }
        if (!$this->hasColumn($pdo, 'event_registrations', 'photo_path')) {
            $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `photo_path` VARCHAR(255) NULL DEFAULT NULL AFTER `country_code`;");
        }
        if (!$this->hasColumn($pdo, 'event_registrations', 'checkin_latitude')) {
            $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `checkin_latitude` DECIMAL(10, 8) NULL DEFAULT NULL AFTER `check_in_method`;");
        }
        if (!$this->hasColumn($pdo, 'event_registrations', 'checkin_longitude')) {
            $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `checkin_longitude` DECIMAL(11, 8) NULL DEFAULT NULL AFTER `checkin_latitude`;");
        }
        if (!$this->hasColumn($pdo, 'event_registrations', 'checkin_distance_meters')) {
            $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `checkin_distance_meters` DECIMAL(10, 2) NULL DEFAULT NULL AFTER `checkin_longitude`;");
        }
        if (!$this->hasColumn($pdo, 'event_registrations', 'checkin_geofence_verified')) {
            $pdo->exec("ALTER TABLE `event_registrations` ADD COLUMN `checkin_geofence_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `checkin_distance_meters`;");
        }

        // Expand check_in_method ENUM to support V2 mobile verification & mobile web check-ins
        $pdo->exec("ALTER TABLE `event_registrations` MODIFY `check_in_method` ENUM('admin_manual', 'qr_scan', 'self_verified', 'mobile_verification', 'mobile_web') NULL DEFAULT NULL;");

        // Backfill phone_normalized from participants
        $this->backfillPhones($pdo);

        // Add UNIQUE constraint on (event_id, phone_normalized)
        if (!$this->hasIndex($pdo, 'event_registrations', 'uk_event_phone_unique')) {
            $pdo->exec("ALTER TABLE `event_registrations` ADD UNIQUE KEY `uk_event_phone_unique` (`event_id`, `phone_normalized`);");
        }
    }

    public function down(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("DROP TABLE IF EXISTS `registration_metadata`;");
            $pdo->exec("DROP INDEX IF EXISTS `uk_event_phone_unique`;");
            return;
        }

        $pdo->exec("DROP TABLE IF EXISTS `registration_metadata`;");

        if ($this->hasIndex($pdo, 'event_registrations', 'uk_event_phone_unique')) {
            $pdo->exec("ALTER TABLE `event_registrations` DROP INDEX `uk_event_phone_unique`;");
        }
        if ($this->hasColumn($pdo, 'event_registrations', 'checkin_geofence_verified')) {
            $pdo->exec("ALTER TABLE `event_registrations` DROP COLUMN `checkin_geofence_verified`;");
        }
        if ($this->hasColumn($pdo, 'event_registrations', 'checkin_distance_meters')) {
            $pdo->exec("ALTER TABLE `event_registrations` DROP COLUMN `checkin_distance_meters`;");
        }
        if ($this->hasColumn($pdo, 'event_registrations', 'checkin_longitude')) {
            $pdo->exec("ALTER TABLE `event_registrations` DROP COLUMN `checkin_longitude`;");
        }
        if ($this->hasColumn($pdo, 'event_registrations', 'checkin_latitude')) {
            $pdo->exec("ALTER TABLE `event_registrations` DROP COLUMN `checkin_latitude`;");
        }
        if ($this->hasColumn($pdo, 'event_registrations', 'photo_path')) {
            $pdo->exec("ALTER TABLE `event_registrations` DROP COLUMN `photo_path`;");
        }
        if ($this->hasColumn($pdo, 'event_registrations', 'country_code')) {
            $pdo->exec("ALTER TABLE `event_registrations` DROP COLUMN `country_code`;");
        }
        if ($this->hasColumn($pdo, 'event_registrations', 'phone_normalized')) {
            $pdo->exec("ALTER TABLE `event_registrations` DROP COLUMN `phone_normalized`;");
        }
        if ($this->hasColumn($pdo, 'event_registrations', 'custom_data')) {
            $pdo->exec("ALTER TABLE `event_registrations` DROP COLUMN `custom_data`;");
        }
        if ($this->hasForeignKey($pdo, 'event_registrations', 'fk_event_reg_form')) {
            $pdo->exec("ALTER TABLE `event_registrations` DROP FOREIGN KEY `fk_event_reg_form`;");
        }
        if ($this->hasColumn($pdo, 'event_registrations', 'form_id')) {
            $pdo->exec("ALTER TABLE `event_registrations` DROP COLUMN `form_id`;");
        }
    }

    private function backfillPhones(PDO $pdo): void
    {
        $stmt = $pdo->query("
            SELECT er.id AS reg_id, p.phone 
            FROM `event_registrations` er
            JOIN `participants` p ON er.participant_id = p.id
            WHERE er.phone_normalized IS NULL AND p.phone IS NOT NULL
        ");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $upd = $pdo->prepare("UPDATE `event_registrations` SET `phone_normalized` = :phone WHERE `id` = :id");

        foreach ($rows as $r) {
            $cleaned = preg_replace('/[^\d]/', '', (string) $r['phone']);
            if (!empty($cleaned)) {
                // If 10 digits without leading country code, prefix +91
                $normalized = strlen($cleaned) === 10 ? '+91' . $cleaned : '+' . $cleaned;
                $upd->execute([':phone' => $normalized, ':id' => $r['reg_id']]);
            }
        }
    }

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1");
        $stmt->execute([':t' => $table, ':c' => $column]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasIndex(PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i LIMIT 1");
        $stmt->execute([':t' => $table, ':i' => $index]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasForeignKey(PDO $pdo, string $table, string $fk): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :t AND CONSTRAINT_NAME = :fk AND CONSTRAINT_TYPE = 'FOREIGN KEY' LIMIT 1");
        $stmt->execute([':t' => $table, ':fk' => $fk]);
        return (bool) $stmt->fetchColumn();
    }

    private function getSqliteColumns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->query("PRAGMA table_info(`{$table}`);");
        $cols = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols[] = $row['name'];
        }
        return $cols;
    }
};
