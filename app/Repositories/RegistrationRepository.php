<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Event Registration Repository
 * Handles PDO operations for event_registrations junction table.
 */
class RegistrationRepository
{
    /**
     * Retrieve paginated registrations with full relational joins on events, campaigns, and participants.
     *
     * @param array $filters Associative array of filters ('campaign_id', 'event_id', 'status', 'search')
     * @param int $page Current 1-indexed page
     * @param int $perPage Items per page (clamped between 1 and 100)
     * @return array Pagination envelope with items and page metadata
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = " WHERE 1=1";
        $params = [];

        if (!empty($filters['campaign_id'])) {
            $where .= " AND e.`campaign_id` = :campaign_id";
            $params[':campaign_id'] = (int) $filters['campaign_id'];
        }

        if (!empty($filters['event_id'])) {
            $where .= " AND r.`event_id` = :event_id";
            $params[':event_id'] = (int) $filters['event_id'];
        }

        if (!empty($filters['status'])) {
            $where .= " AND r.`status` = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim((string) $filters['search']) . '%';
            $where .= " AND (r.`registration_code` LIKE :search OR p.`full_name` LIKE :search OR p.`email` LIKE :search OR p.`phone` LIKE :search)";
            $params[':search'] = $searchTerm;
        }

        // Count total matching records
        $countSql = "SELECT COUNT(*) AS `total` 
                     FROM `event_registrations` r
                     JOIN `events` e ON r.`event_id` = e.`id`
                     JOIN `participants` p ON r.`participant_id` = p.`id`" . $where;
        $totalRow = Database::fetch($countSql, $params);
        $total = (int) ($totalRow['total'] ?? 0);

        // Fetch paginated items with join
        $dataSql = "SELECT r.*,
                           e.`title` AS `event_title`,
                           e.`slug` AS `event_slug`,
                           e.`format` AS `event_format`,
                           e.`venue_name` AS `event_venue_name`,
                           e.`venue_address` AS `event_venue_address`,
                           e.`online_meeting_url` AS `event_online_meeting_url`,
                           e.`start_time` AS `event_start_time`,
                           e.`end_time` AS `event_end_time`,
                           e.`capacity` AS `event_capacity`,
                           e.`requires_approval` AS `event_requires_approval`,
                           e.`status` AS `event_status`,
                           c.`id` AS `campaign_id`,
                           c.`title` AS `campaign_title`,
                           c.`slug` AS `campaign_slug`,
                           p.`full_name` AS `participant_name`,
                           p.`email` AS `participant_email`,
                           p.`phone` AS `participant_phone`,
                           p.`category` AS `participant_category`,
                           p.`organization_name` AS `participant_organization`,
                           p.`status` AS `participant_status`,
                           u.`name` AS `checked_in_by_name`
                    FROM `event_registrations` r
                    JOIN `events` e ON r.`event_id` = e.`id`
                    JOIN `campaigns` c ON e.`campaign_id` = c.`id`
                    JOIN `participants` p ON r.`participant_id` = p.`id`
                    LEFT JOIN `users` u ON r.`checked_in_by` = u.`id`"
                    . $where . " ORDER BY r.`id` DESC LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

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
     * Find single registration by primary key ID with full relational details.
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT r.*,
                       e.`title` AS `event_title`,
                       e.`slug` AS `event_slug`,
                       e.`format` AS `event_format`,
                       e.`venue_name` AS `event_venue_name`,
                       e.`venue_address` AS `event_venue_address`,
                       e.`online_meeting_url` AS `event_online_meeting_url`,
                       e.`start_time` AS `event_start_time`,
                       e.`end_time` AS `event_end_time`,
                       e.`capacity` AS `event_capacity`,
                       e.`requires_approval` AS `event_requires_approval`,
                       e.`status` AS `event_status`,
                       e.`deleted_at` AS `event_deleted_at`,
                       c.`id` AS `campaign_id`,
                       c.`title` AS `campaign_title`,
                       c.`slug` AS `campaign_slug`,
                       p.`full_name` AS `participant_name`,
                       p.`email` AS `participant_email`,
                       p.`phone` AS `participant_phone`,
                       p.`category` AS `participant_category`,
                       p.`organization_name` AS `participant_organization`,
                       p.`agreed_guidelines_at` AS `participant_agreed_guidelines_at`,
                       p.`privacy_consent_at` AS `participant_privacy_consent_at`,
                       p.`status` AS `participant_status`,
                       u.`name` AS `checked_in_by_name`
                FROM `event_registrations` r
                JOIN `events` e ON r.`event_id` = e.`id`
                JOIN `campaigns` c ON e.`campaign_id` = c.`id`
                JOIN `participants` p ON r.`participant_id` = p.`id`
                LEFT JOIN `users` u ON r.`checked_in_by` = u.`id`
                WHERE r.`id` = :id
                LIMIT 1";

        return Database::fetch($sql, [':id' => $id]);
    }

    /**
     * Find registration by alphanumeric registration code with full relational details.
     */
    public function findByCode(string $code): ?array
    {
        $sql = "SELECT r.*,
                       e.`title` AS `event_title`,
                       e.`slug` AS `event_slug`,
                       e.`format` AS `event_format`,
                       e.`venue_name` AS `event_venue_name`,
                       e.`venue_address` AS `event_venue_address`,
                       e.`online_meeting_url` AS `event_online_meeting_url`,
                       e.`start_time` AS `event_start_time`,
                       e.`end_time` AS `event_end_time`,
                       e.`capacity` AS `event_capacity`,
                       e.`requires_approval` AS `event_requires_approval`,
                       e.`status` AS `event_status`,
                       e.`deleted_at` AS `event_deleted_at`,
                       c.`id` AS `campaign_id`,
                       c.`title` AS `campaign_title`,
                       c.`slug` AS `campaign_slug`,
                       p.`full_name` AS `participant_name`,
                       p.`email` AS `participant_email`,
                       p.`phone` AS `participant_phone`,
                       p.`category` AS `participant_category`,
                       p.`organization_name` AS `participant_organization`,
                       p.`status` AS `participant_status`
                FROM `event_registrations` r
                JOIN `events` e ON r.`event_id` = e.`id`
                JOIN `campaigns` c ON e.`campaign_id` = c.`id`
                JOIN `participants` p ON r.`participant_id` = p.`id`
                WHERE r.`registration_code` = :code
                LIMIT 1";

        return Database::fetch($sql, [':code' => trim($code)]);
    }

    /**
     * Find existing enrollment for an event and participant.
     */
    public function findByEventAndParticipant(int $eventId, int $participantId): ?array
    {
        $sql = "SELECT * FROM `event_registrations` 
                WHERE `event_id` = :event_id AND `participant_id` = :participant_id 
                LIMIT 1";

        return Database::fetch($sql, [
            ':event_id'       => $eventId,
            ':participant_id' => $participantId,
        ]);
    }

    /**
     * Get pessimistic lock clause based on database driver.
     * MySQL uses ' FOR UPDATE'; SQLite does not support FOR UPDATE (locks database-wide in transactions).
     */
    private function getLockClause(): string
    {
        try {
            $driver = Database::getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
            return ($driver === 'sqlite') ? '' : ' FOR UPDATE';
        } catch (\Throwable) {
            return ' FOR UPDATE';
        }
    }

    /**
     * Lock registration row FOR UPDATE inside an active transaction.
     */
    public function findByEventAndParticipantForUpdate(int $eventId, int $participantId): ?array
    {
        $lock = $this->getLockClause();
        $sql = "SELECT * FROM `event_registrations` 
                WHERE `event_id` = :event_id AND `participant_id` = :participant_id 
                LIMIT 1{$lock}";

        return Database::fetch($sql, [
            ':event_id'       => $eventId,
            ':participant_id' => $participantId,
        ]);
    }

    /**
     * Lock single registration row by ID FOR UPDATE.
     */
    public function lockRegistration(int $id): ?array
    {
        $lock = $this->getLockClause();
        $sql = "SELECT * FROM `event_registrations` WHERE `id` = :id LIMIT 1{$lock}";
        return Database::fetch($sql, [':id' => $id]);
    }

    /**
     * Lock event row FOR UPDATE during registration/capacity transactions.
     */
    public function lockEventForRegistration(int $eventId): ?array
    {
        $lock = $this->getLockClause();
        $sql = "SELECT * FROM `events` WHERE `id` = :id LIMIT 1{$lock}";
        return Database::fetch($sql, [':id' => $eventId]);
    }

    /**
     * Count confirmed registrations for a specific event.
     */
    public function countConfirmedByEvent(int $eventId): int
    {
        $sql = "SELECT COUNT(*) AS `total` 
                FROM `event_registrations` 
                WHERE `event_id` = :event_id AND `status` = 'confirmed'";
        $row = Database::fetch($sql, [':event_id' => $eventId]);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Count waitlisted registrations for a specific event.
     */
    public function countWaitlistedByEvent(int $eventId): int
    {
        $sql = "SELECT COUNT(*) AS `total` 
                FROM `event_registrations` 
                WHERE `event_id` = :event_id AND `status` = 'waitlisted'";
        $row = Database::fetch($sql, [':event_id' => $eventId]);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Verify if a registration code already exists in the database.
     */
    public function codeExists(string $code): bool
    {
        $sql = "SELECT 1 FROM `event_registrations` WHERE `registration_code` = :code LIMIT 1";
        $row = Database::fetch($sql, [':code' => trim($code)]);
        return $row !== null;
    }

    /**
     * Insert a new registration record.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO `event_registrations` (
                    `registration_code`,
                    `event_id`,
                    `participant_id`,
                    `status`,
                    `attendance_status`,
                    `admin_notes`,
                    `created_at`,
                    `updated_at`
                ) VALUES (
                    :registration_code,
                    :event_id,
                    :participant_id,
                    :status,
                    :attendance_status,
                    :admin_notes,
                    :created_at,
                    :updated_at
                )";

        $now = date('Y-m-d H:i:s');
        $params = [
            ':registration_code' => trim((string) $data['registration_code']),
            ':event_id'          => (int) $data['event_id'],
            ':participant_id'    => (int) $data['participant_id'],
            ':status'            => $data['status'] ?? 'confirmed',
            ':attendance_status' => $data['attendance_status'] ?? 'unmarked',
            ':admin_notes'       => !empty($data['admin_notes']) ? trim((string) $data['admin_notes']) : null,
            ':created_at'        => $now,
            ':updated_at'        => $now,
        ];

        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    /**
     * Update lifecycle status and optional admin notes for a registration record.
     */
    public function updateStatus(int $id, string $status, ?string $adminNotes = null): bool
    {
        $now = date('Y-m-d H:i:s');
        $params = [
            ':id'         => $id,
            ':status'     => $status,
            ':updated_at' => $now,
        ];

        $notesSql = "";
        if ($adminNotes !== null) {
            $notesSql = ", `admin_notes` = :admin_notes";
            $params[':admin_notes'] = trim($adminNotes);
        }

        $sql = "UPDATE `event_registrations` SET 
                    `status` = :status,
                    `updated_at` = :updated_at
                    {$notesSql}
                WHERE `id` = :id";

        Database::execute($sql, $params);
        return true;
    }

    /**
     * Reactivate a previously cancelled registration in-place with a fresh pass code.
     * Preserves row ID, participant ID, event ID, and original created_at timestamp.
     */
    public function reactivate(int $id, string $newStatus, string $newCode, ?string $adminNotes = null): bool
    {
        $now = date('Y-m-d H:i:s');
        $params = [
            ':id'                => $id,
            ':status'            => $newStatus,
            ':registration_code' => trim($newCode),
            ':updated_at'        => $now,
        ];

        $notesSql = "";
        if ($adminNotes !== null) {
            $notesSql = ", `admin_notes` = :admin_notes";
            $params[':admin_notes'] = trim($adminNotes);
        }

        $sql = "UPDATE `event_registrations` SET 
                    `status` = :status,
                    `registration_code` = :registration_code,
                    `attendance_status` = 'unmarked',
                    `updated_at` = :updated_at
                    {$notesSql}
                WHERE `id` = :id";

        Database::execute($sql, $params);
        return true;
    }

    /**
     * Summary KPI metric counts across registrations.
     */
    public function countByStatus(?int $eventId = null): array
    {
        $sql = "SELECT `status`, COUNT(*) AS `total` 
                FROM `event_registrations`";
        $params = [];

        if ($eventId !== null) {
            $sql .= " WHERE `event_id` = :event_id";
            $params[':event_id'] = $eventId;
        }

        $sql .= " GROUP BY `status`";
        $rows = Database::fetchAll($sql, $params);

        $counts = [
            'confirmed'  => 0,
            'pending'    => 0,
            'waitlisted' => 0,
            'cancelled'  => 0,
            'total'      => 0,
        ];

        foreach ($rows as $row) {
            $status = $row['status'] ?? '';
            $total = (int) ($row['total'] ?? 0);
            if (isset($counts[$status])) {
                $counts[$status] = $total;
            }
            $counts['total'] += $total;
        }

        return $counts;
    }

    /**
     * Retrieve waitlisted registrations for an event ordered by created_at ASC, id ASC (FIFO queue).
     */
    public function getWaitlistByEvent(int $eventId): array
    {
        $sql = "SELECT r.*, p.`full_name` AS `participant_name`, p.`email` AS `participant_email`
                FROM `event_registrations` r
                JOIN `participants` p ON r.`participant_id` = p.`id`
                WHERE r.`event_id` = :event_id AND r.`status` = 'waitlisted'
                ORDER BY r.`created_at` ASC, r.`id` ASC";

        return Database::fetchAll($sql, [':event_id' => $eventId]);
    }
}
