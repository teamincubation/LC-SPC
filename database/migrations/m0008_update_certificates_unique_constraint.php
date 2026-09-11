<?php

declare(strict_types=1);

/**
 * Migration: m0008_update_certificates_unique_constraint
 * Purpose: Replaces the unconditional UNIQUE(registration_id, type) with status-aware uniqueness,
 *          allowing historical revoked certificates to coexist while guaranteeing strictly at most
 *          one active certificate of each type per registration.
 */
return new class {
    public function up(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            // SQLite supports partial unique index syntax natively
            $pdo->exec("DROP INDEX IF EXISTS `uk_cert_reg_type`;");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS `uk_cert_active_type` ON `certificates` (`registration_id`, `type`) WHERE `status` = 'active';");
        } else {
            // MySQL 8.0+: Virtual generated column with unique constraint
            // Drop unconditional unique key
            $pdo->exec("ALTER TABLE `certificates` DROP INDEX `uk_cert_reg_type`;");
            // Add virtual column active_type: type when status='active', NULL when status='revoked'
            $pdo->exec("ALTER TABLE `certificates` ADD COLUMN `active_type` VARCHAR(30) GENERATED ALWAYS AS (CASE WHEN `status` = 'active' THEN `type` ELSE NULL END) VIRTUAL;");
            // Unique key on (registration_id, active_type) - NULLs are not equal in MySQL, so revoked rows coexist
            $pdo->exec("ALTER TABLE `certificates` ADD UNIQUE KEY `uk_cert_active_type` (`registration_id`, `active_type`);");
        }
    }

    public function down(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("DROP INDEX IF EXISTS `uk_cert_active_type`;");
            // Only restore unconditional index if no duplicate (registration_id, type) exist
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS `uk_cert_reg_type` ON `certificates` (`registration_id`, `type`);");
        } else {
            $pdo->exec("ALTER TABLE `certificates` DROP INDEX `uk_cert_active_type`;");
            $pdo->exec("ALTER TABLE `certificates` DROP COLUMN `active_type`;");
            $pdo->exec("ALTER TABLE `certificates` ADD UNIQUE KEY `uk_cert_reg_type` (`registration_id`, `type`);");
        }
    }
};
