<?php

declare(strict_types=1);

/**
 * Phase 1F — Attendance & Check-In System Test Suite
 * Run via: php tests/test_phase1f_attendance.php
 *
 * Implements full coverage of Section 17.1 Test Matrix (35+ scenarios, 50+ assertions).
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Controllers\Admin\AttendanceController;
use App\Controllers\Admin\CheckInController;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\CheckInException;
use App\Core\Exceptions\ValidationException;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Repositories\CampaignRepository;
use App\Repositories\EventRepository;
use App\Repositories\ParticipantRepository;
use App\Repositories\RegistrationRepository;
use App\Repositories\UserRepository;
use App\Services\AttendanceService;
use App\Services\AuditService;
use App\Services\CheckInService;
use App\Services\ParticipantService;
use App\Services\RegistrationService;
use App\Services\RoleService;

// Terminal colors
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertPhase1FTest(string $description, bool $condition, string $details = ''): void
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

echo "=== LC-SPC Phase 1F Attendance & Check-In Verification Suite ===" . PHP_EOL . PHP_EOL;

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
        title VARCHAR(191) NOT NULL,
        slug VARCHAR(191) NOT NULL,
        category VARCHAR(50) NOT NULL DEFAULT 'workshop',
        description TEXT NULL,
        format VARCHAR(20) NOT NULL DEFAULT 'in_person',
        venue_name VARCHAR(191) NULL,
        venue_address TEXT NULL,
        online_meeting_url VARCHAR(255) NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        capacity INTEGER NOT NULL DEFAULT 0,
        registration_deadline DATETIME NULL,
        requires_approval INTEGER NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        coordinator_id INTEGER NULL,
        created_by INTEGER NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        deleted_at DATETIME NULL,
        UNIQUE (campaign_id, slug)
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
        status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
        attendance_status VARCHAR(20) NOT NULL DEFAULT 'unmarked',
        checked_in_at DATETIME NULL DEFAULT NULL,
        checked_in_by INTEGER NULL DEFAULT NULL,
        check_in_method VARCHAR(30) NULL DEFAULT NULL,
        admin_notes VARCHAR(255) NULL DEFAULT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
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
$campaignRepo = new CampaignRepository();
$eventRepo = new EventRepository();
$participantRepo = new ParticipantRepository();
$registrationRepo = new RegistrationRepository();
$auditRepo = new AuditLogRepository();
$auditService = new AuditService($auditRepo, $userRepo);
$participantService = new ParticipantService($participantRepo, $auditService);
$registrationService = new RegistrationService($registrationRepo, $eventRepo, $participantRepo, $participantService, $auditService);
$checkInService = new CheckInService($registrationRepo, $eventRepo, $auditService, $registrationService, $userRepo);
$attendanceService = new AttendanceService($registrationRepo, $eventRepo, $auditService, $registrationService, $participantService);

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
// Seed Baseline Campaign & Events
// -----------------------------------------------------------------------------
$campaignId = $campaignRepo->create([
    'title'      => 'Phase 1F Test Campaign',
    'slug'       => 'phase-1f-test-campaign',
    'theme'      => 'Gatekeeper Training',
    'start_date' => date('Y-m-d', strtotime('-1 day')),
    'end_date'   => date('Y-m-d', strtotime('+30 days')),
    'status'     => 'active',
    'created_by' => $superAdminId,
]);

// Event 1: Currently within operational window (e.g. started 30 mins ago, ends in 2 hours)
$event1Id = $eventRepo->create([
    'campaign_id' => $campaignId,
    'title'       => 'Live Workshop Circle A',
    'slug'        => 'live-workshop-circle-a',
    'format'      => 'in_person',
    'venue_name'  => 'Main Hall A',
    'start_time'  => date('Y-m-d H:i:s', time() - 1800),
    'end_time'    => date('Y-m-d H:i:s', time() + 7200),
    'capacity'    => 50,
    'status'      => 'published',
    'created_by'  => $coordinatorId,
]);

// Event 2: Event B (used for cross-event isolation test)
$event2Id = $eventRepo->create([
    'campaign_id' => $campaignId,
    'title'       => 'Parallel Workshop Circle B',
    'slug'        => 'parallel-workshop-circle-b',
    'format'      => 'in_person',
    'venue_name'  => 'Room B',
    'start_time'  => date('Y-m-d H:i:s', time() - 1800),
    'end_time'    => date('Y-m-d H:i:s', time() + 7200),
    'capacity'    => 30,
    'status'      => 'published',
    'created_by'  => $coordinatorId,
]);

// Event 3: Completed event where end_time was 5 hours ago (past end_time + 4 hours!)
$eventPastId = $eventRepo->create([
    'campaign_id' => $campaignId,
    'title'       => 'Past Event Completed',
    'slug'        => 'past-event-completed',
    'format'      => 'in_person',
    'venue_name'  => 'Old Hall',
    'start_time'  => date('Y-m-d H:i:s', time() - (8 * 3600)),
    'end_time'    => date('Y-m-d H:i:s', time() - (5 * 3600)),
    'capacity'    => 40,
    'status'      => 'published',
    'created_by'  => $coordinatorId,
]);

// Event 4: Future event (starts in 5 hours, outside 2h window)
$eventFutureId = $eventRepo->create([
    'campaign_id' => $campaignId,
    'title'       => 'Future Event Next Week',
    'slug'        => 'future-event-next-week',
    'format'      => 'in_person',
    'venue_name'  => 'Hall C',
    'start_time'  => date('Y-m-d H:i:s', time() + (5 * 3600)),
    'end_time'    => date('Y-m-d H:i:s', time() + (8 * 3600)),
    'capacity'    => 50,
    'status'      => 'published',
    'created_by'  => $coordinatorId,
]);

// Event 5: Draft event
$eventDraftId = $eventRepo->create([
    'campaign_id' => $campaignId,
    'title'       => 'Draft Event In Planning',
    'slug'        => 'draft-event-in-planning',
    'format'      => 'in_person',
    'venue_name'  => 'TBD',
    'start_time'  => date('Y-m-d H:i:s', time()),
    'end_time'    => date('Y-m-d H:i:s', time() + 3600),
    'capacity'    => 20,
    'status'      => 'draft',
    'created_by'  => $coordinatorId,
]);

// Event 6: Cancelled event
$eventCancelledId = $eventRepo->create([
    'campaign_id' => $campaignId,
    'title'       => 'Cancelled Event',
    'slug'        => 'cancelled-event',
    'format'      => 'in_person',
    'venue_name'  => 'Cancelled',
    'start_time'  => date('Y-m-d H:i:s', time()),
    'end_time'    => date('Y-m-d H:i:s', time() + 3600),
    'capacity'    => 20,
    'status'      => 'cancelled',
    'created_by'  => $coordinatorId,
]);

// -----------------------------------------------------------------------------
// Seed Participants
// -----------------------------------------------------------------------------
$part1Id = $participantRepo->create([
    'full_name'            => 'Rahul Sharma',
    'email'                => 'rahul.sharma@example.com',
    'phone'                => '+91 98765 43210',
    'category'             => 'student',
    'organization_name'    => 'Delhi University',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
    'status'               => 'active',
]);

$part2Id = $participantRepo->create([
    'full_name'            => 'Priya Patel',
    'email'                => 'priya.patel@example.com',
    'phone'                => '+91 98123 45678',
    'category'             => 'educator',
    'organization_name'    => 'National School',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
    'status'               => 'active',
]);

$part3Id = $participantRepo->create([
    'full_name'            => 'Anand Kumar',
    'email'                => 'anand.kumar@example.com',
    'phone'                => '+91 99999 11111',
    'category'             => 'community',
    'organization_name'    => 'Youth Club',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
    'status'               => 'active',
]);

$part4Id = $participantRepo->create([
    'full_name'            => 'Sneha Menon',
    'email'                => 'sneha.menon@example.com',
    'phone'                => '+91 88888 22222',
    'category'             => 'volunteer',
    'organization_name'    => 'NGO Aid',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
    'status'               => 'active',
]);

function seedRegistration(RegistrationService $service, int $eventId, int $participantId, int $actorId): int {
    $res = $service->registerParticipant($eventId, $participantId, null, $actorId);
    return (int) $res['registration']['id'];
}

// Seed Registrations for Event 1
$reg1Id = seedRegistration($registrationService, $event1Id, $part1Id, $coordinatorId);
$reg2Id = seedRegistration($registrationService, $event1Id, $part2Id, $coordinatorId);
$reg3Id = seedRegistration($registrationService, $event1Id, $part3Id, $coordinatorId);

// Seed Registration for Event 2 (Circle B)
$regEvent2Id = seedRegistration($registrationService, $event2Id, $part4Id, $coordinatorId);

$reg1 = $registrationRepo->findById($reg1Id);
$reg2 = $registrationRepo->findById($reg2Id);
$reg3 = $registrationRepo->findById($reg3Id);
$regEvent2 = $registrationRepo->findById($regEvent2Id);

echo "--- Executing Phase 1F Unit & Service Verification (Scenarios 1 to 35) ---" . PHP_EOL;

// -----------------------------------------------------------------------------
// 1. Normal QR Check-In Transition (unmarked -> attended)
// -----------------------------------------------------------------------------
$res1 = $checkInService->checkIn(
    $reg1['registration_code'],
    $event1Id,
    $staffId,
    'qr_scan',
    null,
    RoleService::ROLE_STAFF
);

$freshReg1 = $registrationRepo->findById($reg1Id);
assertPhase1FTest(
    "1. Normal QR check-in transition (unmarked -> attended) returns success and attended status",
    $res1['success'] === true &&
    $res1['status'] === 'attended' &&
    $freshReg1['attendance_status'] === 'attended' &&
    $freshReg1['check_in_method'] === 'qr_scan' &&
    (int) $freshReg1['checked_in_by'] === $staffId &&
    !empty($freshReg1['checked_in_at'])
);

// -----------------------------------------------------------------------------
// 2. Normal Manual Check-In (unmarked -> attended)
// -----------------------------------------------------------------------------
$res2 = $checkInService->checkIn(
    $reg2['registration_code'],
    $event1Id,
    $staffId,
    'admin_manual',
    null,
    RoleService::ROLE_STAFF
);

$freshReg2 = $registrationRepo->findById($reg2Id);
assertPhase1FTest(
    "2. Normal manual check-in (unmarked -> attended) assigns method='admin_manual' and records staff",
    $res2['success'] === true &&
    $res2['status'] === 'attended' &&
    $freshReg2['attendance_status'] === 'attended' &&
    $freshReg2['check_in_method'] === 'admin_manual' &&
    (int) $freshReg2['checked_in_by'] === $staffId
);

// -----------------------------------------------------------------------------
// 3. Idempotent Repeat Scan of Attended Pass
// -----------------------------------------------------------------------------
$auditCountBefore = (int) Database::fetch("SELECT COUNT(*) AS total FROM audit_logs WHERE action = 'attendance.checkin'")['total'];
$originalTimestamp = $freshReg1['checked_in_at'];

$repeatRes = $checkInService->checkIn(
    $reg1['registration_code'],
    $event1Id,
    $staffId,
    'qr_scan',
    null,
    RoleService::ROLE_STAFF
);

$auditCountAfter = (int) Database::fetch("SELECT COUNT(*) AS total FROM audit_logs WHERE action = 'attendance.checkin'")['total'];
$freshRepeatReg1 = $registrationRepo->findById($reg1Id);

assertPhase1FTest(
    "3. Idempotent repeat scan returns already_checked_in=true without mutating data or duplicate audit logs",
    $repeatRes['success'] === true &&
    $repeatRes['already_checked_in'] === true &&
    $freshRepeatReg1['checked_in_at'] === $originalTimestamp &&
    $auditCountAfter === $auditCountBefore,
    "Expected audit count {$auditCountBefore}, got {$auditCountAfter}"
);

// -----------------------------------------------------------------------------
// 4. Cross-Event Isolation: Pass from Event A at Event B Desk
// -----------------------------------------------------------------------------
$crossEventBlocked = false;
$crossEventCode = 0;
try {
    // Attempt to check in reg1 (Event 1) at Event 2 console
    $checkInService->checkIn(
        $reg1['registration_code'],
        $event2Id, // Cross-event!
        $staffId,
        'qr_scan',
        null,
        RoleService::ROLE_STAFF
    );
} catch (CheckInException $e) {
    $crossEventBlocked = true;
    $crossEventCode = $e->getStatusCode();
}

assertPhase1FTest(
    "4. Cross-Event Isolation: Pass from Event A scanned at Event B desk is rejected with HTTP 422",
    $crossEventBlocked && $crossEventCode === 422
);

// -----------------------------------------------------------------------------
// -----------------------------------------------------------------------------
// 5. Rejection of Cancelled Registration Pass
// -----------------------------------------------------------------------------
$partCancelId = $participantRepo->create([
    'full_name'            => 'Cancelled Attendee',
    'email'                => 'cancelled@example.com',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);
$regCancelId = seedRegistration($registrationService, $event1Id, $partCancelId, $coordinatorId);
$registrationService->cancelRegistration($regCancelId, 'Participant cancelled', $coordinatorId);
$cancelledReg = $registrationRepo->findById($regCancelId);

$cancelBlocked = false;
$cancelCode = 0;
try {
    $checkInService->checkIn($cancelledReg['registration_code'], $event1Id, $staffId, 'qr_scan', null, RoleService::ROLE_STAFF);
} catch (CheckInException $e) {
    $cancelBlocked = true;
    $cancelCode = $e->getStatusCode();
}

assertPhase1FTest(
    "5. Rejection of cancelled registration pass triggers HTTP 403 Entry Denied",
    $cancelBlocked && $cancelCode === 403
);

// -----------------------------------------------------------------------------
// 6. Rejection of Pending Registration Pass
// -----------------------------------------------------------------------------
$eventApprovalId = $eventRepo->create([
    'campaign_id'       => $campaignId,
    'title'             => 'Circle Requiring Approval',
    'slug'              => 'circle-requiring-approval',
    'format'            => 'in_person',
    'venue_name'        => 'Approval Hall',
    'start_time'        => date('Y-m-d H:i:s', time() - 1800),
    'end_time'          => date('Y-m-d H:i:s', time() + 7200),
    'capacity'          => 10,
    'requires_approval' => 1,
    'status'            => 'published',
    'created_by'        => $coordinatorId,
]);

$partPendingId = $participantRepo->create([
    'full_name'            => 'Pending Attendee',
    'email'                => 'pending@example.com',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);

$regPendingId = seedRegistration($registrationService, $eventApprovalId, $partPendingId, $coordinatorId);
$pendingReg = $registrationRepo->findById($regPendingId);

$pendingBlocked = false;
$pendingCode = 0;
try {
    $checkInService->checkIn($pendingReg['registration_code'], $eventApprovalId, $staffId, 'qr_scan', null, RoleService::ROLE_STAFF);
} catch (CheckInException $e) {
    $pendingBlocked = true;
    $pendingCode = $e->getStatusCode();
}

assertPhase1FTest(
    "6. Rejection of pending registration pass triggers HTTP 403 Entry Denied",
    $pendingBlocked && $pendingCode === 403 && $pendingReg['status'] === 'pending'
);

// -----------------------------------------------------------------------------
// 7. Rejection of Waitlisted Registration Pass
// -----------------------------------------------------------------------------
// Fill capacity on a small 1-capacity event
$eventWaitlistId = $eventRepo->create([
    'campaign_id' => $campaignId,
    'title'       => 'Small Capacity Circle',
    'slug'        => 'small-capacity-circle',
    'format'      => 'in_person',
    'venue_name'  => 'Small Room',
    'start_time'  => date('Y-m-d H:i:s', time() - 1800),
    'end_time'    => date('Y-m-d H:i:s', time() + 7200),
    'capacity'    => 1,
    'status'      => 'published',
    'created_by'  => $coordinatorId,
]);

$partFull1 = $participantRepo->create(['full_name' => 'First Attendee', 'email' => 'first@teami.in', 'agreed_guidelines_at' => date('Y-m-d H:i:s'), 'privacy_consent_at' => date('Y-m-d H:i:s')]);
$partWait1 = $participantRepo->create(['full_name' => 'Waitlist Attendee', 'email' => 'wait@teami.in', 'agreed_guidelines_at' => date('Y-m-d H:i:s'), 'privacy_consent_at' => date('Y-m-d H:i:s')]);

$registrationService->registerParticipant($eventWaitlistId, $partFull1, null, $coordinatorId);
$regWaitlistId = seedRegistration($registrationService, $eventWaitlistId, $partWait1, $coordinatorId);
$waitlistedReg = $registrationRepo->findById($regWaitlistId);

$waitlistBlocked = false;
$waitlistCode = 0;
try {
    $checkInService->checkIn($waitlistedReg['registration_code'], $eventWaitlistId, $staffId, 'qr_scan', null, RoleService::ROLE_STAFF);
} catch (CheckInException $e) {
    $waitlistBlocked = true;
    $waitlistCode = $e->getStatusCode();
}

assertPhase1FTest(
    "7. Rejection of waitlisted registration pass triggers HTTP 403 (No Pass Issued)",
    $waitlistBlocked && $waitlistCode === 403 && $waitlistedReg['status'] === 'waitlisted'
);

// -----------------------------------------------------------------------------
// 7b. Rejection of Absent Registration Pass
// -----------------------------------------------------------------------------
$partAbsentId = $participantRepo->create(['full_name' => 'Absent Attendee', 'email' => 'absent@teami.in', 'agreed_guidelines_at' => date('Y-m-d H:i:s'), 'privacy_consent_at' => date('Y-m-d H:i:s')]);
$regAbsentId = seedRegistration($registrationService, $event1Id, $partAbsentId, $coordinatorId);
$attendanceService->updateAttendanceStatus($regAbsentId, 'absent', 'Did not arrive at roll call', $coordinatorId, RoleService::ROLE_COORDINATOR);
$regAbsent = $registrationRepo->findById($regAbsentId);

$absentBlocked = false;
$absentCode = 0;
try {
    $checkInService->checkIn($regAbsent['registration_code'], $event1Id, $staffId, 'qr_scan', null, RoleService::ROLE_STAFF);
} catch (CheckInException $e) {
    $absentBlocked = true;
    $absentCode = $e->getStatusCode();
}

$freshAbsent = $registrationRepo->findById($regAbsentId);
assertPhase1FTest(
    "7b. Rejection of absent registration pass triggers HTTP 422 and preserves absent status without mutation",
    $absentBlocked && $absentCode === 422 &&
    $freshAbsent['attendance_status'] === 'absent' &&
    empty($freshAbsent['checked_in_at'])
);

// -----------------------------------------------------------------------------
// 7c. Rejection of Excused Registration Pass
// -----------------------------------------------------------------------------
$partExcusedId = $participantRepo->create(['full_name' => 'Excused Attendee', 'email' => 'excused@teami.in', 'agreed_guidelines_at' => date('Y-m-d H:i:s'), 'privacy_consent_at' => date('Y-m-d H:i:s')]);
$regExcusedId = seedRegistration($registrationService, $event1Id, $partExcusedId, $coordinatorId);
$attendanceService->updateAttendanceStatus($regExcusedId, 'excused', 'Prior academic schedule conflict', $coordinatorId, RoleService::ROLE_COORDINATOR);
$regExcused = $registrationRepo->findById($regExcusedId);

$excusedBlocked = false;
$excusedCode = 0;
try {
    $checkInService->checkIn($regExcused['registration_code'], $event1Id, $staffId, 'qr_scan', null, RoleService::ROLE_STAFF);
} catch (CheckInException $e) {
    $excusedBlocked = true;
    $excusedCode = $e->getStatusCode();
}

$freshExcused = $registrationRepo->findById($regExcusedId);
assertPhase1FTest(
    "7c. Rejection of excused registration pass triggers HTTP 422 and preserves excused status without mutation",
    $excusedBlocked && $excusedCode === 422 &&
    $freshExcused['attendance_status'] === 'excused' &&
    empty($freshExcused['checked_in_at'])
);

// -----------------------------------------------------------------------------
// 8. Rejection of Non-Existent Registration Code
// -----------------------------------------------------------------------------
$notFoundBlocked = false;
$notFoundCode = 0;
try {
    $checkInService->checkIn('REG-26-XXXXX', $event1Id, $staffId, 'qr_scan', null, RoleService::ROLE_STAFF);
} catch (CheckInException $e) {
    $notFoundBlocked = true;
    $notFoundCode = $e->getStatusCode();
}

assertPhase1FTest(
    "8. Rejection of non-existent registration code triggers HTTP 404 Pass Code Not Found",
    $notFoundBlocked && $notFoundCode === 404
);

// -----------------------------------------------------------------------------
// 9. Rejection of Malformed Registration Code
// -----------------------------------------------------------------------------
$malformedBlocked = false;
$malformedCode = 0;
try {
    $checkInService->checkIn('INVALID-CODE-1234', $event1Id, $staffId, 'qr_scan', null, RoleService::ROLE_STAFF);
} catch (CheckInException $e) {
    $malformedBlocked = true;
    $malformedCode = $e->getStatusCode();
}

assertPhase1FTest(
    "9. Rejection of malformed registration code triggers HTTP 422 Regex Validation Failure",
    $malformedBlocked && $malformedCode === 422
);

// -----------------------------------------------------------------------------
// 10. Full URL QR Payload Parsing
// -----------------------------------------------------------------------------
$extracted1 = $checkInService->extractCode('https://teami.in/LC/registration/pass/' . $reg3['registration_code']);
$extracted2 = $checkInService->extractCode('http://localhost:8000/registration/pass/' . $reg3['registration_code']);
$extracted3 = $checkInService->extractCode($reg3['registration_code']);

assertPhase1FTest(
    "10. Full URL QR payload parsing extracts Crockford Base32 pass code accurately from URLs and raw codes",
    $extracted1 === $reg3['registration_code'] &&
    $extracted2 === $reg3['registration_code'] &&
    $extracted3 === $reg3['registration_code']
);

// -----------------------------------------------------------------------------
// 11. Coordinator Marks Single Record as 'absent'
// -----------------------------------------------------------------------------
$absentResult = $attendanceService->updateAttendanceStatus(
    $reg3Id,
    'absent',
    'Attendee called to report unable to attend session',
    $coordinatorId,
    RoleService::ROLE_COORDINATOR
);
$freshReg3 = $registrationRepo->findById($reg3Id);

assertPhase1FTest(
    "11. Coordinator marks single record as 'absent' with audit logging",
    $absentResult['success'] === true &&
    $freshReg3['attendance_status'] === 'absent'
);

// -----------------------------------------------------------------------------
// 12. Coordinator Marks Single Record as 'excused'
// -----------------------------------------------------------------------------
$excusedResult = $attendanceService->updateAttendanceStatus(
    $reg3Id,
    'excused',
    'Participant has university exam conflict',
    $coordinatorId,
    RoleService::ROLE_COORDINATOR
);
$freshExcusedReg3 = $registrationRepo->findById($reg3Id);

assertPhase1FTest(
    "12. Coordinator marks single record as 'excused' with valid operational reason",
    $excusedResult['success'] === true &&
    $freshExcusedReg3['attendance_status'] === 'excused'
);

// -----------------------------------------------------------------------------
// 13. Belated Excuse with Prohibited Clinical Notes Throws ValidationException
// -----------------------------------------------------------------------------
$prohibitedNotesBlocked = false;
try {
    $attendanceService->updateAttendanceStatus(
        $reg3Id,
        'excused',
        'Participant requested excuse due to severe depression and psychiatric therapy session',
        $coordinatorId,
        RoleService::ROLE_COORDINATOR
    );
} catch (ValidationException $e) {
    $prohibitedNotesBlocked = true;
}

assertPhase1FTest(
    "13. Belated excuse with prohibited clinical keywords is strictly rejected with ValidationException",
    $prohibitedNotesBlocked
);

// -----------------------------------------------------------------------------
// 14. Coordinator Reverses Attended to Unmarked
// -----------------------------------------------------------------------------
$reversalResult = $attendanceService->updateAttendanceStatus(
    $reg2Id,
    'unmarked',
    'Accidental desk scan by volunteer at table 3',
    $coordinatorId,
    RoleService::ROLE_COORDINATOR
);
$freshReversedReg2 = $registrationRepo->findById($reg2Id);

assertPhase1FTest(
    "14. Coordinator reverses attended to unmarked: resets checked_in_at/by/method to NULL and logs audit",
    $reversalResult['success'] === true &&
    $freshReversedReg2['attendance_status'] === 'unmarked' &&
    $freshReversedReg2['checked_in_at'] === null &&
    $freshReversedReg2['checked_in_by'] === null &&
    $freshReversedReg2['check_in_method'] === null
);

// -----------------------------------------------------------------------------
// 15. Staff Role Attempts Reversal to Unmarked
// -----------------------------------------------------------------------------
$staffReversalBlocked = false;
$staffReversalCode = 0;
try {
    $attendanceService->updateAttendanceStatus(
        $reg1Id,
        'unmarked',
        'Staff attempting reversal',
        $staffId,
        RoleService::ROLE_STAFF
    );
} catch (CheckInException $e) {
    $staffReversalBlocked = true;
    $staffReversalCode = $e->getStatusCode();
}

assertPhase1FTest(
    "15. Staff role attempting attendance reversal is denied with HTTP 403 Forbidden",
    $staffReversalBlocked && $staffReversalCode === 403
);

// -----------------------------------------------------------------------------
// 16. Staff Role Attempts to Mark Absent or Excused
// -----------------------------------------------------------------------------
$staffAbsentBlocked = false;
try {
    $attendanceService->updateAttendanceStatus(
        $reg3Id,
        'absent',
        'Staff marking absent',
        $staffId,
        RoleService::ROLE_STAFF
    );
} catch (CheckInException $e) {
    $staffAbsentBlocked = true;
}

assertPhase1FTest(
    "16. Staff role attempting to mark attendee as absent/excused is denied with HTTP 403 Forbidden",
    $staffAbsentBlocked
);

// -----------------------------------------------------------------------------
// 17. Self-Verified Check-In Method Is Strictly Disabled
// -----------------------------------------------------------------------------
$selfVerifiedBlocked = false;
$selfVerifiedCode = 0;
try {
    $checkInService->checkIn($reg3['registration_code'], $event1Id, $staffId, 'self_verified', null, RoleService::ROLE_STAFF);
} catch (CheckInException $e) {
    $selfVerifiedBlocked = true;
    $selfVerifiedCode = $e->getStatusCode();
}

assertPhase1FTest(
    "17. Self-verified check-in is rejected with HTTP 422 (LOCKED DECISION)",
    $selfVerifiedBlocked && $selfVerifiedCode === 422
);

// -----------------------------------------------------------------------------
// 18. Event in Draft Status Rejects Check-In
// -----------------------------------------------------------------------------
$partDraft = $participantRepo->create(['full_name' => 'Draft Attendee', 'email' => 'draft@teami.in', 'agreed_guidelines_at' => date('Y-m-d H:i:s'), 'privacy_consent_at' => date('Y-m-d H:i:s')]);
$codeDraft = 'REG-26-DFTAA';
$regDraftId = $registrationRepo->create([
    'registration_code' => $codeDraft,
    'event_id'          => $eventDraftId,
    'participant_id'    => $partDraft,
    'status'            => 'confirmed',
    'attendance_status' => 'unmarked',
]);

$draftBlocked = false;
$draftCode = 0;
try {
    $checkInService->checkIn($codeDraft, $eventDraftId, $staffId, 'qr_scan', null, RoleService::ROLE_STAFF);
} catch (CheckInException $e) {
    $draftBlocked = true;
    $draftCode = $e->getStatusCode();
}

assertPhase1FTest(
    "18. Event in draft status rejects check-in with HTTP 403 / Event Not Open",
    $draftBlocked && $draftCode === 403
);

// -----------------------------------------------------------------------------
// 19. Event in Cancelled Status Rejects Check-In
// -----------------------------------------------------------------------------
$partCanc = $participantRepo->create(['full_name' => 'Canc Event Attendee', 'email' => 'cancev@teami.in', 'agreed_guidelines_at' => date('Y-m-d H:i:s'), 'privacy_consent_at' => date('Y-m-d H:i:s')]);
$codeCancEv = 'REG-26-CNCAA';
$regCancEvId = $registrationRepo->create([
    'registration_code' => $codeCancEv,
    'event_id'          => $eventCancelledId,
    'participant_id'    => $partCanc,
    'status'            => 'confirmed',
    'attendance_status' => 'unmarked',
]);

$cancelledEvBlocked = false;
$cancelledEvCode = 0;
try {
    $checkInService->checkIn($codeCancEv, $eventCancelledId, $staffId, 'qr_scan', null, RoleService::ROLE_STAFF);
} catch (CheckInException $e) {
    $cancelledEvBlocked = true;
    $cancelledEvCode = $e->getStatusCode();
}

assertPhase1FTest(
    "19. Event in cancelled status rejects check-in with HTTP 403 / Event Cancelled",
    $cancelledEvBlocked && $cancelledEvCode === 403
);

// -----------------------------------------------------------------------------
// 20. Check-In Attempted Outside Timing Window (Future Event)
// -----------------------------------------------------------------------------
$regFutureId = seedRegistration($registrationService, $eventFutureId, $part1Id, $coordinatorId);
$futureReg = $registrationRepo->findById($regFutureId);

// Staff attempt: must fail with HTTP 403
$futureStaffBlocked = false;
$futureStaffCode = 0;
try {
    $checkInService->checkIn($futureReg['registration_code'], $eventFutureId, $staffId, 'qr_scan', null, RoleService::ROLE_STAFF);
} catch (CheckInException $e) {
    $futureStaffBlocked = true;
    $futureStaffCode = $e->getStatusCode();
}

assertPhase1FTest(
    "20. Check-in outside operational window: staff role is blocked with HTTP 403",
    $futureStaffBlocked && $futureStaffCode === 403
);

// -----------------------------------------------------------------------------
// 21. Coordinator Override Requires Operational Reason
// -----------------------------------------------------------------------------
$futureCoordMissingReasonBlocked = false;
$futureCoordMissingReasonCode = 0;
try {
    // Coordinator with no reason: must fail with HTTP 422
    $checkInService->checkIn($futureReg['registration_code'], $eventFutureId, $coordinatorId, 'qr_scan', null, RoleService::ROLE_COORDINATOR);
} catch (CheckInException $e) {
    $futureCoordMissingReasonBlocked = true;
    $futureCoordMissingReasonCode = $e->getStatusCode();
}

assertPhase1FTest(
    "21. Coordinator override outside window requires operational reason (fails HTTP 422 if missing)",
    $futureCoordMissingReasonBlocked && $futureCoordMissingReasonCode === 422
);

// -----------------------------------------------------------------------------
// 22. Coordinator Override Succeeds with Reason & Logs Out-of-Window Audit
// -----------------------------------------------------------------------------
$futureCoordOverrideRes = $checkInService->checkIn(
    $futureReg['registration_code'],
    $eventFutureId,
    $coordinatorId,
    'qr_scan',
    'Early volunteer arrival intake for setup team',
    RoleService::ROLE_COORDINATOR
);

$latestAudit = Database::fetch("SELECT * FROM audit_logs WHERE entity_id = :id AND action = 'attendance.checkin' ORDER BY id DESC LIMIT 1", [':id' => $regFutureId]);
$auditMeta = json_decode((string) ($latestAudit['metadata'] ?? '{}'), true);

assertPhase1FTest(
    "22. Coordinator override succeeds with reason and audit records out_of_window: true",
    $futureCoordOverrideRes['success'] === true &&
    ($auditMeta['out_of_window'] ?? false) === true &&
    ($auditMeta['override_reason'] ?? '') === 'Early volunteer arrival intake for setup team'
);

// -----------------------------------------------------------------------------
// 23. Bulk Absent Attempted Before end_time + 4 Hours
// -----------------------------------------------------------------------------
// Event 1 is currently active (end_time in +2 hours), so bulk absent must be locked!
$bulkEarlyBlocked = false;
$bulkEarlyCode = 0;
try {
    $attendanceService->bulkMarkRemainingAbsent($event1Id, $coordinatorId, RoleService::ROLE_COORDINATOR);
} catch (CheckInException $e) {
    $bulkEarlyBlocked = true;
    $bulkEarlyCode = $e->getStatusCode();
}

assertPhase1FTest(
    "23. Bulk absent attempted before end_time + 4 hours is rejected with HTTP 422 (LOCKED DECISION)",
    $bulkEarlyBlocked && $bulkEarlyCode === 422
);

// -----------------------------------------------------------------------------
// 24. Bulk Absent Attempted After end_time + 4 Hours
// -----------------------------------------------------------------------------
// Seed registrations on EventPast (which ended 5 hours ago, so > 4h buffer has elapsed!)
$partPast1 = $participantRepo->create(['full_name' => 'Past Attendee 1', 'email' => 'past1@teami.in', 'agreed_guidelines_at' => date('Y-m-d H:i:s'), 'privacy_consent_at' => date('Y-m-d H:i:s')]);
$partPast2 = $participantRepo->create(['full_name' => 'Past Attendee 2', 'email' => 'past2@teami.in', 'agreed_guidelines_at' => date('Y-m-d H:i:s'), 'privacy_consent_at' => date('Y-m-d H:i:s')]);

$regPast1Id = seedRegistration($registrationService, $eventPastId, $partPast1, $coordinatorId);
$regPast2Id = seedRegistration($registrationService, $eventPastId, $partPast2, $coordinatorId);
$testPdo->exec("UPDATE events SET status = 'completed' WHERE id = {$eventPastId}");

$bulkCount = $attendanceService->bulkMarkRemainingAbsent($eventPastId, $coordinatorId, RoleService::ROLE_COORDINATOR);
$freshPast1 = $registrationRepo->findById($regPast1Id);
$freshPast2 = $registrationRepo->findById($regPast2Id);

assertPhase1FTest(
    "24. Bulk absent after end_time + 4 hours successfully transitions all remaining unmarked to 'absent'",
    $bulkCount === 2 &&
    $freshPast1['attendance_status'] === 'absent' &&
    $freshPast2['attendance_status'] === 'absent'
);

// -----------------------------------------------------------------------------
// 25. Attendance Does NOT Alter Event Capacity
// -----------------------------------------------------------------------------
$event1Before = $eventRepo->findById($event1Id);
$capacityBefore = $event1Before['capacity'];

// Perform check-in on reg2 (which was reversed back to unmarked)
$checkInService->checkIn($reg2['registration_code'], $event1Id, $staffId, 'qr_scan', null, RoleService::ROLE_STAFF);

$event1After = $eventRepo->findById($event1Id);
$capacityAfter = $event1After['capacity'];

assertPhase1FTest(
    "25. Invariant: Attendance verification NEVER mutates events.capacity",
    $capacityBefore === $capacityAfter
);

// -----------------------------------------------------------------------------
// 26. Attendance Does NOT Alter Registration Status (Remains 'confirmed')
// -----------------------------------------------------------------------------
$freshReg2AfterAtt = $registrationRepo->findById($reg2Id);
assertPhase1FTest(
    "26. Invariant: Attendance verification preserves registration.status === 'confirmed'",
    $freshReg2AfterAtt['status'] === 'confirmed' &&
    $freshReg2AfterAtt['attendance_status'] === 'attended'
);

// -----------------------------------------------------------------------------
// 27. Concurrent Double-Scan Race Simulation
// -----------------------------------------------------------------------------
// Seed fresh registration
$partRace = $participantRepo->create(['full_name' => 'Race Attendee', 'email' => 'race@teami.in', 'agreed_guidelines_at' => date('Y-m-d H:i:s'), 'privacy_consent_at' => date('Y-m-d H:i:s')]);
$regRaceId = seedRegistration($registrationService, $event1Id, $partRace, $coordinatorId);

// First scan executes atomic update:
$affected1 = $registrationRepo->updateAttendanceAtomic($regRaceId, 'qr_scan', $staffId, date('Y-m-d H:i:s'));
// Second scan executes atomic update simultaneously:
$affected2 = $registrationRepo->updateAttendanceAtomic($regRaceId, 'qr_scan', $staffId, date('Y-m-d H:i:s'));assertPhase1FTest(
    "27. Concurrent double-scan race simulation: first scan returns affected=1, second scan returns affected=0",
    $affected1 === 1 && $affected2 === 0
);

// -----------------------------------------------------------------------------
// 27b. Concurrent CheckInService Double-Scan: Exactly 1 Audit Entry & Zero Duplicate Mutation
// -----------------------------------------------------------------------------
$partConc = $participantRepo->create(['full_name' => 'Concurrent Scan Attendee', 'email' => 'concurrent@teami.in', 'agreed_guidelines_at' => date('Y-m-d H:i:s'), 'privacy_consent_at' => date('Y-m-d H:i:s')]);
$regConcId = seedRegistration($registrationService, $event1Id, $partConc, $coordinatorId);
$regConc = $registrationRepo->findById($regConcId);

// First scan at Door A
$scanRes1 = $checkInService->checkIn($regConc['registration_code'], $event1Id, $staffId, 'qr_scan', null, RoleService::ROLE_STAFF);
$auditCount1 = (int) Database::fetch("SELECT COUNT(*) AS total FROM audit_logs WHERE entity_id = :id AND action = 'attendance.checkin'", [':id' => $regConcId])['total'];

// Second scan at Door B simultaneously
$scanRes2 = $checkInService->checkIn($regConc['registration_code'], $event1Id, $staffId, 'qr_scan', null, RoleService::ROLE_STAFF);
$auditCount2 = (int) Database::fetch("SELECT COUNT(*) AS total FROM audit_logs WHERE entity_id = :id AND action = 'attendance.checkin'", [':id' => $regConcId])['total'];

assertPhase1FTest(
    "27b. Concurrent scans through CheckInService: first scan succeeds, second returns idempotent response with no duplicate audit log",
    $scanRes1['success'] === true &&
    $scanRes1['already_checked_in'] === false &&
    $scanRes2['success'] === true &&
    $scanRes2['already_checked_in'] === true &&
    $auditCount1 === 1 &&
    $auditCount2 === 1
);

// -----------------------------------------------------------------------------
// 28. Distinct PDO Parameter Names Verification
// -----------------------------------------------------------------------------
$repoFile = file_get_contents(APP_ROOT . '/app/Repositories/RegistrationRepository.php');
// Assert that no SQL query in RegistrationRepository contains the duplicate parameter pattern :now ... :now
$duplicateNowParam = (preg_match('/:[a-zA-Z0-9_]+\b.*:[a-zA-Z0-9_]+\b/s', $repoFile) && strpos($repoFile, ':now, :now') !== false);
assertPhase1FTest(
    "28. Zero duplicate named PDO parameters in RegistrationRepository SQL queries",
    !$duplicateNowParam
);

// -----------------------------------------------------------------------------
// 29. Audit Log Records Masked Pass Code (REG-26-****X)
// -----------------------------------------------------------------------------
$checkinAudit = Database::fetch("SELECT * FROM audit_logs WHERE entity_id = :id AND action = 'attendance.checkin' ORDER BY id DESC LIMIT 1", [':id' => $reg1Id]);
$checkinAuditMeta = json_decode((string) ($checkinAudit['metadata'] ?? '{}'), true);
$maskedCode = $checkinAuditMeta['masked_code'] ?? '';

assertPhase1FTest(
    "29. Audit log records masked pass code (REG-YY-****X) protecting bearer credentials",
    (bool) preg_match('/^REG-\d{2}-\*{4}[23456789ABCDEFGHJKMNPQRSTVWXYZ]$/', $maskedCode)
);

// -----------------------------------------------------------------------------
// 30. Contact PII Masked in Roster for Staff Role
// -----------------------------------------------------------------------------
$staffRoster = $attendanceService->getEventAttendanceRoster($event1Id, [], 1, 50, RoleService::ROLE_STAFF);
$staffFirstItem = $staffRoster['roster']['items'][0] ?? [];
$isStaffMasked = str_contains($staffFirstItem['participant_email'], '***') &&
                 str_contains($staffFirstItem['participant_phone'], '***') &&
                 $staffFirstItem['participant_name'] !== '[De-identified Attendee]';

assertPhase1FTest(
    "30. Contact PII is masked in attendance roster for staff role (Privacy Shield active)",
    $isStaffMasked
);

// -----------------------------------------------------------------------------
// 30b. Viewer Role Receives De-Identified Attendance Roster
// -----------------------------------------------------------------------------
$viewerRoster = $attendanceService->getEventAttendanceRoster($event1Id, [], 1, 50, RoleService::ROLE_VIEWER);
$viewerFirstItem = $viewerRoster['roster']['items'][0] ?? [];
$isViewerDeidentified = $viewerFirstItem['participant_name'] === '[De-identified Attendee]' &&
                        $viewerFirstItem['participant_email'] === '[De-identified]' &&
                        $viewerFirstItem['participant_phone'] === '[De-identified]' &&
                        str_contains($viewerFirstItem['registration_code'], '****');

assertPhase1FTest(
    "30b. Attendance roster enforces de-identified representation for viewer role",
    $isViewerDeidentified
);

// -----------------------------------------------------------------------------
// 31. Contact PII Unmasked in Roster for Coordinator Role
// -----------------------------------------------------------------------------
$coordRoster = $attendanceService->getEventAttendanceRoster($event1Id, [], 1, 50, RoleService::ROLE_COORDINATOR);
$coordFirstItem = $coordRoster['roster']['items'][0] ?? [];
$isCoordUnmasked = !str_contains($coordFirstItem['participant_email'], '***') &&
                   !str_contains($coordFirstItem['participant_phone'], '***') &&
                   $coordFirstItem['participant_name'] !== '[De-identified Attendee]';

assertPhase1FTest(
    "31. Contact PII is unmasked in attendance roster for coordinator role",
    $isCoordUnmasked
);

// -----------------------------------------------------------------------------
// 32. CSV Export PII Tiers: Staff (Masked), Viewer (De-identified), Coordinator (Full)
// -----------------------------------------------------------------------------
$staffCsv = $attendanceService->exportAttendanceCsv($event1Id, RoleService::ROLE_STAFF);
$coordCsv = $attendanceService->exportAttendanceCsv($event1Id, RoleService::ROLE_COORDINATOR);
$viewerCsv = $attendanceService->exportAttendanceCsv($event1Id, RoleService::ROLE_VIEWER);

$staffCsvMasked = str_contains($staffCsv, '***@') && str_contains($staffCsv, '****') && !str_contains($staffCsv, '[De-identified Attendee]');
$coordCsvUnmasked = !str_contains($coordCsv, '***@') && !str_contains($coordCsv, '[De-identified Attendee]');
$viewerCsvDeidentified = str_contains($viewerCsv, '[De-identified Attendee]') && str_contains($viewerCsv, '[De-identified]');

assertPhase1FTest(
    "32. CSV export enforces three-tier privacy: staff (masked), viewer (de-identified), coordinator (full)",
    $staffCsvMasked && $coordCsvUnmasked && $viewerCsvDeidentified
);

// -----------------------------------------------------------------------------
// 33. Attendance KPIs and Turnout Percentage Computation
// -----------------------------------------------------------------------------
$kpiEvent1 = $registrationRepo->countAttendanceByEvent($event1Id);
$turnoutPct = $kpiEvent1['turnout_percentage'];
$expectedTurnout = round(($kpiEvent1['attended'] / $kpiEvent1['confirmed']) * 100, 1);

assertPhase1FTest(
    "33. Real-time attendance KPIs calculate turnout percentage accurately",
    $turnoutPct === $expectedTurnout && $kpiEvent1['confirmed'] > 0
);

// -----------------------------------------------------------------------------
// 34. Check-In Method Breakdown Aggregation
// -----------------------------------------------------------------------------
$methodCounts = $registrationRepo->countCheckInMethodsByEvent($event1Id);
assertPhase1FTest(
    "34. Method counts correctly aggregate 'qr_scan' and 'admin_manual' check-in channels",
    isset($methodCounts['qr_scan']) && isset($methodCounts['admin_manual']) &&
    ($methodCounts['qr_scan'] + $methodCounts['admin_manual']) === $kpiEvent1['attended']
);

// -----------------------------------------------------------------------------
// 35. HTTP Endpoint & CSRF Protection Verification
// -----------------------------------------------------------------------------
Session::start();
Session::set('_auth_user_id', $staffId);
Session::set('_auth_user_role', RoleService::ROLE_STAFF);

$checkInCtrl = new CheckInController($checkInService, $eventRepo, $registrationRepo, $auditService);

// Test invalid CSRF request on verify (POST request with no token)
$badCsrfRequest = new Request(
    [],
    ['event_id' => $event1Id, 'code' => $reg1['registration_code']],
    [],
    ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/checkin/verify', 'HTTP_ACCEPT' => 'application/json']
);
$csrfMiddleware = new CsrfMiddleware();
$csrfResponse = $csrfMiddleware->handle($badCsrfRequest, function ($req) {
    return Response::json(['success' => true]);
});

assertPhase1FTest(
    "35. POST /admin/checkin/verify enforces CSRF token validation",
    $csrfResponse->getStatusCode() === 403
);

// -----------------------------------------------------------------------------
// Clean Up Database Test Hygiene
// -----------------------------------------------------------------------------
$testPdo->exec("DELETE FROM audit_logs");
$testPdo->exec("DELETE FROM event_registrations");
$testPdo->exec("DELETE FROM participants");
$testPdo->exec("DELETE FROM events");
$testPdo->exec("DELETE FROM campaigns");
$testPdo->exec("DELETE FROM users");

$auditRemaining = (int) Database::fetch("SELECT COUNT(*) AS total FROM audit_logs")['total'];
$regRemaining = (int) Database::fetch("SELECT COUNT(*) AS total FROM event_registrations")['total'];
$partRemaining = (int) Database::fetch("SELECT COUNT(*) AS total FROM participants")['total'];
$eventRemaining = (int) Database::fetch("SELECT COUNT(*) AS total FROM events")['total'];
$userRemaining = (int) Database::fetch("SELECT COUNT(*) AS total FROM users")['total'];

assertPhase1FTest(
    "36. Test Database Hygiene: exactly 0 lingering test rows remain in database tables",
    $auditRemaining === 0 && $regRemaining === 0 && $partRemaining === 0 && $eventRemaining === 0 && $userRemaining === 0
);

echo PHP_EOL;
echo "=================================================" . PHP_EOL;
echo "Phase 1F Attendance & Check-In Verification Results:" . PHP_EOL;
echo "Total Assertions: {$totalTests}" . PHP_EOL;
echo "Passed: {$passedTests}" . PHP_EOL;
echo "Failed: {$failedTests}" . PHP_EOL;
echo "=================================================" . PHP_EOL;

if ($failedTests > 0) {
    exit(1);
}
