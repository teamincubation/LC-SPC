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
            return;
        }

        // MySQL 8.0+: Virtual generated column with unique constraint
        // Step 1: Ensure supporting index for fk_cert_registration exists BEFORE dropping uk_cert_reg_type
        if (!$this->hasIndex($pdo, 'certificates', 'idx_cert_registration')) {
            $pdo->exec("ALTER TABLE `certificates` ADD KEY `idx_cert_registration` (`registration_id`);");
        }

        // Step 2: Drop the unconditional unique key (only if still present)
        if ($this->hasIndex($pdo, 'certificates', 'uk_cert_reg_type')) {
            $pdo->exec("ALTER TABLE `certificates` DROP INDEX `uk_cert_reg_type`;");
        }

        // Step 3: Add virtual column active_type: type when status='active', NULL when status='revoked'
        if (!$this->hasColumn($pdo, 'certificates', 'active_type')) {
            $pdo->exec("ALTER TABLE `certificates` ADD COLUMN `active_type` VARCHAR(30) GENERATED ALWAYS AS (CASE WHEN `status` = 'active' THEN `type` ELSE NULL END) VIRTUAL;");
        }

        // Step 4: Add unique key on (registration_id, active_type)
        if (!$this->hasIndex($pdo, 'certificates', 'uk_cert_active_type')) {
            $pdo->exec("ALTER TABLE `certificates` ADD UNIQUE KEY `uk_cert_active_type` (`registration_id`, `active_type`);");
        }
    }

    public function down(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Pre-check for duplicate historical rows that would prevent unconditional UNIQUE restoration
        $stmt = $pdo->query("SELECT `registration_id`, `type`, COUNT(*) AS `c` FROM `certificates` GROUP BY `registration_id`, `type` HAVING `c` > 1 LIMIT 1");
        $duplicate = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if ($duplicate) {
            throw new RuntimeException(
                "Cannot rollback migration m0008: historical duplicate certificates exist for registration ID " .
                $duplicate['registration_id'] . " and type '{$duplicate['type']}'. " .
                "Restoring unconditional UNIQUE(registration_id, type) would cause a constraint violation. " .
                "Rollback safely aborted to prevent data loss."
            );
        }

        if ($driver === 'sqlite') {
            $pdo->exec("DROP INDEX IF EXISTS `uk_cert_active_type`;");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS `uk_cert_reg_type` ON `certificates` (`registration_id`, `type`);");
            return;
        }

        // MySQL 8.0+ Rollback Sequence:
        // Step 1: Drop status-aware unique key
        if ($this->hasIndex($pdo, 'certificates', 'uk_cert_active_type')) {
            $pdo->exec("ALTER TABLE `certificates` DROP INDEX `uk_cert_active_type`;");
        }

        // Step 2: Drop active_type virtual column
        if ($this->hasColumn($pdo, 'certificates', 'active_type')) {
            $pdo->exec("ALTER TABLE `certificates` DROP COLUMN `active_type`;");
        }

        // Step 3: Restore unconditional unique key uk_cert_reg_type
        if (!$this->hasIndex($pdo, 'certificates', 'uk_cert_reg_type')) {
            $pdo->exec("ALTER TABLE `certificates` ADD UNIQUE KEY `uk_cert_reg_type` (`registration_id`, `type`);");
        }

        // Step 4: Only after uk_cert_reg_type is restored (which supports the FK), drop auxiliary index
        if ($this->hasIndex($pdo, 'certificates', 'idx_cert_registration')) {
            $pdo->exec("ALTER TABLE `certificates` DROP KEY `idx_cert_registration`;");
        }
    }

    private function hasIndex(PDO $pdo, string $table, string $indexName): bool
    {
        $stmt = $pdo->prepare("
            SELECT 1 FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = :table_name 
              AND INDEX_NAME = :index_name 
            LIMIT 1
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':index_name' => $indexName,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasColumn(PDO $pdo, string $table, string $columnName): bool
    {
        $stmt = $pdo->prepare("
            SELECT 1 FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = :table_name 
              AND COLUMN_NAME = :column_name 
            LIMIT 1
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $columnName,
        ]);
        return (bool) $stmt->fetchColumn();
    }
};
