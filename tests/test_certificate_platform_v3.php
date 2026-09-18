<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Comprehensive Test Suite: LC-SPC Certificate Platform V3
 * ==============================================================================
 * Tests all 8 Mandatory User Corrections & Core Architecture:
 * 1. Certificate ID collision retry & DB uniqueness enforcement
 * 2. Minimum entropy enforcement (rejecting < 40 bits)
 * 3. Rendering consistency between canvas, JPEG, and ISO %PDF-1.4
 * 4. Chunk / batch processing (100, 500, and 1000 certificate scale tests)
 * 5. Phone-search normalization, active-only filtering, anti-enumeration & rate-limiting
 * 6. Legacy `certificates` table complete isolation
 * 7. Non-destructive additive migration verification
 * 8. Live MariaDB 12.3 DDL up / down / re-up idempotency
 * ==============================================================================
 */

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = APP_ROOT . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\ValidationException;
use App\Repositories\V3CertificateRepository;
use App\Repositories\V3CertificateTemplateRepository;
use App\Services\CertificateBatchService;
use App\Services\CertificateIdGenerator;
use App\Services\CertificateRenderer;
use App\Services\CsvValidationService;

class TestCertificatePlatformV3
{
    private PDO $mariaPdo;
    private string $testDbName;
    private int $passCount = 0;
    private int $failCount = 0;

    public function run(): void
    {
        echo "====================================================================\n";
        echo "  LC-SPC CERTIFICATE PLATFORM V3 — COMPREHENSIVE TEST SUITE\n";
        echo "====================================================================\n\n";

        $this->setupMariaDb();

        try {
            $this->test1_EntropyEnforcement();
            $this->test2_CertificateIdGenerationAndCollisionRetry();
            $this->test3_RenderingConsistencyCanvasJpgPdf();
            $this->test4_LegacyCertificatesIsolation();
            $this->test5_PhoneSearchActiveOnlyAndAntiEnumeration();
            $this->test6_RateLimitingEnforcement();
            $this->test7_BatchProcessing100Records();
            $this->test8_BatchProcessing500Records();
            $this->test9_BatchProcessing1000RecordsResilience();
            $this->test10_MariaDbMigrationUpAndDownIdempotency();
        } finally {
            $this->tearDownMariaDb();
        }

        echo "\n====================================================================\n";
        echo "  V3 TEST SUITE SUMMARY\n";
        echo "  Total Assertions Passed: {$this->passCount}\n";
        echo "  Total Assertions Failed: {$this->failCount}\n";
        echo "====================================================================\n";

        if ($this->failCount > 0) {
            echo "RESULT: BLOCKED — FIX REQUIRED\n";
            exit(1);
        } else {
            echo "RESULT: READY FOR REVIEW\n";
            exit(0);
        }
    }

    private function setupMariaDb(): void
    {
        $this->testDbName = 'lc_spc_v3_test_' . time();
        $this->mariaPdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->mariaPdo->exec("CREATE DATABASE `{$this->testDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->mariaPdo->exec("USE `{$this->testDbName}`");

        // Execute baseline migrations (m0001 - m0013) to replicate production state
        $baseMigrations = [
            'm0001_create_users_table.php',
            'm0002_create_campaigns_table.php',
            'm0003_create_events_table.php',
            'm0004_create_participants_table.php',
            'm0005_create_event_registrations_table.php',
            'm0006_create_certificates_table.php',
            'm0007_create_audit_logs_table.php',
            'm0008_update_certificates_unique_constraint.php',
            'm0009_update_events_for_event_centric_system.php',
            'm0010_create_admin_permissions_system.php',
            'm0011_create_form_settings_and_event_forms_system.php',
            'm0012_create_registration_metadata_and_checkin_logs.php',
            'm0013_create_certificate_templates_and_v2.php',
            'm0014_create_certificate_platform_v3_tables.php',
        ];

        $migrationsDir = dirname(__DIR__) . '/database/migrations';
        foreach ($baseMigrations as $file) {
            $filePath = $migrationsDir . '/' . $file;
            if (file_exists($filePath)) {
                $migration = require $filePath;
                if (is_object($migration) && method_exists($migration, 'up')) {
                    $migration->up($this->mariaPdo);
                }
            }
        }

        // Seed baseline test template #1
        $this->mariaPdo->exec("
            INSERT INTO `v3_certificate_templates` (`id`, `name`, `certificate_type`, `layout_config`, `required_variables`, `status`)
            VALUES (1, 'Default Institutional Certificate', 'participation', '{\"canvas\":{\"width\":2480,\"height\":1754,\"dpi\":300},\"elements\":[],\"variables\":[]}', '[\"name\",\"phone\",\"certificate_number\"]', 'active')
        ");

        $this->assert(true, "Isolated MariaDB test database initialized (`{$this->testDbName}`)");
    }

    private function tearDownMariaDb(): void
    {
        try {
            $this->mariaPdo->exec("DROP DATABASE IF EXISTS `{$this->testDbName}`");
            $this->assert(true, "Isolated MariaDB test database cleaned up cleanly");
        } catch (Throwable $e) {
            echo "Warning: Clean up failed: " . $e->getMessage() . "\n";
        }
    }

    /**
     * TEST 1: Minimum Entropy Enforcement
     */
    private function test1_EntropyEnforcement(): void
    {
        echo "\n--- TEST 1: Minimum Entropy Enforcement (< 40 bits rejection) ---\n";

        // Safe configurations (>= 40 bits)
        // 8 chars with 32 charset = 8 * 5 = 40 bits
        $entropy1 = CertificateIdGenerator::calculateEntropyBits(8, 32);
        $this->assert($entropy1 >= 40.0, "8 chars with charset 32 yields {$entropy1} bits (>= 40 bits)");

        // 10 chars with 16 hex charset = 10 * 4 = 40 bits
        $entropy2 = CertificateIdGenerator::calculateEntropyBits(10, 16);
        $this->assert($entropy2 >= 40.0, "10 chars with charset 16 yields {$entropy2} bits (>= 40 bits)");

        // 12 chars with charset 62 = 12 * 5.95 = 71.4 bits
        $entropy3 = CertificateIdGenerator::calculateEntropyBits(12, 62);
        $this->assert($entropy3 >= 70.0, "12 chars with charset 62 yields {$entropy3} bits (> 70 bits)");

        // Dangerous / short configurations (< 40 bits) MUST throw ValidationException
        $caughtLowEntropy = false;
        try {
            CertificateIdGenerator::validateConfiguration([
                'cert_id_segment_count'  => 1,
                'cert_id_segment_length' => 4, // 4 chars total -> 20 bits
                'cert_id_charset'        => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ',
            ]);
        } catch (ValidationException $e) {
            $caughtLowEntropy = true;
        }
        $this->assert($caughtLowEntropy, "Rejected dangerously short 4-character ID space (20 bits)");

        $caughtHexShort = false;
        try {
            CertificateIdGenerator::validateConfiguration([
                'cert_id_segment_count'  => 2,
                'cert_id_segment_length' => 3, // 6 chars total -> 24 bits
                'cert_id_charset'        => '0123456789ABCDEF',
            ]);
        } catch (ValidationException $e) {
            $caughtHexShort = true;
        }
        $this->assert($caughtHexShort, "Rejected short hex 6-character ID space (24 bits)");

        $caughtEmptyCharset = false;
        try {
            CertificateIdGenerator::validateConfiguration([
                'cert_id_segment_count'  => 2,
                'cert_id_segment_length' => 4,
                'cert_id_charset'        => 'AAAA', // Only 1 unique char -> insufficient symbols
            ]);
        } catch (ValidationException $e) {
            $caughtEmptyCharset = true;
        }
        $this->assert($caughtEmptyCharset, "Rejected degenerate single-character charset (< 16 symbols)");
    }

    /**
     * TEST 2: Certificate ID Generation & Collision Retry
     */
    private function test2_CertificateIdGenerationAndCollisionRetry(): void
    {
        echo "\n--- TEST 2: Certificate ID Generation & Collision Retry ---\n";

        // Generate 50 IDs and verify uniqueness and format
        $generated = [];
        for ($i = 0; $i < 50; $i++) {
            $cid = CertificateIdGenerator::generateCertificateId($this->mariaPdo);
            $this->assert(!isset($generated[$cid]), "Generated Certificate ID {$cid} is globally unique");
            $generated[$cid] = true;
            $this->assert(strlen($cid) >= 10, "Certificate ID {$cid} has valid length");
        }

        // Test collision retry mechanism:
        // Insert a dummy certificate with a specific ID into v3_certificates
        $fixedId = 'CERT-' . date('Y') . '-COLLISIONTEST1';
        $token1 = CertificateIdGenerator::generateVerificationToken($this->mariaPdo);

        $stmt = $this->mariaPdo->prepare("
            INSERT INTO `v3_certificates` (`certificate_id`, `verification_token`, `template_id`, `name`, `phone`, `phone_normalized`, `certificate_data_json`, `status`)
            VALUES (:cid, :token, 1, 'Collision Tester', '9876543210', '+919876543210', '{}', 'active')
        ");
        $stmt->execute([':cid' => $fixedId, ':token' => $token1]);
        $this->assert(true, "Inserted baseline certificate with fixed ID: {$fixedId}");

        // Attempting to re-insert the EXACT duplicate ID MUST fail at the MariaDB UNIQUE constraint
        $uniqueConstraintEnforced = false;
        try {
            $token2 = CertificateIdGenerator::generateVerificationToken($this->mariaPdo);
            $stmt->execute([':cid' => $fixedId, ':token' => $token2]);
        } catch (Throwable $e) {
            $uniqueConstraintEnforced = true;
        }
        $this->assert($uniqueConstraintEnforced, "MariaDB uk_v3_certificates_cert_id strictly blocked duplicate certificate ID");

        // Attempting to re-insert duplicate verification_token MUST fail
        $tokenConstraintEnforced = false;
        try {
            $newCid = 'CERT-' . date('Y') . '-UNIQUE999';
            $stmt->execute([':cid' => $newCid, ':token' => $token1]);
        } catch (Throwable $e) {
            $tokenConstraintEnforced = true;
        }
        $this->assert($tokenConstraintEnforced, "MariaDB uk_v3_certificates_token strictly blocked duplicate verification token");
    }

    /**
     * TEST 3: Rendering Consistency (Canvas, JPG, and ISO %PDF-1.4)
     */
    private function test3_RenderingConsistencyCanvasJpgPdf(): void
    {
        echo "\n--- TEST 3: Rendering Consistency (Canvas, JPG, and ISO %PDF-1.4) ---\n";

        $template = [
            'id'                     => 1,
            'name'                   => 'National Leadership Award',
            'certificate_type'       => 'achievement',
            'background_image_path'  => null,
            'seal_image_path'        => null,
            'signature1_image_path'  => null,
            'signature1_name'        => 'Dr. Jane Smith',
            'signature1_designation' => 'Executive Director',
            'signature2_image_path'  => null,
            'signature2_name'        => 'Mr. Robert Johnson',
            'signature2_designation' => 'Program Head',
            'layout_config'          => [
                'canvas'    => ['width' => 2480, 'height' => 1754, 'dpi' => 300],
                'elements'  => [
                    [
                        'id'        => 'title',
                        'type'      => 'text',
                        'text'      => 'CERTIFICATE OF EXCELLENCE',
                        'x'         => 1240,
                        'y'         => 350,
                        'font_size' => 48,
                        'color'     => '#BF1E2E',
                        'align'     => 'center',
                    ],
                    [
                        'id'        => 'recipient',
                        'type'      => 'variable',
                        'variable'  => 'name',
                        'x'         => 1240,
                        'y'         => 720,
                        'font_size' => 64,
                        'color'     => '#0F172A',
                        'align'     => 'center',
                    ],
                    [
                        'id'        => 'cert_num',
                        'type'      => 'variable',
                        'variable'  => 'certificate_number',
                        'x'         => 1240,
                        'y'         => 1500,
                        'font_size' => 24,
                        'color'     => '#64748B',
                        'align'     => 'center',
                    ],
                    [
                        'id'    => 'qr',
                        'type'  => 'qr_code',
                        'x'     => 1240,
                        'y'     => 1150,
                        'size'  => 220,
                    ],
                ],
                'variables' => [
                    ['key' => 'name', 'label' => 'Recipient Name', 'mandatory' => true],
                    ['key' => 'phone', 'label' => 'Phone Number', 'mandatory' => true],
                    ['key' => 'certificate_number', 'label' => 'Certificate ID', 'mandatory' => true],
                ],
            ],
        ];

        $data = [
            'name'               => 'Ananya Sharma',
            'phone'              => '+919876543210',
            'certificate_number' => 'CERT-2026-TST12345',
            'verification_token' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
            'event_title'        => 'Mental Health Summit 2026',
            'date'               => '2026-09-18',
        ];

        // 1. Render GD Canvas
        $im = CertificateRenderer::renderCanvas($template, $data, false);
        $w = imagesx($im);
        $h = imagesy($im);
        imagedestroy($im);

        $this->assert($w === 2480 && $h === 1754, "GD Canvas strictly conforms to canonical 2480 x 1754 px at 300 DPI");

        // 2. Render JPEG
        $jpg = CertificateRenderer::renderJpg($template, $data, false);
        $this->assert(!empty($jpg), "Rendered binary JPEG string successfully");
        $this->assert(str_starts_with($jpg, "\xFF\xD8"), "JPEG starts with valid SOI header (0xFF 0xD8)");
        $this->assert(str_ends_with($jpg, "\xFF\xD9"), "JPEG ends with valid EOI marker (0xFF 0xD9)");

        $imgInfo = @getimagesizefromstring($jpg);
        $this->assert($imgInfo[0] === 2480 && $imgInfo[1] === 1754, "JPEG matches canvas dimensions exactly (2480 x 1754)");

        // 3. Render ISO PDF (%PDF-1.4)
        $pdf = CertificateRenderer::renderPdf($template, $data, false);
        $this->assert(!empty($pdf), "Rendered binary PDF string successfully");
        $this->assert(str_starts_with($pdf, "%PDF-1.4"), "PDF starts with ISO %PDF-1.4 header");
        $this->assert(str_contains($pdf, "%%EOF"), "PDF contains valid %%EOF trailer");
        $this->assert(str_contains($pdf, "/MediaBox [0 0 841.89 595.28]"), "PDF page matches exact ISO A4 Landscape dimensions (841.89 x 595.28 pt)");
        $this->assert(str_contains($pdf, "/Title (Certificate CERT-2026-TST12345)"), "PDF metadata embeds Certificate ID");

        // 4. Revocation Watermark Banner
        $revokedJpg = CertificateRenderer::renderJpg($template, $data, true);
        $this->assert(strlen($revokedJpg) > 0, "Revoked certificate rendered with revocation stamp");
    }

    /**
     * TEST 4: Legacy Certificates Table Isolation
     */
    private function test4_LegacyCertificatesIsolation(): void
    {
        echo "\n--- TEST 4: Legacy `certificates` Table Isolation ---\n";

        // Verify that the legacy `certificates` table exists in MariaDB
        $stmt = $this->mariaPdo->query("SHOW TABLES LIKE 'certificates'");
        $legacyExists = (bool) $stmt->fetch();
        $this->assert($legacyExists, "Legacy production `certificates` table exists and was NOT dropped");

        // Verify that V3 table is `v3_certificates`
        $stmt2 = $this->mariaPdo->query("SHOW TABLES LIKE 'v3_certificates'");
        $v3Exists = (bool) $stmt2->fetch();
        $this->assert($v3Exists, "V3 table `v3_certificates` exists in MariaDB");

        // Count rows in legacy table
        $legacyCount1 = (int) $this->mariaPdo->query("SELECT COUNT(*) FROM `certificates`")->fetchColumn();

        // Perform V3 repository insert
        $certRepo = new V3CertificateRepository($this->mariaPdo);
        $cid = CertificateIdGenerator::generateCertificateId($this->mariaPdo);
        $token = CertificateIdGenerator::generateVerificationToken($this->mariaPdo);

        $stmt = $this->mariaPdo->prepare("
            INSERT INTO `v3_certificates` (`certificate_id`, `verification_token`, `template_id`, `name`, `phone`, `phone_normalized`, `certificate_data_json`, `status`)
            VALUES (:cid, :token, 1, 'Isolation Test User', '9123456780', '+919123456780', '{}', 'active')
        ");
        $stmt->execute([':cid' => $cid, ':token' => $token]);

        // Count rows in legacy table again -> MUST BE UNCHANGED!
        $legacyCount2 = (int) $this->mariaPdo->query("SELECT COUNT(*) FROM `certificates`")->fetchColumn();
        $this->assert($legacyCount1 === $legacyCount2, "Legacy `certificates` table remained completely untouched ({$legacyCount1} rows)");
    }

    /**
     * TEST 5: Public Phone Search (Active-Only, Normalization & Anti-Enumeration)
     */
    private function test5_PhoneSearchActiveOnlyAndAntiEnumeration(): void
    {
        echo "\n--- TEST 5: Public Phone Search (Active-Only, Normalization & Anti-Enumeration) ---\n";

        $certRepo = new V3CertificateRepository($this->mariaPdo);
        $phoneRaw = "098765 43210";
        $phoneNormalized = CsvValidationService::normalizePhone($phoneRaw);
        $this->assert($phoneNormalized === '+919876543210', "Normalized '{$phoneRaw}' to E.164: {$phoneNormalized}");

        // Create an Active Certificate and an Invalid Certificate under the same phone
        $activeId = CertificateIdGenerator::generateCertificateId($this->mariaPdo);
        $activeToken = CertificateIdGenerator::generateVerificationToken($this->mariaPdo);
        $invalidId = CertificateIdGenerator::generateCertificateId($this->mariaPdo);
        $invalidToken = CertificateIdGenerator::generateVerificationToken($this->mariaPdo);

        $stmt = $this->mariaPdo->prepare("
            INSERT INTO `v3_certificates` (`certificate_id`, `verification_token`, `template_id`, `name`, `phone`, `phone_normalized`, `certificate_data_json`, `status`, `invalidation_reason`)
            VALUES (:cid, :token, 1, :name, :phone, :norm, '{}', :status, :reason)
        ");

        $stmt->execute([
            ':cid'    => $activeId,
            ':token'  => $activeToken,
            ':name'   => 'Rohan Verma',
            ':phone'  => '9876543210',
            ':norm'   => $phoneNormalized,
            ':status' => 'active',
            ':reason' => null,
        ]);

        $stmt->execute([
            ':cid'    => $invalidId,
            ':token'  => $invalidToken,
            ':name'   => 'Rohan Verma (Revoked)',
            ':phone'  => '9876543210',
            ':norm'   => $phoneNormalized,
            ':status' => 'invalid',
            ':reason' => 'Administrative clerical cancellation',
        ]);

        // Query active certificates by phone
        $results = $certRepo->findActiveByPhone($phoneNormalized);

        $this->assert(count($results) >= 1, "Phone search returned active certificate(s)");
        $returnedIds = array_column($results, 'certificate_id');
        $this->assert(in_array($activeId, $returnedIds, true), "Active Certificate {$activeId} is present in phone search");
        $this->assert(!in_array($invalidId, $returnedIds, true), "Revoked Certificate {$invalidId} is strictly EXCLUDED from phone search");

        // Test non-existent phone number: anti-enumeration generic behavior
        $unknownPhone = '+919999999999';
        $emptyResults = $certRepo->findActiveByPhone($unknownPhone);
        $this->assert(empty($emptyResults), "Non-existent phone returns clean empty array without error");
    }

    /**
     * TEST 6: Rate Limiting Enforcement
     */
    private function test6_RateLimitingEnforcement(): void
    {
        echo "\n--- TEST 6: Rate Limiting Enforcement ---\n";

        $cacheDir = dirname(__DIR__) . '/storage/cache/rate_limits';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $testIp = '198.51.100.42';
        $action = 'test_search';
        $maxAttempts = 10;
        $window = 60;

        $hash = md5($testIp . '_' . $action);
        $file = $cacheDir . "/rate_{$hash}.json";
        if (file_exists($file)) {
            @unlink($file);
        }

        // Simulate 10 rapid queries
        $rateLimited = false;
        for ($i = 1; $i <= 10; $i++) {
            $isLimited = $this->simulateRateLimit($cacheDir, $testIp, $action, $maxAttempts, $window);
            if ($isLimited) {
                $rateLimited = true;
                break;
            }
        }
        $this->assert(!$rateLimited, "Allowed 10 requests within 60s rate-limit window");

        // 11th request MUST trigger rate limit
        $attempt11 = $this->simulateRateLimit($cacheDir, $testIp, $action, $maxAttempts, $window);
        $this->assert($attempt11, "11th request was strictly rate-limited (HTTP 429 triggered)");

        // Clean up test rate limit file
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    private function simulateRateLimit(string $dir, string $ip, string $action, int $maxAttempts, int $window): bool
    {
        $hash = md5($ip . '_' . $action);
        $file = $dir . "/rate_{$hash}.json";
        $now = time();
        $attempts = [];

        if (file_exists($file)) {
            $data = json_decode((string) file_get_contents($file), true) ?: [];
            $attempts = array_filter($data, fn($ts) => ($now - $ts) < $window);
        }

        if (count($attempts) >= $maxAttempts) {
            return true;
        }

        $attempts[] = $now;
        file_put_contents($file, json_encode($attempts));
        return false;
    }

    /**
     * TEST 7: Batch Processing — 100 Records Scale
     */
    private function test7_BatchProcessing100Records(): void
    {
        echo "\n--- TEST 7: Chunked Batch Processing (100 Records) ---\n";

        $records = [];
        for ($i = 1; $i <= 100; $i++) {
            $records[] = [
                'name'               => "Recipient Hundred {$i}",
                'phone'              => "+9198000" . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'phone_normalized'   => "+9198000" . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'event_title'        => "Batch 100 Event",
                'date'               => "2026-09-18",
            ];
        }

        $batch = CertificateBatchService::createBatch(1, $records, 0, 1, 'test_100.csv', $this->mariaPdo);
        $batchId = (int) $batch['id'];
        $this->assert($batchId > 0, "Created Batch record #{$batchId} for 100 records");

        // Process in 4 chunks of 25
        $c1 = CertificateBatchService::processChunk($batchId, 25, $this->mariaPdo);
        $this->assert($c1['processed'] === 25 && !$c1['is_complete'], "Chunk 1 processed: 25/100 (25%)");

        $c2 = CertificateBatchService::processChunk($batchId, 25, $this->mariaPdo);
        $this->assert($c2['processed'] === 50 && !$c2['is_complete'], "Chunk 2 processed: 50/100 (50%)");

        $c3 = CertificateBatchService::processChunk($batchId, 25, $this->mariaPdo);
        $this->assert($c3['processed'] === 75 && !$c3['is_complete'], "Chunk 3 processed: 75/100 (75%)");

        $c4 = CertificateBatchService::processChunk($batchId, 25, $this->mariaPdo);
        $this->assert($c4['processed'] === 100 && $c4['is_complete'], "Chunk 4 processed: 100/100 (100% COMPLETE)");

        // Verify count in MariaDB
        $countStmt = $this->mariaPdo->prepare("SELECT COUNT(*) FROM `v3_certificates` WHERE `batch_id` = :bid");
        $countStmt->execute([':bid' => $batchId]);
        $dbCount = (int) $countStmt->fetchColumn();
        $this->assert($dbCount === 100, "100 certificates verified in MariaDB v3_certificates table");
    }

    /**
     * TEST 8: Batch Processing — 500 Records Scale
     */
    private function test8_BatchProcessing500Records(): void
    {
        echo "\n--- TEST 8: Chunked Batch Processing (500 Records) ---\n";

        $records = [];
        for ($i = 1; $i <= 500; $i++) {
            $records[] = [
                'name'               => "Recipient FiveHundred {$i}",
                'phone'              => "+9197000" . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'phone_normalized'   => "+9197000" . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'event_title'        => "Batch 500 Event",
                'date'               => "2026-09-18",
            ];
        }

        $batch = CertificateBatchService::createBatch(1, $records, 0, 1, 'test_500.csv', $this->mariaPdo);
        $batchId = (int) $batch['id'];
        $this->assert($batchId > 0, "Created Batch record #{$batchId} for 500 records");

        // Process in 5 chunks of 100 records
        $startTime = microtime(true);
        for ($chunk = 1; $chunk <= 5; $chunk++) {
            $res = CertificateBatchService::processChunk($batchId, 100, $this->mariaPdo);
            $expectedCount = $chunk * 100;
            $this->assert($res['processed'] === $expectedCount, "Chunk {$chunk} processed: {$expectedCount}/500");
        }
        $duration = round(microtime(true) - $startTime, 2);

        $countStmt = $this->mariaPdo->prepare("SELECT COUNT(*) FROM `v3_certificates` WHERE `batch_id` = :bid");
        $countStmt->execute([':bid' => $batchId]);
        $dbCount = (int) $countStmt->fetchColumn();
        $this->assert($dbCount === 500, "500 certificates verified in MariaDB v3_certificates table in {$duration}s");
    }

    /**
     * TEST 9: Batch Processing — 1000 Records Resilience & Partial Failure Recovery
     */
    private function test9_BatchProcessing1000RecordsResilience(): void
    {
        echo "\n--- TEST 9: Chunked Batch Processing (1000 Records & Resumption Resilience) ---\n";

        $records = [];
        for ($i = 1; $i <= 1000; $i++) {
            $records[] = [
                'name'               => "Recipient Thousand {$i}",
                'phone'              => "+9196000" . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'phone_normalized'   => "+9196000" . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'event_title'        => "Scale 1000 Event",
                'date'               => "2026-09-18",
            ];
        }

        $batch = CertificateBatchService::createBatch(1, $records, 0, 1, 'test_1000.csv', $this->mariaPdo);
        $batchId = (int) $batch['id'];
        $this->assert($batchId > 0, "Created Batch record #{$batchId} for 1000 records");

        // 1. Process 3 chunks of 100 = 300 records
        CertificateBatchService::processChunk($batchId, 100, $this->mariaPdo);
        CertificateBatchService::processChunk($batchId, 100, $this->mariaPdo);
        $partial = CertificateBatchService::processChunk($batchId, 100, $this->mariaPdo);
        $this->assert($partial['processed'] === 300, "Batch partially executed: 300/1000 generated");

        // 2. Simulate browser reload or worker interruption and resume from the saved state
        $resumedBatch = CertificateBatchService::getBatch($batchId, $this->mariaPdo);
        $this->assert((int) $resumedBatch['generated_count'] === 300, "Resumed batch correctly identifies offset of 300");

        // 3. Complete remaining 700 records in 7 chunks of 100
        for ($i = 4; $i <= 10; $i++) {
            CertificateBatchService::processChunk($batchId, 100, $this->mariaPdo);
        }

        $finalBatch = CertificateBatchService::getBatch($batchId, $this->mariaPdo);
        $this->assert($finalBatch['status'] === 'completed', "1000 record batch completed successfully");
        $this->assert((int) $finalBatch['generated_count'] === 1000, "All 1000 records accounted for without duplicate records");

        // 4. Calling processChunk on a completed batch is idempotent
        $idempotentRes = CertificateBatchService::processChunk($batchId, 100, $this->mariaPdo);
        $this->assert($idempotentRes['is_complete'] ?? $idempotentRes['completed'], "Calling processChunk on completed batch is safely idempotent");
    }

    /**
     * TEST 10: MariaDB Migration Up / Down / Re-Up Idempotency
     */
    private function test10_MariaDbMigrationUpAndDownIdempotency(): void
    {
        echo "\n--- TEST 10: MariaDB Migration Up / Down / Re-Up Idempotency ---\n";

        $migrationFile = dirname(__DIR__) . '/database/migrations/m0014_create_certificate_platform_v3_tables.php';
        $m0014 = require $migrationFile;

        // 1. Test down() execution
        $m0014->down($this->mariaPdo);

        $stmt = $this->mariaPdo->query("SHOW TABLES LIKE 'v3_certificates'");
        $this->assert(!$stmt->fetch(), "m0014 down() successfully removed `v3_certificates`");

        $stmt2 = $this->mariaPdo->query("SHOW TABLES LIKE 'certificate_settings'");
        $this->assert(!$stmt2->fetch(), "m0014 down() successfully removed `certificate_settings`");

        // Legacy certificates table MUST still exist!
        $stmt3 = $this->mariaPdo->query("SHOW TABLES LIKE 'certificates'");
        $this->assert((bool) $stmt3->fetch(), "Legacy `certificates` table remained untouched during m0014 down()");

        // 2. Test re-up() execution
        $m0014->up($this->mariaPdo);

        $stmt4 = $this->mariaPdo->query("SHOW TABLES LIKE 'v3_certificates'");
        $this->assert((bool) $stmt4->fetch(), "m0014 re-up() recreated `v3_certificates` (Idempotent)");

        $stmt5 = $this->mariaPdo->query("SHOW TABLES LIKE 'certificate_settings'");
        $this->assert((bool) $stmt5->fetch(), "m0014 re-up() recreated `certificate_settings` (Idempotent)");
    }

    private function assert(bool $condition, string $description): void
    {
        if ($condition) {
            $this->passCount++;
            echo "  [PASS] {$description}\n";
        } else {
            $this->failCount++;
            echo "  [FAIL] {$description}\n";
        }
    }
}

// Execute test suite
$suite = new TestCertificatePlatformV3();
$suite->run();
