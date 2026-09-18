<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\ValidationException;
use PDO;

/**
 * Secure Font Management Service
 * Validates font headers/magic bytes, extensions, and file sizes.
 * Prevents arbitrary executable uploads.
 */
class FontManagementService
{
    public const MAX_FONT_SIZE_BYTES = 5242880; // 5 MB
    public const ALLOWED_EXTENSIONS = ['ttf', 'otf'];

    /**
     * Upload and register a new custom font.
     */
    public static function uploadFont(array $file, string $fontName, ?int $userId, ?PDO $pdo = null): array
    {
        $name = trim($fontName);
        if ($name === '' || strlen($name) < 2) {
            throw new ValidationException('Font name must be at least 2 characters.', ['font_name' => 'Name too short.']);
        }

        if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            throw new ValidationException('No font file was uploaded.', ['font_file' => 'File missing.']);
        }

        if ($file['size'] > self::MAX_FONT_SIZE_BYTES) {
            throw new ValidationException('Font file size exceeds maximum limit of 5 MB.', ['font_file' => 'File too large.']);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new ValidationException('Only TrueType (.ttf) and OpenType (.otf) fonts are allowed.', ['font_file' => 'Invalid file extension.']);
        }

        // Validate Magic Bytes
        $handle = fopen($file['tmp_name'], 'rb');
        $magic = fread($handle, 4);
        fclose($handle);

        $isValidMagic = false;
        // TTF magic: 0x00010000 or 'true'
        if ($magic === "\x00\x01\x00\x00" || $magic === "true") {
            $isValidMagic = true;
        }
        // OTF magic: 'OTTO'
        if ($magic === "OTTO") {
            $isValidMagic = true;
        }

        if (!$isValidMagic) {
            throw new ValidationException('Invalid font file signature. Executable or corrupted files are prohibited.', ['font_file' => 'Invalid font signature.']);
        }

        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        $fontDir = $appRoot . '/storage/fonts';
        if (!is_dir($fontDir)) {
            @mkdir($fontDir, 0755, true);
        }

        $safeBase = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $name));
        $hash = substr(bin2hex(random_bytes(4)), 0, 8);
        $filename = "{$safeBase}_{$hash}.{$ext}";
        $destPath = "{$fontDir}/{$filename}";

        if (!move_uploaded_file($file['tmp_name'], $destPath) && !copy($file['tmp_name'], $destPath)) {
            throw new ValidationException('Failed to save font file to disk.');
        }

        $relPath = "storage/fonts/{$filename}";
        $fontFamily = str_replace(['_', '-'], ' ', ucwords($safeBase));

        $sql = "INSERT INTO `certificate_fonts` (
                    `name`, `font_family`, `file_path`, `file_size`, `format`, `is_active`, `created_by`, `created_at`
                ) VALUES (
                    :name, :fam, :path, :sz, :fmt, 1, :uid, NOW()
                )";
        $params = [
            ':name' => $name,
            ':fam'  => $fontFamily,
            ':path' => $relPath,
            ':sz'   => $file['size'],
            ':fmt'  => $ext,
            ':uid'  => $userId,
        ];

        if ($pdo !== null) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $id = (int) $pdo->lastInsertId();
        } else {
            Database::execute($sql, $params);
            $id = (int) Database::lastInsertId();
        }

        return [
            'id'          => $id,
            'name'        => $name,
            'font_family' => $fontFamily,
            'file_path'   => $relPath,
        ];
    }

    /**
     * List all registered fonts.
     */
    public static function getAllFonts(?PDO $pdo = null): array
    {
        $sql = "SELECT * FROM `certificate_fonts` ORDER BY `id` DESC";
        if ($pdo !== null) {
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        return Database::fetchAll($sql);
    }

    /**
     * Delete a font safely.
     */
    public static function deleteFont(int $id, ?PDO $pdo = null): bool
    {
        $sql = "SELECT * FROM `certificate_fonts` WHERE `id` = :id LIMIT 1";
        $row = ($pdo !== null)
            ? ($pdo->query("SELECT * FROM `certificate_fonts` WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC) ?: null)
            : Database::fetch($sql, [':id' => $id]);

        if ($row) {
            $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
            $full = $appRoot . '/' . ltrim($row['file_path'], '/\\');
            if (file_exists($full)) {
                @unlink($full);
            }

            $delSql = "DELETE FROM `certificate_fonts` WHERE `id` = :id";
            if ($pdo !== null) {
                $stmt = $pdo->prepare($delSql);
                $stmt->execute([':id' => $id]);
                return $stmt->rowCount() > 0;
            }
            return Database::execute($delSql, [':id' => $id]) > 0;
        }

        return false;
    }
}
