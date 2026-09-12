<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Permission Repository
 * Handles querying and persistence of granular system permissions and user assignments.
 */
class PermissionRepository
{
    /**
     * Get all registered system permissions.
     */
    public function getAll(): array
    {
        return Database::fetchAll("SELECT * FROM `permissions` ORDER BY `module` ASC, `id` ASC");
    }

    /**
     * Find permission by unique name (e.g. 'events.create').
     */
    public function findByName(string $name): ?array
    {
        return Database::fetch("SELECT * FROM `permissions` WHERE `name` = :name LIMIT 1", [':name' => $name]);
    }

    /**
     * Get list of permission names assigned to a user.
     */
    public function getUserPermissionNames(int $userId): array
    {
        $sql = "
            SELECT p.name 
            FROM `permissions` p
            JOIN `user_permissions` up ON p.id = up.permission_id
            WHERE up.user_id = :user_id
        ";
        $stmt = Database::query($sql, [':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get list of permission IDs assigned to a user.
     */
    public function getUserPermissionIds(int $userId): array
    {
        $sql = "SELECT permission_id FROM `user_permissions` WHERE `user_id` = :user_id";
        $stmt = Database::query($sql, [':user_id' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Sync user permissions (replace current set with new IDs).
     */
    public function syncUserPermissions(int $userId, array $permissionIds): void
    {
        Database::query("DELETE FROM `user_permissions` WHERE `user_id` = :user_id", [':user_id' => $userId]);

        if (empty($permissionIds)) {
            return;
        }

        $insSql = "INSERT INTO `user_permissions` (`user_id`, `permission_id`) VALUES (:user_id, :permission_id)";
        foreach ($permissionIds as $permId) {
            $permId = (int) $permId;
            if ($permId > 0) {
                Database::query($insSql, [
                    ':user_id'       => $userId,
                    ':permission_id' => $permId,
                ]);
            }
        }
    }
}
