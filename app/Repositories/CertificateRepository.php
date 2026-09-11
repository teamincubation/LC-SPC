<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Certificate Data Access Repository
 * Handles all PDO database interactions for certificates with prepared statements.
 */
class CertificateRepository
{
    /**
     * Find a certificate by primary ID with full relational joins.
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT c.*,
                       r.registration_code,
                       r.status AS registration_status,
                       r.attendance_status,
                       r.checked_in_at,
                       e.id AS event_id,
                       e.title AS event_title,
                       e.slug AS event_slug,
                       e.format AS event_format,
                       e.venue_name AS event_venue_name,
                       e.venue_address AS event_venue_address,
                       e.online_meeting_url AS event_online_meeting_url,
                       e.start_time AS event_start_time,
                       e.end_time AS event_end_time,
                       e.status AS event_status,
                       e.coordinator_id,
                       u_coord.name AS coordinator_name,
                       u_coord.email AS coordinator_email,
                       camp.id AS campaign_id,
                       camp.title AS campaign_title,
                       camp.slug AS campaign_slug,
                       p.id AS participant_id,
                       p.full_name AS participant_name,
                       p.email AS participant_email,
                       p.phone AS participant_phone,
                       p.category AS participant_category,
                       p.status AS participant_status,
                       u_issued.name AS issued_by_name,
                       u_revoked.name AS revoked_by_name
                FROM `certificates` c
                JOIN `event_registrations` r ON c.registration_id = r.id
                JOIN `events` e ON r.event_id = e.id
                JOIN `campaigns` camp ON e.campaign_id = camp.id
                JOIN `participants` p ON r.participant_id = p.id
                LEFT JOIN `users` u_coord ON e.coordinator_id = u_coord.id
                LEFT JOIN `users` u_issued ON c.issued_by = u_issued.id
                LEFT JOIN `users` u_revoked ON c.revoked_by = u_revoked.id
                WHERE c.id = :id
                LIMIT 1";

        return Database::fetch($sql, [':id' => $id]);
    }

    /**
     * Find a certificate by its 256-bit verification token (for public verification).
     */
    public function findByToken(string $token): ?array
    {
        $sql = "SELECT c.*,
                       r.registration_code,
                       r.status AS registration_status,
                       r.attendance_status,
                       e.id AS event_id,
                       e.title AS event_title,
                       e.slug AS event_slug,
                       e.format AS event_format,
                       e.venue_name AS event_venue_name,
                       e.start_time AS event_start_time,
                       e.end_time AS event_end_time,
                       e.status AS event_status,
                       e.coordinator_id,
                       u_coord.name AS coordinator_name,
                       camp.id AS campaign_id,
                       camp.title AS campaign_title,
                       camp.slug AS campaign_slug,
                       u_issued.name AS issued_by_name
                FROM `certificates` c
                JOIN `event_registrations` r ON c.registration_id = r.id
                JOIN `events` e ON r.event_id = e.id
                JOIN `campaigns` camp ON e.campaign_id = camp.id
                LEFT JOIN `users` u_coord ON e.coordinator_id = u_coord.id
                LEFT JOIN `users` u_issued ON c.issued_by = u_issued.id
                WHERE c.verification_token = :token
                LIMIT 1";

        return Database::fetch($sql, [':token' => $token]);
    }

    /**
     * Find a certificate by human-readable certificate number.
     */
    public function findByNumber(string $certificateNumber): ?array
    {
        $sql = "SELECT c.*,
                       r.registration_code,
                       e.id AS event_id,
                       e.title AS event_title,
                       camp.title AS campaign_title,
                       p.full_name AS participant_name
                FROM `certificates` c
                JOIN `event_registrations` r ON c.registration_id = r.id
                JOIN `events` e ON r.event_id = e.id
                JOIN `campaigns` camp ON e.campaign_id = camp.id
                JOIN `participants` p ON r.participant_id = p.id
                WHERE c.certificate_number = :number
                LIMIT 1";

        return Database::fetch($sql, [':number' => $certificateNumber]);
    }

    /**
     * Find active certificate for a specific registration and type.
     */
    public function findActiveByRegistrationAndType(int $registrationId, string $type): ?array
    {
        $sql = "SELECT * FROM `certificates`
                WHERE `registration_id` = :reg_id 
                  AND `type` = :type 
                  AND `status` = 'active'
                LIMIT 1";

        return Database::fetch($sql, [
            ':reg_id' => $registrationId,
            ':type'   => $type,
        ]);
    }

    /**
     * Find the primary active certificate for a registration (used by pass view).
     */
    public function findActiveByRegistrationId(int $registrationId): ?array
    {
        $sql = "SELECT * FROM `certificates`
                WHERE `registration_id` = :reg_id
                  AND `status` = 'active'
                ORDER BY `id` DESC
                LIMIT 1";

        return Database::fetch($sql, [':reg_id' => $registrationId]);
    }

    /**
     * Get all certificates (active and superseded/revoked) for a registration.
     */
    public function getHistoryByRegistration(int $registrationId): array
    {
        $sql = "SELECT c.*, 
                       u_issued.name AS issued_by_name, 
                       u_revoked.name AS revoked_by_name
                FROM `certificates` c
                LEFT JOIN `users` u_issued ON c.issued_by = u_issued.id
                LEFT JOIN `users` u_revoked ON c.revoked_by = u_revoked.id
                WHERE c.registration_id = :reg_id
                ORDER BY c.id DESC";

        return Database::fetchAll($sql, [':reg_id' => $registrationId]);
    }

    /**
     * Insert a new certificate record.
     */
    public function insert(array $data): int
    {
        $sql = "INSERT INTO `certificates` (
                    `certificate_number`,
                    `verification_token`,
                    `registration_id`,
                    `recipient_name_snapshot`,
                    `type`,
                    `issue_date`,
                    `status`,
                    `issued_by`,
                    `created_at`,
                    `updated_at`
                ) VALUES (
                    :certificate_number,
                    :verification_token,
                    :registration_id,
                    :recipient_name_snapshot,
                    :type,
                    :issue_date,
                    :status,
                    :issued_by,
                    :created_at,
                    :updated_at
                )";

        $now = date('Y-m-d H:i:s');
        $params = [
            ':certificate_number'      => $data['certificate_number'],
            ':verification_token'      => $data['verification_token'],
            ':registration_id'         => (int) $data['registration_id'],
            ':recipient_name_snapshot' => $data['recipient_name_snapshot'],
            ':type'                    => $data['type'] ?? 'participation',
            ':issue_date'              => $data['issue_date'] ?? date('Y-m-d'),
            ':status'                  => $data['status'] ?? 'active',
            ':issued_by'               => !empty($data['issued_by']) ? (int) $data['issued_by'] : null,
            ':created_at'              => $data['created_at'] ?? $now,
            ':updated_at'              => $data['updated_at'] ?? $now,
        ];

        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    /**
     * Mark a certificate as revoked.
     */
    public function revoke(int $id, int $revokedBy, string $reason, ?string $revokedAt = null): bool
    {
        $sql = "UPDATE `certificates` SET
                    `status` = 'revoked',
                    `revoked_by` = :revoked_by,
                    `revocation_reason` = :reason,
                    `revoked_at` = :revoked_at,
                    `updated_at` = :updated_at
                WHERE `id` = :id";

        $now = date('Y-m-d H:i:s');
        $params = [
            ':id'         => $id,
            ':revoked_by' => $revokedBy,
            ':reason'     => $reason,
            ':revoked_at' => $revokedAt ?? $now,
            ':updated_at' => $now,
        ];

        return Database::execute($sql, $params) > 0;
    }

    /**
     * Global paginated certificate directory with multi-criteria filtering.
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = " WHERE 1=1";
        $params = [];

        if (!empty($filters['event_id'])) {
            $where .= " AND r.`event_id` = :event_id";
            $params[':event_id'] = (int) $filters['event_id'];
        }

        if (!empty($filters['campaign_id'])) {
            $where .= " AND e.`campaign_id` = :campaign_id";
            $params[':campaign_id'] = (int) $filters['campaign_id'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'revoked'], true)) {
            $where .= " AND c.`status` = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['type']) && in_array($filters['type'], ['participation', 'volunteer', 'speaker', 'appreciation'], true)) {
            $where .= " AND c.`type` = :type";
            $params[':type'] = $filters['type'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim((string) $filters['search']) . '%';
            $where .= " AND (c.`certificate_number` LIKE :search OR c.`recipient_name_snapshot` LIKE :search OR r.`registration_code` LIKE :search OR e.`title` LIKE :search)";
            $params[':search'] = $searchTerm;
        }

        // Count total
        $countSql = "SELECT COUNT(*) AS `total`
                     FROM `certificates` c
                     JOIN `event_registrations` r ON c.`registration_id` = r.`id`
                     JOIN `events` e ON r.`event_id` = e.`id`
                     JOIN `campaigns` camp ON e.`campaign_id` = camp.`id`
                     JOIN `participants` p ON r.`participant_id` = p.`id`" . $where;
        $totalRow = Database::fetch($countSql, $params);
        $total = (int) ($totalRow['total'] ?? 0);

        // Fetch paginated records
        $dataSql = "SELECT c.*,
                           r.`registration_code`,
                           e.`id` AS `event_id`,
                           e.`title` AS `event_title`,
                           e.`start_time` AS `event_start_time`,
                           camp.`id` AS `campaign_id`,
                           camp.`title` AS `campaign_title`,
                           p.`id` AS `participant_id`,
                           p.`full_name` AS `participant_name`,
                           p.`email` AS `participant_email`,
                           p.`phone` AS `participant_phone`,
                           p.`status` AS `participant_status`,
                           u_issued.`name` AS `issued_by_name`,
                           u_revoked.`name` AS `revoked_by_name`
                    FROM `certificates` c
                    JOIN `event_registrations` r ON c.`registration_id` = r.`id`
                    JOIN `events` e ON r.`event_id` = e.`id`
                    JOIN `campaigns` camp ON e.`campaign_id` = camp.`id`
                    JOIN `participants` p ON r.`participant_id` = p.`id`
                    LEFT JOIN `users` u_issued ON c.`issued_by` = u_issued.`id`
                    LEFT JOIN `users` u_revoked ON c.`revoked_by` = u_revoked.`id`"
                    . $where . " ORDER BY c.`id` DESC LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

        $items = Database::fetchAll($dataSql, $params);
        $totalPages = (int) ceil($total / $perPage);

        return [
            'items'        => $items,
            'total'        => $total,
            'page'         => $page,
            'per_page'     => $perPage,
            'total_pages'  => max(1, $totalPages),
            'has_previous' => $page > 1,
            'has_next'     => $page < $totalPages,
        ];
    }

    /**
     * Get candidate attendee registrations eligible to receive a certificate for an event.
     * Excludes registrations that already hold an active certificate of the specified type.
     */
    public function getEligibleCandidatesForEvent(int $eventId, string $type = 'participation'): array
    {
        $sql = "SELECT r.id AS registration_id,
                       r.registration_code,
                       r.status AS registration_status,
                       r.attendance_status,
                       r.checked_in_at,
                       r.check_in_method,
                       p.id AS participant_id,
                       p.full_name AS participant_name,
                       p.email AS participant_email,
                       p.phone AS participant_phone,
                       p.category AS participant_category,
                       p.status AS participant_status,
                       active_cert.id AS active_cert_id,
                       active_cert.certificate_number AS active_cert_number
                FROM `event_registrations` r
                JOIN `participants` p ON r.participant_id = p.id
                LEFT JOIN `certificates` active_cert 
                       ON active_cert.registration_id = r.id 
                      AND active_cert.type = :type 
                      AND active_cert.status = 'active'
                WHERE r.event_id = :event_id
                  AND r.status = 'confirmed'
                  AND r.attendance_status = 'attended'
                ORDER BY p.full_name ASC";

        return Database::fetchAll($sql, [
            ':event_id' => $eventId,
            ':type'     => $type,
        ]);
    }

    /**
     * Retrieve certificate aggregation metrics.
     */
    public function getMetrics(?int $eventId = null): array
    {
        $where = " WHERE 1=1";
        $params = [];

        if ($eventId !== null) {
            $where .= " AND r.event_id = :event_id";
            $params[':event_id'] = $eventId;
        }

        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) AS active_count,
                       SUM(CASE WHEN c.status = 'revoked' THEN 1 ELSE 0 END) AS revoked_count,
                       SUM(CASE WHEN c.status = 'active' AND c.type = 'participation' THEN 1 ELSE 0 END) AS participation_count,
                       SUM(CASE WHEN c.status = 'active' AND c.type = 'volunteer' THEN 1 ELSE 0 END) AS volunteer_count,
                       SUM(CASE WHEN c.status = 'active' AND c.type = 'speaker' THEN 1 ELSE 0 END) AS speaker_count,
                       SUM(CASE WHEN c.status = 'active' AND c.type = 'appreciation' THEN 1 ELSE 0 END) AS appreciation_count
                FROM `certificates` c
                JOIN `event_registrations` r ON c.registration_id = r.id" . $where;

        $row = Database::fetch($sql, $params);

        return [
            'total'               => (int) ($row['total'] ?? 0),
            'active_count'        => (int) ($row['active_count'] ?? 0),
            'revoked_count'       => (int) ($row['revoked_count'] ?? 0),
            'participation_count' => (int) ($row['participation_count'] ?? 0),
            'volunteer_count'     => (int) ($row['volunteer_count'] ?? 0),
            'speaker_count'       => (int) ($row['speaker_count'] ?? 0),
            'appreciation_count'  => (int) ($row['appreciation_count'] ?? 0),
        ];
    }
}
