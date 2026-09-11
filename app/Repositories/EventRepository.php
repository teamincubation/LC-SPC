<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Event Data Access Repository
 * Encapsulates all PDO operations for the events table with prepared statements.
 */
class EventRepository
{
    /**
     * Retrieve all events with optional filters (campaign, status, category, format, search).
     *
     * @param bool $includeDeleted Include soft-deleted events
     * @param array $filters Associative array of filter parameters
     * @return array List of event associative arrays
     */
    public function all(bool $includeDeleted = false, array $filters = []): array
    {
        $sql = "SELECT e.*, 
                       c.title AS campaign_title, 
                       c.slug AS campaign_slug, 
                       u.name AS coordinator_name, 
                       u.email AS coordinator_email 
                FROM `events` e 
                JOIN `campaigns` c ON e.campaign_id = c.id 
                LEFT JOIN `users` u ON e.coordinator_id = u.id 
                WHERE 1=1";
        $params = [];

        if (!$includeDeleted) {
            $sql .= " AND e.`deleted_at` IS NULL";
        }

        if (!empty($filters['campaign_id'])) {
            $sql .= " AND e.`campaign_id` = :campaign_id";
            $params[':campaign_id'] = (int) $filters['campaign_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND e.`status` = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $sql .= " AND e.`category` = :category";
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['format'])) {
            $sql .= " AND e.`format` = :format";
            $params[':format'] = $filters['format'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (e.`title` LIKE :search OR e.`slug` LIKE :search OR e.`venue_name` LIKE :search OR e.`description` LIKE :search)";
            $params[':search'] = '%' . trim((string) $filters['search']) . '%';
        }

        $sql .= " ORDER BY e.`start_time` DESC, e.`id` DESC";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Find an event by its primary ID.
     */
    public function findById(int $id, bool $includeDeleted = false): ?array
    {
        $sql = "SELECT e.*, 
                       c.title AS campaign_title, 
                       c.slug AS campaign_slug, 
                       u.name AS coordinator_name, 
                       u.email AS coordinator_email 
                FROM `events` e 
                JOIN `campaigns` c ON e.campaign_id = c.id 
                LEFT JOIN `users` u ON e.coordinator_id = u.id 
                WHERE e.`id` = :id";
        $params = [':id' => $id];

        if (!$includeDeleted) {
            $sql .= " AND e.`deleted_at` IS NULL";
        }

        $sql .= " LIMIT 1";

        return Database::fetch($sql, $params);
    }

    /**
     * Find an event by campaign ID and slug.
     */
    public function findByCampaignAndSlug(int $campaignId, string $slug, bool $includeDeleted = false): ?array
    {
        $sql = "SELECT e.*, 
                       c.title AS campaign_title, 
                       c.slug AS campaign_slug, 
                       u.name AS coordinator_name, 
                       u.email AS coordinator_email 
                FROM `events` e 
                JOIN `campaigns` c ON e.campaign_id = c.id 
                LEFT JOIN `users` u ON e.coordinator_id = u.id 
                WHERE e.`campaign_id` = :campaign_id AND e.`slug` = :slug";
        $params = [
            ':campaign_id' => $campaignId,
            ':slug'        => strtolower(trim($slug)),
        ];

        if (!$includeDeleted) {
            $sql .= " AND e.`deleted_at` IS NULL";
        }

        $sql .= " LIMIT 1";

        return Database::fetch($sql, $params);
    }

    /**
     * Check if a slug already exists within a specific campaign.
     * Note: Evaluates all rows (including soft-deleted) because of MySQL UNIQUE KEY uk_events_campaign_slug.
     */
    public function slugExistsInCampaign(int $campaignId, string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS `total` 
                FROM `events` 
                WHERE `campaign_id` = :campaign_id AND `slug` = :slug";
        $params = [
            ':campaign_id' => $campaignId,
            ':slug'        => strtolower(trim($slug)),
        ];

        if ($excludeId !== null) {
            $sql .= " AND `id` != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $row = Database::fetch($sql, $params);
        return (int) ($row['total'] ?? 0) > 0;
    }

    /**
     * Insert a new event record.
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO `events` (
                    `campaign_id`, `coordinator_id`, `title`, `slug`, 
                    `category`, `description`, `format`, 
                    `venue_name`, `venue_address`, `online_meeting_url`, 
                    `start_time`, `end_time`, `capacity`, 
                    `registration_deadline`, `requires_approval`, 
                    `status`, `created_at`, `updated_at`, `deleted_at`
                ) VALUES (
                    :campaign_id, :coordinator_id, :title, :slug, 
                    :category, :description, :format, 
                    :venue_name, :venue_address, :online_meeting_url, 
                    :start_time, :end_time, :capacity, 
                    :registration_deadline, :requires_approval, 
                    :status, :created_at, :updated_at, NULL
                )";

        $params = [
            ':campaign_id'          => (int) $data['campaign_id'],
            ':coordinator_id'       => !empty($data['coordinator_id']) ? (int) $data['coordinator_id'] : null,
            ':title'                => trim((string) ($data['title'] ?? '')),
            ':slug'                 => strtolower(trim((string) ($data['slug'] ?? ''))),
            ':category'             => $data['category'] ?? 'workshop',
            ':description'          => !empty($data['description']) ? trim((string) $data['description']) : null,
            ':format'               => $data['format'] ?? 'in_person',
            ':venue_name'           => !empty($data['venue_name']) ? trim((string) $data['venue_name']) : null,
            ':venue_address'        => !empty($data['venue_address']) ? trim((string) $data['venue_address']) : null,
            ':online_meeting_url'   => !empty($data['online_meeting_url']) ? trim((string) $data['online_meeting_url']) : null,
            ':start_time'           => $data['start_time'],
            ':end_time'             => $data['end_time'],
            ':capacity'             => isset($data['capacity']) ? (int) $data['capacity'] : 0,
            ':registration_deadline'=> !empty($data['registration_deadline']) ? $data['registration_deadline'] : null,
            ':requires_approval'    => !empty($data['requires_approval']) ? 1 : 0,
            ':status'               => $data['status'] ?? 'draft',
            ':created_at'           => $now,
            ':updated_at'           => $now,
        ];

        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    /**
     * Update an existing event record.
     */
    public function update(int $id, array $data): bool
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE `events` SET 
                    `campaign_id` = :campaign_id,
                    `coordinator_id` = :coordinator_id,
                    `title` = :title,
                    `slug` = :slug,
                    `category` = :category,
                    `description` = :description,
                    `format` = :format,
                    `venue_name` = :venue_name,
                    `venue_address` = :venue_address,
                    `online_meeting_url` = :online_meeting_url,
                    `start_time` = :start_time,
                    `end_time` = :end_time,
                    `capacity` = :capacity,
                    `registration_deadline` = :registration_deadline,
                    `requires_approval` = :requires_approval,
                    `status` = :status,
                    `updated_at` = :updated_at 
                WHERE `id` = :id";

        $params = [
            ':id'                   => $id,
            ':campaign_id'          => (int) $data['campaign_id'],
            ':coordinator_id'       => !empty($data['coordinator_id']) ? (int) $data['coordinator_id'] : null,
            ':title'                => trim((string) ($data['title'] ?? '')),
            ':slug'                 => strtolower(trim((string) ($data['slug'] ?? ''))),
            ':category'             => $data['category'] ?? 'workshop',
            ':description'          => !empty($data['description']) ? trim((string) $data['description']) : null,
            ':format'               => $data['format'] ?? 'in_person',
            ':venue_name'           => !empty($data['venue_name']) ? trim((string) $data['venue_name']) : null,
            ':venue_address'        => !empty($data['venue_address']) ? trim((string) $data['venue_address']) : null,
            ':online_meeting_url'   => !empty($data['online_meeting_url']) ? trim((string) $data['online_meeting_url']) : null,
            ':start_time'           => $data['start_time'],
            ':end_time'             => $data['end_time'],
            ':capacity'             => isset($data['capacity']) ? (int) $data['capacity'] : 0,
            ':registration_deadline'=> !empty($data['registration_deadline']) ? $data['registration_deadline'] : null,
            ':requires_approval'    => !empty($data['requires_approval']) ? 1 : 0,
            ':status'               => $data['status'] ?? 'draft',
            ':updated_at'           => $now,
        ];

        Database::execute($sql, $params);
        return true;
    }

    /**
     * Soft-delete an event by setting deleted_at timestamp.
     */
    public function softDelete(int $id): bool
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE `events` 
                SET `deleted_at` = :deleted_at, `updated_at` = :updated_at 
                WHERE `id` = :id AND `deleted_at` IS NULL";

        $affected = Database::execute($sql, [':id' => $id, ':deleted_at' => $now, ':updated_at' => $now]);
        return $affected > 0;
    }

    /**
     * Restore a soft-deleted event by clearing deleted_at.
     */
    public function restore(int $id): bool
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE `events` 
                SET `deleted_at` = NULL, `updated_at` = :now 
                WHERE `id` = :id AND `deleted_at` IS NOT NULL";

        $affected = Database::execute($sql, [':id' => $id, ':now' => $now]);
        return $affected > 0;
    }

    /**
     * Aggregate event counts by status, optionally filtered by campaign.
     */
    public function countByStatus(?int $campaignId = null): array
    {
        $sql = "SELECT `status`, COUNT(*) AS `total` 
                FROM `events` 
                WHERE `deleted_at` IS NULL";
        $params = [];

        if ($campaignId !== null) {
            $sql .= " AND `campaign_id` = :campaign_id";
            $params[':campaign_id'] = $campaignId;
        }

        $sql .= " GROUP BY `status`";
        $rows = Database::fetchAll($sql, $params);

        $counts = [
            'draft'     => 0,
            'published' => 0,
            'ongoing'   => 0,
            'completed' => 0,
            'cancelled' => 0,
            'total'     => 0,
        ];

        foreach ($rows as $row) {
            $st = $row['status'] ?? '';
            $cnt = (int) ($row['total'] ?? 0);
            if (isset($counts[$st])) {
                $counts[$st] = $cnt;
            }
            $counts['total'] += $cnt;
        }

        return $counts;
    }

    /**
     * Get count of confirmed registrations for an event.
     */
    public function getConfirmedRegistrationCount(int $eventId): int
    {
        $sql = "SELECT COUNT(*) AS `total` 
                FROM `event_registrations` 
                WHERE `event_id` = :event_id AND `status` = 'confirmed'";
        $row = Database::fetch($sql, [':event_id' => $eventId]);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Find a public event by campaign slug and event slug.
     * Enforces public visibility: non-deleted, non-draft, active campaign.
     * Computes confirmed registration count and campaign metadata.
     */
    public function findPublicByCampaignAndSlug(string $campaignSlug, string $eventSlug): ?array
    {
        $sql = "SELECT e.*, 
                       c.title AS campaign_title, 
                       c.slug AS campaign_slug, 
                       c.theme AS campaign_theme,
                       c.status AS campaign_status,
                       (SELECT COUNT(*) 
                        FROM `event_registrations` er 
                        WHERE er.`event_id` = e.`id` AND er.`status` = 'confirmed'
                       ) AS confirmed_count
                FROM `events` e
                JOIN `campaigns` c ON e.`campaign_id` = c.`id`
                WHERE c.`slug` = :campaign_slug
                  AND e.`slug` = :event_slug
                  AND e.`deleted_at` IS NULL
                  AND c.`deleted_at` IS NULL
                  AND e.`status` IN ('published', 'ongoing', 'completed', 'cancelled')
                LIMIT 1";

        $row = Database::fetch($sql, [
            ':campaign_slug' => strtolower(trim($campaignSlug)),
            ':event_slug'    => strtolower(trim($eventSlug)),
        ]);

        return $row ?: null;
    }

    /**
     * Retrieve public upcoming events with optional filters (category, format, campaign_slug, search).
     * Enforces non-deleted, status = 'published', and start_time >= current timestamp.
     */
    public function getPublicUpcomingEvents(array $filters = [], int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $now = date('Y-m-d H:i:s');

        $sql = "SELECT e.*, 
                       c.title AS campaign_title, 
                       c.slug AS campaign_slug,
                       (SELECT COUNT(*) 
                        FROM `event_registrations` er 
                        WHERE er.`event_id` = e.`id` AND er.`status` = 'confirmed'
                       ) AS confirmed_count
                FROM `events` e
                JOIN `campaigns` c ON e.`campaign_id` = c.`id`
                WHERE e.`deleted_at` IS NULL
                  AND c.`deleted_at` IS NULL
                  AND e.`status` = 'published'
                  AND c.`status` = 'active'
                  AND e.`start_time` >= :now";

        $params = [':now' => $now];

        if (!empty($filters['category'])) {
            $sql .= " AND e.`category` = :category";
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['format'])) {
            $sql .= " AND e.`format` = :format";
            $params[':format'] = $filters['format'];
        }

        if (!empty($filters['campaign_slug'])) {
            $sql .= " AND c.`slug` = :campaign_slug";
            $params[':campaign_slug'] = strtolower(trim((string) $filters['campaign_slug']));
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (e.`title` LIKE :search OR e.`description` LIKE :search OR e.`venue_name` LIKE :search)";
            $params[':search'] = '%' . trim((string) $filters['search']) . '%';
        }

        $sql .= " ORDER BY e.`start_time` ASC, e.`id` ASC LIMIT {$limit}";

        return Database::fetchAll($sql, $params);
    }
}

