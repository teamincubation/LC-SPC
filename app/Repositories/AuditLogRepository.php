<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Audit Log Data Access Repository
 * Implements append-only storage for the audit_logs table.
 * Strictly zero UPDATE or DELETE operations.
 */
class AuditLogRepository
{
    /**
     * Append a new audit log record.
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO `audit_logs` (
                    `actor_id`, `actor_type`, `action`, `entity_type`, `entity_id`, 
                    `ip_address`, `user_agent`, `metadata`, `created_at`
                ) VALUES (
                    :actor_id, :actor_type, :action, :entity_type, :entity_id, 
                    :ip_address, :user_agent, :metadata, :created_at
                )";

        $metadataJson = null;
        if (!empty($data['metadata'])) {
            $metadataJson = json_encode(
                $data['metadata'],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        $actorId = isset($data['actor_id']) && (int) $data['actor_id'] > 0 ? (int) $data['actor_id'] : null;
        $actorType = !empty($data['actor_type']) ? (string) $data['actor_type'] : 'admin';
        if ($actorId === null && $actorType === 'admin') {
            $actorType = 'anonymous';
        }

        Database::execute($sql, [
            ':actor_id'    => $actorId,
            ':actor_type'  => $actorType,
            ':action'      => $data['action'],
            ':entity_type' => $data['entity_type'],
            ':entity_id'   => $data['entity_id'] ?? null,
            ':ip_address'  => substr($data['ip_address'] ?? '127.0.0.1', 0, 45),
            ':user_agent'  => !empty($data['user_agent']) ? substr($data['user_agent'], 0, 255) : null,
            ':metadata'    => $metadataJson,
            ':created_at'  => $now,
        ]);

        return (int) Database::lastInsertId();
    }

    /**
     * Retrieve recent audit logs for administration view.
     */
    public function getRecent(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = "SELECT a.*, u.name AS actor_name, u.email AS actor_email, u.role AS actor_role 
                FROM `audit_logs` a
                LEFT JOIN `users` u ON a.actor_id = u.id
                ORDER BY a.id DESC 
                LIMIT {$limit}";

        return Database::fetchAll($sql);
    }

    /**
     * Retrieve audit logs by action slug.
     */
    public function findByAction(string $action, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = "SELECT a.*, u.name AS actor_name, u.email AS actor_email 
                FROM `audit_logs` a
                LEFT JOIN `users` u ON a.actor_id = u.id
                WHERE a.action = :action
                ORDER BY a.id DESC 
                LIMIT {$limit}";

        return Database::fetchAll($sql, [':action' => $action]);
    }
}
