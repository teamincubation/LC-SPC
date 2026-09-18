<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\CertificateRenderer;
use PDO;

/**
 * Dedicated Repository for v3_certificate_templates table
 */
class V3CertificateTemplateRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function getAll(bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM `v3_certificate_templates`";
        if ($activeOnly) {
            $sql .= " WHERE `status` = 'active'";
        }
        $sql .= " ORDER BY `id` DESC";

        $rows = $this->fetchAll($sql);
        foreach ($rows as &$r) {
            $r['layout_config'] = is_string($r['layout_config']) ? json_decode($r['layout_config'], true) : $r['layout_config'];
            $r['required_variables'] = is_string($r['required_variables']) ? json_decode($r['required_variables'], true) : $r['required_variables'];
        }
        return $rows;
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM `v3_certificate_templates` WHERE `id` = :id LIMIT 1";
        $row = $this->fetchOne($sql, [':id' => $id]);
        if ($row) {
            $row['layout_config'] = is_string($row['layout_config']) ? json_decode($row['layout_config'], true) : $row['layout_config'];
            $row['required_variables'] = is_string($row['required_variables']) ? json_decode($row['required_variables'], true) : $row['required_variables'];
        }
        return $row;
    }

    public function create(array $data): int
    {
        $layoutJson = isset($data['layout_config'])
            ? (is_string($data['layout_config']) ? $data['layout_config'] : json_encode($data['layout_config']))
            : json_encode(['elements' => CertificateRenderer::getDefaultElements()]);

        $varsJson = isset($data['required_variables'])
            ? (is_string($data['required_variables']) ? $data['required_variables'] : json_encode($data['required_variables']))
            : json_encode(['name', 'phone', 'certificate_number']);

        $sql = "INSERT INTO `v3_certificate_templates` (
                    `name`, `description`, `certificate_type`, `status`,
                    `background_image_path`, `seal_image_path`,
                    `signature1_image_path`, `signature1_name`, `signature1_designation`,
                    `signature2_image_path`, `signature2_name`, `signature2_designation`,
                    `layout_config`, `required_variables`, `created_by`, `created_at`, `updated_at`
                ) VALUES (
                    :name, :desc, :type, :status,
                    :bg, :seal,
                    :sig1, :s1name, :s1desig,
                    :sig2, :s2name, :s2desig,
                    :layout, :vars, :uid, NOW(), NOW()
                )";

        $params = [
            ':name'    => $data['name'] ?? 'Untitled Template',
            ':desc'    => $data['description'] ?? null,
            ':type'    => $data['certificate_type'] ?? 'participation',
            ':status'  => $data['status'] ?? 'active',
            ':bg'      => $data['background_image_path'] ?? null,
            ':seal'    => $data['seal_image_path'] ?? null,
            ':sig1'    => $data['signature1_image_path'] ?? null,
            ':s1name'  => $data['signature1_name'] ?? null,
            ':s1desig' => $data['signature1_designation'] ?? null,
            ':sig2'    => $data['signature2_image_path'] ?? null,
            ':s2name'  => $data['signature2_name'] ?? null,
            ':s2desig' => $data['signature2_designation'] ?? null,
            ':layout'  => $layoutJson,
            ':vars'    => $varsJson,
            ':uid'     => $data['created_by'] ?? null,
        ];

        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $this->pdo->lastInsertId();
        }

        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $existing = $this->findById($id);
        if (!$existing) {
            return false;
        }

        $layoutJson = isset($data['layout_config'])
            ? (is_string($data['layout_config']) ? $data['layout_config'] : json_encode($data['layout_config']))
            : (is_string($existing['layout_config']) ? $existing['layout_config'] : json_encode($existing['layout_config']));

        $varsJson = isset($data['required_variables'])
            ? (is_string($data['required_variables']) ? $data['required_variables'] : json_encode($data['required_variables']))
            : (is_string($existing['required_variables']) ? $existing['required_variables'] : json_encode($existing['required_variables']));

        $sql = "UPDATE `v3_certificate_templates` SET
                    `name` = :name,
                    `description` = :desc,
                    `certificate_type` = :type,
                    `status` = :status,
                    `background_image_path` = :bg,
                    `seal_image_path` = :seal,
                    `signature1_image_path` = :sig1,
                    `signature1_name` = :s1name,
                    `signature1_designation` = :s1desig,
                    `signature2_image_path` = :sig2,
                    `signature2_name` = :s2name,
                    `signature2_designation` = :s2desig,
                    `layout_config` = :layout,
                    `required_variables` = :vars,
                    `updated_at` = NOW()
                WHERE `id` = :id";

        $params = [
            ':id'      => $id,
            ':name'    => $data['name'] ?? $existing['name'],
            ':desc'    => array_key_exists('description', $data) ? $data['description'] : $existing['description'],
            ':type'    => $data['certificate_type'] ?? $existing['certificate_type'],
            ':status'  => $data['status'] ?? $existing['status'],
            ':bg'      => array_key_exists('background_image_path', $data) ? $data['background_image_path'] : $existing['background_image_path'],
            ':seal'    => array_key_exists('seal_image_path', $data) ? $data['seal_image_path'] : $existing['seal_image_path'],
            ':sig1'    => array_key_exists('signature1_image_path', $data) ? $data['signature1_image_path'] : $existing['signature1_image_path'],
            ':s1name'  => array_key_exists('signature1_name', $data) ? $data['signature1_name'] : $existing['signature1_name'],
            ':s1desig' => array_key_exists('signature1_designation', $data) ? $data['signature1_designation'] : $existing['signature1_designation'],
            ':sig2'    => array_key_exists('signature2_image_path', $data) ? $data['signature2_image_path'] : $existing['signature2_image_path'],
            ':s2name'  => array_key_exists('signature2_name', $data) ? $data['signature2_name'] : $existing['signature2_name'],
            ':s2desig' => array_key_exists('signature2_designation', $data) ? $data['signature2_designation'] : $existing['signature2_designation'],
            ':layout'  => $layoutJson,
            ':vars'    => $varsJson,
        ];

        return $this->execute($sql, $params) > 0;
    }

    public function duplicate(int $id, ?int $userId): ?int
    {
        $tpl = $this->findById($id);
        if (!$tpl) {
            return null;
        }

        $copyData = $tpl;
        $copyData['name'] = $tpl['name'] . ' (Copy)';
        $copyData['created_by'] = $userId;

        return $this->create($copyData);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM `v3_certificate_templates` WHERE `id` = :id";
        return $this->execute($sql, [':id' => $id]) > 0;
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
