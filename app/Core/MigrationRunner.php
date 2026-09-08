<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Migration Runner for Database Schema Versioning
 * Executes and tracks chronological database schema migrations.
 * Treated as the authoritative source of database structure.
 */
class MigrationRunner
{
    private PDO $pdo;
    private string $migrationsDir;

    public function __construct(?PDO $pdo = null, ?string $migrationsDir = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->migrationsDir = $migrationsDir ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    }

    /**
     * Ensure the migrations tracking table exists.
     */
    public function ensureMigrationTable(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `migration` VARCHAR(255) NOT NULL UNIQUE,
                `batch` INTEGER NOT NULL,
                `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL UNIQUE,
                `batch` INT UNSIGNED NOT NULL,
                `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        }

        $this->pdo->exec($sql);
    }

    /**
     * Retrieve list of executed migration filenames.
     */
    public function getExecutedMigrations(): array
    {
        $this->ensureMigrationTable();
        $stmt = $this->pdo->query("SELECT `migration` FROM `migrations` ORDER BY `id` ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Get the next batch number.
     */
    public function getNextBatchNumber(): int
    {
        $this->ensureMigrationTable();
        $stmt = $this->pdo->query("SELECT MAX(`batch`) as max_batch FROM `migrations`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row['max_batch'] !== null) ? ((int) $row['max_batch'] + 1) : 1;
    }

    /**
     * Run all pending migrations.
     *
     * @return array List of executed migration names
     */
    public function migrate(): array
    {
        $this->ensureMigrationTable();
        $executed = $this->getExecutedMigrations();
        $allFiles = $this->getMigrationFiles();

        $pending = array_diff($allFiles, $executed);
        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatchNumber();
        $ran = [];

        foreach ($pending as $file) {
            $filePath = $this->migrationsDir . DIRECTORY_SEPARATOR . $file;
            $migration = require $filePath;

            if (!is_object($migration) || !method_exists($migration, 'up')) {
                throw new RuntimeException("Migration [{$file}] must return an anonymous class implementing an up(PDO \$pdo) method.");
            }

            try {
                $migration->up($this->pdo);

                $stmt = $this->pdo->prepare("INSERT INTO `migrations` (`migration`, `batch`) VALUES (:migration, :batch)");
                $stmt->execute([
                    ':migration' => $file,
                    ':batch' => $batch,
                ]);

                $ran[] = $file;
                Logger::info("Migration executed successfully: {$file}");
            } catch (Throwable $e) {
                Logger::error("Migration failed [{$file}]: " . $e->getMessage());
                throw new RuntimeException("Migration [{$file}] failed: " . $e->getMessage(), (int) $e->getCode(), $e);
            }
        }

        return $ran;
    }

    /**
     * Rollback the last migration batch.
     *
     * @return array List of rolled-back migration names
     */
    public function rollback(): array
    {
        $this->ensureMigrationTable();
        $stmt = $this->pdo->query("SELECT MAX(`batch`) as max_batch FROM `migrations`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastBatch = $row['max_batch'] !== null ? (int) $row['max_batch'] : null;

        if ($lastBatch === null) {
            return [];
        }

        $stmt = $this->pdo->prepare("SELECT `migration` FROM `migrations` WHERE `batch` = :batch ORDER BY `id` DESC");
        $stmt->execute([':batch' => $lastBatch]);
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $rolledBack = [];
        foreach ($migrations as $file) {
            $filePath = $this->migrationsDir . DIRECTORY_SEPARATOR . $file;
            if (file_exists($filePath)) {
                $migration = require $filePath;
                if (is_object($migration) && method_exists($migration, 'down')) {
                    $migration->down($this->pdo);
                }
            }

            $delStmt = $this->pdo->prepare("DELETE FROM `migrations` WHERE `migration` = :migration");
            $delStmt->execute([':migration' => $file]);

            $rolledBack[] = $file;
            Logger::info("Migration rolled back: {$file}");
        }

        return $rolledBack;
    }

    /**
     * Return migration status information.
     */
    public function status(): array
    {
        $this->ensureMigrationTable();
        $executed = $this->getExecutedMigrations();
        $allFiles = $this->getMigrationFiles();

        $status = [];
        foreach ($allFiles as $file) {
            $status[] = [
                'migration' => $file,
                'ran' => in_array($file, $executed, true),
            ];
        }

        return $status;
    }

    /**
     * Get all migration files from the directory sorted alphabetically.
     */
    private function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }

        $files = scandir($this->migrationsDir);
        if ($files === false) {
            return [];
        }

        $migrationFiles = [];
        foreach ($files as $file) {
            if (str_ends_with($file, '.php')) {
                $migrationFiles[] = $file;
            }
        }

        sort($migrationFiles);
        return $migrationFiles;
    }
}