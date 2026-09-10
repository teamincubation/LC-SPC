<?php

declare(strict_types=1);

/**
 * Phase 1D — Participant Management Automated Test Suite
 * Run via: php tests/test_phase1d_participants.php
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Core\App;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Repositories\ParticipantRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\ParticipantService;
use App\Services\RoleService;

// Colors for terminal output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertParticipantTest(string $description, bool $condition, string $details = ''): void
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

echo "=== LC-SPC Phase 1D Participant Management Verification Suite ===" . PHP_EOL . PHP_EOL;

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
        deleted_at DATETIME NULL
    );

    CREATE TABLE events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INTEGER NOT NULL,
        coordinator_id INTEGER NULL,
        title VARCHAR(191) NOT NULL,
        slug VARCHAR(191) NOT NULL,
        category VARCHAR(50) NOT NULL DEFAULT 'workshop',
        description TEXT NULL,
        format VARCHAR(20) NOT NULL DEFAULT 'in_person',
        venue_name VARCHAR(255) NULL,
        venue_address TEXT NULL,
        online_meeting_url VARCHAR(255) NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        capacity INTEGER NOT NULL DEFAULT 0,
        registration_deadline DATETIME NULL,
        requires_approval INTEGER NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        deleted_at DATETIME NULL
    );

    CREATE TABLE participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name VARCHAR(150) NOT NULL,
        email VARCHAR(191) NULL,
        phone VARCHAR(25) NULL,
        category VARCHAR(20) NOT NULL DEFAULT 'community',
        organization_name VARCHAR(191) NULL,
        agreed_guidelines_at DATETIME NOT NULL,
        privacy_consent_at DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
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
$participantRepo = new ParticipantRepository();
$auditRepo = new AuditLogRepository();
$auditService = new AuditService($auditRepo, $userRepo);
$participantService = new ParticipantService($participantRepo, $auditService);

// -----------------------------------------------------------------------------
// Seed Test Users (RBAC Matrix Actors)
// -----------------------------------------------------------------------------
$superAdminId = $userRepo->create([
    'name'          => 'Super Admin Test',
    'email'         => 'superadmin@teami.in',
    'password_hash' => Security::hashPassword('SuperAdminPass123!'),
    'role'          => RoleService::ROLE_SUPER_ADMIN,
    'status'        => 'active',
]);

$coordinatorId = $userRepo->create([
    'name'          => 'Coordinator Test',
    'email'         => 'coordinator@teami.in',
    'password_hash' => Security::hashPassword('CoordinatorPass123!'),
    'role'          => RoleService::ROLE_COORDINATOR,
    'status'        => 'active',
]);

$staffId = $userRepo->create([
    'name'          => 'Staff Test',
    'email'         => 'staff@teami.in',
    'password_hash' => Security::hashPassword('StaffPass123!'),
    'role'          => RoleService::ROLE_STAFF,
    'status'        => 'active',
]);

$viewerId = $userRepo->create([
    'name'          => 'Viewer Test',
    'email'         => 'viewer@teami.in',
    'password_hash' => Security::hashPassword('ViewerPass123!'),
    'role'          => RoleService::ROLE_VIEWER,
    'status'        => 'active',
]);

function getTestCsrfToken(): string
{
    return bin2hex(random_bytes(32));
}

// Helper for HTTP simulation
function simulateParticipantHttp(string $method, string $uri, array $sessionData = [], array $postData = [], array $queryParams = []): Response
{
    global $testPdo;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'LC-SPC-TestRunner/1.0';

    $_GET = $queryParams;
    $_POST = $postData;

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
    } else {
        @session_start();
        $_SESSION = [];
    }

    foreach ($sessionData as $k => $v) {
        $_SESSION[$k] = $v;
    }

    if (!empty($_SESSION['_auth_user_id']) && empty($_SESSION['_auth_last_activity'])) {
        $_SESSION['_auth_last_activity'] = time();
    }

    // Re-bind Database instance
    $ref = new ReflectionProperty(Database::class, 'instance');
    $ref->setAccessible(true);
    $ref->setValue(null, $testPdo);

    $router = new Router();
    require APP_ROOT . '/app/routes.php';

    $request = new Request($queryParams, $postData, [], $_SERVER);
    return $router->dispatch($request);
}

// =============================================================================
// TEST SUITE 1: Participant Repository Foundation & Server-Side Queries
// =============================================================================
echo "--- 1. Participant Repository CRUD, Filtering & Pagination ---" . PHP_EOL;

// 1.1 Create with server-generated timestamps
$p1Id = $participantRepo->create([
    'full_name'            => 'Aarav Patel',
    'email'                => 'aarav.patel@example.com',
    'phone'                => '+919876543210',
    'category'             => 'student',
    'organization_name'    => 'National University',
    'agreed_guidelines_at' => '2026-09-10 10:00:00',
    'privacy_consent_at'   => '2026-09-10 10:00:00',
    'status'               => 'active',
]);
assertParticipantTest("ParticipantRepository::create stores record and returns ID", $p1Id > 0);

// 1.2 findById
$p1 = $participantRepo->findById($p1Id);
assertParticipantTest(
    "ParticipantRepository::findById retrieves correct attributes",
    $p1 !== null && $p1['full_name'] === 'Aarav Patel' && $p1['category'] === 'student' && $p1['status'] === 'active'
);

// 1.3 findByEmail and findOneByEmail
$byEmail = $participantRepo->findByEmail('aarav.patel@example.com');
assertParticipantTest(
    "ParticipantRepository::findByEmail matches exact email",
    !empty($byEmail) && (int) $byEmail[0]['id'] === $p1Id
);

$oneByEmail = $participantRepo->findOneByEmail('aarav.patel@example.com');
assertParticipantTest(
    "ParticipantRepository::findOneByEmail returns single record",
    $oneByEmail !== null && (int) $oneByEmail['id'] === $p1Id
);

// 1.4 findPotentialDuplicate by email
$dupByEmail = $participantRepo->findPotentialDuplicate('Aarav Patel', 'AARAV.PATEL@example.com', null);
assertParticipantTest(
    "ParticipantRepository::findPotentialDuplicate matches case-insensitive email",
    $dupByEmail !== null && (int) $dupByEmail['id'] === $p1Id
);

// 1.5 findPotentialDuplicate by phone
$dupByPhone = $participantRepo->findPotentialDuplicate('Aarav', 'aarav.patel@example.com', '+919876543210');
assertParticipantTest(
    "ParticipantRepository::findPotentialDuplicate matches normalized phone digits",
    $dupByPhone !== null && (int) $dupByPhone['id'] === $p1Id
);

// 1.6 findPotentialDuplicate returns null when no duplicate exists
$dupNone = $participantRepo->findPotentialDuplicate('Someone Else', 'unregistered@domain.org', '+911122334455');
assertParticipantTest(
    "ParticipantRepository::findPotentialDuplicate returns null for unique contact",
    $dupNone === null
);

// Seed additional participants for pagination & search tests
$p2Id = $participantRepo->create([
    'full_name'            => 'Dr. Maya Sen',
    'email'                => 'dr.sen@hospital.org',
    'phone'                => '+919811122233',
    'category'             => 'professional',
    'organization_name'    => 'City Healthcare Trust',
    'agreed_guidelines_at' => '2026-09-10 10:05:00',
    'privacy_consent_at'   => '2026-09-10 10:05:00',
    'status'               => 'active',
]);

$p3Id = $participantRepo->create([
    'full_name'            => 'Rohan Verma',
    'email'                => 'rohan.v@community.net',
    'phone'                => '+919833344455',
    'category'             => 'community',
    'organization_name'    => 'Youth Club',
    'agreed_guidelines_at' => '2026-09-10 10:10:00',
    'privacy_consent_at'   => '2026-09-10 10:10:00',
    'status'               => 'flagged',
]);

$p4Id = $participantRepo->create([
    'full_name'            => 'Blocked User',
    'email'                => 'spam@blocked.org',
    'phone'                => '+919999999999',
    'category'             => 'other',
    'organization_name'    => null,
    'agreed_guidelines_at' => '2026-09-10 10:15:00',
    'privacy_consent_at'   => '2026-09-10 10:15:00',
    'status'               => 'blocked',
]);

// 1.7 Pagination: category filter
$pagCat = $participantRepo->paginate(['category' => 'student'], 1, 10);
assertParticipantTest(
    "ParticipantRepository::paginate filters by category ('student')",
    $pagCat['total'] === 1 && $pagCat['items'][0]['full_name'] === 'Aarav Patel'
);

// 1.8 Pagination: status filter
$pagStat = $participantRepo->paginate(['status' => 'flagged'], 1, 10);
assertParticipantTest(
    "ParticipantRepository::paginate filters by status ('flagged')",
    $pagStat['total'] === 1 && $pagStat['items'][0]['full_name'] === 'Rohan Verma'
);

// 1.9 Pagination: multi-parameter search across fields
$pagSearch = $participantRepo->paginate(['search' => 'City Healthcare'], 1, 10);
assertParticipantTest(
    "ParticipantRepository::paginate searches organization_name successfully",
    $pagSearch['total'] === 1 && $pagSearch['items'][0]['full_name'] === 'Dr. Maya Sen'
);

// 1.10 Pagination metadata accuracy
$pagMeta = $participantRepo->paginate([], 1, 2);
assertParticipantTest(
    "ParticipantRepository::paginate calculates total, total_pages, and current_page accurately",
    $pagMeta['total'] === 4 && $pagMeta['per_page'] === 2 && $pagMeta['total_pages'] === 2 && count($pagMeta['items']) === 2
);

// 1.11 Update participant
$participantRepo->update($p1Id, [
    'full_name'         => 'Aarav Patel (Updated)',
    'organization_name' => 'National University & IIT',
]);
$p1Updated = $participantRepo->findById($p1Id);
assertParticipantTest(
    "ParticipantRepository::update modifies allowed attributes",
    $p1Updated['full_name'] === 'Aarav Patel (Updated)' && $p1Updated['organization_name'] === 'National University & IIT'
);

// 1.12 updateStatus
$participantRepo->updateStatus($p1Id, 'flagged');
$p1Flagged = $participantRepo->findById($p1Id);
assertParticipantTest(
    "ParticipantRepository::updateStatus transitions lifecycle status",
    $p1Flagged['status'] === 'flagged'
);
// Reset back to active
$participantRepo->updateStatus($p1Id, 'active');

// 1.13 countByStatus and countByCategory
$counts = $participantRepo->countByStatus();
assertParticipantTest(
    "ParticipantRepository::countByStatus aggregates correctly",
    ($counts['active'] ?? 0) === 2 && ($counts['flagged'] ?? 0) === 1 && ($counts['blocked'] ?? 0) === 1 && ($counts['total'] ?? 0) === 4
);

echo PHP_EOL;

// =============================================================================
// TEST SUITE 2: Service Layer, Validation, Deduplication & Privacy Masking
// =============================================================================
echo "--- 2. Service Layer: Validation, Deduplication & Privacy Shield ---" . PHP_EOL;

// 2.1 PII Masking: maskEmail
$maskedEmail = ParticipantService::maskEmail('aarav.patel@example.com');
assertParticipantTest(
    "ParticipantService::maskEmail preserves first char, domain, and obscures username",
    $maskedEmail === 'a***@example.com'
);

// 2.2 PII Masking: maskPhone
$maskedPhone = ParticipantService::maskPhone('+91 98765 43210');
assertParticipantTest(
    "ParticipantService::maskPhone obscures prefix and shows last 4 digits",
    str_ends_with($maskedPhone, '3210') && str_contains($maskedPhone, '*****')
);

// 2.3 PII Masking: Role-governed maskParticipant for viewer/staff
$sampleParticipant = [
    'id'        => 99,
    'full_name' => 'Confidential Individual',
    'email'     => 'confidential@example.org',
    'phone'     => '+919988776655',
];
$maskedForViewer = ParticipantService::maskParticipant($sampleParticipant, RoleService::ROLE_VIEWER);
assertParticipantTest(
    "ParticipantService::maskParticipant applies masking for ROLE_VIEWER",
    $maskedForViewer['email'] === 'c***@example.org' &&
    str_ends_with($maskedForViewer['phone'], '6655') &&
    str_contains($maskedForViewer['phone'], '*****') &&
    $maskedForViewer['_is_masked'] === true
);

$maskedForStaff = ParticipantService::maskParticipant($sampleParticipant, RoleService::ROLE_STAFF);
assertParticipantTest(
    "ParticipantService::maskParticipant applies masking for ROLE_STAFF",
    $maskedForStaff['email'] === 'c***@example.org' && $maskedForStaff['_is_masked'] === true
);

// 2.4 PII Masking: Coordinator and Super Admin receive unmasked data
$unmaskedForCoord = ParticipantService::maskParticipant($sampleParticipant, RoleService::ROLE_COORDINATOR);
assertParticipantTest(
    "ParticipantService::maskParticipant does NOT mask for ROLE_COORDINATOR",
    $unmaskedForCoord['email'] === 'confidential@example.org' &&
    $unmaskedForCoord['phone'] === '+919988776655' &&
    $unmaskedForCoord['_is_masked'] === false
);

// 2.5 Consent Validation: agreed_guidelines required
$consentFailed = false;
try {
    $participantService->createParticipant([
        'full_name'         => 'No Consent Test',
        'email'             => 'noconsent@example.com',
        'agreed_guidelines' => false,
        'privacy_consent'   => true,
    ], $coordinatorId);
} catch (ValidationException $e) {
    $consentFailed = isset($e->errors['agreed_guidelines']);
}
assertParticipantTest("ParticipantService::createParticipant rejects missing agreed_guidelines", $consentFailed);

// 2.6 Consent Validation: privacy_consent required
$privacyFailed = false;
try {
    $participantService->createParticipant([
        'full_name'         => 'No Privacy Test',
        'email'             => 'noprivacy@example.com',
        'agreed_guidelines' => true,
        'privacy_consent'   => false,
    ], $coordinatorId);
} catch (ValidationException $e) {
    $privacyFailed = isset($e->errors['privacy_consent']);
}
assertParticipantTest("ParticipantService::createParticipant rejects missing privacy_consent", $privacyFailed);

// 2.7 Successful Creation: generates immutable server timestamps
$createdP = $participantService->createParticipant([
    'full_name'         => 'Kavita Iyer',
    'email'             => 'kavita.iyer@example.com',
    'phone'             => '+919777788888',
    'category'          => 'student',
    'organization_name' => 'State College',
    'agreed_guidelines' => true,
    'privacy_consent'   => true,
    'status'            => 'active',
], $coordinatorId);
assertParticipantTest(
    "ParticipantService::createParticipant logs immutable server-side consent timestamps",
    !empty($createdP['agreed_guidelines_at']) && !empty($createdP['privacy_consent_at'])
);

// 2.8 Deduplication: duplicate detected on admin create halts with warning (never silent merge)
$duplicateCaught = false;
$dupErrorData = [];
try {
    $participantService->createParticipant([
        'full_name'         => 'Kavita Duplicate Attempt',
        'email'             => 'kavita.iyer@example.com', // Duplicate email!
        'phone'             => '+919111122222',
        'agreed_guidelines' => true,
        'privacy_consent'   => true,
    ], $coordinatorId, false); // confirmDistinct = false
} catch (ValidationException $e) {
    $duplicateCaught = true;
    $dupErrorData = $e->errors;
}
assertParticipantTest(
    "ParticipantService::createParticipant halts with duplicate warning when candidate exists",
    $duplicateCaught && isset($dupErrorData['duplicate_detected']) && (int) ($dupErrorData['duplicate_id'] ?? 0) === (int) $createdP['id']
);

// 2.9 Deduplication: with confirmDistinct = true, creates distinct record (no overwrite/merge)
$distinctCreated = $participantService->createParticipant([
    'full_name'         => 'Kavita Family Member',
    'email'             => 'kavita.iyer@example.com', // Shared email
    'phone'             => '+919777788888',
    'agreed_guidelines' => true,
    'privacy_consent'   => true,
], $coordinatorId, true); // confirmDistinct = true
assertParticipantTest(
    "ParticipantService::createParticipant creates distinct record when confirmed distinct",
    $distinctCreated['id'] !== $createdP['id'] && $distinctCreated['full_name'] === 'Kavita Family Member'
);

// Verify original record was NOT overwritten
$originalAfter = $participantRepo->findById((int) $createdP['id']);
assertParticipantTest(
    "Original participant record is never overwritten or merged during duplicate confirmation",
    $originalAfter['full_name'] === 'Kavita Iyer'
);

// 2.10 Immutability of Consent Timestamps during update
$origGuidelinesAt = $createdP['agreed_guidelines_at'];
$origPrivacyAt = $createdP['privacy_consent_at'];
$updatedP = $participantService->updateParticipant((int) $createdP['id'], [
    'full_name'            => 'Kavita Iyer Ph.D.',
    'agreed_guidelines_at' => '1999-01-01 00:00:00', // Malicious attempt to change
    'privacy_consent_at'   => '1999-01-01 00:00:00',
], $coordinatorId);
assertParticipantTest(
    "ParticipantService::updateParticipant preserves original consent timestamps (strictly immutable)",
    $updatedP['agreed_guidelines_at'] === $origGuidelinesAt &&
    $updatedP['privacy_consent_at'] === $origPrivacyAt &&
    $updatedP['full_name'] === 'Kavita Iyer Ph.D.'
);

// 2.11 Status Change with Reason Sanitization (Rule 11: Scrub clinical/distress data)
$participantService->updateStatus((int) $createdP['id'], 'flagged', 'Attendee contacted desk for email fix', $coordinatorId);
$afterStatus = $participantRepo->findById((int) $createdP['id']);
assertParticipantTest(
    "ParticipantService::updateStatus transitions participant to 'flagged'",
    $afterStatus['status'] === 'flagged'
);

// Check audit log for status change
$latestAudit = $testPdo->query("SELECT * FROM audit_logs WHERE entity_type = 'participant' AND entity_id = " . (int) $createdP['id'] . " ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$auditMeta = json_decode($latestAudit['metadata'] ?? '{}', true);
assertParticipantTest(
    "Participant status change audit log contains reason in metadata and logs transition",
    ($auditMeta['new_status'] ?? '') === 'flagged' && ($auditMeta['reason'] ?? '') === 'Attendee contacted desk for email fix'
);

// 2.12 Status change scrubs sensitive distress keywords (Rule 11 compliance)
$participantService->updateStatus((int) $createdP['id'], 'active', 'Attending doctor counselling session for depression', $coordinatorId);
$scrubbedAudit = $testPdo->query("SELECT * FROM audit_logs WHERE entity_type = 'participant' AND entity_id = " . (int) $createdP['id'] . " ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$scrubbedMeta = json_decode($scrubbedAudit['metadata'] ?? '{}', true);
assertParticipantTest(
    "Participant status reason sanitization scrubs prohibited clinical/distress terms",
    !str_contains(strtolower($scrubbedMeta['reason'] ?? ''), 'counselling') &&
    !str_contains(strtolower($scrubbedMeta['reason'] ?? ''), 'depression')
);

// 2.13 resolveOrCreateParticipant (Reusable Phase 1E logic)
$resolved = $participantService->resolveOrCreateParticipant([
    'full_name'         => 'Kavita Iyer Ph.D.',
    'email'             => 'kavita.iyer@example.com',
    'agreed_guidelines' => true,
    'privacy_consent'   => true,
]);
assertParticipantTest(
    "ParticipantService::resolveOrCreateParticipant resolves existing participant by email",
    (int) $resolved['id'] === (int) $createdP['id']
);

$newResolved = $participantService->resolveOrCreateParticipant([
    'full_name'         => 'Brand New Attendee',
    'email'             => 'brandnew@community.org',
    'agreed_guidelines' => true,
    'privacy_consent'   => true,
]);
assertParticipantTest(
    "ParticipantService::resolveOrCreateParticipant creates new participant when non-existent",
    $newResolved['id'] > 0 && $newResolved['full_name'] === 'Brand New Attendee'
);

echo PHP_EOL;

// =============================================================================
// TEST SUITE 3: RBAC Enforcement, HTTP Controller Actions & Privacy Shield
// =============================================================================
echo "--- 3. HTTP Controller Endpoints & RBAC Matrix Verification ---" . PHP_EOL;

// 3.1 GET /admin/participants (Viewer - Allowed, HTTP 200, PII Masked)
$respViewerList = simulateParticipantHttp('GET', '/admin/participants', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $viewerId,
    '_auth_user_role' => RoleService::ROLE_VIEWER,
]);
assertParticipantTest(
    "GET /admin/participants returns HTTP 200 for ROLE_VIEWER",
    $respViewerList->getStatusCode() === 200 && str_contains($respViewerList->getBody(), 'Participant Directory')
);
assertParticipantTest(
    "GET /admin/participants response body masks contact emails for ROLE_VIEWER",
    str_contains($respViewerList->getBody(), 'a***@example.com') && !str_contains($respViewerList->getBody(), 'aarav.patel@example.com')
);

// 3.2 GET /admin/participants (Coordinator - Allowed, HTTP 200, Unmasked)
$respCoordList = simulateParticipantHttp('GET', '/admin/participants', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
]);
assertParticipantTest(
    "GET /admin/participants displays unmasked emails for ROLE_COORDINATOR",
    $respCoordList->getStatusCode() === 200 && str_contains($respCoordList->getBody(), 'aarav.patel@example.com')
);

// 3.3 GET /admin/participants/create (Coordinator - Allowed, HTTP 200)
$respCoordCreate = simulateParticipantHttp('GET', '/admin/participants/create', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
]);
assertParticipantTest(
    "GET /admin/participants/create returns HTTP 200 for ROLE_COORDINATOR",
    $respCoordCreate->getStatusCode() === 200 && str_contains($respCoordCreate->getBody(), 'Register Participant')
);

// 3.4 GET /admin/participants/create (Staff/Viewer - Denied, HTTP 403)
$respStaffCreate = simulateParticipantHttp('GET', '/admin/participants/create', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $staffId,
    '_auth_user_role' => RoleService::ROLE_STAFF,
]);
assertParticipantTest(
    "GET /admin/participants/create returns HTTP 403 Forbidden for ROLE_STAFF",
    $respStaffCreate->getStatusCode() === 403
);

$respViewerCreate = simulateParticipantHttp('GET', '/admin/participants/create', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $viewerId,
    '_auth_user_role' => RoleService::ROLE_VIEWER,
]);
assertParticipantTest(
    "GET /admin/participants/create returns HTTP 403 Forbidden for ROLE_VIEWER",
    $respViewerCreate->getStatusCode() === 403
);

// 3.5 POST /admin/participants (Staff - Denied, HTTP 403)
$csrfToken = getTestCsrfToken();
$respStaffPost = simulateParticipantHttp('POST', '/admin/participants', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $staffId,
    '_auth_user_role' => RoleService::ROLE_STAFF,
    '_csrf_token'     => $csrfToken,
], [
    '_csrf_token'       => $csrfToken,
    'full_name'         => 'Forbidden Create',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
]);
assertParticipantTest(
    "POST /admin/participants returns HTTP 403 Forbidden for ROLE_STAFF",
    $respStaffPost->getStatusCode() === 403
);

// 3.6 POST /admin/participants (Coordinator - Allowed, HTTP 302 redirect)
$csrfToken = getTestCsrfToken();
$respCoordPost = simulateParticipantHttp('POST', '/admin/participants', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
    '_csrf_token'     => $csrfToken,
], [
    '_csrf_token'       => $csrfToken,
    'full_name'         => 'Http Created Attendee',
    'email'             => 'http.attendee@university.edu',
    'phone'             => '+919444455555',
    'category'          => 'student',
    'organization_name' => 'Metro College',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
    'status'            => 'active',
]);
assertParticipantTest(
    "POST /admin/participants stores participant and redirects to directory",
    $respCoordPost->getStatusCode() === 302 && $respCoordPost->getHeader('Location') === '/admin/participants'
);

// Verify newly created record exists in DB
$httpP = $participantRepo->findOneByEmail('http.attendee@university.edu');
assertParticipantTest(
    "POST /admin/participants successfully persisted record in repository",
    $httpP !== null && $httpP['full_name'] === 'Http Created Attendee'
);

// 3.7 GET /admin/participants/{id} (Viewer - Allowed, HTTP 200, Masked Contact)
$respViewerShow = simulateParticipantHttp('GET', "/admin/participants/{$httpP['id']}", [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $viewerId,
    '_auth_user_role' => RoleService::ROLE_VIEWER,
]);
assertParticipantTest(
    "GET /admin/participants/{id} returns HTTP 200 for ROLE_VIEWER",
    $respViewerShow->getStatusCode() === 200 && str_contains($respViewerShow->getBody(), 'Http Created Attendee')
);
assertParticipantTest(
    "GET /admin/participants/{id} applies Privacy Shield mask for ROLE_VIEWER",
    str_contains($respViewerShow->getBody(), 'h***@university.edu') &&
    str_contains($respViewerShow->getBody(), '5555') &&
    str_contains($respViewerShow->getBody(), '*****') &&
    str_contains($respViewerShow->getBody(), 'Privacy Shield Active')
);

// 3.8 GET /admin/participants/{id} (Coordinator - Allowed, HTTP 200, Unmasked)
$respCoordShow = simulateParticipantHttp('GET', "/admin/participants/{$httpP['id']}", [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
]);
assertParticipantTest(
    "GET /admin/participants/{id} displays unmasked contact for ROLE_COORDINATOR",
    $respCoordShow->getStatusCode() === 200 &&
    str_contains($respCoordShow->getBody(), 'http.attendee@university.edu') &&
    str_contains($respCoordShow->getBody(), '+919444455555')
);

// 3.9 GET /admin/participants/{id}/edit (Coordinator - Allowed, HTTP 200, Read-Only Consent Badges)
$respCoordEdit = simulateParticipantHttp('GET', "/admin/participants/{$httpP['id']}/edit", [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
]);
assertParticipantTest(
    "GET /admin/participants/{id}/edit displays form with immutable consent timestamps",
    $respCoordEdit->getStatusCode() === 200 &&
    str_contains($respCoordEdit->getBody(), 'Immutable Compliance Timestamps') &&
    !str_contains($respCoordEdit->getBody(), 'name="agreed_guidelines"')
);

// 3.10 GET /admin/participants/{id}/edit (Staff/Viewer - Denied, HTTP 403)
$respStaffEdit = simulateParticipantHttp('GET', "/admin/participants/{$httpP['id']}/edit", [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $staffId,
    '_auth_user_role' => RoleService::ROLE_STAFF,
]);
assertParticipantTest(
    "GET /admin/participants/{id}/edit returns HTTP 403 Forbidden for ROLE_STAFF",
    $respStaffEdit->getStatusCode() === 403
);

// 3.11 POST /admin/participants/{id} (Coordinator - Allowed, HTTP 302 redirect)
$csrfToken = getTestCsrfToken();
$respCoordUpdate = simulateParticipantHttp('POST', "/admin/participants/{$httpP['id']}", [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
    '_csrf_token'     => $csrfToken,
], [
    '_csrf_token'       => $csrfToken,
    'full_name'         => 'Http Created Attendee (Modified)',
    'email'             => 'http.attendee@university.edu',
    'phone'             => '+919444455555',
    'category'          => 'professional',
    'organization_name' => 'Metro Medical College',
    'status'            => 'active',
]);
assertParticipantTest(
    "POST /admin/participants/{id} updates record and redirects to profile",
    $respCoordUpdate->getStatusCode() === 302 && $respCoordUpdate->getHeader('Location') === "/admin/participants/{$httpP['id']}"
);

$httpPAfter = $participantRepo->findById((int) $httpP['id']);
assertParticipantTest(
    "POST /admin/participants/{id} persisted updated fields in repository",
    $httpPAfter['full_name'] === 'Http Created Attendee (Modified)' && $httpPAfter['category'] === 'professional'
);

// 3.12 POST /admin/participants/{id}/status (Coordinator - Allowed, HTTP 302)
$csrfToken = getTestCsrfToken();
$respCoordStatus = simulateParticipantHttp('POST', "/admin/participants/{$httpP['id']}/status", [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
    '_csrf_token'     => $csrfToken,
], [
    '_csrf_token' => $csrfToken,
    'status'      => 'blocked',
    'reason'      => 'Disciplinary action: misconduct during previous session',
]);
assertParticipantTest(
    "POST /admin/participants/{id}/status updates status and redirects",
    $respCoordStatus->getStatusCode() === 302
);

$httpPBlocked = $participantRepo->findById((int) $httpP['id']);
assertParticipantTest(
    "POST /admin/participants/{id}/status transitioned participant to 'blocked'",
    $httpPBlocked['status'] === 'blocked'
);

// 3.13 POST /admin/participants/{id}/status (Staff - Denied, HTTP 403)
$csrfToken = getTestCsrfToken();
$respStaffStatus = simulateParticipantHttp('POST', "/admin/participants/{$httpP['id']}/status", [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $staffId,
    '_auth_user_role' => RoleService::ROLE_STAFF,
    '_csrf_token'     => $csrfToken,
], [
    '_csrf_token' => $csrfToken,
    'status'      => 'active',
]);
assertParticipantTest(
    "POST /admin/participants/{id}/status returns HTTP 403 Forbidden for ROLE_STAFF",
    $respStaffStatus->getStatusCode() === 403
);

// 3.14 Duplicate Warning Flow in Controller (POST /admin/participants)
$csrfToken = getTestCsrfToken();
$respDupPost = simulateParticipantHttp('POST', '/admin/participants', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
    '_csrf_token'     => $csrfToken,
], [
    '_csrf_token'       => $csrfToken,
    'full_name'         => 'Duplicate Attendee Entry',
    'email'             => 'http.attendee@university.edu', // Duplicate!
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
]);
assertParticipantTest(
    "POST /admin/participants with duplicate email redirects back to create form",
    $respDupPost->getStatusCode() === 302 && $respDupPost->getHeader('Location') === '/admin/participants/create'
);

// Follow flash session into GET /admin/participants/create
$respDupCreatePage = simulateParticipantHttp('GET', '/admin/participants/create', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
    '_flash'          => [
        'duplicate' => [
            'message' => 'Potential duplicate detected in registry.',
            'id'      => $httpP['id'],
            'name'    => 'Http Created Attendee (Modified)',
        ],
    ],
]);
assertParticipantTest(
    "GET /admin/participants/create displays duplicate warning notice and confirm_distinct checkbox",
    str_contains($respDupCreatePage->getBody(), 'Potential Duplicate Detected') &&
    str_contains($respDupCreatePage->getBody(), 'Confirm Distinct Participant')
);

// =============================================================================
// 4. Verifying Database Test Hygiene (Zero Lingering Test Rows)
// =============================================================================
echo PHP_EOL . "--- 4. Verifying Database Test Hygiene (Zero Lingering Test Rows) ---" . PHP_EOL;

Database::execute("DELETE FROM participants");
Database::execute("DELETE FROM events");
Database::execute("DELETE FROM campaigns");
Database::execute("DELETE FROM audit_logs");
Database::execute("DELETE FROM users");

$participantRowCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM participants")['total'] ?? -1);
$auditRowCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM audit_logs")['total'] ?? -1);
$userRowCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM users")['total'] ?? -1);

assertParticipantTest(
    "Database 'participants' table retains exactly 0 business/test rows",
    $participantRowCount === 0,
    "Expected 0 rows, found {$participantRowCount}"
);

assertParticipantTest(
    "Database 'audit_logs' table retains exactly 0 business/test rows",
    $auditRowCount === 0,
    "Expected 0 rows, found {$auditRowCount}"
);

assertParticipantTest(
    "Database 'users' table retains exactly 0 business/test rows",
    $userRowCount === 0,
    "Expected 0 rows, found {$userRowCount}"
);

// =============================================================================
// SUMMARY REPORT
// =============================================================================
echo PHP_EOL;
echo "=================================================" . PHP_EOL;
echo "Phase 1D Participant Management Suite Results:" . PHP_EOL;
echo "Total Assertions: {$totalTests}" . PHP_EOL;
echo "Passed: {$green}{$passedTests}{$reset}" . PHP_EOL;
echo "Failed: " . ($failedTests > 0 ? "{$red}{$failedTests}{$reset}" : "0") . PHP_EOL;
echo "=================================================" . PHP_EOL;

if ($failedTests > 0) {
    exit(1);
}
exit(0);
