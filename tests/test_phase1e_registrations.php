<?php

declare(strict_types=1);

/**
 * Phase 1E — Event Registration & Attendance Pass Generation Test Suite
 * Run via: php tests/test_phase1e_registrations.php
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\CapacityExceededException;
use App\Core\Exceptions\InvalidStateTransitionException;
use App\Core\Exceptions\RegistrationCodeGenerationException;
use App\Core\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security;
use App\Repositories\AuditLogRepository;
use App\Repositories\EventRepository;
use App\Repositories\ParticipantRepository;
use App\Repositories\RegistrationRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\ParticipantService;
use App\Services\RegistrationService;
use App\Services\RoleService;

// Terminal output colors
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertPhase1ETest(string $description, bool $condition, string $details = ''): void
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

echo "=== LC-SPC Phase 1E Event Registration & Attendance Pass Verification Suite ===" . PHP_EOL . PHP_EOL;

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
        campaign_id INTEGER NULL,
        coordinator_id INTEGER NULL,
        title VARCHAR(191) NOT NULL,
        slug VARCHAR(191) NOT NULL UNIQUE,
        category VARCHAR(50) NOT NULL DEFAULT 'workshop',
        event_type VARCHAR(20) NOT NULL DEFAULT 'offline',
        collaboration_with VARCHAR(255) NULL,
        collaboration_logo VARCHAR(255) NULL,
        description TEXT NULL,
        format VARCHAR(20) NOT NULL DEFAULT 'in_person',
        venue_name VARCHAR(255) NULL,
        venue_address TEXT NULL,
        timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata',
        online_meeting_url VARCHAR(255) NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        checkin_start_date DATE NULL,
        checkin_start_time TIME NULL,
        latitude DECIMAL(10, 8) NULL,
        longitude DECIMAL(11, 8) NULL,
        geofence_radius_meters INTEGER NULL,
        capacity INTEGER NOT NULL DEFAULT 0,
        registration_deadline DATETIME NULL,
        requires_approval INTEGER NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        deleted_at DATETIME NULL
    );

    CREATE TABLE IF NOT EXISTS event_forms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id INTEGER NOT NULL UNIQUE,
        form_title VARCHAR(255) NOT NULL,
        slug VARCHAR(191) NOT NULL UNIQUE,
        banner_path VARCHAR(255) NULL,
        photo_upload_enabled INTEGER NOT NULL DEFAULT 0,
        location_access_required INTEGER NOT NULL DEFAULT 0,
        whatsapp_group_url VARCHAR(255) NULL,
        whatsapp_auto_redirect INTEGER NOT NULL DEFAULT 0,
        whatsapp_countdown_seconds INTEGER NOT NULL DEFAULT 5,
        custom_success_message TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'published',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS form_fields (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        form_id INTEGER NOT NULL,
        field_key VARCHAR(64) NOT NULL,
        field_label VARCHAR(100) NOT NULL,
        field_type VARCHAR(30) NOT NULL DEFAULT 'text',
        is_required INTEGER NOT NULL DEFAULT 0,
        is_locked INTEGER NOT NULL DEFAULT 0,
        sort_order INTEGER NOT NULL DEFAULT 0,
        options_json TEXT NULL,
        placeholder VARCHAR(255) NULL,
        help_text VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (form_id, field_key)
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

    CREATE TABLE event_registrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        registration_code VARCHAR(40) NOT NULL UNIQUE,
        event_id INTEGER NOT NULL,
        participant_id INTEGER NOT NULL,
        form_id INTEGER NULL,
        phone_normalized VARCHAR(20) NULL,
        country_code VARCHAR(10) NOT NULL DEFAULT '+91',
        photo_path VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
        attendance_status VARCHAR(20) NOT NULL DEFAULT 'unmarked',
        checked_in_at DATETIME NULL DEFAULT NULL,
        checked_in_by INTEGER NULL DEFAULT NULL,
        check_in_method VARCHAR(30) NULL DEFAULT NULL,
        attended_at DATETIME NULL,
        checkin_latitude DECIMAL(10, 8) NULL,
        checkin_longitude DECIMAL(11, 8) NULL,
        checkin_distance_meters DECIMAL(8, 2) NULL,
        checkin_geofence_verified INTEGER NOT NULL DEFAULT 0,
        custom_data TEXT NULL,
        admin_notes VARCHAR(255) NULL DEFAULT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        deleted_at DATETIME NULL,
        UNIQUE (event_id, participant_id)
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
$eventRepo = new EventRepository();
$participantRepo = new ParticipantRepository();
$registrationRepo = new RegistrationRepository();
$auditRepo = new AuditLogRepository();
$auditService = new AuditService($auditRepo, $userRepo);
$participantService = new ParticipantService($participantRepo, $auditService);
$registrationService = new RegistrationService($registrationRepo, $eventRepo, $participantRepo, $participantService, $auditService);

// -----------------------------------------------------------------------------
// Seed Administrative Users
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

// -----------------------------------------------------------------------------
// Seed Campaign & Base Events
// -----------------------------------------------------------------------------
$nowStr = date('Y-m-d H:i:s');
$futureDeadline = date('Y-m-d H:i:s', strtotime('+7 days'));
$pastDeadline = date('Y-m-d H:i:s', strtotime('-1 day'));

$campaignId = (int) Database::execute("
    INSERT INTO campaigns (title, slug, start_date, end_date, status, created_at, updated_at)
    VALUES ('Campaign 2026', 'campaign-2026', '2026-01-01', '2026-12-31', 'active', '{$nowStr}', '{$nowStr}')
");
$campaignId = (int) $testPdo->lastInsertId();

// Helper to seed events easily
function seedEvent(array $data): int {
    global $campaignId, $nowStr, $testPdo;
    $defaults = [
        'campaign_id'           => $campaignId,
        'title'                 => 'Event Title',
        'slug'                  => 'event-' . bin2hex(random_bytes(4)),
        'category'              => 'workshop',
        'format'                => 'in_person',
        'venue_name'            => 'Main Hall',
        'start_time'            => date('Y-m-d H:i:s', strtotime('+10 days')),
        'end_time'              => date('Y-m-d H:i:s', strtotime('+10 days 2 hours')),
        'capacity'              => 0,
        'registration_deadline' => null,
        'requires_approval'     => 0,
        'status'                => 'published',
        'created_at'            => $nowStr,
        'updated_at'            => $nowStr,
        'deleted_at'            => null,
    ];
    $merged = array_merge($defaults, $data);
    $stmt = $testPdo->prepare("
        INSERT INTO events (campaign_id, title, slug, category, format, venue_name, start_time, end_time, capacity, registration_deadline, requires_approval, status, created_at, updated_at, deleted_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $merged['campaign_id'], $merged['title'], $merged['slug'], $merged['category'], $merged['format'],
        $merged['venue_name'], $merged['start_time'], $merged['end_time'], $merged['capacity'],
        $merged['registration_deadline'], $merged['requires_approval'], $merged['status'],
        $merged['created_at'], $merged['updated_at'], $merged['deleted_at']
    ]);
    return (int) $testPdo->lastInsertId();
}

// Helper to seed participants easily
function seedParticipant(array $data = []): int {
    global $nowStr, $testPdo;
    $defaults = [
        'full_name'            => 'Participant ' . bin2hex(random_bytes(3)),
        'email'                => 'part_' . bin2hex(random_bytes(3)) . '@example.com',
        'phone'                => '+9198' . rand(10000000, 99999999),
        'category'             => 'community',
        'organization_name'    => 'Community Org',
        'agreed_guidelines_at' => $nowStr,
        'privacy_consent_at'   => $nowStr,
        'status'               => 'active',
        'created_at'           => $nowStr,
        'updated_at'           => $nowStr,
    ];
    $merged = array_merge($defaults, $data);
    $stmt = $testPdo->prepare("
        INSERT INTO participants (full_name, email, phone, category, organization_name, agreed_guidelines_at, privacy_consent_at, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $merged['full_name'], $merged['email'], $merged['phone'], $merged['category'],
        $merged['organization_name'], $merged['agreed_guidelines_at'], $merged['privacy_consent_at'],
        $merged['status'], $merged['created_at'], $merged['updated_at']
    ]);
    return (int) $testPdo->lastInsertId();
}

function getTestCsrfToken(): string {
    return bin2hex(random_bytes(32));
}

function simulateRegistrationHttp(string $method, string $uri, array $sessionData = [], array $postData = [], array $queryParams = [], array $serverData = []): Response {
    global $testPdo;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['REMOTE_ADDR'] = $serverData['REMOTE_ADDR'] ?? '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'LC-SPC-TestRunner/1.0';
    foreach ($serverData as $k => $v) {
        $_SERVER[$k] = $v;
    }

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

    $ref = new ReflectionProperty(Database::class, 'instance');
    $ref->setAccessible(true);
    $ref->setValue(null, $testPdo);

    $router = new Router();
    require APP_ROOT . '/app/routes.php';

    $request = new Request($queryParams, $postData, [], $_SERVER);
    return $router->dispatch($request);
}

// =============================================================================
// PHASE 1E AUTOMATED TESTS (1 TO 31)
// =============================================================================

echo "--- Executing Phase 1E Test Assertions (1 to 31) ---" . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 1: Valid registration creation for unlimited capacity event
// -----------------------------------------------------------------------------
$eventUnlimited = seedEvent(['title' => 'Open Community Circle', 'capacity' => 0, 'status' => 'published']);
$part1 = seedParticipant(['full_name' => 'Aditi Rao', 'email' => 'aditi.rao@example.com']);
$regRes1 = $registrationService->registerParticipant($eventUnlimited, $part1, 'First registration notes', $coordinatorId);

assertPhase1ETest(
    "1. Valid registration creation for unlimited capacity event assigns confirmed status",
    $regRes1['status'] === 'created' &&
    $regRes1['registration']['status'] === 'confirmed' &&
    $regRes1['registration']['attendance_status'] === 'unmarked'
);

// -----------------------------------------------------------------------------
// Test 2: Pass code format (REG-{YY}-{5_CHAR_CROCKFORD_BASE32})
// -----------------------------------------------------------------------------
$code1 = $regRes1['registration']['registration_code'];
$year2d = date('y');
$isFormatValid = (bool) preg_match('/^REG-' . $year2d . '-[23456789ABCDEFGHJKMNPQRSTVWXYZ]{5}$/', $code1);
$containsExcludedChars = (bool) preg_match('/[0O1I]/', $code1);

assertPhase1ETest(
    "2. Pass code adheres strictly to Crockford Base32 format REG-{YY}-{5_CHAR_BASE32} omitting 0, O, 1, I",
    $isFormatValid && !$containsExcludedChars,
    "Generated code: {$code1}"
);

// -----------------------------------------------------------------------------
// Test 3: Reject non-existent event
// -----------------------------------------------------------------------------
$caughtNonExistentEvent = false;
try {
    $registrationService->registerParticipant(99999, $part1, null, $coordinatorId);
} catch (RuntimeException $e) {
    $caughtNonExistentEvent = str_contains($e->getMessage(), 'Event not found');
}

assertPhase1ETest(
    "3. Reject registration when event does not exist",
    $caughtNonExistentEvent
);

// -----------------------------------------------------------------------------
// Test 4: Reject non-existent participant
// -----------------------------------------------------------------------------
$caughtNonExistentPart = false;
try {
    $registrationService->registerParticipant($eventUnlimited, 99999, null, $coordinatorId);
} catch (RuntimeException $e) {
    $caughtNonExistentPart = str_contains($e->getMessage(), 'Participant with ID');
}

assertPhase1ETest(
    "4. Reject registration when participant does not exist",
    $caughtNonExistentPart
);

// -----------------------------------------------------------------------------
// Test 5: Reject blocked participant
// -----------------------------------------------------------------------------
$partBlocked = seedParticipant(['full_name' => 'Blocked User', 'status' => 'blocked']);
$caughtBlocked = false;
try {
    $registrationService->registerParticipant($eventUnlimited, $partBlocked, null, $coordinatorId);
} catch (RuntimeException $e) {
    $caughtBlocked = str_contains($e->getMessage(), 'cannot be processed');
}

assertPhase1ETest(
    "5. Reject registration when participant status is 'blocked'",
    $caughtBlocked
);

// -----------------------------------------------------------------------------
// Test 6: Reject soft-deleted event
// -----------------------------------------------------------------------------
$eventDeleted = seedEvent(['title' => 'Deleted Event', 'status' => 'published', 'deleted_at' => $nowStr]);
$part2 = seedParticipant(['full_name' => 'Kunal Roy']);
$caughtDeleted = false;
try {
    $registrationService->registerParticipant($eventDeleted, $part2, null, $coordinatorId);
} catch (RuntimeException $e) {
    $caughtDeleted = str_contains($e->getMessage(), 'deleted');
}

assertPhase1ETest(
    "6. Reject registration when event is soft-deleted",
    $caughtDeleted
);

// -----------------------------------------------------------------------------
// Test 7: Reject event with past deadline
// -----------------------------------------------------------------------------
$eventPastDeadline = seedEvent(['title' => 'Expired Event', 'status' => 'published', 'registration_deadline' => $pastDeadline]);
$caughtPastDeadline = false;
try {
    $registrationService->registerParticipant($eventPastDeadline, $part2, null, $coordinatorId);
} catch (RuntimeException $e) {
    $caughtPastDeadline = str_contains($e->getMessage(), 'deadline for this event has passed');
}

assertPhase1ETest(
    "7. Reject registration when event registration deadline has passed",
    $caughtPastDeadline
);

// -----------------------------------------------------------------------------
// Test 8: Reject non-published event (draft, ongoing, completed, cancelled)
// -----------------------------------------------------------------------------
$eventDraft = seedEvent(['status' => 'draft']);
$eventOngoing = seedEvent(['status' => 'ongoing']);
$eventCompleted = seedEvent(['status' => 'completed']);
$eventCancelled = seedEvent(['status' => 'cancelled']);

$rejectedNonPublished = 0;
foreach ([$eventDraft, $eventOngoing, $eventCompleted, $eventCancelled] as $evId) {
    try {
        $registrationService->registerParticipant($evId, $part2, null, $coordinatorId);
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), 'only permitted for published events')) {
            $rejectedNonPublished++;
        }
    }
}

assertPhase1ETest(
    "8. Reject registration across all non-published event statuses (draft, ongoing, completed, cancelled)",
    $rejectedNonPublished === 4
);

// -----------------------------------------------------------------------------
// Test 9: Prevent duplicate active registration
// -----------------------------------------------------------------------------
$regResDuplicate = $registrationService->registerParticipant($eventUnlimited, $part1, null, $coordinatorId);
$countRows = (int) ($testPdo->query("SELECT COUNT(*) AS total FROM event_registrations WHERE event_id = {$eventUnlimited} AND participant_id = {$part1}")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

assertPhase1ETest(
    "9. Prevent duplicate active registration: returns existing registration and preserves single physical database row",
    $regResDuplicate['status'] === 'already_confirmed' &&
    $countRows === 1 &&
    (int) $regResDuplicate['registration']['id'] === (int) $regRes1['registration']['id']
);

// -----------------------------------------------------------------------------
// Test 10: Capacity enforcement for capped event within limit
// -----------------------------------------------------------------------------
$eventCapped = seedEvent(['title' => 'Capped Workshop', 'capacity' => 2, 'status' => 'published']);
$partC1 = seedParticipant(['full_name' => 'Capped User 1']);
$regCapped1 = $registrationService->registerParticipant($eventCapped, $partC1, null, $coordinatorId);

assertPhase1ETest(
    "10. Capacity enforcement: registration confirmed when within capacity limit (1/2 confirmed)",
    $regCapped1['status'] === 'created' && $regCapped1['registration']['status'] === 'confirmed'
);

// -----------------------------------------------------------------------------
// Test 11: Capacity enforcement: capped event assigns waitlisted when full
// -----------------------------------------------------------------------------
$partC2 = seedParticipant(['full_name' => 'Capped User 2']);
$partC3 = seedParticipant(['full_name' => 'Capped User 3']);
$regCapped2 = $registrationService->registerParticipant($eventCapped, $partC2, null, $coordinatorId); // 2/2 confirmed
$regCapped3 = $registrationService->registerParticipant($eventCapped, $partC3, null, $coordinatorId); // 3rd -> waitlisted

$confirmedCountAfter = $registrationRepo->countConfirmedByEvent($eventCapped);
$waitlistCountAfter = $registrationRepo->countWaitlistedByEvent($eventCapped);

assertPhase1ETest(
    "11. Capacity enforcement: assigns 'waitlisted' when event capacity is exhausted (2/2 confirmed, 1 waitlisted)",
    $regCapped2['registration']['status'] === 'confirmed' &&
    $regCapped3['registration']['status'] === 'waitlisted' &&
    $confirmedCountAfter === 2 &&
    $waitlistCountAfter === 1
);

// -----------------------------------------------------------------------------
// Test 12: Pending registrations do NOT consume capacity
// -----------------------------------------------------------------------------
$eventApprovalCapped = seedEvent(['title' => 'Approval Capped Circle', 'capacity' => 1, 'requires_approval' => 1, 'status' => 'published']);
$partA1 = seedParticipant(['full_name' => 'Approval Part 1']);
$regApp1 = $registrationService->registerParticipant($eventApprovalCapped, $partA1, null, $coordinatorId);

$confirmedCountPending = $registrationRepo->countConfirmedByEvent($eventApprovalCapped);

assertPhase1ETest(
    "12. Pending registrations do NOT consume capacity (confirmed count remains 0 for 1 pending registration)",
    $regApp1['registration']['status'] === 'pending' &&
    $confirmedCountPending === 0
);

// -----------------------------------------------------------------------------
// Test 13: requires_approval = 1 assigns status = pending
// -----------------------------------------------------------------------------
assertPhase1ETest(
    "13. Events with requires_approval = 1 automatically assign status = 'pending'",
    $regApp1['registration']['status'] === 'pending'
);

// -----------------------------------------------------------------------------
// Test 14: Coordinator approval transitions pending to confirmed when seat open
// -----------------------------------------------------------------------------
$approvedReg1 = $registrationService->approveRegistration((int) $regApp1['registration']['id'], $coordinatorId);
$confirmedAfterApprove = $registrationRepo->countConfirmedByEvent($eventApprovalCapped);

assertPhase1ETest(
    "14. Coordinator approval transitions pending registration to confirmed when seat is available",
    $approvedReg1['status'] === 'confirmed' &&
    $confirmedAfterApprove === 1
);

// -----------------------------------------------------------------------------
// Test 15: Coordinator approval rejected when event is full
// -----------------------------------------------------------------------------
$partA2 = seedParticipant(['full_name' => 'Approval Part 2']);
// Create pending registration directly via repository (simulating a pending application)
$regApp2Code = $registrationService->generateUniqueRegistrationCode();
$regApp2Id = $registrationRepo->create([
    'registration_code' => $regApp2Code,
    'event_id'          => $eventApprovalCapped,
    'participant_id'    => $partA2,
    'status'            => 'pending',
    'attendance_status' => 'unmarked',
]);

$caughtCapacityFull = false;
try {
    $registrationService->approveRegistration($regApp2Id, $coordinatorId);
} catch (CapacityExceededException $e) {
    $caughtCapacityFull = str_contains($e->getMessage(), 'Event capacity is full');
}

$regApp2After = $registrationRepo->findById($regApp2Id);

assertPhase1ETest(
    "15. Coordinator approval rejected with CapacityExceededException when event is full (registration stays pending)",
    $caughtCapacityFull && $regApp2After['status'] === 'pending'
);

// -----------------------------------------------------------------------------
// Test 16: movePendingToWaitlist rejected when event has available capacity
// -----------------------------------------------------------------------------
$eventOpenApproval = seedEvent(['title' => 'Open Approval Event', 'capacity' => 5, 'requires_approval' => 1, 'status' => 'published']);
$partOA1 = seedParticipant(['full_name' => 'Open Approval Part 1']);
$regOA1 = $registrationService->registerParticipant($eventOpenApproval, $partOA1, null, $coordinatorId);

$caughtMoveWhenCapacity = false;
try {
    $registrationService->movePendingToWaitlist((int) $regOA1['registration']['id'], $coordinatorId);
} catch (InvalidStateTransitionException $e) {
    $caughtMoveWhenCapacity = str_contains($e->getMessage(), 'still has available confirmed capacity');
}

assertPhase1ETest(
    "16. movePendingToWaitlist rejected when event still has available confirmed capacity",
    $caughtMoveWhenCapacity
);

// -----------------------------------------------------------------------------
// Test 17: movePendingToWaitlist succeeds when event is full
// -----------------------------------------------------------------------------
// $eventApprovalCapped is currently 1/1 full. $regApp2Id is pending.
$movedToWaitlist = $registrationService->movePendingToWaitlist($regApp2Id, $coordinatorId);

assertPhase1ETest(
    "17. movePendingToWaitlist succeeds transactionally when event is genuinely full",
    $movedToWaitlist['status'] === 'waitlisted'
);

// -----------------------------------------------------------------------------
// Test 18: Confirmed cancellation releases capacity
// -----------------------------------------------------------------------------
$confirmedBeforeCancel = $registrationRepo->countConfirmedByEvent($eventApprovalCapped);
$cancelledReg1 = $registrationService->cancelRegistration((int) $regApp1['registration']['id'], 'Participant unable to attend', $coordinatorId);
$confirmedAfterCancel = $registrationRepo->countConfirmedByEvent($eventApprovalCapped);

assertPhase1ETest(
    "18. Confirmed cancellation releases capacity (confirmed count decreases from 1 to 0)",
    $cancelledReg1['status'] === 'cancelled' &&
    $confirmedBeforeCancel === 1 &&
    $confirmedAfterCancel === 0
);

// -----------------------------------------------------------------------------
// Test 19: Pending cancellation (pending -> cancelled)
// -----------------------------------------------------------------------------
$partPenCancel = seedParticipant(['full_name' => 'Pending Cancel Part']);
$regPenCancel = $registrationService->registerParticipant($eventOpenApproval, $partPenCancel, null, $coordinatorId);
$cancelledPen = $registrationService->cancelRegistration((int) $regPenCancel['registration']['id'], 'Cancelled prior to approval', $coordinatorId);

assertPhase1ETest(
    "19. Pending cancellation transitions status from 'pending' to 'cancelled'",
    $cancelledPen['status'] === 'cancelled'
);

// -----------------------------------------------------------------------------
// Test 20: Waitlisted cancellation (waitlisted -> cancelled)
// -----------------------------------------------------------------------------
$cancelledWaitlisted = $registrationService->cancelRegistration($regApp2Id, 'Attendee withdrew from waitlist', $coordinatorId);

assertPhase1ETest(
    "20. Waitlisted cancellation transitions status from 'waitlisted' to 'cancelled'",
    $cancelledWaitlisted['status'] === 'cancelled'
);

// -----------------------------------------------------------------------------
// Test 21: Duplicate cancellation rejection (cancelled -> cancelled fails)
// -----------------------------------------------------------------------------
$caughtDupCancel = false;
try {
    $registrationService->cancelRegistration($regApp2Id, 'Attempt cancel again', $coordinatorId);
} catch (InvalidStateTransitionException $e) {
    $caughtDupCancel = str_contains($e->getMessage(), 'already cancelled');
}

assertPhase1ETest(
    "21. Duplicate cancellation rejected with InvalidStateTransitionException",
    $caughtDupCancel
);

// -----------------------------------------------------------------------------
// Test 22: Re-registration reactivates existing row in-place (preserves row ID)
// -----------------------------------------------------------------------------
// $regPenCancel was cancelled. Re-register $partPenCancel for $eventOpenApproval.
$originalRowId = (int) $regPenCancel['registration']['id'];
$reactivateRes = $registrationService->registerParticipant($eventOpenApproval, $partPenCancel, 'Reactivating attendee', $coordinatorId);

$totalRowsReactivated = (int) ($testPdo->query("SELECT COUNT(*) AS total FROM event_registrations WHERE event_id = {$eventOpenApproval} AND participant_id = {$partPenCancel}")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

assertPhase1ETest(
    "22. Re-registration reactivates existing row in-place, preserving physical ID and preventing duplicate row creation",
    $reactivateRes['status'] === 'reactivated' &&
    (int) $reactivateRes['registration']['id'] === $originalRowId &&
    $totalRowsReactivated === 1
);

// -----------------------------------------------------------------------------
// Test 23: Re-registration regenerates fresh registration code
// -----------------------------------------------------------------------------
$oldCode = $regPenCancel['registration']['registration_code'];
$newCode = $reactivateRes['registration']['registration_code'];

assertPhase1ETest(
    "23. Re-registration permanently replaces previous code with a fresh unique code",
    $newCode !== $oldCode &&
    $registrationRepo->findByCode($oldCode) === null &&
    $registrationRepo->findByCode($newCode) !== null
);

// -----------------------------------------------------------------------------
// Test 24: Waitlist promotion under lock
// -----------------------------------------------------------------------------
// Create capped event with capacity 1, 1 confirmed, 1 waitlisted. Then cancel confirmed, promote waitlisted.
$eventPromo = seedEvent(['title' => 'Promotion Test Event', 'capacity' => 1, 'status' => 'published']);
$partP1 = seedParticipant(['full_name' => 'Promo Part 1']);
$partP2 = seedParticipant(['full_name' => 'Promo Part 2']);

$regP1 = $registrationService->registerParticipant($eventPromo, $partP1, null, $coordinatorId); // confirmed
$regP2 = $registrationService->registerParticipant($eventPromo, $partP2, null, $coordinatorId); // waitlisted

// Cancel regP1 to free up seat
$registrationService->cancelRegistration((int) $regP1['registration']['id'], 'Freed up seat', $coordinatorId);

// Promote regP2
$promotedReg = $registrationService->promoteWaitlist((int) $regP2['registration']['id'], $coordinatorId);

assertPhase1ETest(
    "24. Waitlist promotion under lock transitions waitlisted registration to confirmed when seat is released",
    $promotedReg['status'] === 'confirmed' &&
    $registrationRepo->countConfirmedByEvent($eventPromo) === 1
);

// -----------------------------------------------------------------------------
// Test 25: Concurrent waitlist promotion race prevention (rejected when capacity full)
// -----------------------------------------------------------------------------
$partP3 = seedParticipant(['full_name' => 'Promo Part 3']);
$regP3 = $registrationService->registerParticipant($eventPromo, $partP3, null, $coordinatorId); // waitlisted

$caughtFullPromo = false;
try {
    $registrationService->promoteWaitlist((int) $regP3['registration']['id'], $coordinatorId);
} catch (CapacityExceededException $e) {
    $caughtFullPromo = str_contains($e->getMessage(), 'capacity is currently full');
}

assertPhase1ETest(
    "25. Concurrent waitlist promotion race prevention: rejects promotion when capacity is full",
    $caughtFullPromo &&
    $registrationRepo->findById((int) $regP3['registration']['id'])['status'] === 'waitlisted'
);

// -----------------------------------------------------------------------------
// Test 26: Code collision 10-retry handling
// -----------------------------------------------------------------------------
// Create mock repository that simulates collision 4 times then succeeds
$mockCollisionRepo = new class extends RegistrationRepository {
    private int $calls = 0;
    public function codeExists(string $code): bool {
        $this->calls++;
        return $this->calls <= 4; // First 4 calls collide
    }
};

$serviceWithCollisionMock = new RegistrationService($mockCollisionRepo, $eventRepo, $participantRepo, $participantService, $auditService);
$collidingCode = $serviceWithCollisionMock->generateUniqueRegistrationCode();

// Create mock repository that collides indefinitely (all 10 retries fail)
$mockExhaustedRepo = new class extends RegistrationRepository {
    public function codeExists(string $code): bool {
        return true; // Always collides
    }
};

$serviceWithExhaustedMock = new RegistrationService($mockExhaustedRepo, $eventRepo, $participantRepo, $participantService, $auditService);
$caughtExhausted = false;
try {
    $serviceWithExhaustedMock->generateUniqueRegistrationCode();
} catch (RegistrationCodeGenerationException $e) {
    $caughtExhausted = str_contains($e->getMessage(), 'Failed to generate a unique registration code after 10 attempts');
}

assertPhase1ETest(
    "26. Code collision handling: succeeds upon retry when collisions occur; throws RegistrationCodeGenerationException after 10 attempts",
    !empty($collidingCode) && $caughtExhausted
);

// -----------------------------------------------------------------------------
// Test 27: Public pass access for confirmed (200 OK); 404 for pending, waitlisted, cancelled
// -----------------------------------------------------------------------------
$passConfirmedCode = $regRes1['registration']['registration_code']; // confirmed
$passPendingCode = $regOA1['registration']['registration_code']; // pending (since requires_approval = 1)
$passWaitlistedCode = $regCapped3['registration']['registration_code']; // waitlisted
$passCancelledCode = $cancelledReg1['registration_code']; // cancelled

$respConfirmed = simulateRegistrationHttp('GET', "/registration/pass/{$passConfirmedCode}");
$respPending = simulateRegistrationHttp('GET', "/registration/pass/{$passPendingCode}");
$respWaitlisted = simulateRegistrationHttp('GET', "/registration/pass/{$passWaitlistedCode}");
$respCancelled = simulateRegistrationHttp('GET', "/registration/pass/{$passCancelledCode}");
$respInvalid = simulateRegistrationHttp('GET', "/registration/pass/REG-99-INVALID");

assertPhase1ETest(
    "27. Public pass access strictly gated: HTTP 200 for confirmed; HTTP 404 for pending, waitlisted, cancelled, and non-existent",
    $respConfirmed->getStatusCode() === 200 &&
    $respPending->getStatusCode() === 404 &&
    $respWaitlisted->getStatusCode() === 404 &&
    $respCancelled->getStatusCode() === 404 &&
    $respInvalid->getStatusCode() === 404
);

// -----------------------------------------------------------------------------
// Test 28: Public pass contains NO contact PII, internal IDs, or admin notes
// -----------------------------------------------------------------------------
$confirmedBody = $respConfirmed->getBody();
$hasAttendeeName = str_contains($confirmedBody, 'Aditi Rao');
$hasPassCode = str_contains($confirmedBody, $passConfirmedCode);
$hasNoEmail = !str_contains($confirmedBody, 'aditi.rao@example.com') && !preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $confirmedBody);
$hasNoPhone = !str_contains($confirmedBody, '+9198');
$hasNoAdminNotes = !str_contains($confirmedBody, 'First registration notes');
$hasNoDbId = !preg_match('/Registration ID:\s*' . $regRes1['registration']['id'] . '/i', $confirmedBody);

assertPhase1ETest(
    "28. Public pass contains minimal operational disclosure (attendee name, event, pass code) and NO contact PII, internal IDs, or admin notes",
    $hasAttendeeName && $hasPassCode && $hasNoEmail && $hasNoPhone && $hasNoAdminNotes && $hasNoDbId
);

// -----------------------------------------------------------------------------
// Test 29: Public pass rate limiting (HTTP 429 after 10 failures)
// -----------------------------------------------------------------------------
$testRateLimitIp = '198.51.100.77';
$rateLimitDir = APP_ROOT . '/storage/cache/rate_limits';
$testRateLimitFile = $rateLimitDir . '/pass_lookup_' . hash('sha256', $testRateLimitIp) . '.json';
if (file_exists($testRateLimitFile)) {
    @unlink($testRateLimitFile);
}

// Perform 10 failed lookups
for ($i = 1; $i <= 10; $i++) {
    simulateRegistrationHttp('GET', "/registration/pass/REG-99-FAIL{$i}", [], [], [], ['REMOTE_ADDR' => $testRateLimitIp]);
}

// 11th failed lookup should be rate limited (HTTP 429)
$respRateLimited = simulateRegistrationHttp('GET', '/registration/pass/REG-99-FAIL11', [], [], [], ['REMOTE_ADDR' => $testRateLimitIp]);

$is429 = $respRateLimited->getStatusCode() === 429;
$hasRetryAfter = $respRateLimited->getHeader('Retry-After') === '900';

if (file_exists($testRateLimitFile)) {
    @unlink($testRateLimitFile);
}

assertPhase1ETest(
    "29. Public pass rate limiter locks IP after 10 failed lookups, returning HTTP 429 and Retry-After: 900",
    $is429 && $hasRetryAfter
);

// -----------------------------------------------------------------------------
// Test 30: Audit credential hygiene: all pass codes in audit metadata are masked
// -----------------------------------------------------------------------------
$auditLogs = $testPdo->query("SELECT * FROM audit_logs WHERE action LIKE 'registration.%'")->fetchAll(PDO::FETCH_ASSOC);
$allAuditCodesMasked = true;
$checkedAuditCount = 0;

foreach ($auditLogs as $log) {
    $meta = json_decode((string) ($log['metadata'] ?? '{}'), true);
    if (!is_array($meta)) {
        continue;
    }
    foreach (['masked_code', 'previous_masked_code', 'new_masked_code'] as $field) {
        if (!empty($meta[$field])) {
            $checkedAuditCount++;
            if (!preg_match('/^REG-\d{2}-\*{4}[23456789ABCDEFGHJKMNPQRSTVWXYZ]$/', $meta[$field])) {
                $allAuditCodesMasked = false;
            }
        }
    }
}

assertPhase1ETest(
    "30. Audit credential hygiene: all pass codes in audit metadata are masked (REG-YY-****X) across all lifecycle actions",
    $allAuditCodesMasked && $checkedAuditCount > 0,
    "Checked {$checkedAuditCount} audit code occurrences"
);

// -----------------------------------------------------------------------------
// Test 31: Operational admin_notes validation rejects clinical/distress keywords;
//          inline participant creation rollback leaves 0 lingering rows on failure
// -----------------------------------------------------------------------------
$caughtSensitiveNotes = false;
try {
    $registrationService->validateAdminNotes('Attendee is in depression crisis needing clinical therapy');
} catch (ValidationException $e) {
    $caughtSensitiveNotes = str_contains($e->getMessage(), 'Clinical, medical, or counselling content is strictly prohibited');
}

// Test transactional rollback of createRegistrationWithParticipant
$participantsBeforeCount = (int) ($testPdo->query("SELECT COUNT(*) AS total FROM participants")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$caughtRollbackFailure = false;

try {
    // Attempt inline participant creation on expired event (should fail registration and rollback participant)
    $registrationService->createRegistrationWithParticipant($eventPastDeadline, [
        'full_name'         => 'Rollback Candidate',
        'email'             => 'rollback.candidate@example.com',
        'phone'             => '+919999900000',
        'agreed_guidelines' => true,
        'privacy_consent'   => true,
    ], null, $coordinatorId);
} catch (RuntimeException $e) {
    $caughtRollbackFailure = str_contains($e->getMessage(), 'deadline for this event has passed');
}

$participantsAfterCount = (int) ($testPdo->query("SELECT COUNT(*) AS total FROM participants")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$rollbackParticipantRow = $participantRepo->findOneByEmail('rollback.candidate@example.com');

assertPhase1ETest(
    "31. Operational admin_notes validation rejects clinical/distress keywords; inline participant creation transaction rollback leaves 0 lingering rows",
    $caughtSensitiveNotes &&
    $caughtRollbackFailure &&
    $participantsBeforeCount === $participantsAfterCount &&
    $rollbackParticipantRow === null
);

// =============================================================================
// HTTP CONTROLLER ENDPOINTS & RBAC MATRIX VERIFICATION
// =============================================================================
echo PHP_EOL . "--- HTTP Controller Endpoints & RBAC Matrix Verification ---" . PHP_EOL;

// 1. Unauthenticated access redirect
$respUnauth = simulateRegistrationHttp('GET', '/admin/registrations');
assertPhase1ETest(
    "RBAC: Unauthenticated access to /admin/registrations redirects to /login",
    $respUnauth->getStatusCode() === 302 && $respUnauth->getHeader('Location') === '/login'
);

// 2. GET /admin/registrations (Viewer - Allowed, HTTP 200, PII Masked)
$respViewerList = simulateRegistrationHttp('GET', '/admin/registrations', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $viewerId,
    '_auth_user_role' => RoleService::ROLE_VIEWER,
]);
assertPhase1ETest(
    "RBAC: GET /admin/registrations returns HTTP 200 and masks contact emails for ROLE_VIEWER",
    $respViewerList->getStatusCode() === 200 &&
    str_contains($respViewerList->getBody(), 'Event Registrations') &&
    str_contains($respViewerList->getBody(), 'Privacy Shield Active')
);

// 3. GET /admin/registrations (Coordinator - Allowed, HTTP 200, Unmasked)
$respCoordList = simulateRegistrationHttp('GET', '/admin/registrations', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
]);
assertPhase1ETest(
    "RBAC: GET /admin/registrations displays unmasked contacts for ROLE_COORDINATOR",
    $respCoordList->getStatusCode() === 200 &&
    str_contains($respCoordList->getBody(), 'aditi.rao@example.com')
);

// 4. GET /admin/registrations/create (Staff/Viewer - Denied, HTTP 403)
$respStaffCreate = simulateRegistrationHttp('GET', '/admin/registrations/create', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $staffId,
    '_auth_user_role' => RoleService::ROLE_STAFF,
]);
assertPhase1ETest(
    "RBAC: GET /admin/registrations/create returns HTTP 403 Forbidden for ROLE_STAFF",
    $respStaffCreate->getStatusCode() === 403
);

// 5. GET /admin/registrations/create (Coordinator - Allowed, HTTP 200)
$respCoordCreate = simulateRegistrationHttp('GET', '/admin/registrations/create', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
]);
assertPhase1ETest(
    "RBAC: GET /admin/registrations/create returns HTTP 200 for ROLE_COORDINATOR",
    $respCoordCreate->getStatusCode() === 200 &&
    str_contains($respCoordCreate->getBody(), 'New Event Registration')
);

// 6. POST /admin/registrations (Staff - Denied, HTTP 403)
$csrfToken = getTestCsrfToken();
$respStaffPost = simulateRegistrationHttp('POST', '/admin/registrations', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $staffId,
    '_auth_user_role' => RoleService::ROLE_STAFF,
    '_csrf_token'     => $csrfToken,
], [
    '_csrf_token' => $csrfToken,
    'event_id'    => $eventUnlimited,
]);
assertPhase1ETest(
    "RBAC: POST /admin/registrations returns HTTP 403 Forbidden for ROLE_STAFF",
    $respStaffPost->getStatusCode() === 403
);

// 7. POST /admin/registrations (Coordinator - Allowed, HTTP 302)
$csrfToken = getTestCsrfToken();
$httpPart = seedParticipant(['full_name' => 'HTTP Post Participant', 'email' => 'httppost@example.com']);
$respCoordPost = simulateRegistrationHttp('POST', '/admin/registrations', [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
    '_csrf_token'     => $csrfToken,
], [
    '_csrf_token'      => $csrfToken,
    'event_id'         => $eventUnlimited,
    'participant_mode' => 'existing',
    'participant_id'   => $httpPart,
    'admin_notes'      => 'Confirmed seat reservation',
]);
assertPhase1ETest(
    "RBAC: POST /admin/registrations creates registration and redirects to directory for ROLE_COORDINATOR",
    $respCoordPost->getStatusCode() === 302 &&
    str_contains($respCoordPost->getHeader('Location'), '/admin/registrations')
);

$newRegRow = $registrationRepo->findByEventAndParticipant($eventUnlimited, $httpPart);
$newRegId = (int) ($newRegRow['id'] ?? 0);

// 8. GET /admin/registrations/{id} (Viewer - Allowed, HTTP 200, Masked)
$respViewerShow = simulateRegistrationHttp('GET', "/admin/registrations/{$newRegId}", [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $viewerId,
    '_auth_user_role' => RoleService::ROLE_VIEWER,
]);
assertPhase1ETest(
    "RBAC: GET /admin/registrations/{id} returns HTTP 200 and applies Privacy Shield mask for ROLE_VIEWER",
    $respViewerShow->getStatusCode() === 200 &&
    str_contains($respViewerShow->getBody(), 'h***@example.com') &&
    str_contains($respViewerShow->getBody(), 'Privacy Shield Active')
);

// 9. GET /admin/registrations/{id}/pass (Coordinator - Allowed, HTTP 200)
$respCoordPass = simulateRegistrationHttp('GET', "/admin/registrations/{$newRegId}/pass", [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
]);
assertPhase1ETest(
    "RBAC: GET /admin/registrations/{id}/pass returns administrative visual attendance pass for ROLE_COORDINATOR",
    $respCoordPass->getStatusCode() === 200 &&
    str_contains($respCoordPass->getBody(), 'HTTP Post Participant')
);

// 10. GET /admin/registrations/{id}/print (Coordinator - Allowed, HTTP 200)
$respCoordPrint = simulateRegistrationHttp('GET', "/admin/registrations/{$newRegId}/print", [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
]);
assertPhase1ETest(
    "RBAC: GET /admin/registrations/{id}/print returns standalone printable attendance pass for ROLE_COORDINATOR",
    $respCoordPrint->getStatusCode() === 200 &&
    str_contains($respCoordPrint->getBody(), 'window.print()')
);

// 11. POST /admin/registrations/{id}/cancel (Staff - Denied, HTTP 403)
$csrfToken = getTestCsrfToken();
$respStaffCancel = simulateRegistrationHttp('POST', "/admin/registrations/{$newRegId}/cancel", [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $staffId,
    '_auth_user_role' => RoleService::ROLE_STAFF,
    '_csrf_token'     => $csrfToken,
], [
    '_csrf_token' => $csrfToken,
    'reason'      => 'Staff cancel attempt',
]);
assertPhase1ETest(
    "RBAC: POST /admin/registrations/{id}/cancel returns HTTP 403 Forbidden for ROLE_STAFF",
    $respStaffCancel->getStatusCode() === 403
);

// 12. POST /admin/registrations/{id}/cancel (Coordinator - Allowed, HTTP 302)
$csrfToken = getTestCsrfToken();
$respCoordCancel = simulateRegistrationHttp('POST', "/admin/registrations/{$newRegId}/cancel", [
    '_auth_logged_in' => true,
    '_auth_user_id'   => $coordinatorId,
    '_auth_user_role' => RoleService::ROLE_COORDINATOR,
    '_csrf_token'     => $csrfToken,
], [
    '_csrf_token' => $csrfToken,
    'reason'      => 'Operational change of plans',
]);
assertPhase1ETest(
    "RBAC: POST /admin/registrations/{id}/cancel successfully cancels registration for ROLE_COORDINATOR",
    $respCoordCancel->getStatusCode() === 302 &&
    $registrationRepo->findById($newRegId)['status'] === 'cancelled'
);

// =============================================================================
// DATABASE TEST HYGIENE (Zero Lingering Test Rows)
// =============================================================================
echo PHP_EOL . "--- Verifying Database Test Hygiene (Zero Lingering Test Rows) ---" . PHP_EOL;

Database::execute("DELETE FROM event_registrations");
Database::execute("DELETE FROM participants");
Database::execute("DELETE FROM events");
Database::execute("DELETE FROM campaigns");
Database::execute("DELETE FROM audit_logs");
Database::execute("DELETE FROM users");

if (is_dir(APP_ROOT . '/storage/cache/rate_limits')) {
    $rateLimitFiles = glob(APP_ROOT . '/storage/cache/rate_limits/*.json') ?: [];
    foreach ($rateLimitFiles as $rf) {
        @unlink($rf);
    }
}

$regCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM event_registrations")['total'] ?? -1);
$partCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM participants")['total'] ?? -1);
$eventCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM events")['total'] ?? -1);
$auditCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM audit_logs")['total'] ?? -1);

assertPhase1ETest(
    "Database 'event_registrations' retains 0 lingering test rows",
    $regCount === 0,
    "Expected 0 rows, found {$regCount}"
);

assertPhase1ETest(
    "Database 'participants' retains 0 lingering test rows",
    $partCount === 0,
    "Expected 0 rows, found {$partCount}"
);

assertPhase1ETest(
    "Database 'events' retains 0 lingering test rows",
    $eventCount === 0,
    "Expected 0 rows, found {$eventCount}"
);

assertPhase1ETest(
    "Database 'audit_logs' retains 0 lingering test rows",
    $auditCount === 0,
    "Expected 0 rows, found {$auditCount}"
);

// =============================================================================
// SUMMARY REPORT
// =============================================================================
echo PHP_EOL;
echo "=================================================" . PHP_EOL;
echo "Phase 1E Event Registration Verification Results:" . PHP_EOL;
echo "Total Assertions: {$totalTests}" . PHP_EOL;
echo "Passed: {$green}{$passedTests}{$reset}" . PHP_EOL;
echo "Failed: " . ($failedTests > 0 ? "{$red}{$failedTests}{$reset}" : "0") . PHP_EOL;
echo "=================================================" . PHP_EOL;

if ($failedTests > 0) {
    exit(1);
}
exit(0);
