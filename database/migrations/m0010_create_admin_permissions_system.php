<?php

declare(strict_types=1);

/**
 * Migration: m0010_create_admin_permissions_system
 * Purpose: Creates granular permissions and user_permissions tables to support
 *          module/action-level access control beyond the 4-role hierarchy.
 */
return new class {
    public function up(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `permissions` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `name` VARCHAR(100) NOT NULL UNIQUE,
                    `module` VARCHAR(50) NOT NULL,
                    `action` VARCHAR(50) NOT NULL,
                    `description` VARCHAR(255) NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS `user_permissions` (
                    `user_id` INTEGER NOT NULL,
                    `permission_id` INTEGER NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`user_id`, `permission_id`),
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
                );
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `permissions` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL,
                    `module` VARCHAR(50) NOT NULL,
                    `action` VARCHAR(50) NOT NULL,
                    `description` VARCHAR(255) NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_permissions_name` (`name`),
                    INDEX `idx_permissions_module_action` (`module`, `action`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `user_permissions` (
                    `user_id` INT UNSIGNED NOT NULL,
                    `permission_id` INT UNSIGNED NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`user_id`, `permission_id`),
                    INDEX `idx_user_perm_permission` (`permission_id`),
                    CONSTRAINT `fk_user_perm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_user_perm_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }

        // Seed default canonical permissions across all 11 core modules
        $permissions = [
            // Dashboard
            ['dashboard.view', 'dashboard', 'view', 'View administrative dashboard metrics and summaries'],

            // Admin Management (Super Admin only)
            ['admins.view', 'admins', 'view', 'View administrative users list and details'],
            ['admins.create', 'admins', 'create', 'Create new administrative users'],
            ['admins.edit', 'admins', 'edit', 'Update admin profiles and status'],
            ['admins.delete', 'admins', 'delete', 'Deactivate or delete administrative users'],
            ['admins.manage', 'admins', 'manage', 'Assign roles, reset passwords, and configure permissions'],

            // Events
            ['events.view', 'events', 'view', 'View events and event details'],
            ['events.create', 'events', 'create', 'Create new events'],
            ['events.edit', 'events', 'edit', 'Edit event information, schedules, and geofences'],
            ['events.delete', 'events', 'delete', 'Cancel or soft-delete events'],
            ['events.export', 'events', 'export', 'Export event directories and lists'],
            ['events.manage', 'events', 'manage', 'Full operational control of event lifecycles'],

            // Event Registration Forms
            ['forms.view', 'forms', 'view', 'View event registration forms'],
            ['forms.create', 'forms', 'create', 'Create or configure registration forms'],
            ['forms.edit', 'forms', 'edit', 'Edit custom fields, banners, and form settings'],
            ['forms.delete', 'forms', 'delete', 'Delete registration forms (when permitted)'],
            ['forms.manage', 'forms', 'manage', 'Full management of form lifecycles and custom fields'],

            // Registrations
            ['registrations.view', 'registrations', 'view', 'View event registrations'],
            ['registrations.create', 'registrations', 'create', 'Manually register participants'],
            ['registrations.edit', 'registrations', 'edit', 'Update registration status and details'],
            ['registrations.delete', 'registrations', 'delete', 'Cancel or remove registrations'],
            ['registrations.export', 'registrations', 'export', 'Export registration data and passes'],
            ['registrations.manage', 'registrations', 'manage', 'Full control of registration approvals and waitlists'],

            // Check-in
            ['checkin.view', 'checkin', 'view', 'Access check-in console and view check-in status'],
            ['checkin.manage', 'checkin', 'manage', 'Verify check-ins, override geofence, and reconcile rosters'],

            // Analytics
            ['analytics.view', 'analytics', 'view', 'View registration, attendance, and geographic analytics'],
            ['analytics.export', 'analytics', 'export', 'Export analytics reports and segmentations'],

            // Certificates
            ['certificates.view', 'certificates', 'view', 'View certificates and recipient snapshots'],
            ['certificates.create', 'certificates', 'create', 'Issue individual and bulk certificates'],
            ['certificates.edit', 'certificates', 'edit', 'Design certificate templates and correct clerical names'],
            ['certificates.delete', 'certificates', 'delete', 'Revoke issued certificates'],
            ['certificates.export', 'certificates', 'export', 'Download PDF/JPG certificates and batch exports'],
            ['certificates.manage', 'certificates', 'manage', 'Full management of certificate templates and credentials'],

            // Reports
            ['reports.view', 'reports', 'view', 'View executive and operational reports'],
            ['reports.export', 'reports', 'export', 'Generate and download Final Event Reports'],

            // Form Settings (Global)
            ['form_settings.view', 'form_settings', 'view', 'View global registration form settings'],
            ['form_settings.manage', 'form_settings', 'manage', 'Configure global form defaults, WhatsApp redirects, and location rules'],

            // System Settings
            ['settings.view', 'settings', 'view', 'View system configuration and diagnostic status'],
            ['settings.manage', 'settings', 'manage', 'Modify system settings and operational parameters'],
        ];

        $sql = ($driver === 'sqlite')
            ? "INSERT OR IGNORE INTO `permissions` (`name`, `module`, `action`, `description`) VALUES (:name, :module, :action, :desc)"
            : "INSERT IGNORE INTO `permissions` (`name`, `module`, `action`, `description`) VALUES (:name, :module, :action, :desc)";
        $stmt = $pdo->prepare($sql);

        foreach ($permissions as $p) {
            $stmt->execute([
                ':name'   => $p[0],
                ':module' => $p[1],
                ':action' => $p[2],
                ':desc'   => $p[3],
            ]);
        }
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `user_permissions`;");
        $pdo->exec("DROP TABLE IF EXISTS `permissions`;");
    }
};
