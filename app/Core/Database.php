<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Database Management Class
 * Encapsulates PDO with prepared statements, transaction management, and safe UTF-8MB4 connection.
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Get or initialize the shared PDO instance.
     *
     * @throws RuntimeException if connection fails.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = Config::get('database.host', '127.0.0.1');
        $port = (int) Config::get('database.port', 3306);
        $db = Config::get('database.database', 'u806388046_LC');
        $charset = Config::get('database.charset', 'utf8mb4');
        $username = Config::get('database.username', 'u806388046_LC_SPC');
        $password = (string) Config::get('database.password', '');
        $options = Config::get('database.options', []);

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

        try {
            self::$instance = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // Log the error securely without leaking passwords
            Logger::error('Database connection failed: ' . $e->getMessage());

            if (Config::get('app.debug', false)) {
                throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
            }

            throw new RuntimeException('Database connection could not be established. Please check system configuration.');
        }

        return self::$instance;
    }

    /**
     * Execute a query with prepared statement parameters and return the statement.
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row as an associative array.
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Fetch all matching rows as an array of associative arrays.
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE query and return the number of affected rows.
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Return the ID of the last inserted row.
     */
    public static function lastInsertId(?string $name = null): string
    {
        return self::getConnection()->lastInsertId($name);
    }

    /**
     * Begin a database transaction.
     */
    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    /**
     * Alias for beginTransaction().
     */
    public static function begin(): bool
    {
        return self::beginTransaction();
    }

    /**
     * Commit the current transaction.
     */
    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    /**
     * Rollback the current transaction.
     */
    public static function rollback(): bool
    {
        if (self::getConnection()->inTransaction()) {
            return self::getConnection()->rollBack();
        }
        return false;
    }

    /**
     * Execute a callback inside a database transaction with automatic commit and rollback.
     *
     * @param callable $callback function(PDO $pdo): mixed
     * @return mixed result of the callback
     */
    public static function transaction(callable $callback): mixed
    {
        self::begin();
        try {
            $result = $callback(self::getConnection());
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollback();
            throw $e;
        }
    }

    /**
     * Check if a database connection can be established without throwing an unhandled exception.
     */
    public static function isConnected(): bool
    {
        try {
            self::getConnection()->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Safe connectivity test returning status without credentials.
     */
    public static function checkHealth(): array
    {
        try {
            $start = microtime(true);
            self::getConnection()->query('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'connected',
                'latency_ms' => $latency,
                'charset' => Config::get('database.charset', 'utf8mb4'),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'disconnected',
                'message' => Config::get('app.debug') ? $e->getMessage() : 'Database unreachable',
            ];
        }
    }

    /**
     * Reset the connection instance (useful for testing or reconnecting).
     */
    public static function disconnect(): void
    {
        self::$instance = null;
    }
}
