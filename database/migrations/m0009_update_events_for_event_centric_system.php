<?php

declare(strict_types=1);

/**
 * Migration: m0009_update_events_for_event_centric_system
 * Purpose: Transitions Events to the primary operational entity by:
 *          - Making campaign_id NULLABLE (with ON DELETE SET NULL)
 *          - Adding event_type (online, offline, hybrid)
 *          - Adding collaboration fields (collaboration_with, collaboration_logo)
 *          - Adding timezone configuration (default Asia/Kolkata)
 *          - Adding checkin timing configuration (checkin_start_date, checkin_start_time)
 *          - Adding server-side geofencing fields (latitude, longitude, geofence_radius_meters)
 *          - Enforcing global unique slug on events
 */
return new class {
    public function up(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            // Check and add columns if they do not exist
            $columns = $this->getSqliteColumns($pdo, 'events');
            
            if (!in_array('event_type', $columns, true)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `event_type` VARCHAR(20) NOT NULL DEFAULT 'offline';");
            }
            if (!in_array('collaboration_with', $columns, true)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `collaboration_with` VARCHAR(255) NULL;");
            }
            if (!in_array('collaboration_logo', $columns, true)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `collaboration_logo` VARCHAR(255) NULL;");
            }
            if (!in_array('timezone', $columns, true)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `timezone` VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata';");
            }
            if (!in_array('checkin_start_date', $columns, true)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `checkin_start_date` DATE NULL;");
            }
            if (!in_array('checkin_start_time', $columns, true)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `checkin_start_time` TIME NULL;");
            }
            if (!in_array('latitude', $columns, true)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `latitude` DECIMAL(10, 8) NULL;");
            }
            if (!in_array('longitude', $columns, true)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `longitude` DECIMAL(11, 8) NULL;");
            }
            if (!in_array('geofence_radius_meters', $columns, true)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `geofence_radius_meters` INTEGER NULL;");
            }

            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS `uk_events_slug` ON `events` (`slug`);");
            return;
        }

        // MySQL / MariaDB Execution:
        // 1. Make campaign_id nullable and update foreign key to SET NULL
        if ($this->hasForeignKey($pdo, 'events', 'fk_events_campaign_id')) {
            $pdo->exec("ALTER TABLE `events` DROP FOREIGN KEY `fk_events_campaign_id`;");
        }
        $pdo->exec("ALTER TABLE `events` MODIFY `campaign_id` INT UNSIGNED NULL DEFAULT NULL;");
        $pdo->exec("ALTER TABLE `events` ADD CONSTRAINT `fk_events_campaign_id` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL;");

        // 2. Add event_type enum if missing
        if (!$this->hasColumn($pdo, 'events', 'event_type')) {
            $pdo->exec("ALTER TABLE `events` ADD COLUMN `event_type` ENUM('online', 'offline', 'hybrid') NOT NULL DEFAULT 'offline' AFTER `category`;");
        }

        // 3. Add collaboration fields
        if (!$this->hasColumn($pdo, 'events', 'collaboration_with')) {
            $pdo->exec("ALTER TABLE `events` ADD COLUMN `collaboration_with` VARCHAR(255) NULL DEFAULT NULL AFTER `title`;");
        }
        if (!$this->hasColumn($pdo, 'events', 'collaboration_logo')) {
            $pdo->exec("ALTER TABLE `events` ADD COLUMN `collaboration_logo` VARCHAR(255) NULL DEFAULT NULL AFTER `collaboration_with`;");
        }

        // 4. Add timezone
        if (!$this->hasColumn($pdo, 'events', 'timezone')) {
            $pdo->exec("ALTER TABLE `events` ADD COLUMN `timezone` VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata' AFTER `venue_address`;");
        }

        // 5. Add check-in timing
        if (!$this->hasColumn($pdo, 'events', 'checkin_start_date')) {
            $pdo->exec("ALTER TABLE `events` ADD COLUMN `checkin_start_date` DATE NULL DEFAULT NULL AFTER `timezone`;");
        }
        if (!$this->hasColumn($pdo, 'events', 'checkin_start_time')) {
            $pdo->exec("ALTER TABLE `events` ADD COLUMN `checkin_start_time` TIME NULL DEFAULT NULL AFTER `checkin_start_date`;");
        }

        // 6. Add geofencing fields
        if (!$this->hasColumn($pdo, 'events', 'latitude')) {
            $pdo->exec("ALTER TABLE `events` ADD COLUMN `latitude` DECIMAL(10, 8) NULL DEFAULT NULL AFTER `checkin_start_time`;");
        }
        if (!$this->hasColumn($pdo, 'events', 'longitude')) {
            $pdo->exec("ALTER TABLE `events` ADD COLUMN `longitude` DECIMAL(11, 8) NULL DEFAULT NULL AFTER `latitude`;");
        }
        if (!$this->hasColumn($pdo, 'events', 'geofence_radius_meters')) {
            $pdo->exec("ALTER TABLE `events` ADD COLUMN `geofence_radius_meters` INT UNSIGNED NULL DEFAULT NULL AFTER `longitude`;");
        }

        // 7. Enforce unique slug on events
        if (!$this->hasIndex($pdo, 'events', 'uk_events_slug')) {
            $pdo->exec("ALTER TABLE `events` ADD UNIQUE KEY `uk_events_slug` (`slug`);");
        }
    }

    public function down(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("DROP INDEX IF EXISTS `uk_events_slug`;");
            return;
        }

        if ($this->hasIndex($pdo, 'events', 'uk_events_slug')) {
            $pdo->exec("ALTER TABLE `events` DROP INDEX `uk_events_slug`;");
        }
        if ($this->hasColumn($pdo, 'events', 'geofence_radius_meters')) {
            $pdo->exec("ALTER TABLE `events` DROP COLUMN `geofence_radius_meters`;");
        }
        if ($this->hasColumn($pdo, 'events', 'longitude')) {
            $pdo->exec("ALTER TABLE `events` DROP COLUMN `longitude`;");
        }
        if ($this->hasColumn($pdo, 'events', 'latitude')) {
            $pdo->exec("ALTER TABLE `events` DROP COLUMN `latitude`;");
        }
        if ($this->hasColumn($pdo, 'events', 'checkin_start_time')) {
            $pdo->exec("ALTER TABLE `events` DROP COLUMN `checkin_start_time`;");
        }
        if ($this->hasColumn($pdo, 'events', 'checkin_start_date')) {
            $pdo->exec("ALTER TABLE `events` DROP COLUMN `checkin_start_date`;");
        }
        if ($this->hasColumn($pdo, 'events', 'timezone')) {
            $pdo->exec("ALTER TABLE `events` DROP COLUMN `timezone`;");
        }
        if ($this->hasColumn($pdo, 'events', 'collaboration_logo')) {
            $pdo->exec("ALTER TABLE `events` DROP COLUMN `collaboration_logo`;");
        }
        if ($this->hasColumn($pdo, 'events', 'collaboration_with')) {
            $pdo->exec("ALTER TABLE `events` DROP COLUMN `collaboration_with`;");
        }
        if ($this->hasColumn($pdo, 'events', 'event_type')) {
            $pdo->exec("ALTER TABLE `events` DROP COLUMN `event_type`;");
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
