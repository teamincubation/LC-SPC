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
                    LEFT JOIN `campaigns` c ON e.`campaign_id` = c.`id`
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
                LEFT JOIN `campaigns` c ON e.`campaign_id` = c.`id`
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
                       p.`status` AS `participant_status`,
                       u.`name` AS `checked_in_by_name`
                FROM `event_registrations` r
                JOIN `events` e ON r.`event_id` = e.`id`
                LEFT JOIN `campaigns` c ON e.`campaign_id` = c.`id`
                JOIN `participants` p ON r.`participant_id` = p.`id`
                LEFT JOIN `users` u ON r.`checked_in_by` = u.`id`
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
     * Count all registrations for a specific event.
     */
    public function countByEvent(int $eventId): int
    {
        $sql = "SELECT COUNT(*) AS `total` 
                FROM `event_registrations` 
                WHERE `event_id` = :event_id";
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
        $customDataJson = null;
        if (isset($data['custom_data'])) {
            $customDataJson = is_array($data['custom_data']) 
                ? json_encode($data['custom_data'], JSON_UNESCAPED_UNICODE) 
                : (string) $data['custom_data'];
        }

        $sql = "INSERT INTO `event_registrations` (
                    `registration_code`,
                    `event_id`,
                    `participant_id`,
                    `form_id`,
                    `custom_data`,
                    `phone_normalized`,
                    `country_code`,
                    `photo_path`,
                    `status`,
                    `attendance_status`,
                    `admin_notes`,
                    `created_at`,
                    `updated_at`
                ) VALUES (
                    :registration_code,
                    :event_id,
                    :participant_id,
                    :form_id,
                    :custom_data,
                    :phone_normalized,
                    :country_code,
                    :photo_path,
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
            ':form_id'           => !empty($data['form_id']) ? (int) $data['form_id'] : null,
            ':custom_data'       => $customDataJson,
            ':phone_normalized'  => !empty($data['phone_normalized']) ? trim((string) $data['phone_normalized']) : null,
            ':country_code'      => !empty($data['country_code']) ? trim((string) $data['country_code']) : '+91',
            ':photo_path'        => !empty($data['photo_path']) ? trim((string) $data['photo_path']) : null,
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
     * Find a registration by event ID and normalized phone number.
     */
    public function findByEventAndPhone(int $eventId, string $phoneNormalized): ?array
    {
        $sql = "SELECT r.*, p.full_name, p.email, p.phone 
                FROM `event_registrations` r
                JOIN `participants` p ON r.participant_id = p.id
                WHERE r.`event_id` = :event_id AND r.`phone_normalized` = :phone 
                LIMIT 1";
        return Database::fetch($sql, [
            ':event_id' => $eventId,
            ':phone'    => $phoneNormalized,
        ]);
    }

    /**
     * Update check-in status and physical telemetry.
     */
    public function updateCheckin(int $id, array $data): bool
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE `event_registrations` SET 
                    `attendance_status` = :attendance_status,
                    `check_in_method` = :check_in_method,
                    `attended_at` = :attended_at,
                    `checkin_latitude` = :lat,
                    `checkin_longitude` = :lng,
                    `checkin_distance_meters` = :dist,
                    `checkin_geofence_verified` = :geofence_verified,
                    `updated_at` = :updated_at
                WHERE `id` = :id";

        return Database::execute($sql, [
            ':id'                 => $id,
            ':attendance_status'  => $data['attendance_status'] ?? 'attended',
            ':check_in_method'    => $data['check_in_method'] ?? 'qr_scanner',
            ':attended_at'        => $data['attended_at'] ?? $now,
            ':lat'                => isset($data['checkin_latitude']) && $data['checkin_latitude'] !== '' ? (float) $data['checkin_latitude'] : null,
            ':lng'                => isset($data['checkin_longitude']) && $data['checkin_longitude'] !== '' ? (float) $data['checkin_longitude'] : null,
            ':dist'               => isset($data['checkin_distance_meters']) && $data['checkin_distance_meters'] !== '' ? (float) $data['checkin_distance_meters'] : null,
            ':geofence_verified'  => (int) ($data['checkin_geofence_verified'] ?? 0),
            ':updated_at'         => $now,
        ]) > 0;
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

    /**
     * Atomic conditional check-in mutation.
     * Transitions attendance_status from 'unmarked' to 'attended' atomically.
     * Uses strictly distinct parameter names to comply with native prepared statements.
     *
     * @return int Affected row count (1 if this transaction performed check-in; 0 if already marked or condition failed)
     */
    public function updateAttendanceAtomic(int $regId, string $method, int $staffUserId, string $now): int
    {
        $sql = "UPDATE `event_registrations`
                SET `attendance_status` = 'attended',
                    `checked_in_at` = :checked_in_at,
                    `checked_in_by` = :checked_in_by,
                    `check_in_method` = :check_in_method,
                    `updated_at` = :updated_at
                WHERE `id` = :id AND `attendance_status` = 'unmarked'";

        $params = [
            ':id'              => $regId,
            ':checked_in_at'   => $now,
            ':checked_in_by'   => $staffUserId,
            ':check_in_method' => $method,
            ':updated_at'      => $now,
        ];

        return Database::execute($sql, $params);
    }

    /**
     * Update attendance status for an event registration (used for corrections and reversals).
     * When reversing to 'unmarked', clears checked_in_at, checked_in_by, and check_in_method.
     */
    public function updateAttendanceStatus(
        int $regId,
        string $status,
        ?string $adminNotes = null,
        ?int $staffUserId = null,
        ?string $method = null
    ): bool {
        $now = date('Y-m-d H:i:s');
        $params = [
            ':id'         => $regId,
            ':updated_at' => $now,
        ];

        $notesSql = "";
        if ($adminNotes !== null) {
            $notesSql = ", `admin_notes` = :admin_notes";
            $params[':admin_notes'] = trim($adminNotes);
        }

        if ($status === 'unmarked') {
            $sql = "UPDATE `event_registrations` SET
                        `attendance_status` = 'unmarked',
                        `checked_in_at` = NULL,
                        `checked_in_by` = NULL,
                        `check_in_method` = NULL,
                        `updated_at` = :updated_at
                        {$notesSql}
                    WHERE `id` = :id";
        } elseif ($status === 'attended') {
            $sql = "UPDATE `event_registrations` SET
                        `attendance_status` = 'attended',
                        `checked_in_at` = COALESCE(`checked_in_at`, :checked_in_at),
                        `checked_in_by` = COALESCE(`checked_in_by`, :checked_in_by),
                        `check_in_method` = COALESCE(`check_in_method`, :check_in_method),
                        `updated_at` = :updated_at
                        {$notesSql}
                    WHERE `id` = :id";
            $params[':checked_in_at'] = $now;
            $params[':checked_in_by'] = $staffUserId;
            $params[':check_in_method'] = $method ?? 'admin_manual';
        } else {
            // 'absent' or 'excused'
            $sql = "UPDATE `event_registrations` SET
                        `attendance_status` = :attendance_status,
                        `updated_at` = :updated_at
                        {$notesSql}
                    WHERE `id` = :id";
            $params[':attendance_status'] = $status;
        }

        Database::execute($sql, $params);
        return true;
    }

    /**
     * Compute real-time attendance metric counts for a specific event.
     */
    public function countAttendanceByEvent(int $eventId): array
    {
        $sql = "SELECT `attendance_status`, COUNT(*) AS `total` 
                FROM `event_registrations` 
                WHERE `event_id` = :event_id AND `status` = 'confirmed'
                GROUP BY `attendance_status`";

        $rows = Database::fetchAll($sql, [':event_id' => $eventId]);

        $metrics = [
            'confirmed'          => 0,
            'attended'           => 0,
            'absent'             => 0,
            'excused'            => 0,
            'unmarked'           => 0,
            'turnout_percentage' => 0.0,
        ];

        foreach ($rows as $row) {
            $status = $row['attendance_status'] ?? '';
            $count = (int) ($row['total'] ?? 0);
            if (isset($metrics[$status])) {
                $metrics[$status] = $count;
            }
            $metrics['confirmed'] += $count;
        }

        if ($metrics['confirmed'] > 0) {
            $metrics['turnout_percentage'] = round(($metrics['attended'] / $metrics['confirmed']) * 100, 1);
        }

        return $metrics;
    }

    /**
     * Compute breakdown of check-in methods for attended participants of an event.
     */
    public function countCheckInMethodsByEvent(int $eventId): array
    {
        $sql = "SELECT `check_in_method`, COUNT(*) AS `total` 
                FROM `event_registrations` 
                WHERE `event_id` = :event_id AND `attendance_status` = 'attended'
                GROUP BY `check_in_method`";

        $rows = Database::fetchAll($sql, [':event_id' => $eventId]);

        $counts = [
            'qr_scan'      => 0,
            'admin_manual' => 0,
        ];

        foreach ($rows as $row) {
            $method = $row['check_in_method'] ?? '';
            $count = (int) ($row['total'] ?? 0);
            if (isset($counts[$method])) {
                $counts[$method] = $count;
            }
        }

        return $counts;
    }

    /**
     * Bulk mark all remaining confirmed 'unmarked' registrations as 'absent' for an event.
     *
     * @return int Number of registrations marked absent
     */
    public function markRemainingAbsent(int $eventId, string $now): int
    {
        $sql = "UPDATE `event_registrations` 
                SET `attendance_status` = 'absent',
                    `updated_at` = :updated_at
                WHERE `event_id` = :event_id 
                  AND `status` = 'confirmed' 
                  AND `attendance_status` = 'unmarked'";

        return Database::execute($sql, [
            ':event_id'   => $eventId,
            ':updated_at' => $now,
        ]);
    }

    /**
     * Retrieve paginated attendance roster for an event with participant and staff checker details.
     */
    public function getAttendanceRoster(int $eventId, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = " WHERE r.`event_id` = :event_id AND r.`status` = 'confirmed'";
        $params = [':event_id' => $eventId];

        if (!empty($filters['attendance_status']) && $filters['attendance_status'] !== 'all') {
            $where .= " AND r.`attendance_status` = :attendance_status";
            $params[':attendance_status'] = $filters['attendance_status'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim((string) $filters['search']) . '%';
            $where .= " AND (r.`registration_code` LIKE :search OR p.`full_name` LIKE :search OR p.`email` LIKE :search OR p.`phone` LIKE :search)";
            $params[':search'] = $searchTerm;
        }

        $countSql = "SELECT COUNT(*) AS `total`
                     FROM `event_registrations` r
                     JOIN `participants` p ON r.`participant_id` = p.`id`" . $where;
        $totalRow = Database::fetch($countSql, $params);
        $total = (int) ($totalRow['total'] ?? 0);

        $dataSql = "SELECT r.*,
                           p.`full_name` AS `participant_name`,
                           p.`email` AS `participant_email`,
                           p.`phone` AS `participant_phone`,
                           p.`category` AS `participant_category`,
                           p.`organization_name` AS `participant_organization`,
                           p.`status` AS `participant_status`,
                           u.`name` AS `checked_in_by_name`
                    FROM `event_registrations` r
                    JOIN `participants` p ON r.`participant_id` = p.`id`
                    LEFT JOIN `users` u ON r.`checked_in_by` = u.`id`"
                    . $where . " ORDER BY r.`id` ASC LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

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
     * Retrieve all confirmed registrations for an event for CSV export.
     */
    public function getAllConfirmedForExport(int $eventId): array
    {
        $sql = "SELECT r.*,
                       e.`title` AS `event_title`,
                       p.`full_name` AS `participant_name`,
                       p.`email` AS `participant_email`,
                       p.`phone` AS `participant_phone`,
                       p.`category` AS `participant_category`,
                       p.`organization_name` AS `participant_organization`,
                       u.`name` AS `checked_in_by_name`
                FROM `event_registrations` r
                JOIN `events` e ON r.`event_id` = e.`id`
                JOIN `participants` p ON r.`participant_id` = p.`id`
                LEFT JOIN `users` u ON r.`checked_in_by` = u.`id`
                WHERE r.`event_id` = :event_id AND r.`status` = 'confirmed'
                ORDER BY r.`id` ASC";

        return Database::fetchAll($sql, [':event_id' => $eventId]);
    }
}

