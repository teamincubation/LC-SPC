<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Participant Data Access Repository
 * Encapsulates all PDO operations for the participants table with prepared statements and server-side pagination.
 */
class ParticipantRepository
{
    /**
     * Retrieve paginated participants with server-side filtering and search.
     *
     * @param array $filters Associative array of filters ('status', 'category', 'search')
     * @param int $page Current 1-indexed page
     * @param int $perPage Items per page (clamped between 1 and 100)
     * @return array Pagination envelope with items, total, and page metadata
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = " WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $where .= " AND `status` = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $where .= " AND `category` = :category";
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['search'])) {
            $where .= " AND (`full_name` LIKE :search OR `email` LIKE :search OR `phone` LIKE :search OR `organization_name` LIKE :search)";
            $params[':search'] = '%' . trim((string) $filters['search']) . '%';
        }

        // 1. Total records count
        $countSql = "SELECT COUNT(*) AS total FROM `participants`" . $where;
        $totalRow = Database::fetch($countSql, $params);
        $total = (int) ($totalRow['total'] ?? 0);

        // 2. Fetch page items with strictly sanitized integer LIMIT and OFFSET
        $dataSql = "SELECT * FROM `participants`" . $where . " ORDER BY `id` DESC LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
        $items = Database::fetchAll($dataSql, $params);

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'last_page'   => $lastPage,
            'total_pages' => $lastPage,
            'has_more'    => $page < $lastPage,
        ];
    }

    /**
     * Retrieve all participants with optional filters without pagination (for specialized lookups/exports).
     */
    public function all(array $filters = []): array
    {
        $where = " WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $where .= " AND `status` = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $where .= " AND `category` = :category";
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['search'])) {
            $where .= " AND (`full_name` LIKE :search OR `email` LIKE :search OR `phone` LIKE :search OR `organization_name` LIKE :search)";
            $params[':search'] = '%' . trim((string) $filters['search']) . '%';
        }

        $sql = "SELECT * FROM `participants`" . $where . " ORDER BY `id` DESC";
        return Database::fetchAll($sql, $params);
    }

    /**
     * Find a participant by primary ID.
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM `participants` WHERE `id` = :id LIMIT 1";
        return Database::fetch($sql, [':id' => $id]);
    }

    /**
     * Find candidate participants sharing an email address.
     */
    public function findByEmail(string $email, int $limit = 5): array
    {
        $email = trim(strtolower($email));
        if ($email === '') {
            return [];
        }

        $sql = "SELECT * FROM `participants` WHERE `email` = :email ORDER BY `id` ASC LIMIT " . max(1, (int) $limit);
        return Database::fetchAll($sql, [':email' => $email]);
    }

    /**
     * Find a single participant by exact email address.
     */
    public function findOneByEmail(string $email): ?array
    {
        $results = $this->findByEmail($email, 1);
        return $results[0] ?? null;
    }

    /**
     * Find potential duplicates based on email + normalized full name, email + phone, or shared email.
     * Never matches on name alone if email is blank.
     */
    public function findPotentialDuplicate(string $fullName, ?string $email, ?string $phone = null, ?int $excludeId = null): ?array
    {
        $email = trim(strtolower((string) $email));
        if ($email === '') {
            // Unidentifiable by email; no merge or duplicate detection on name alone
            return null;
        }

        $candidates = $this->findByEmail($email, 10);
        if (empty($candidates)) {
            return null;
        }

        $normalizedTargetName = preg_replace('/\s+/', ' ', trim(mb_strtolower($fullName)));
        $cleanTargetPhone = !empty($phone) ? preg_replace('/[^0-9]/', '', (string) $phone) : '';

        // Priority 1: Exact normalized name match with same email
        foreach ($candidates as $candidate) {
            if ($excludeId !== null && (int) $candidate['id'] === (int) $excludeId) {
                continue;
            }

            $normalizedCandidateName = preg_replace('/\s+/', ' ', trim(mb_strtolower((string) $candidate['full_name'])));
            if ($normalizedTargetName !== '' && $normalizedCandidateName === $normalizedTargetName) {
                return $candidate;
            }
        }

        // Priority 2: Exact phone match with same email (at least 7 digits)
        if ($cleanTargetPhone !== '' && strlen($cleanTargetPhone) >= 7) {
            foreach ($candidates as $candidate) {
                if ($excludeId !== null && (int) $candidate['id'] === (int) $excludeId) {
                    continue;
                }

                $cleanCandidatePhone = !empty($candidate['phone']) ? preg_replace('/[^0-9]/', '', (string) $candidate['phone']) : '';
                if ($cleanCandidatePhone === $cleanTargetPhone) {
                    return $candidate;
                }
            }
        }

        // Priority 3: Shared email candidate (for administrative duplicate warning)
        foreach ($candidates as $candidate) {
            if ($excludeId !== null && (int) $candidate['id'] === (int) $excludeId) {
                continue;
            }
            return $candidate;
        }

        return null;
    }

    /**
     * Create a new participant record.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO `participants` (
                    `full_name`,
                    `email`,
                    `phone`,
                    `category`,
                    `organization_name`,
                    `agreed_guidelines_at`,
                    `privacy_consent_at`,
                    `status`,
                    `created_at`,
                    `updated_at`
                ) VALUES (
                    :full_name,
                    :email,
                    :phone,
                    :category,
                    :organization_name,
                    :agreed_guidelines_at,
                    :privacy_consent_at,
                    :status,
                    :created_at,
                    :updated_at
                )";

        $now = date('Y-m-d H:i:s');
        $params = [
            ':full_name'            => trim((string) $data['full_name']),
            ':email'                => !empty($data['email']) ? trim(strtolower((string) $data['email'])) : null,
            ':phone'                => !empty($data['phone']) ? trim((string) $data['phone']) : null,
            ':category'             => $data['category'] ?? 'community',
            ':organization_name'    => !empty($data['organization_name']) ? trim((string) $data['organization_name']) : null,
            ':agreed_guidelines_at' => $data['agreed_guidelines_at'],
            ':privacy_consent_at'   => $data['privacy_consent_at'],
            ':status'               => $data['status'] ?? 'active',
            ':created_at'           => $now,
            ':updated_at'           => $now,
        ];

        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    /**
     * Update an existing participant record (excluding immutable consent timestamps).
     */
    public function update(int $id, array $data): bool
    {
        $existing = $this->findById($id);
        if (!$existing) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE `participants` SET
                    `full_name`         = :full_name,
                    `email`             = :email,
                    `phone`             = :phone,
                    `category`          = :category,
                    `organization_name` = :organization_name,
                    `status`            = :status,
                    `updated_at`        = :updated_at
                WHERE `id` = :id";

        $params = [
            ':id'                => $id,
            ':full_name'         => isset($data['full_name']) ? trim((string) $data['full_name']) : $existing['full_name'],
            ':email'             => array_key_exists('email', $data) ? (!empty($data['email']) ? trim(strtolower((string) $data['email'])) : null) : $existing['email'],
            ':phone'             => array_key_exists('phone', $data) ? (!empty($data['phone']) ? trim((string) $data['phone']) : null) : $existing['phone'],
            ':category'          => $data['category'] ?? $existing['category'],
            ':organization_name' => array_key_exists('organization_name', $data) ? (!empty($data['organization_name']) ? trim((string) $data['organization_name']) : null) : $existing['organization_name'],
            ':status'            => $data['status'] ?? $existing['status'],
            ':updated_at'        => $now,
        ];

        return Database::execute($sql, $params) > 0;
    }

    /**
     * Transition participant lifecycle status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE `participants` SET `status` = :status, `updated_at` = :updated_at WHERE `id` = :id";
        return Database::execute($sql, [':id' => $id, ':status' => $status, ':updated_at' => $now]) > 0;
    }

    /**
     * Count participants by status.
     */
    public function countByStatus(): array
    {
        $sql = "SELECT `status`, COUNT(*) AS cnt FROM `participants` GROUP BY `status`";
        $rows = Database::fetchAll($sql);

        $counts = [
            'active'  => 0,
            'flagged' => 0,
            'blocked' => 0,
            'total'   => 0,
        ];

        foreach ($rows as $row) {
            $st = $row['status'];
            $cnt = (int) $row['cnt'];
            if (isset($counts[$st])) {
                $counts[$st] = $cnt;
            }
            $counts['total'] += $cnt;
        }

        return $counts;
    }

    /**
     * Count participants by category.
     */
    public function countByCategory(): array
    {
        $sql = "SELECT `category`, COUNT(*) AS cnt FROM `participants` GROUP BY `category`";
        $rows = Database::fetchAll($sql);

        $counts = [
            'student'      => 0,
            'professional' => 0,
            'community'    => 0,
            'other'        => 0,
        ];

        foreach ($rows as $row) {
            $cat = $row['category'];
            $cnt = (int) $row['cnt'];
            if (isset($counts[$cat])) {
                $counts[$cat] = $cnt;
            }
        }

        return $counts;
    }
}
