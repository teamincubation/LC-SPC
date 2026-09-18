<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\CertificateException;
use App\Core\Exceptions\ValidationException;
use PDO;

/**
 * Cryptographically Secure Certificate ID & Verification Token Generator
 * Enforces minimum 40 bits of entropy, non-sequential generation via random_bytes(),
 * database UNIQUE verification, and collision retry loops.
 */
class CertificateIdGenerator
{
    public const MIN_ENTROPY_BITS = 40.0;
    public const MIN_SEGMENT_LENGTH = 4;
    public const MIN_SEGMENT_COUNT = 2; // At least 2 segments of 4 chars = 8 chars
    public const MIN_CHARSET_SIZE = 16;
    public const DEFAULT_CHARSET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // 32 chars: 32^8 = 2^40 bits

    /**
     * Calculate Shannon entropy in bits for a given character count and character set.
     */
    public static function calculateEntropyBits(int $totalChars, int $charsetSize): float
    {
        if ($totalChars <= 0 || $charsetSize <= 1) {
            return 0.0;
        }
        return $totalChars * (log($charsetSize) / log(2));
    }

    /**
     * Validate ID format configuration against minimum entropy constraints.
     * Prevents administrators from setting insecure, guessable, or dangerously short IDs.
     *
     * @throws ValidationException
     */
    public static function validateConfiguration(array $config): void
    {
        $prefix = trim((string) ($config['cert_id_prefix'] ?? 'LC'));
        $segCount = (int) ($config['cert_id_segment_count'] ?? 2);
        $segLength = (int) ($config['cert_id_segment_length'] ?? 4);
        $charset = (string) ($config['cert_id_charset'] ?? self::DEFAULT_CHARSET);

        // Sanitize charset: unique uppercase characters only
        $uniqueChars = count(array_unique(str_split($charset)));
        if ($uniqueChars < self::MIN_CHARSET_SIZE) {
            throw new ValidationException(
                "Certificate ID charset must contain at least " . self::MIN_CHARSET_SIZE . " unique symbols to ensure non-predictability.",
                ['cert_id_charset' => 'Must contain at least ' . self::MIN_CHARSET_SIZE . ' unique symbols.']
            );
        }

        $totalRandomChars = $segCount * $segLength;
        if ($totalRandomChars < 8) {
            throw new ValidationException(
                "Certificate ID must contain at least 8 random characters to prevent enumeration.",
                ['cert_id_segment_length' => 'Total random characters (segments × length) must be at least 8.']
            );
        }

        $entropyBits = self::calculateEntropyBits($totalRandomChars, $uniqueChars);
        if ($entropyBits < self::MIN_ENTROPY_BITS) {
            throw new ValidationException(
                sprintf("Certificate ID entropy (%.1f bits) is below the minimum required standard (%.1f bits).", $entropyBits, self::MIN_ENTROPY_BITS),
                ['entropy' => 'Insufficient cryptographic entropy. Increase segment length or character set.']
            );
        }
    }

    /**
     * Generate an unbiased random string from a character set using CSPRNG random_bytes().
     */
    public static function generateSecureRandomString(int $length, string $charset): string
    {
        $charsetLen = strlen($charset);
        if ($charsetLen < 2) {
            throw new CertificateException('Invalid character set length for ID generation.', 500);
        }

        // Rejection sampling mask for unbiased distribution
        $mask = 1;
        while ($mask < $charsetLen) {
            $mask = ($mask << 1) | 1;
        }

        $result = '';
        while (strlen($result) < $length) {
            $bytes = random_bytes($length * 2);
            $byteCount = strlen($bytes);
            for ($i = 0; $i < $byteCount && strlen($result) < $length; $i++) {
                $val = ord($bytes[$i]) & $mask;
                if ($val < $charsetLen) {
                    $result .= $charset[$val];
                }
            }
        }

        return $result;
    }

    /**
     * Generate a complete, formatted Certificate ID with collision retry against v3_certificates table.
     * Example outputs:
     *   LC26-X7Q9-M4KP
     *   LC-SPC-26-X7Q9-M4KP
     *
     * @throws CertificateException on collision exhaustion
     */
    public static function generateCertificateId(?PDO $pdo = null, array $settingsOverride = []): string
    {
        $settings = array_merge(self::loadSettings($pdo), $settingsOverride);

        $prefix = strtoupper(trim((string) ($settings['cert_id_prefix'] ?? 'LC')));
        $includeYear = !empty($settings['cert_id_include_year']) && $settings['cert_id_include_year'] !== '0';
        $separator = (string) ($settings['cert_id_separator'] ?? '-');
        $segCount = max(2, (int) ($settings['cert_id_segment_count'] ?? 2));
        $segLength = max(4, (int) ($settings['cert_id_segment_length'] ?? 4));
        $charset = (string) ($settings['cert_id_charset'] ?? self::DEFAULT_CHARSET);

        $yearSegment = $includeYear ? date('y') : '';

        // Collision retry loop
        $maxRetries = 15;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $segments = [];
            for ($s = 0; $s < $segCount; $s++) {
                $segments[] = self::generateSecureRandomString($segLength, $charset);
            }
            $randomPart = implode($separator, $segments);

            $idParts = [];
            if ($prefix !== '') {
                $idParts[] = $prefix . $yearSegment;
            } elseif ($yearSegment !== '') {
                $idParts[] = $yearSegment;
            }
            $idParts[] = $randomPart;

            $candidateId = implode($separator, $idParts);

            if (!self::isCertificateIdTaken($candidateId, $pdo)) {
                return $candidateId;
            }
        }

        throw new CertificateException("Failed to generate a unique Certificate ID after {$maxRetries} collision retries.", 500);
    }

    /**
     * Generate a 256-bit CSPRNG hex verification token (64 hex characters) with collision retry.
     */
    public static function generateVerificationToken(?PDO $pdo = null): string
    {
        $maxRetries = 10;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $token = bin2hex(random_bytes(32)); // 256 bits of entropy
            if (!self::isVerificationTokenTaken($token, $pdo)) {
                return $token;
            }
        }

        throw new CertificateException("Failed to generate a unique verification token.", 500);
    }

    /**
     * Check whether candidate certificate ID already exists in v3_certificates table.
     */
    public static function isCertificateIdTaken(string $candidateId, ?PDO $pdo = null): bool
    {
        try {
            $sql = "SELECT 1 FROM `v3_certificates` WHERE `certificate_id` = :cid LIMIT 1";
            if ($pdo !== null) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':cid' => $candidateId]);
                return $stmt->fetchColumn() !== false;
            }
            $row = Database::fetch($sql, [':cid' => $candidateId]);
            return !empty($row);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check whether verification token already exists in v3_certificates table.
     */
    public static function isVerificationTokenTaken(string $token, ?PDO $pdo = null): bool
    {
        try {
            $sql = "SELECT 1 FROM `v3_certificates` WHERE `verification_token` = :tok LIMIT 1";
            if ($pdo !== null) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':tok' => $token]);
                return $stmt->fetchColumn() !== false;
            }
            $row = Database::fetch($sql, [':tok' => $token]);
            return !empty($row);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Load settings from database or fallback to defaults.
     */
    public static function loadSettings(?PDO $pdo = null): array
    {
        $defaults = [
            'cert_id_prefix'         => 'LC',
            'cert_id_include_year'   => '1',
            'cert_id_separator'      => '-',
            'cert_id_segment_count'  => '2',
            'cert_id_segment_length' => '4',
            'cert_id_charset'        => self::DEFAULT_CHARSET,
        ];

        try {
            $sql = "SELECT `setting_key`, `setting_value` FROM `certificate_settings`";
            $rows = ($pdo !== null) ? $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR) : Database::fetchAll($sql);

            if ($pdo === null && is_array($rows)) {
                $mapped = [];
                foreach ($rows as $r) {
                    $mapped[$r['setting_key']] = $r['setting_value'];
                }
                $rows = $mapped;
            }

            if (is_array($rows) && !empty($rows)) {
                return array_merge($defaults, $rows);
            }
        } catch (\Throwable) {
            // Fall back to defaults if database is not yet migrated
        }

        return $defaults;
    }
}
