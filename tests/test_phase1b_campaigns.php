<?php

declare(strict_types=1);

/**
 * Phase 1B — Campaign Management Automated Test Suite
 * Run via: php tests/test_phase1b_campaigns.php
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Core\App;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security;
use App\Core\Session;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\RoleMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\CampaignRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\CampaignService;
use App\Services\RoleService;

// Colors for terminal output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertCampaignTest(string $description, bool $condition, string $details = ''): void
{
    global $totalTests, $passedTests, $failedTests, $green, $red, $reset;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  {$green}[PASS]{$reset} {$description}" . PHP_EOL;
    } else {
        $failedTests++;
        echo "  {$red}[FAIL]{$reset} {$description}" . PHP_EOL;
        if ($details) {
            echo "         Details: {$details}" . PHP_EOL;
        }
    }
}

Env::load(APP_ROOT . '/.env');
Config::load(APP_ROOT . '/config');

echo "=== LC-SPC Phase 1B Campaign Management Verification Suite ===" . PHP_EOL . PHP_EOL;

// -----------------------------------------------------------------------------
// Isolated In-Memory SQLite Test Database Setup
// -----------------------------------------------------------------------------
$testPdo = new PDO('sqlite::memory:');
$testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$testPdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(191) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'staff',
        phone VARCHAR(25) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        failed_logins INTEGER NOT NULL DEFAULT 0,
        locked_until DATETIME NULL,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        deleted_at DATETIME NULL
    );

    CREATE TABLE campaigns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title VARCHAR(191) NOT NULL,
        slug VARCHAR(191) NOT NULL UNIQUE,
        theme VARCHAR(255) NULL,
        description TEXT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_by INTEGER NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        deleted_at DATETIME NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    );

    CREATE TABLE events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INTEGER NOT NULL,
        title VARCHAR(191) NOT NULL,
        slug VARCHAR(191) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        deleted_at DATETIME NULL,
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT
    );

    CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        actor_id INTEGER NULL,
        actor_type VARCHAR(20) NOT NULL DEFAULT 'admin',
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) NOT NULL,
        entity_id INTEGER NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255) NULL,
        metadata TEXT NULL,
        created_at DATETIME NOT NULL
    );
");

// Inject in-memory connection into Database::$instance
$ref = new ReflectionProperty(Database::class, 'instance');
$ref->setAccessible(true);
$ref->setValue(null, $testPdo);

$userRepo = new UserRepository();
$campaignRepo = new CampaignRepository();
$auditRepo = new AuditLogRepository();
$auditService = new AuditService($auditRepo, $userRepo);
$campaignService = new CampaignService($campaignRepo, $auditService);

// Create Test Actors in users table
$superAdminId = $userRepo->create([
    'name'          => 'Super Admin Operator',
    'email'         => 'superadmin@teami.in',
    'password_hash' => Security::hashPassword('SuperAdminPass123!'),
    'role'          => 'super_admin',
    'status'        => 'active',
]);

$coordinatorId = $userRepo->create([
    'name'          => 'Campaign Coordinator',
    'email'         => 'coordinator@teami.in',
    'password_hash' => Security::hashPassword('CoordinatorPass123!'),
    'role'          => 'coordinator',
    'status'        => 'active',
]);

$staffId = $userRepo->create([
    'name'          => 'Operations Staff',
    'email'         => 'staff@teami.in',
    'password_hash' => Security::hashPassword('StaffPass123!'),
    'role'          => 'staff',
    'status'        => 'active',
]);

$viewerId = $userRepo->create([
    'name'          => 'Auditing Viewer',
    'email'         => 'viewer@teami.in',
    'password_hash' => Security::hashPassword('ViewerPass123!'),
    'role'          => 'viewer',
    'status'        => 'active',
]);

// -----------------------------------------------------------------------------
// 1. Testing Campaign Creation & Validation Rules
// -----------------------------------------------------------------------------
echo "1. Testing Campaign Creation & Validation Rules..." . PHP_EOL;

// Test 1: Valid Campaign Creation
$validData = [
    'title'       => 'Suicide Prevention Campaign 2026',
    'slug'        => 'spc-2026',
    'theme'       => 'Words and Beyond',
    'description' => 'Comprehensive community awareness and listening circles across 2026.',
    'start_date'  => '2026-01-01',
    'end_date'    => '2026-12-31',
    'status'      => 'active',
];

$campaign = $campaignService->createCampaign($validData, $coordinatorId);

assertCampaignTest(
    "1. Campaign creation succeeds with valid data",
    !empty($campaign['id']) &&
    $campaign['title'] === 'Suicide Prevention Campaign 2026' &&
    $campaign['slug'] === 'spc-2026' &&
    $campaign['theme'] === 'Words and Beyond' &&
    $campaign['status'] === 'active' &&
    $campaign['created_by'] === $coordinatorId &&
    $campaign['deleted_at'] === null
);

// Test 2: Required Field Validation (Title & Dates)
$missingTitleErrors = $campaignService->validate([
    'title'      => '',
    'start_date' => '2026-01-01',
    'end_date'   => '2026-12-31',
    'status'     => 'active',
]);

$shortTitleErrors = $campaignService->validate([
    'title'      => 'AB',
    'start_date' => '2026-01-01',
    'end_date'   => '2026-12-31',
    'status'     => 'active',
]);

assertCampaignTest(
    "2. Required field validation works (title presence and minimum length)",
    isset($missingTitleErrors['title']) && isset($shortTitleErrors['title'])
);

// Test 3: Invalid Dates Rejected (Malformed format, impossible calendar dates)
$invalidDate1 = $campaignService->validate([
    'title'      => 'Valid Campaign Title',
    'start_date' => '2026-02-31', // 31st February is invalid
    'end_date'   => '2026-12-31',
    'status'     => 'active',
]);

$invalidDate2 = $campaignService->validate([
    'title'      => 'Valid Campaign Title',
    'start_date' => 'not-a-date',
    'end_date'   => '2026-12-31',
    'status'     => 'active',
]);

assertCampaignTest(
    "3. Invalid dates are rejected (format & calendar validity)",
    isset($invalidDate1['start_date']) && isset($invalidDate2['start_date'])
);

// Test 4: start_date > end_date is rejected
$chronologyErrors = $campaignService->validate([
    'title'      => 'Valid Campaign Title',
    'start_date' => '2026-10-15',
    'end_date'   => '2026-10-01', // End date before start date
    'status'     => 'active',
]);

assertCampaignTest(
    "4. start_date > end_date is rejected",
    isset($chronologyErrors['end_date']) && str_contains($chronologyErrors['end_date'], 'on or after')
);

// Test 5: Invalid status is rejected
$invalidStatusErrors = $campaignService->validate([
    'title'      => 'Valid Campaign Title',
    'start_date' => '2026-01-01',
    'end_date'   => '2026-12-31',
    'status'     => 'invalid_status_value',
]);

assertCampaignTest(
    "5. Invalid status is rejected (must be draft, active, completed, or archived)",
    isset($invalidStatusErrors['status'])
);

// Test 6: Duplicate slug handled safely
$autoSlug = $campaignService->generateUniqueSlug('Suicide Prevention Campaign 2026'); // Collision on 'spc-2026' or slugified title
$manualDuplicateErrors = $campaignService->validate([
    'title'      => 'Another Campaign',
    'slug'       => 'spc-2026', // Already used in Test 1
    'start_date' => '2026-05-01',
    'end_date'   => '2026-05-31',
    'status'     => 'draft',
]);

assertCampaignTest(
    "6. Duplicate slug is rejected or safely handled (collision resolution)",
    $autoSlug !== 'spc-2026' && isset($manualDuplicateErrors['slug'])
);

// -----------------------------------------------------------------------------
// 2. Testing Campaign Modification, Soft-Delete & Restore Lifecycle
// -----------------------------------------------------------------------------
echo PHP_EOL . "2. Testing Campaign Modification, Soft-Delete & Restore Lifecycle..." . PHP_EOL;

// Test 7: Campaign update works
$updated = $campaignService->updateCampaign($campaign['id'], [
    'title'       => 'Suicide Prevention Campaign 2026 — Updated',
    'slug'        => 'spc-2026-updated',
    'theme'       => 'Listening Heals',
    'description' => 'Updated description content.',
    'start_date'  => '2026-01-15',
    'end_date'    => '2026-12-15',
    'status'      => 'completed',
], $coordinatorId);

assertCampaignTest(
    "7. Campaign update works (title, theme, status, and dates modified)",
    $updated['title'] === 'Suicide Prevention Campaign 2026 — Updated' &&
    $updated['slug'] === 'spc-2026-updated' &&
    $updated['theme'] === 'Listening Heals' &&
    $updated['status'] === 'completed' &&
    $updated['start_date'] === '2026-01-15'
);

// Test 8: Campaign soft-delete works
$softDeleteSuccess = $campaignService->softDeleteCampaign($campaign['id'], $superAdminId);
$deletedRecord = $campaignRepo->findById($campaign['id'], true);

assertCampaignTest(
    "8. Campaign soft-delete works (deleted_at set, row retained in database)",
    $softDeleteSuccess &&
    !empty($deletedRecord) &&
    $deletedRecord['deleted_at'] !== null
);

// Test 9: Soft-deleted campaigns excluded from normal listing
$activeCatalog = $campaignRepo->all(false);
$normalFind = $campaignRepo->findById($campaign['id'], false);

assertCampaignTest(
    "9. Soft-deleted campaigns are excluded from normal listing",
    count($activeCatalog) === 0 &&
    $normalFind === null
);

// Test 10: Restore works
$restoreSuccess = $campaignService->restoreCampaign($campaign['id'], $superAdminId);
$restoredRecord = $campaignRepo->findById($campaign['id'], false);

assertCampaignTest(
    "10. Restore works (deleted_at cleared, record reappears in active catalog)",
    $restoreSuccess &&
    $restoredRecord !== null &&
    $restoredRecord['deleted_at'] === null
);

// -----------------------------------------------------------------------------
// 3. Testing RBAC Privilege Gates & Middleware Enforcement
// -----------------------------------------------------------------------------
echo PHP_EOL . "3. Testing RBAC Privilege Gates & Middleware Enforcement..." . PHP_EOL;

// Helper to simulate request through router pipeline
function simulateRequest(string $method, string $uri, ?int $authUserId, ?string $authUserRole, array $postData = [], bool $includeCsrf = true): Response
{
    $_SESSION = [];
    if ($authUserId !== null) {
        $_SESSION['_auth_user_id'] = $authUserId;
        $_SESSION['_auth_user_role'] = $authUserRole;
        $_SESSION['_auth_last_activity'] = time();
    }

    if ($includeCsrf) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token'] = $token;
        $postData['_csrf_token'] = $token;
    }

    $request = new Request([], $postData, [], [
        'REQUEST_METHOD' => $method,
        'REQUEST_URI'    => $uri,
        'HTTP_ACCEPT'    => 'application/json',
    ]);

    $router = new Router();
    require APP_ROOT . '/app/routes.php';

    return $router->dispatch($request);
}

// Test 11: Unauthorized roles receive HTTP 403 on mutation
$anonCreate = simulateRequest('POST', '/admin/campaigns', null, null, ['title' => 'Anon Camp']);
assertCampaignTest(
    "11. Unauthenticated request to /admin/campaigns redirects to /login (HTTP 302 or 401)",
    $anonCreate->getStatusCode() === 302 || $anonCreate->getStatusCode() === 401
);

// Test 12: Viewer cannot perform unauthorized mutations
$viewerCreate = simulateRequest('POST', '/admin/campaigns', $viewerId, 'viewer', ['title' => 'Viewer Camp']);
$viewerEdit = simulateRequest('POST', "/admin/campaigns/{$campaign['id']}", $viewerId, 'viewer', ['title' => 'Viewer Edit']);
$viewerDelete = simulateRequest('POST', "/admin/campaigns/{$campaign['id']}/delete", $viewerId, 'viewer', []);

assertCampaignTest(
    "12. Viewer cannot perform unauthorized mutations (create, edit, delete return HTTP 403)",
    $viewerCreate->getStatusCode() === 403 &&
    $viewerEdit->getStatusCode() === 403 &&
    $viewerDelete->getStatusCode() === 403
);

// Test 13: Staff cannot perform unauthorized campaign mutations
$staffCreate = simulateRequest('POST', '/admin/campaigns', $staffId, 'staff', ['title' => 'Staff Camp']);
$staffEdit = simulateRequest('POST', "/admin/campaigns/{$campaign['id']}", $staffId, 'staff', ['title' => 'Staff Edit']);
$staffDelete = simulateRequest('POST', "/admin/campaigns/{$campaign['id']}/delete", $staffId, 'staff', []);

assertCampaignTest(
    "13. Staff cannot perform unauthorized campaign mutations (create, edit, delete return HTTP 403)",
    $staffCreate->getStatusCode() === 403 &&
    $staffEdit->getStatusCode() === 403 &&
    $staffDelete->getStatusCode() === 403
);

// Test 14: Coordinator permissions match architecture (create/edit allowed, delete restricted to super_admin)
$coordCreateForm = simulateRequest('GET', '/admin/campaigns/create', $coordinatorId, 'coordinator');
$coordDelete = simulateRequest('POST', "/admin/campaigns/{$campaign['id']}/delete", $coordinatorId, 'coordinator');
$superAdminDelete = simulateRequest('POST', "/admin/campaigns/{$campaign['id']}/delete", $superAdminId, 'super_admin');

assertCampaignTest(
    "14. Coordinator can access create (200), cannot delete (403); super_admin can delete (302/redirect)",
    $coordCreateForm->getStatusCode() === 200 &&
    $coordDelete->getStatusCode() === 403 &&
    $superAdminDelete->getStatusCode() === 302
);

// Re-restore campaign for remaining tests
$campaignService->restoreCampaign($campaign['id'], $superAdminId);

// -----------------------------------------------------------------------------
// 4. Testing Security, CSRF, Output Escaping & SQL Injection Defense
// -----------------------------------------------------------------------------
echo PHP_EOL . "4. Testing Security, CSRF, Output Escaping & SQL Injection Defense..." . PHP_EOL;

// Test 15: CSRF protection works on all state-changing campaign actions
$csrfMissingCreate = simulateRequest('POST', '/admin/campaigns', $coordinatorId, 'coordinator', ['title' => 'No CSRF'], false);
$csrfMissingUpdate = simulateRequest('POST', "/admin/campaigns/{$campaign['id']}", $coordinatorId, 'coordinator', ['title' => 'No CSRF'], false);
$csrfMissingDelete = simulateRequest('POST', "/admin/campaigns/{$campaign['id']}/delete", $superAdminId, 'super_admin', [], false);

assertCampaignTest(
    "15. CSRF protection works on all state-changing campaign actions (missing token returns HTTP 403)",
    $csrfMissingCreate->getStatusCode() === 403 &&
    $csrfMissingUpdate->getStatusCode() === 403 &&
    $csrfMissingDelete->getStatusCode() === 403
);

// Test 16: XSS payloads are safely escaped in views
$xssPayload = "<script>alert('XSS-SPC-TEST')</script>";
$escaped = e($xssPayload);
assertCampaignTest(
    "16. XSS payloads are safely escaped via e() helper",
    $escaped === "&lt;script&gt;alert(&#039;XSS-SPC-TEST&#039;)&lt;/script&gt;" &&
    !str_contains($escaped, '<script>')
);

// Test 17: SQL injection attempts do not alter or query unintended data
$sqlPayload = "' OR '1'='1' -- ";
$searchResults = $campaignRepo->all(false, null, $sqlPayload);
$injectedCampaign = $campaignRepo->findBySlug($sqlPayload);

assertCampaignTest(
    "17. SQL injection attempts in search or slug fail safely via prepared statements",
    is_array($searchResults) &&
    $injectedCampaign === null
);

// -----------------------------------------------------------------------------
// 5. Testing Forensic Audit Logging & Data Scrubbing
// -----------------------------------------------------------------------------
echo PHP_EOL . "5. Testing Forensic Audit Logging & Data Scrubbing..." . PHP_EOL;

// Test 18: Audit records created for required campaign actions
$auditLogs = Database::fetchAll("SELECT * FROM audit_logs WHERE entity_type = 'campaign' AND entity_id = :id ORDER BY id ASC", [':id' => $campaign['id']]);
$recordedActions = array_column($auditLogs, 'action');

assertCampaignTest(
    "18. Audit records are created for required campaign actions",
    in_array('campaign.create', $recordedActions, true) &&
    in_array('campaign.update', $recordedActions, true) &&
    in_array('campaign.delete', $recordedActions, true) &&
    in_array('campaign.restore', $recordedActions, true)
);

// Test 19: Audit metadata contains no sensitive credentials or tokens
$allMetadataText = implode(' ', array_column($auditLogs, 'metadata'));
assertCampaignTest(
    "19. Audit metadata contains no sensitive credentials/tokens",
    !str_contains($allMetadataText, 'password') &&
    !str_contains($allMetadataText, 'password_hash') &&
    !str_contains($allMetadataText, '_csrf_token') &&
    !str_contains($allMetadataText, 'LCSPC_SESSION')
);

// -----------------------------------------------------------------------------
// 6. Testing Foreign Key & Integrity Constraints
// -----------------------------------------------------------------------------
echo PHP_EOL . "6. Testing Foreign Key & Integrity Constraints..." . PHP_EOL;

// Test 20: Existing database constraints remain respected (RESTRICT on active events)
$testEventId = Database::execute("
    INSERT INTO events (campaign_id, title, slug, status, created_at, updated_at, deleted_at)
    VALUES (:cid, 'Mental Health Workshop 101', 'mhw-101', 'published', datetime('now'), datetime('now'), NULL)
", [':cid' => $campaign['id']]);

$restrictTriggered = false;
try {
    $campaignService->softDeleteCampaign($campaign['id'], $superAdminId);
} catch (RuntimeException $e) {
    $restrictTriggered = str_contains($e->getMessage(), 'active events');
}

assertCampaignTest(
    "20. Existing database constraints respected (RESTRICT prevents deleting campaign with active events)",
    $restrictTriggered &&
    $campaignRepo->hasEvents($campaign['id']) === true
);

// Clean up test event
Database::execute("DELETE FROM events WHERE campaign_id = :cid", [':cid' => $campaign['id']]);

// -----------------------------------------------------------------------------
// 7. Verifying Database Test Hygiene (Zero Lingering Test Rows)
// -----------------------------------------------------------------------------
echo PHP_EOL . "7. Verifying Database Test Hygiene (Zero Lingering Test Rows)..." . PHP_EOL;

// Explicitly clean up test records created during test suite
Database::execute("DELETE FROM campaigns WHERE id = :id", [':id' => $campaign['id']]);
Database::execute("DELETE FROM audit_logs WHERE entity_type = 'campaign'");
Database::execute("DELETE FROM users WHERE id IN (:id1, :id2, :id3, :id4)", [
    ':id1' => $superAdminId,
    ':id2' => $coordinatorId,
    ':id3' => $staffId,
    ':id4' => $viewerId,
]);

$campaignRowCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM campaigns")['total'] ?? -1);
$auditRowCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM audit_logs")['total'] ?? -1);
$userRowCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM users")['total'] ?? -1);

assertCampaignTest(
    "Database 'campaigns' table retains exactly 0 business/test rows",
    $campaignRowCount === 0,
    "Expected 0 rows, found {$campaignRowCount}"
);

assertCampaignTest(
    "Database 'audit_logs' table retains exactly 0 business/test rows",
    $auditRowCount === 0,
    "Expected 0 rows, found {$auditRowCount}"
);

assertCampaignTest(
    "Database 'users' table retains exactly 0 business/test rows",
    $userRowCount === 0,
    "Expected 0 rows, found {$userRowCount}"
);

echo PHP_EOL . "----------------------------------------------------" . PHP_EOL;
echo "Phase 1B Tests Passed: {$passedTests} / {$totalTests}" . PHP_EOL;

if ($failedTests > 0) {
    echo "{$red}{$failedTests} TESTS FAILED!{$reset}" . PHP_EOL;
    exit(1);
} else {
    echo "{$green}ALL PHASE 1B CAMPAIGN REQUIREMENTS VERIFIED SUCCESSFULLY.{$reset}" . PHP_EOL;
    exit(0);
}
