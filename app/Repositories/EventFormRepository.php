<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Event Form Repository
 * Handles storage for Event Registration Forms (1 Event = 1 Form) and custom form fields.
 */
class EventFormRepository
{
    /**
     * Find form by ID.
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT ef.*, e.title AS event_title, e.status AS event_status, e.start_time, e.end_time, e.venue_name, e.format AS event_format
            FROM `event_forms` ef
            JOIN `events` e ON ef.event_id = e.id
            WHERE ef.id = :id
            LIMIT 1
        ";
        return Database::fetch($sql, [':id' => $id]);
    }

    /**
     * Find form by associated event ID.
     */
    public function findByEventId(int $eventId): ?array
    {
        $sql = "
            SELECT ef.*, e.title AS event_title, e.status AS event_status, e.start_time, e.end_time, e.venue_name, e.format AS event_format
            FROM `event_forms` ef
            JOIN `events` e ON ef.event_id = e.id
            WHERE ef.event_id = :eid
            LIMIT 1
        ";
        return Database::fetch($sql, [':eid' => $eventId]);
    }

    /**
     * Find form by public unique slug.
     */
    public function findBySlug(string $slug): ?array
    {
        $sql = "
            SELECT ef.*, e.title AS event_title, e.status AS event_status, e.start_time, e.end_time, 
                   e.venue_name, e.venue_address, e.format AS event_format, e.collaboration_with, e.collaboration_logo
            FROM `event_forms` ef
            JOIN `events` e ON ef.event_id = e.id
            WHERE ef.slug = :slug AND e.deleted_at IS NULL
            LIMIT 1
        ";
        return Database::fetch($sql, [':slug' => strtolower(trim($slug))]);
    }

    /**
     * Check if a registration slug already exists.
     */
    public function slugExists(string $slug, ?int $excludeFormId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `event_forms` WHERE `slug` = :slug";
        $params = [':slug' => strtolower(trim($slug))];

        if ($excludeFormId !== null) {
            $sql .= " AND `id` != :exclude_id";
            $params[':exclude_id'] = $excludeFormId;
        }

        $stmt = Database::query($sql, $params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * List all event registration forms.
     */
    public function getAllForms(): array
    {
        $sql = "
            SELECT ef.*, e.title AS event_title, e.status AS event_status, e.start_time, e.end_time,
                   (SELECT COUNT(*) FROM `event_registrations` er WHERE er.event_id = ef.event_id) AS registration_count
            FROM `event_forms` ef
            JOIN `events` e ON ef.event_id = e.id
            WHERE e.deleted_at IS NULL
            ORDER BY e.start_time DESC
        ";
        return Database::fetchAll($sql);
    }

    /**
     * Create event form record (called during atomic event creation).
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO `event_forms` (
                    `event_id`, `form_title`, `slug`, `banner_path`, `photo_upload_enabled`,
                    `location_access_required`, `whatsapp_group_url`, `whatsapp_auto_redirect`,
                    `whatsapp_countdown_seconds`, `custom_success_message`, `status`, `created_at`, `updated_at`
                ) VALUES (
                    :event_id, :form_title, :slug, :banner_path, :photo_upload_enabled,
                    :location_access_required, :whatsapp_group_url, :whatsapp_auto_redirect,
                    :whatsapp_countdown_seconds, :custom_success_message, :status, :created_at, :updated_at
                )";

        Database::execute($sql, [
            ':event_id'                   => (int) $data['event_id'],
            ':form_title'                 => trim($data['form_title']),
            ':slug'                       => strtolower(trim($data['slug'])),
            ':banner_path'                => $data['banner_path'] ?? null,
            ':photo_upload_enabled'       => (int) ($data['photo_upload_enabled'] ?? 0),
            ':location_access_required'   => (int) ($data['location_access_required'] ?? 0),
            ':whatsapp_group_url'         => !empty($data['whatsapp_group_url']) ? trim((string) $data['whatsapp_group_url']) : null,
            ':whatsapp_auto_redirect'     => (int) ($data['whatsapp_auto_redirect'] ?? 0),
            ':whatsapp_countdown_seconds' => max(1, (int) ($data['whatsapp_countdown_seconds'] ?? 5)),
            ':custom_success_message'     => !empty($data['custom_success_message']) ? trim((string) $data['custom_success_message']) : null,
            ':status'                     => $data['status'] ?? 'published',
            ':created_at'                 => $now,
            ':updated_at'                 => $now,
        ]);

        return (int) Database::lastInsertId();
    }

    /**
     * Update event form settings.
     */
    public function update(int $id, array $data): bool
    {
        $now = date('Y-m-d H:i:s');
        $fields = ['updated_at = :now'];
        $params = [':id' => $id, ':now' => $now];

        if (isset($data['form_title'])) {
            $fields[] = '`form_title` = :form_title';
            $params[':form_title'] = trim((string) $data['form_title']);
        }
        if (isset($data['slug'])) {
            $fields[] = '`slug` = :slug';
            $params[':slug'] = strtolower(trim((string) $data['slug']));
        }
        if (array_key_exists('banner_path', $data)) {
            $fields[] = '`banner_path` = :banner_path';
            $params[':banner_path'] = $data['banner_path'];
        }
        if (isset($data['photo_upload_enabled'])) {
            $fields[] = '`photo_upload_enabled` = :photo_upload_enabled';
            $params[':photo_upload_enabled'] = (int) $data['photo_upload_enabled'];
        }
        if (isset($data['location_access_required'])) {
            $fields[] = '`location_access_required` = :location_access_required';
            $params[':location_access_required'] = (int) $data['location_access_required'];
        }
        if (array_key_exists('whatsapp_group_url', $data)) {
            $fields[] = '`whatsapp_group_url` = :whatsapp_group_url';
            $params[':whatsapp_group_url'] = !empty($data['whatsapp_group_url']) ? trim((string) $data['whatsapp_group_url']) : null;
        }
        if (isset($data['whatsapp_auto_redirect'])) {
            $fields[] = '`whatsapp_auto_redirect` = :whatsapp_auto_redirect';
            $params[':whatsapp_auto_redirect'] = (int) $data['whatsapp_auto_redirect'];
        }
        if (isset($data['whatsapp_countdown_seconds'])) {
            $fields[] = '`whatsapp_countdown_seconds` = :whatsapp_countdown_seconds';
            $params[':whatsapp_countdown_seconds'] = max(1, (int) $data['whatsapp_countdown_seconds']);
        }
        if (array_key_exists('custom_success_message', $data)) {
            $fields[] = '`custom_success_message` = :custom_success_message';
            $params[':custom_success_message'] = !empty($data['custom_success_message']) ? trim((string) $data['custom_success_message']) : null;
        }
        if (isset($data['status'])) {
            $fields[] = '`status` = :status';
            $params[':status'] = (string) $data['status'];
        }

        $sql = "UPDATE `event_forms` SET " . implode(', ', $fields) . " WHERE `id` = :id";
        return Database::execute($sql, $params) > 0;
    }

    /**
     * Get all custom and standard fields for a form in sort order.
     */
    public function getFields(int $formId): array
    {
        $sql = "SELECT * FROM `form_fields` WHERE `form_id` = :form_id ORDER BY `sort_order` ASC, `id` ASC";
        return Database::fetchAll($sql, [':form_id' => $formId]);
    }

    /**
     * Add a custom field to a form.
     */
    public function addField(int $formId, array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO `form_fields` (
                    `form_id`, `field_key`, `field_label`, `field_type`, `is_required`,
                    `is_locked`, `sort_order`, `options_json`, `placeholder`, `help_text`, `created_at`, `updated_at`
                ) VALUES (
                    :form_id, :field_key, :field_label, :field_type, :is_required,
                    :is_locked, :sort_order, :options_json, :placeholder, :help_text, :created_at, :updated_at
                )";

        Database::execute($sql, [
            ':form_id'      => $formId,
            ':field_key'    => strtolower(preg_replace('/[^a-z0-9_]/', '_', trim($data['field_key']))),
            ':field_label'  => trim($data['field_label']),
            ':field_type'   => $data['field_type'] ?? 'text',
            ':is_required'  => (int) ($data['is_required'] ?? 0),
            ':is_locked'    => (int) ($data['is_locked'] ?? 0),
            ':sort_order'   => (int) ($data['sort_order'] ?? 10),
            ':options_json' => !empty($data['options_json']) ? (is_string($data['options_json']) ? $data['options_json'] : json_encode($data['options_json'])) : null,
            ':placeholder'  => !empty($data['placeholder']) ? trim((string) $data['placeholder']) : null,
            ':help_text'    => !empty($data['help_text']) ? trim((string) $data['help_text']) : null,
            ':created_at'   => $now,
            ':updated_at'   => $now,
        ]);

        return (int) Database::lastInsertId();
    }

    /**
     * Find field by ID.
     */
    public function findFieldById(int $fieldId): ?array
    {
        return Database::fetch("SELECT * FROM `form_fields` WHERE `id` = :id LIMIT 1", [':id' => $fieldId]);
    }

    /**
     * Update custom field.
     */
    public function updateField(int $fieldId, array $data): bool
    {
        $field = $this->findFieldById($fieldId);
        if (!$field) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $fields = ['updated_at = :now'];
        $params = [':id' => $fieldId, ':now' => $now];

        // Locked fields cannot have their field_key or field_label modified
        if (!$field['is_locked']) {
            if (isset($data['field_label'])) {
                $fields[] = '`field_label` = :label';
                $params[':label'] = trim((string) $data['field_label']);
            }
            if (isset($data['field_type'])) {
                $fields[] = '`field_type` = :type';
                $params[':type'] = (string) $data['field_type'];
            }
            if (isset($data['is_required'])) {
                $fields[] = '`is_required` = :req';
                $params[':req'] = (int) $data['is_required'];
            }
        }

        if (isset($data['sort_order'])) {
            $fields[] = '`sort_order` = :ord';
            $params[':ord'] = (int) $data['sort_order'];
        }
        if (array_key_exists('placeholder', $data)) {
            $fields[] = '`placeholder` = :ph';
            $params[':ph'] = !empty($data['placeholder']) ? trim((string) $data['placeholder']) : null;
        }
        if (array_key_exists('help_text', $data)) {
            $fields[] = '`help_text` = :ht';
            $params[':ht'] = !empty($data['help_text']) ? trim((string) $data['help_text']) : null;
        }
        if (array_key_exists('options_json', $data)) {
            $fields[] = '`options_json` = :opt';
            $params[':opt'] = !empty($data['options_json']) ? (is_string($data['options_json']) ? $data['options_json'] : json_encode($data['options_json'])) : null;
        }

        $sql = "UPDATE `form_fields` SET " . implode(', ', $fields) . " WHERE `id` = :id";
        return Database::execute($sql, $params) > 0;
    }

    /**
     * Delete custom field (locked fields cannot be deleted).
     */
    public function deleteField(int $fieldId): bool
    {
        $field = $this->findFieldById($fieldId);
        if (!$field || $field['is_locked']) {
            return false;
        }

        return Database::execute("DELETE FROM `form_fields` WHERE `id` = :id", [':id' => $fieldId]) > 0;
    }

    /**
     * Update sort order for fields.
     */
    public function updateFieldSortOrders(int $formId, array $orderMap): void
    {
        $sql = "UPDATE `form_fields` SET `sort_order` = :ord WHERE `id` = :id AND `form_id` = :form_id";
        foreach ($orderMap as $fieldId => $order) {
            Database::execute($sql, [
                ':ord'     => (int) $order,
                ':id'      => (int) $fieldId,
                ':form_id' => $formId,
            ]);
        }
    }

    /**
     * Check if a form has registrations.
     */
    public function countRegistrations(int $formId): int
    {
        $form = $this->findById($formId);
        if (!$form) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM `event_registrations` WHERE `event_id` = :event_id";
        $stmt = Database::query($sql, [':event_id' => $form['event_id']]);
        return (int) $stmt->fetchColumn();
    }
}
