<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\CertificateException;
use PDO;
use Throwable;

/**
 * Chunk & Batch Oriented Certificate Generation Service
 * Enables scalable generation across 100/500/1000+ certificates without HTTP timeouts,
 * supporting progress tracking, chunked execution, and transactional recovery.
 */
class CertificateBatchService
{
    public const CHUNK_SIZE = 25;

    /**
     * Create a new generation batch with validated CSV records.
     */
    public static function createBatch(
        int $templateId,
        array $validRecords,
        int $invalidCount,
        ?int $userId,
        string $originalFilename,
        ?PDO $pdo = null
    ): array {
        $batchCode = 'BATCH-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $totalRecords = count($validRecords) + $invalidCount;
        $validCount = count($validRecords);

        // Store records payload in private storage
        $storageDir = self::getStorageDir('storage/batches');
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
        $dataPath = "storage/batches/{$batchCode}.json";
        $fullPath = self::resolveStoragePath($dataPath);
        file_put_contents($fullPath, json_encode($validRecords, JSON_PRETTY_PRINT));

        $sql = "INSERT INTO `v3_certificate_batches` (
                    `batch_code`, `template_id`, `total_records`, `valid_records`, `invalid_records`,
                    `generated_count`, `status`, `csv_filename`, `csv_data_path`, `generated_by`, `created_at`, `updated_at`
                ) VALUES (
                    :code, :tid, :tot, :val, :inv,
                    0, :status, :fname, :dpath, :uid, NOW(), NOW()
                )";

        $status = ($validCount === 0) ? 'completed' : 'pending';

        $params = [
            ':code'   => $batchCode,
            ':tid'    => $templateId,
            ':tot'    => $totalRecords,
            ':val'    => $validCount,
            ':inv'    => $invalidCount,
            ':status' => $status,
            ':fname'  => $originalFilename,
            ':dpath'  => $dataPath,
            ':uid'    => $userId,
        ];

        if ($pdo !== null) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $batchId = (int) $pdo->lastInsertId();
        } else {
            Database::execute($sql, $params);
            $batchId = (int) Database::lastInsertId();
        }

        return [
            'id'            => $batchId,
            'batch_code'    => $batchCode,
            'template_id'   => $templateId,
            'total_records' => $totalRecords,
            'valid_records' => $validCount,
            'invalid_count' => $invalidCount,
            'status'        => $status,
        ];
    }

    /**
     * Process a chunk of certificates for the given batch ID.
     *
     * @param int $batchId Primary ID of the batch
     * @param int $chunkSize Number of certificates to render and insert in this step
     * @param PDO|null $pdo Optional injected PDO instance
     * @return array Progress report metadata
     * @throws CertificateException
     */
    public static function processChunk(int $batchId, int $chunkSize = self::CHUNK_SIZE, ?PDO $pdo = null): array
    {
        $batch = self::getBatch($batchId, $pdo);
        if (!$batch) {
            throw new CertificateException("Batch #{$batchId} not found.", 404);
        }

        if ($batch['status'] === 'completed') {
            return [
                'batch_id'    => $batchId,
                'batch_code'  => $batch['batch_code'],
                'status'      => 'completed',
                'generated'   => (int) $batch['generated_count'],
                'processed'   => (int) $batch['generated_count'],
                'total_valid' => (int) $batch['valid_records'],
                'total'       => (int) $batch['valid_records'],
                'percentage'  => 100.0,
                'is_complete' => true,
                'completed'   => true,
            ];
        }

        $template = self::getTemplate((int) $batch['template_id'], $pdo);
        if (!$template) {
            throw new CertificateException("Template for Batch #{$batchId} not found.", 404);
        }

        $dataPath = self::resolveStoragePath((string) ($batch['csv_data_path'] ?? ''));
        if (!file_exists($dataPath)) {
            throw new CertificateException("Batch data storage file is missing.", 500);
        }

        $records = json_decode((string) file_get_contents($dataPath), true) ?: [];
        $totalValid = count($records);
        $offset = (int) $batch['generated_count'];

        // Get the slice for this chunk
        $slice = array_slice($records, $offset, $chunkSize);

        if (empty($slice)) {
            // Nothing left to process
            self::updateBatchStatus($batchId, 'completed', $totalValid, $pdo);
            return [
                'batch_id'    => $batchId,
                'batch_code'  => $batch['batch_code'],
                'status'      => 'completed',
                'generated'   => $totalValid,
                'processed'   => $totalValid,
                'total_valid' => $totalValid,
                'total'       => $totalValid,
                'percentage'  => 100.0,
                'is_complete' => true,
                'completed'   => true,
            ];
        }

        $year = date('Y');
        $batchCode = $batch['batch_code'];
        $pdfDir = self::getStorageDir("storage/certificates/pdf/{$year}/{$batchCode}");
        $imgDir = self::getStorageDir("storage/certificates/images/{$year}/{$batchCode}");

        if (!is_dir($pdfDir)) {
            @mkdir($pdfDir, 0755, true);
        }
        if (!is_dir($imgDir)) {
            @mkdir($imgDir, 0755, true);
        }

        $chunkSuccessCount = 0;

        foreach ($slice as $row) {
            $cid = CertificateIdGenerator::generateCertificateId($pdo);
            $token = CertificateIdGenerator::generateVerificationToken($pdo);

            $certData = array_merge($row, [
                'certificate_number' => $cid,
                'verification_token' => $token,
                'template_name'      => $template['name'],
            ]);

            // Render PDF and High-res JPG
            $pdfContent = CertificateRenderer::renderPdf($template, $certData, false);
            $jpgContent = CertificateRenderer::renderJpg($template, $certData, false);

            $pdfRelPath = "storage/certificates/pdf/{$year}/{$batchCode}/{$cid}.pdf";
            $imgRelPath = "storage/certificates/images/{$year}/{$batchCode}/{$cid}.jpg";

            file_put_contents(self::resolveStoragePath($pdfRelPath), $pdfContent);
            file_put_contents(self::resolveStoragePath($imgRelPath), $jpgContent);

            $snapshotJson = json_encode([
                'certificate_id'     => $cid,
                'verification_token' => $token,
                'template_id'        => $template['id'],
                'template_name'      => $template['name'],
                'data'               => $row,
                'generated_at'       => date('Y-m-d H:i:s'),
            ]);

            $insertSql = "INSERT INTO `v3_certificates` (
                            `certificate_id`, `verification_token`, `batch_id`, `template_id`,
                            `name`, `phone`, `phone_normalized`, `email`, `event_title`, `event_type`,
                            `date`, `place`, `certificate_data_json`, `pdf_path`, `image_path`,
                            `status`, `created_by`, `created_at`, `updated_at`
                        ) VALUES (
                            :cid, :token, :bid, :tid,
                            :name, :phone, :phone_norm, :email, :event_title, :event_type,
                            :date, :place, :snap, :pdf, :img,
                            'active', :uid, NOW(), NOW()
                        )";

            $insertParams = [
                ':cid'         => $cid,
                ':token'       => $token,
                ':bid'         => $batchId,
                ':tid'         => $template['id'],
                ':name'        => $row['name'],
                ':phone'       => $row['phone'],
                ':phone_norm'  => $row['phone_normalized'] ?? CsvValidationService::normalizePhoneNumber($row['phone']),
                ':email'       => $row['email'] ?? null,
                ':event_title' => $row['event_title'] ?? $template['name'],
                ':event_type'  => $row['event_type'] ?? $template['certificate_type'],
                ':date'        => $row['date'] ?? date('d F Y'),
                ':place'       => $row['place'] ?? null,
                ':snap'        => $snapshotJson,
                ':pdf'         => $pdfRelPath,
                ':img'         => $imgRelPath,
                ':uid'         => $batch['generated_by'] ?? null,
            ];

            if ($pdo !== null) {
                $stmt = $pdo->prepare($insertSql);
                $stmt->execute($insertParams);
            } else {
                Database::execute($insertSql, $insertParams);
            }

            $chunkSuccessCount++;
        }

        $newGeneratedCount = $offset + $chunkSuccessCount;
        $isFinished = ($newGeneratedCount >= $totalValid);
        $newStatus = $isFinished ? 'completed' : 'processing';

        self::updateBatchStatus($batchId, $newStatus, $newGeneratedCount, $pdo);

        $percentage = $totalValid > 0 ? round(($newGeneratedCount / $totalValid) * 100, 1) : 100.0;

        return [
            'batch_id'    => $batchId,
            'batch_code'  => $batch['batch_code'],
            'status'      => $newStatus,
            'generated'   => $newGeneratedCount,
            'processed'   => $newGeneratedCount,
            'total_valid' => $totalValid,
            'total'       => $totalValid,
            'failed'      => (int) ($batch['invalid_records'] ?? 0),
            'percentage'  => $percentage,
            'is_complete' => $isFinished,
            'completed'   => $isFinished,
        ];
    }

    /**
     * Get batch record by ID.
     */
    public static function getBatch(int $batchId, ?PDO $pdo = null): ?array
    {
        $sql = "SELECT * FROM `v3_certificate_batches` WHERE `id` = :id LIMIT 1";
        if ($pdo !== null) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $batchId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
        return Database::fetch($sql, [':id' => $batchId]);
    }

    /**
     * Get template record by ID.
     */
    public static function getTemplate(int $templateId, ?PDO $pdo = null): ?array
    {
        $sql = "SELECT * FROM `v3_certificate_templates` WHERE `id` = :id LIMIT 1";
        if ($pdo !== null) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $templateId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
        return Database::fetch($sql, [':id' => $templateId]);
    }

    /**
     * Update batch status and generated count.
     */
    private static function updateBatchStatus(int $batchId, string $status, int $generatedCount, ?PDO $pdo = null): void
    {
        $sql = "UPDATE `v3_certificate_batches`
                SET `status` = :st, `generated_count` = :gc, `updated_at` = NOW()
                WHERE `id` = :id";
        $params = [':st' => $status, ':gc' => $generatedCount, ':id' => $batchId];

        if ($pdo !== null) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            Database::execute($sql, $params);
        }
    }

    private static function resolveStoragePath(string $relPath): string
    {
        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        return $appRoot . '/' . ltrim($relPath, '/\\');
    }

    private static function getStorageDir(string $relPath): string
    {
        return self::resolveStoragePath($relPath);
    }
}
