<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * User Data Access Repository
 * Encapsulates all PDO operations for the users table with prepared statements.
 */
class UserRepository
{
    /**
     * Find an active, non-deleted user by email.
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM `users` WHERE `email` = :email AND `deleted_at` IS NULL LIMIT 1";
        return Database::fetch($sql, [':email' => strtolower(trim($email))]);
    }

    /**
     * Find an active, non-deleted user by primary ID.
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM `users` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1";
        return Database::fetch($sql, [':id' => $id]);
    }

    /**
     * Find user by primary ID including soft-deleted accounts (for audit forensics).
     */
    public function findByIdIncludingDeleted(int $id): ?array
    {
        $sql = "SELECT * FROM `users` WHERE `id` = :id LIMIT 1";
        return Database::fetch($sql, [':id' => $id]);
    }

    /**
     * Retrieve all active, non-deleted users eligible for event coordination.
     */
    public function getEligibleCoordinators(): array
    {
        $sql = "SELECT `id`, `name`, `email`, `role` 
                FROM `users` 
                WHERE `status` = 'active' 
                  AND `deleted_at` IS NULL 
                  AND `role` IN ('super_admin', 'coordinator', 'staff') 
                ORDER BY `name` ASC";
        return Database::fetchAll($sql);
    }

    /**
     * Increment the consecutive failed logins counter and return updated count.
     */
    public function incrementFailedLogins(int $id): int
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE `users` 
                SET `failed_logins` = `failed_logins` + 1, `updated_at` = :now 
                WHERE `id` = :id";
        Database::execute($sql, [':id' => $id, ':now' => $now]);

        $query = "SELECT `failed_logins` FROM `users` WHERE `id` = :id LIMIT 1";
        $row = Database::fetch($query, [':id' => $id]);

        return (int) ($row['failed_logins'] ?? 1);
    }

    /**
     * Temporarily lock an account for specified duration in minutes.
     */
    public function lockAccount(int $id, int $minutes = 15): void
    {
        $now = date('Y-m-d H:i:s');
        $lockedUntil = date('Y-m-d H:i:s', time() + ($minutes * 60));
        $sql = "UPDATE `users` 
                SET `locked_until` = :locked_until, `updated_at` = :now 
                WHERE `id` = :id";
        Database::execute($sql, [':id' => $id, ':locked_until' => $lockedUntil, ':now' => $now]);
    }

    /**
     * Reset failed login counter, clear lockout timestamp, and record last login time.
     */
    public function resetFailedLoginsAndTouchLastLogin(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE `users` 
                SET `failed_logins` = 0, `locked_until` = NULL, `last_login_at` = :last_login_at, `updated_at` = :updated_at 
                WHERE `id` = :id";
        Database::execute($sql, [':id' => $id, ':last_login_at' => $now, ':updated_at' => $now]);
    }

    /**
     * Clear expired lockout.
     */
    public function clearLockout(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE `users` 
                SET `failed_logins` = 0, `locked_until` = NULL, `updated_at` = :now 
                WHERE `id` = :id";
        Database::execute($sql, [':id' => $id, ':now' => $now]);
    }

    /**
     * Update password hash.
     */
    public function updatePasswordHash(int $id, string $newHash): void
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE `users` 
                SET `password_hash` = :hash, `updated_at` = :now 
                WHERE `id` = :id";
        Database::execute($sql, [':id' => $id, ':hash' => $newHash, ':now' => $now]);
    }

    /**
     * Check if an active/non-deleted email already exists.
     */
    public function emailExists(string $email): bool
    {
        $sql = "SELECT COUNT(*) FROM `users` WHERE `email` = :email AND `deleted_at` IS NULL";
        $stmt = Database::query($sql, [':email' => strtolower(trim($email))]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Count total active super_admin accounts.
     */
    public function countSuperAdmins(): int
    {
        $sql = "SELECT COUNT(*) FROM `users` 
                WHERE `role` = 'super_admin' AND `status` = 'active' AND `deleted_at` IS NULL";
        $stmt = Database::query($sql);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Insert a new user record.
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO `users` (
                    `name`, `email`, `password_hash`, `role`, `phone`, `status`, 
                    `failed_logins`, `created_at`, `updated_at`
                ) VALUES (
                    :name, :email, :password_hash, :role, :phone, :status, 
                    :failed_logins, :created_at, :updated_at
                )";

        Database::execute($sql, [
            ':name'          => trim($data['name']),
            ':email'         => strtolower(trim($data['email'])),
            ':password_hash' => $data['password_hash'],
            ':role'          => $data['role'] ?? 'staff',
            ':phone'         => !empty($data['phone']) ? trim($data['phone']) : null,
            ':status'        => $data['status'] ?? 'active',
            ':failed_logins' => $data['failed_logins'] ?? 0,
            ':created_at'    => $now,
            ':updated_at'    => $now,
        ]);

        return (int) Database::lastInsertId();
    }

    /**
     * Soft-delete a user record.
     */
    public function softDelete(int $id): bool
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE `users` SET `deleted_at` = :deleted_at, `updated_at` = :updated_at WHERE `id` = :id";
        return Database::execute($sql, [':id' => $id, ':deleted_at' => $now, ':updated_at' => $now]) > 0;
    }
}
