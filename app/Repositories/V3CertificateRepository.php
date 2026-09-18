<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Dedicated Repository for Certificate Platform V3 (v3_certificates table)
 * Completely isolated from legacy certificates table.
 */
class V3CertificateRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find certificate by internal ID.
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT c.*, t.name as template_name, t.layout_config, t.background_image_path,
                       t.seal_image_path, t.signature1_image_path, t.signature1_name, t.signature1_designation,
                       t.signature2_image_path, t.signature2_name, t.signature2_designation
                FROM `v3_certificates` c
                LEFT JOIN `v3_certificate_templates` t ON c.template_id = t.id
                WHERE c.id = :id AND c.deleted_at IS NULL
                LIMIT 1";

        return $this->fetchOne($sql, [':id' => $id]);
    }

    /**
     * Find certificate by 256-bit verification token.
     */
    public function findByToken(string $token): ?array
    {
        $sql = "SELECT c.*, t.name as template_name, t.layout_config, t.background_image_path,
                       t.seal_image_path, t.signature1_image_path, t.signature1_name, t.signature1_designation,
                       t.signature2_image_path, t.signature2_name, t.signature2_designation
                FROM `v3_certificates` c
                LEFT JOIN `v3_certificate_templates` t ON c.template_id = t.id
                WHERE c.verification_token = :token AND c.deleted_at IS NULL
                LIMIT 1";

        return $this->fetchOne($sql, [':token' => trim($token)]);
    }

    /**
     * Find certificate by public Certificate ID.
     */
    public function findByCertificateId(string $certId): ?array
    {
        $sql = "SELECT c.*, t.name as template_name
                FROM `v3_certificates` c
                LEFT JOIN `v3_certificate_templates` t ON c.template_id = t.id
                WHERE c.certificate_id = :cid AND c.deleted_at IS NULL
                LIMIT 1";

        return $this->fetchOne($sql, [':cid' => trim($certId)]);
    }

    /**
     * Public Phone Search: MUST return ONLY active, non-revoked certificates.
     * Never returns invalid or revoked certificates to public phone lookup.
     */
    public function findActiveByPhone(string $normalizedPhone): array
    {
        $sql = "SELECT c.id, c.certificate_id, c.verification_token, c.name, c.event_title,
                       c.date, c.event_type, c.status, c.created_at
                FROM `v3_certificates` c
                WHERE c.phone_normalized = :phone
                  AND c.status = 'active'
                  AND c.deleted_at IS NULL
                ORDER BY c.created_at DESC";

        return $this->fetchAll($sql, [':phone' => trim($normalizedPhone)]);
    }

    /**
     * Search certificates for administrative table with pagination and filtering.
     */
    public function search(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $where = ["c.deleted_at IS NULL"];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "c.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['template_id'])) {
            $where[] = "c.template_id = :tid";
            $params[':tid'] = (int) $filters['template_id'];
        }

        if (!empty($filters['date'])) {
            $where[] = "c.date LIKE :dt";
            $params[':dt'] = '%' . $filters['date'] . '%';
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $where[] = "(c.certificate_id LIKE :s1 OR c.name LIKE :s2 OR c.phone LIKE :s3 OR c.event_title LIKE :s4)";
            $params[':s1'] = $term;
            $params[':s2'] = $term;
            $params[':s3'] = $term;
            $params[':s4'] = $term;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT c.*, t.name as template_name
                FROM `v3_certificates` c
                LEFT JOIN `v3_certificate_templates` t ON c.template_id = t.id
                WHERE {$whereClause}
                ORDER BY c.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Count total matching certificates for search pagination.
     */
    public function countSearch(array $filters = []): int
    {
        $where = ["c.deleted_at IS NULL"];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "c.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['template_id'])) {
            $where[] = "c.template_id = :tid";
            $params[':tid'] = (int) $filters['template_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $where[] = "(c.certificate_id LIKE :s1 OR c.name LIKE :s2 OR c.phone LIKE :s3 OR c.event_title LIKE :s4)";
            $params[':s1'] = $term;
            $params[':s2'] = $term;
            $params[':s3'] = $term;
            $params[':s4'] = $term;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) as cnt FROM `v3_certificates` c WHERE {$whereClause}";

        $row = $this->fetchOne($sql, $params);
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Invalidate / Revoke certificate with reason and audit timestamp.
     */
    public function invalidate(int $id, ?int $userId, string $reason): bool
    {
        $sql = "UPDATE `v3_certificates`
                SET `status` = 'invalid',
                    `invalidated_at` = NOW(),
                    `invalidated_by` = :uid1,
                    `invalidation_reason` = :reason,
                    `revoked_at` = NOW(),
                    `revoked_by` = :uid2,
                    `updated_at` = NOW()
                WHERE `id` = :id";

        return $this->execute($sql, [
            ':id'     => $id,
            ':uid1'   => $userId,
            ':reason' => $reason,
            ':uid2'   => $userId,
        ]) > 0;
    }

    /**
     * Soft delete / Archive certificate.
     */
    public function softDelete(int $id, ?int $userId): bool
    {
        $sql = "UPDATE `v3_certificates`
                SET `deleted_at` = NOW(),
                    `status` = 'archived',
                    `updated_at` = NOW()
                WHERE `id` = :id";

        return $this->execute($sql, [':id' => $id]) > 0;
    }

    /**
     * Fetch KPI dashboard metrics.
     */
    public function getDashboardMetrics(): array
    {
        $totalCerts = (int) ($this->fetchOne("SELECT COUNT(*) as c FROM `v3_certificates` WHERE deleted_at IS NULL")['c'] ?? 0);
        $validCerts = (int) ($this->fetchOne("SELECT COUNT(*) as c FROM `v3_certificates` WHERE status = 'active' AND deleted_at IS NULL")['c'] ?? 0);
        $invalidCerts = (int) ($this->fetchOne("SELECT COUNT(*) as c FROM `v3_certificates` WHERE status IN ('invalid', 'revoked') AND deleted_at IS NULL")['c'] ?? 0);
        $templatesCount = (int) ($this->fetchOne("SELECT COUNT(*) as c FROM `v3_certificate_templates` WHERE status = 'active'")['c'] ?? 0);

        $todayStart = date('Y-m-d 00:00:00');
        $todayCerts = (int) ($this->fetchOne("SELECT COUNT(*) as c FROM `v3_certificates` WHERE created_at >= :ts AND deleted_at IS NULL", [':ts' => $todayStart])['c'] ?? 0);

        $recentBatches = $this->fetchAll(
            "SELECT b.*, t.name as template_name
             FROM `v3_certificate_batches` b
             LEFT JOIN `v3_certificate_templates` t ON b.template_id = t.id
             ORDER BY b.id DESC
             LIMIT 5"
        );

        return [
            'total_certificates'     => $totalCerts,
            'valid_certificates'     => $validCerts,
            'invalid_certificates'   => $invalidCerts,
            'templates_count'        => $templatesCount,
            'generated_today'        => $todayCerts,
            'recent_batches'         => $recentBatches,
        ];
    }

    private function fetchOne(string $sql, array $params = []): ?array
    {
        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        }
        return Database::fetch($sql, $params);
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        return Database::fetchAll($sql, $params);
    }

    private function execute(string $sql, array $params = []): int
    {
        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        }
        return Database::execute($sql, $params);
    }
}
