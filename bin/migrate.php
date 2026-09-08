<?php

declare(strict_types=1);

/**
 * LC-SPC CLI Database Migration Tool
 * Usage:
 *   php bin/migrate.php migrate
 *   php bin/migrate.php rollback
 *   php bin/migrate.php status
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/app/Core/Env.php';
require_once APP_ROOT . '/app/Core/Config.php';
require_once APP_ROOT . '/app/Core/Logger.php';
require_once APP_ROOT . '/app/Core/Database.php';
require_once APP_ROOT . '/app/Database/MigrationRunner.php';

use App\Core\Config;
use App\Core\Env;
use App\Database\MigrationRunner;

// Load environment and configuration
Env::load(APP_ROOT . '/.env');
Config::load(APP_ROOT . '/config');

$command = $argv[1] ?? 'migrate';

echo "=== LC-SPC Database Migration CLI ===" . PHP_EOL;
echo "Environment: " . Config::get('app.env', 'production') . PHP_EOL;
echo "Database:    " . Config::get('database.database', 'u806388046_LC') . PHP_EOL;
echo "Host:        " . Config::get('database.host', '127.0.0.1') . PHP_EOL;
echo "--------------------------------------" . PHP_EOL;

try {
    $runner = new MigrationRunner();

    switch ($command) {
        case 'migrate':
            echo "Running pending migrations..." . PHP_EOL;
            $ran = $runner->migrate();
            if (empty($ran)) {
                echo "No pending migrations found. Database is up to date." . PHP_EOL;
            } else {
                foreach ($ran as $file) {
                    echo "  [OK] Migrated: {$file}" . PHP_EOL;
                }
                echo "Completed successfully." . PHP_EOL;
            }
            break;

        case 'rollback':
            echo "Rolling back last batch..." . PHP_EOL;
            $rolled = $runner->rollback();
            if (empty($rolled)) {
                echo "Nothing to rollback." . PHP_EOL;
            } else {
                foreach ($rolled as $file) {
                    echo "  [OK] Rolled back: {$file}" . PHP_EOL;
                }
            }
            break;

        case 'status':
            echo "Migration Status:" . PHP_EOL;
            $status = $runner->status();
            if (empty($status)) {
                echo "No migration files found." . PHP_EOL;
            } else {
                foreach ($status as $item) {
                    $statusText = $item['ran'] ? '[RAN]    ' : '[PENDING]';
                    echo "  {$statusText} {$item['migration']}" . PHP_EOL;
                }
            }
            break;

        default:
            echo "Unknown command: {$command}. Available commands: migrate, rollback, status" . PHP_EOL;
            exit(1);
    }
} catch (Throwable $e) {
    echo PHP_EOL . "[ERROR] " . $e->getMessage() . PHP_EOL;
    exit(1);
}
