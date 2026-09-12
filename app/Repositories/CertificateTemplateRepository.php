<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Certificate Template Repository
 * Manages certificate template design configurations, backgrounds, seals,
 * signatures, and layout coordinates per event.
 */
class CertificateTemplateRepository
{
    /**
     * Find certificate template by Event ID.
     */
    public function findByEventId(int $eventId): ?array
    {
        $sql = "SELECT * FROM `certificate_templates` WHERE `event_id` = :event_id LIMIT 1";
        $row = Database::fetch($sql, [':event_id' => $eventId]);

        if ($row && !empty($row['layout_config'])) {
            $row['layout_config'] = is_string($row['layout_config'])
                ? json_decode($row['layout_config'], true)
                : $row['layout_config'];
        }

        return $row ?: null;
    }

    /**
     * Create or update template for an event.
     */
    public function saveOrUpdate(int $eventId, array $data): int
    {
        $existing = $this->findByEventId($eventId);
        $now = date('Y-m-d H:i:s');

        $layoutJson = isset($data['layout_config'])
            ? (is_string($data['layout_config']) ? $data['layout_config'] : json_encode($data['layout_config']))
            : json_encode($this->getDefaultLayoutConfig());

        if ($existing) {
            $sql = "UPDATE `certificate_templates` SET
                        `background_image_path` = :background_image_path,
                        `seal_image_path` = :seal_image_path,
                        `signature1_image_path` = :signature1_image_path,
                        `signature1_name` = :signature1_name,
                        `signature1_designation` = :signature1_designation,
                        `signature2_image_path` = :signature2_image_path,
                        `signature2_name` = :signature2_name,
                        `signature2_designation` = :signature2_designation,
                        `layout_config` = :layout_config,
                        `updated_at` = :updated_at
                    WHERE `event_id` = :event_id";

            Database::execute($sql, [
                ':background_image_path' => $data['background_image_path'] ?? $existing['background_image_path'],
                ':seal_image_path'       => $data['seal_image_path'] ?? $existing['seal_image_path'],
                ':signature1_image_path' => $data['signature1_image_path'] ?? $existing['signature1_image_path'],
                ':signature1_name'       => $data['signature1_name'] ?? $existing['signature1_name'],
                ':signature1_designation'=> $data['signature1_designation'] ?? $existing['signature1_designation'],
                ':signature2_image_path' => $data['signature2_image_path'] ?? $existing['signature2_image_path'],
                ':signature2_name'       => $data['signature2_name'] ?? $existing['signature2_name'],
                ':signature2_designation'=> $data['signature2_designation'] ?? $existing['signature2_designation'],
                ':layout_config'         => $layoutJson,
                ':updated_at'            => $now,
                ':event_id'              => $eventId,
            ]);

            return (int) $existing['id'];
        }

        $sql = "INSERT INTO `certificate_templates` (
                    `event_id`,
                    `background_image_path`,
                    `seal_image_path`,
                    `signature1_image_path`,
                    `signature1_name`,
                    `signature1_designation`,
                    `signature2_image_path`,
                    `signature2_name`,
                    `signature2_designation`,
                    `layout_config`,
                    `created_at`,
                    `updated_at`
                ) VALUES (
                    :event_id,
                    :background_image_path,
                    :seal_image_path,
                    :signature1_image_path,
                    :signature1_name,
                    :signature1_designation,
                    :signature2_image_path,
                    :signature2_name,
                    :signature2_designation,
                    :layout_config,
                    :created_at,
                    :updated_at
                )";

        Database::execute($sql, [
            ':event_id'              => $eventId,
            ':background_image_path' => $data['background_image_path'] ?? null,
            ':seal_image_path'       => $data['seal_image_path'] ?? null,
            ':signature1_image_path' => $data['signature1_image_path'] ?? null,
            ':signature1_name'       => $data['signature1_name'] ?? null,
            ':signature1_designation'=> $data['signature1_designation'] ?? null,
            ':signature2_image_path' => $data['signature2_image_path'] ?? null,
            ':signature2_name'       => $data['signature2_name'] ?? null,
            ':signature2_designation'=> $data['signature2_designation'] ?? null,
            ':layout_config'         => $layoutJson,
            ':created_at'            => $now,
            ':updated_at'            => $now,
        ]);

        return (int) Database::lastInsertId();
    }

    /**
     * Default layout configuration coordinates and styling.
     */
    public function getDefaultLayoutConfig(): array
    {
        return [
            'width' => 1920,
            'height' => 1080,
            'orientation' => 'landscape',
            'title' => [
                'text' => 'CERTIFICATE OF PARTICIPATION',
                'x' => 960,
                'y' => 280,
                'font_size' => 44,
                'color' => '#1e293b',
                'align' => 'center',
            ],
            'subtitle' => [
                'text' => 'THIS IS PROUDLY PRESENTED TO',
                'x' => 960,
                'y' => 380,
                'font_size' => 18,
                'color' => '#64748b',
                'align' => 'center',
            ],
            'recipient_name' => [
                'x' => 960,
                'y' => 470,
                'font_size' => 48,
                'color' => '#0f172a',
                'font_weight' => 'bold',
                'align' => 'center',
            ],
            'body_text' => [
                'text' => 'for actively participating in the event {Event} held on {EventDate}.',
                'x' => 960,
                'y' => 560,
                'font_size' => 22,
                'color' => '#334155',
                'align' => 'center',
                'max_width' => 1400,
            ],
            'certificate_id' => [
                'prefix' => 'Certificate ID: ',
                'x' => 960,
                'y' => 640,
                'font_size' => 16,
                'color' => '#475569',
                'align' => 'center',
            ],
            'qr_code' => [
                'x' => 1650,
                'y' => 840,
                'size' => 140,
            ],
            'seal' => [
                'x' => 960,
                'y' => 840,
                'size' => 130,
            ],
            'signature1' => [
                'x' => 350,
                'y' => 840,
                'width' => 220,
                'height' => 80,
            ],
            'signature2' => [
                'x' => 1570,
                'y' => 840,
                'width' => 220,
                'height' => 80,
            ],
        ];
    }
}
