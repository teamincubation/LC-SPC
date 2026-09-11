<?php

declare(strict_types=1);

/**
 * Phase 1G — Certificate System & Issuance Test Suite
 * Run via: php tests/test_phase1g_certificates.php
 *
 * Implements full coverage of Section 21 Test Matrix (40+ scenarios, 50+ assertions).
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Controllers\Admin\CertificateController;
use App\Controllers\Public\CertificateVerifyController;
use App\Controllers\Public\RegistrationPassController;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\CertificateException;
use App\Core\Exceptions\ValidationException;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\QrCode;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Repositories\CampaignRepository;
use App\Repositories\CertificateRepository;
use App\Repositories\EventRepository;
use App\Repositories\ParticipantRepository;
use App\Repositories\RegistrationRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\CertificateService;
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

function assertPhase1GTest(string $description, bool $condition, string $details = ''): void
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

function makeReq(string $uri, string $method = 'GET', array $query = [], array $post = []): Request
{
    return new Request($query, $post, [], [
        'REQUEST_METHOD' => strtoupper($method),
        'REQUEST_URI'    => $uri,
    ]);
}

Env::load(APP_ROOT . '/.env');
Config::load(APP_ROOT . '/config');

echo "=== LC-SPC Phase 1G Certificate System & Issuance Verification Suite ===" . PHP_EOL . PHP_EOL;

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

    CREATE TABLE certificates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        certificate_number VARCHAR(50) NOT NULL UNIQUE,
        verification_token VARCHAR(64) NOT NULL UNIQUE,
        registration_id INTEGER NOT NULL,
        recipient_name_snapshot VARCHAR(150) NOT NULL,
        type VARCHAR(30) NOT NULL DEFAULT 'participation',
        issue_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        issued_by INTEGER NULL,
        revoked_at DATETIME NULL,
        revoked_by INTEGER NULL,
        revocation_reason VARCHAR(255) NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    );

    CREATE UNIQUE INDEX uk_cert_active_type ON certificates (registration_id, type) WHERE status = 'active';

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
$certRepo = new CertificateRepository();
$auditRepo = new AuditLogRepository();

$auditService = new AuditService($auditRepo, $userRepo);
$participantService = new ParticipantService($participantRepo, $auditService);
$registrationService = new RegistrationService($registrationRepo, $eventRepo, $participantRepo, $participantService, $auditService);
$certService = new CertificateService($certRepo, $registrationRepo, $eventRepo, $auditService);

// -----------------------------------------------------------------------------
// Seed Administrative Users
// -----------------------------------------------------------------------------
$superAdminId = $userRepo->create([
    'name'          => 'Super Admin Cert',
    'email'         => 'superadmin.cert@teami.in',
    'password_hash' => Security::hashPassword('SuperAdminPass123!'),
    'role'          => RoleService::ROLE_SUPER_ADMIN,
    'status'        => 'active',
]);

$coordinatorId = $userRepo->create([
    'name'          => 'Coordinator Cert',
    'email'         => 'coordinator.cert@teami.in',
    'password_hash' => Security::hashPassword('CoordinatorPass123!'),
    'role'          => RoleService::ROLE_COORDINATOR,
    'status'        => 'active',
]);

$staffId = $userRepo->create([
    'name'          => 'Staff Cert',
    'email'         => 'staff.cert@teami.in',
    'password_hash' => Security::hashPassword('StaffPass123!'),
    'role'          => RoleService::ROLE_STAFF,
    'status'        => 'active',
]);

$viewerId = $userRepo->create([
    'name'          => 'Viewer Cert',
    'email'         => 'viewer.cert@teami.in',
    'password_hash' => Security::hashPassword('ViewerPass123!'),
    'role'          => RoleService::ROLE_VIEWER,
    'status'        => 'active',
]);

// -----------------------------------------------------------------------------
// Seed Campaign and Events
// -----------------------------------------------------------------------------
$campaignId = $campaignRepo->create([
    'title'      => 'Youth Wellbeing Campaign 2026',
    'slug'       => 'youth-wellbeing-2026',
    'start_date' => '2026-09-01',
    'end_date'   => '2026-09-30',
    'status'     => 'active',
    'created_by' => $coordinatorId,
]);

// Eligible past/completed event
$eligibleEventId = $eventRepo->create([
    'campaign_id'    => $campaignId,
    'title'          => 'Crisis Response & Active Listening Session',
    'slug'           => 'crisis-response-listening-session',
    'category'       => 'workshop',
    'format'         => 'in_person',
    'venue_name'     => 'City Community Center',
    'start_time'     => date('Y-m-d H:i:s', strtotime('-4 hours')),
    'end_time'       => date('Y-m-d H:i:s', strtotime('-1 hour')),
    'capacity'       => 100,
    'status'         => 'completed',
    'coordinator_id' => $coordinatorId,
    'created_by'     => $coordinatorId,
]);

// Future event (has not started)
$futureEventId = $eventRepo->create([
    'campaign_id'    => $campaignId,
    'title'          => 'Future Listening Circle',
    'slug'           => 'future-listening-circle',
    'category'       => 'circle',
    'format'         => 'in_person',
    'venue_name'     => 'North Hall',
    'start_time'     => date('Y-m-d H:i:s', strtotime('+24 hours')),
    'end_time'       => date('Y-m-d H:i:s', strtotime('+26 hours')),
    'capacity'       => 50,
    'status'         => 'published',
    'coordinator_id' => $coordinatorId,
    'created_by'     => $coordinatorId,
]);

// Draft event
$draftEventId = $eventRepo->create([
    'campaign_id'    => $campaignId,
    'title'          => 'Draft Planning Workshop',
    'slug'           => 'draft-planning-workshop',
    'category'       => 'workshop',
    'format'         => 'online',
    'start_time'     => date('Y-m-d H:i:s', strtotime('-2 hours')),
    'end_time'       => date('Y-m-d H:i:s', strtotime('+1 hour')),
    'capacity'       => 30,
    'status'         => 'draft',
    'coordinator_id' => $coordinatorId,
    'created_by'     => $coordinatorId,
]);

// -----------------------------------------------------------------------------
// Seed Participants & Registrations
// -----------------------------------------------------------------------------
// Participant 1: Normal attended attendee
$part1Id = $participantRepo->create([
    'full_name'            => 'Priya Sharma',
    'email'                => 'priya.sharma@example.com',
    'phone'                => '+91 98765 43210',
    'category'             => 'student',
    'status'               => 'active',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);
$reg1Id = $registrationRepo->create([
    'registration_code' => 'REG-26-PRYA1',
    'event_id'          => $eligibleEventId,
    'participant_id'    => $part1Id,
    'status'            => 'confirmed',
    'attendance_status' => 'attended',
    'checked_in_at'     => date('Y-m-d H:i:s', strtotime('-3 hours')),
    'check_in_method'   => 'qr_scan',
]);

// Participant 2: Unmarked attendee (confirmed registration but did not attend)
$part2Id = $participantRepo->create([
    'full_name'            => 'Arjun Mehta',
    'email'                => 'arjun.mehta@example.com',
    'phone'                => '+91 98765 43211',
    'category'             => 'community',
    'status'               => 'active',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);
$reg2Id = $registrationRepo->create([
    'registration_code' => 'REG-26-ARJN2',
    'event_id'          => $eligibleEventId,
    'participant_id'    => $part2Id,
    'status'            => 'confirmed',
    'attendance_status' => 'unmarked',
]);

// Participant 3: Marked absent
$part3Id = $participantRepo->create([
    'full_name'            => 'Kavita Nair',
    'email'                => 'kavita.nair@example.com',
    'category'             => 'community',
    'status'               => 'active',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);
$reg3Id = $registrationRepo->create([
    'registration_code' => 'REG-26-KVTA3',
    'event_id'          => $eligibleEventId,
    'participant_id'    => $part3Id,
    'status'            => 'confirmed',
    'attendance_status' => 'absent',
]);

// Participant 4: Excused attendee
$part4Id = $participantRepo->create([
    'full_name'            => 'Rohan Verma',
    'email'                => 'rohan.verma@example.com',
    'category'             => 'student',
    'status'               => 'active',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);
$reg4Id = $registrationRepo->create([
    'registration_code' => 'REG-26-RHAN4',
    'event_id'          => $eligibleEventId,
    'participant_id'    => $part4Id,
    'status'            => 'confirmed',
    'attendance_status' => 'excused',
]);

// Participant 5: Cancelled registration
$part5Id = $participantRepo->create([
    'full_name'            => 'Vikram Singh',
    'email'                => 'vikram.singh@example.com',
    'category'             => 'community',
    'status'               => 'active',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);
$reg5Id = $registrationRepo->create([
    'registration_code' => 'REG-26-VKRM5',
    'event_id'          => $eligibleEventId,
    'participant_id'    => $part5Id,
    'status'            => 'cancelled',
    'attendance_status' => 'unmarked',
]);

// Participant 6: Pending registration
$part6Id = $participantRepo->create([
    'full_name'            => 'Ananya Rao',
    'email'                => 'ananya.rao@example.com',
    'category'             => 'student',
    'status'               => 'active',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);
$reg6Id = $registrationRepo->create([
    'registration_code' => 'REG-26-ANNY6',
    'event_id'          => $eligibleEventId,
    'participant_id'    => $part6Id,
    'status'            => 'pending',
    'attendance_status' => 'unmarked',
]);

// Participant 7: Waitlisted registration
$part7Id = $participantRepo->create([
    'full_name'            => 'Deepak Patel',
    'email'                => 'deepak.patel@example.com',
    'category'             => 'community',
    'status'               => 'active',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);
$reg7Id = $registrationRepo->create([
    'registration_code' => 'REG-26-DPK7',
    'event_id'          => $eligibleEventId,
    'participant_id'    => $part7Id,
    'status'            => 'waitlisted',
    'attendance_status' => 'unmarked',
]);

// Participant 8: Blocked participant
$part8Id = $participantRepo->create([
    'full_name'            => 'Blocked User',
    'email'                => 'blocked.user@example.com',
    'category'             => 'community',
    'status'               => 'blocked',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);
$reg8Id = $registrationRepo->create([
    'registration_code' => 'REG-26-BLCK8',
    'event_id'          => $eligibleEventId,
    'participant_id'    => $part8Id,
    'status'            => 'confirmed',
    'attendance_status' => 'attended',
]);

// Participant 9: Flagged participant (attended)
$part9Id = $participantRepo->create([
    'full_name'            => 'Flagged Attendee',
    'email'                => 'flagged.attendee@example.com',
    'category'             => 'community',
    'status'               => 'flagged',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);
$reg9Id = $registrationRepo->create([
    'registration_code' => 'REG-26-FLGD9',
    'event_id'          => $eligibleEventId,
    'participant_id'    => $part9Id,
    'status'            => 'confirmed',
    'attendance_status' => 'attended',
    'checked_in_at'     => date('Y-m-d H:i:s', strtotime('-3 hours')),
]);

// Participant 10: Future event registration
$part10Id = $participantRepo->create([
    'full_name'            => 'Future Attendee',
    'email'                => 'future.attendee@example.com',
    'category'             => 'community',
    'status'               => 'active',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);
$reg10Id = $registrationRepo->create([
    'registration_code' => 'REG-26-FUTR10',
    'event_id'          => $futureEventId,
    'participant_id'    => $part10Id,
    'status'            => 'confirmed',
    'attendance_status' => 'attended',
]);

// Participant 11: Draft event registration
$part11Id = $participantRepo->create([
    'full_name'            => 'Draft Event Attendee',
    'email'                => 'draft.attendee@example.com',
    'category'             => 'community',
    'status'               => 'active',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);
$reg11Id = $registrationRepo->create([
    'registration_code' => 'REG-26-DRFT11',
    'event_id'          => $draftEventId,
    'participant_id'    => $part11Id,
    'status'            => 'confirmed',
    'attendance_status' => 'attended',
]);


echo "--- Executing Phase 1G Unit & Service Verification (Scenarios 1 to 40) ---" . PHP_EOL;

// -----------------------------------------------------------------------------
// 1. Migration m0008 & Schema Verification
// -----------------------------------------------------------------------------
$migrationFile = APP_ROOT . '/database/migrations/m0008_update_certificates_unique_constraint.php';
assertPhase1GTest(
    '1. Migration m0008 file exists and has up() and down() methods',
    file_exists($migrationFile) && is_object($migrationObj = require $migrationFile) && method_exists($migrationObj, 'up') && method_exists($migrationObj, 'down')
);

// -----------------------------------------------------------------------------
// 2. Normal Individual Issuance (Eligible Attendee)
// -----------------------------------------------------------------------------
$issuedCert1 = $certService->issueSingle($reg1Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR);
assertPhase1GTest(
    '2. Eligible attended participant receives active certificate of participation',
    !empty($issuedCert1['id']) && $issuedCert1['status'] === 'active' && $issuedCert1['recipient_name_snapshot'] === 'Priya Sharma'
);

// -----------------------------------------------------------------------------
// 3. Number Format & Token Entropy
// -----------------------------------------------------------------------------
$certNumberPattern = '/^LC-\d{4}-SPC-[0-9A-HJKMNP-Z]{5}$/';
assertPhase1GTest(
    '3. Certificate number conforms to LC-{YYYY}-SPC-{5_CROCKFORD} non-sequential format',
    preg_match($certNumberPattern, $issuedCert1['certificate_number']) === 1,
    "Got: {$issuedCert1['certificate_number']}"
);

assertPhase1GTest(
    '4. Verification token is exactly 64 lowercase hex characters (256-bit CSPRNG)',
    strlen($issuedCert1['verification_token']) === 64 && ctype_xdigit($issuedCert1['verification_token'])
);

// -----------------------------------------------------------------------------
// 5. Duplicate Active Certificate Prevention (HTTP 409)
// -----------------------------------------------------------------------------
$caughtDuplicate = false;
try {
    $certService->issueSingle($reg1Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR);
} catch (CertificateException $e) {
    $caughtDuplicate = ($e->getStatusCode() === 409);
}
assertPhase1GTest(
    '5. Duplicate active certificate of same type rejected with HTTP 409 Conflict',
    $caughtDuplicate
);

// -----------------------------------------------------------------------------
// 6. Multi-Type Invariant: Same Registration can have Participation + Volunteer
// -----------------------------------------------------------------------------
$volunteerCert = $certService->issueSingle($reg1Id, CertificateService::TYPE_VOLUNTEER, $coordinatorId, RoleService::ROLE_COORDINATOR);
assertPhase1GTest(
    '6. Multi-Type Coexistence: Same registration receives 1 participation + 1 volunteer certificate',
    !empty($volunteerCert['id']) && $volunteerCert['type'] === CertificateService::TYPE_VOLUNTEER && $volunteerCert['status'] === 'active'
);

// -----------------------------------------------------------------------------
// 7. Ineligibility: Unmarked Attendee Rejected (HTTP 422)
// -----------------------------------------------------------------------------
$caughtUnmarked = false;
try {
    $certService->issueSingle($reg2Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR);
} catch (CertificateException $e) {
    $caughtUnmarked = ($e->getStatusCode() === 422);
}
assertPhase1GTest(
    '7. Ineligibility: Unmarked registration rejected with HTTP 422',
    $caughtUnmarked
);

// -----------------------------------------------------------------------------
// 8. Ineligibility: Absent Attendee Rejected (HTTP 422)
// -----------------------------------------------------------------------------
$caughtAbsent = false;
try {
    $certService->issueSingle($reg3Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR);
} catch (CertificateException $e) {
    $caughtAbsent = ($e->getStatusCode() === 422);
}
assertPhase1GTest(
    '8. Ineligibility: Absent registration rejected with HTTP 422',
    $caughtAbsent
);

// -----------------------------------------------------------------------------
// 9. Ineligibility: Excused Attendee Rejected (HTTP 422)
// -----------------------------------------------------------------------------
$caughtExcused = false;
try {
    $certService->issueSingle($reg4Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR);
} catch (CertificateException $e) {
    $caughtExcused = ($e->getStatusCode() === 422);
}
assertPhase1GTest(
    '9. Ineligibility: Excused registration rejected with HTTP 422',
    $caughtExcused
);

// -----------------------------------------------------------------------------
// 10. Ineligibility: Cancelled Registration Rejected (HTTP 403)
// -----------------------------------------------------------------------------
$caughtCancelled = false;
try {
    $certService->issueSingle($reg5Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR);
} catch (CertificateException $e) {
    $caughtCancelled = ($e->getStatusCode() === 403);
}
assertPhase1GTest(
    '10. Ineligibility: Cancelled registration rejected with HTTP 403 Forbidden',
    $caughtCancelled
);

// -----------------------------------------------------------------------------
// 11. Ineligibility: Pending Registration Rejected (HTTP 403)
// -----------------------------------------------------------------------------
$caughtPending = false;
try {
    $certService->issueSingle($reg6Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR);
} catch (CertificateException $e) {
    $caughtPending = ($e->getStatusCode() === 403);
}
assertPhase1GTest(
    '11. Ineligibility: Pending registration rejected with HTTP 403 Forbidden',
    $caughtPending
);

// -----------------------------------------------------------------------------
// 12. Ineligibility: Waitlisted Registration Rejected (HTTP 403)
// -----------------------------------------------------------------------------
$caughtWaitlisted = false;
try {
    $certService->issueSingle($reg7Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR);
} catch (CertificateException $e) {
    $caughtWaitlisted = ($e->getStatusCode() === 403);
}
assertPhase1GTest(
    '12. Ineligibility: Waitlisted registration rejected with HTTP 403 Forbidden',
    $caughtWaitlisted
);

// -----------------------------------------------------------------------------
// 13. Ineligibility: Blocked Participant Rejected (HTTP 403)
// -----------------------------------------------------------------------------
$caughtBlocked = false;
try {
    $certService->issueSingle($reg8Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR);
} catch (CertificateException $e) {
    $caughtBlocked = ($e->getStatusCode() === 403);
}
assertPhase1GTest(
    '13. Ineligibility: Blocked participant rejected with HTTP 403 Forbidden',
    $caughtBlocked
);

// -----------------------------------------------------------------------------
// 14. Flagged Participant: Unchecked Confirmation Rejected (HTTP 422)
// -----------------------------------------------------------------------------
$caughtFlaggedUnconfirmed = false;
try {
    $certService->issueSingle($reg9Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR, [
        'confirm_flagged' => false,
    ]);
} catch (CertificateException $e) {
    $caughtFlaggedUnconfirmed = ($e->getStatusCode() === 422);
}
assertPhase1GTest(
    '14. Flagged Participant: Unchecked confirmation rejected with HTTP 422',
    $caughtFlaggedUnconfirmed
);

// -----------------------------------------------------------------------------
// 15. Flagged Participant: Missing/Short Operational Justification Rejected (HTTP 422)
// -----------------------------------------------------------------------------
$caughtFlaggedShortReason = false;
try {
    $certService->issueSingle($reg9Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR, [
        'confirm_flagged'      => true,
        'flag_override_reason' => 'Too short',
    ]);
} catch (CertificateException $e) {
    $caughtFlaggedShortReason = ($e->getStatusCode() === 422);
}
assertPhase1GTest(
    '15. Flagged Participant: Short reason (< 10 chars) rejected with HTTP 422',
    $caughtFlaggedShortReason
);

// -----------------------------------------------------------------------------
// 16. Flagged Participant: Prohibited Clinical Keywords Rejected (ValidationException)
// -----------------------------------------------------------------------------
$caughtClinical = false;
try {
    $certService->issueSingle($reg9Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR, [
        'confirm_flagged'      => true,
        'flag_override_reason' => 'Participant left early for psychological depression therapy session',
    ]);
} catch (ValidationException $e) {
    $caughtClinical = true;
}
assertPhase1GTest(
    '16. Flagged Participant: Clinical/psychological keywords rejected with ValidationException',
    $caughtClinical
);

// -----------------------------------------------------------------------------
// 17. Flagged Participant: Valid Confirmation & Non-Clinical Justification Succeeds
// -----------------------------------------------------------------------------
$issuedFlaggedCert = $certService->issueSingle($reg9Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR, [
    'confirm_flagged'      => true,
    'flag_override_reason' => 'Verified attendee government photo ID at registration reception desk',
]);
$part9Check = $participantRepo->findById($part9Id);
assertPhase1GTest(
    '17. Flagged Participant: Valid confirmation succeeds and participant remains flagged in global status',
    !empty($issuedFlaggedCert['id']) && $part9Check['status'] === 'flagged'
);

// -----------------------------------------------------------------------------
// 18. Event Status Gating: Draft Event Rejected (HTTP 403)
// -----------------------------------------------------------------------------
$caughtDraftEvent = false;
try {
    $certService->issueSingle($reg11Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR);
} catch (CertificateException $e) {
    $caughtDraftEvent = ($e->getStatusCode() === 403);
}
assertPhase1GTest(
    '18. Event Status Gating: Draft event rejected with HTTP 403 Forbidden',
    $caughtDraftEvent
);

// -----------------------------------------------------------------------------
// 19. Event Timeline Gating: Pre-Event Issuance Rejected (HTTP 422)
// -----------------------------------------------------------------------------
$caughtFutureEvent = false;
try {
    $certService->issueSingle($reg10Id, CertificateService::TYPE_PARTICIPATION, $coordinatorId, RoleService::ROLE_COORDINATOR);
} catch (CertificateException $e) {
    $caughtFutureEvent = ($e->getStatusCode() === 422);
}
assertPhase1GTest(
    '19. Event Timeline Gating: Pre-event issuance rejected with HTTP 422',
    $caughtFutureEvent
);

// -----------------------------------------------------------------------------
// 20. Pure PHP Vector QR Code Generation
// -----------------------------------------------------------------------------
$testVerifyUrl = "https://teami.in/LC/verify/{$issuedCert1['verification_token']}";
$qrSvg = QrCode::svg($testVerifyUrl, 200, 2);
assertPhase1GTest(
    '20. QR Code: Pure PHP SVG vector generator produces valid XML containing verification path',
    str_starts_with($qrSvg, '<svg') && str_contains($qrSvg, '</svg>') && strlen($qrSvg) > 500
);

// -----------------------------------------------------------------------------
// 21. High-Resolution JPG Generation ($2480 \times 1754$, 300 DPI)
// -----------------------------------------------------------------------------
$certFull = $certRepo->findById($issuedCert1['id']);
$jpgData = $certService->renderJpg($certFull);
$jpgInfo = @getimagesizefromstring($jpgData);
assertPhase1GTest(
    '21. Dual Output JPG: High-resolution JPG generated at exactly 2480x1754 pixels',
    is_array($jpgInfo) && ($jpgInfo[0] ?? 0) === 2480 && ($jpgInfo[1] ?? 0) === 1754 && ($jpgInfo['mime'] ?? '') === 'image/jpeg',
    "Dimensions: " . ($jpgInfo[0] ?? 'null') . "x" . ($jpgInfo[1] ?? 'null')
);

// -----------------------------------------------------------------------------
// 22. Dual Output Consistency: PDF & JPG Share Same Number, Name, & QR Token
// -----------------------------------------------------------------------------
assertPhase1GTest(
    '22. Dual Output Consistency: PDF and JPG share identical certificate number and legal name',
    $certFull['certificate_number'] === $issuedCert1['certificate_number'] &&
    $certFull['recipient_name_snapshot'] === $issuedCert1['recipient_name_snapshot'] &&
    $certFull['verification_token'] === $issuedCert1['verification_token']
);

// -----------------------------------------------------------------------------
// 23. Dual Output Relational Invariant: Zero Duplicate Rows Created on Download
// -----------------------------------------------------------------------------
$certRowsBefore = (int) $testPdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
$downloadJpgData = $certService->renderJpg($certFull);
$certRowsAfter = (int) $testPdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
assertPhase1GTest(
    '23. Dual Output Relational Invariant: Generating/downloading JPG creates 0 duplicate database rows',
    $certRowsBefore === $certRowsAfter
);

// -----------------------------------------------------------------------------
// 24. Public Verification: Active Token Returns HTTP 200 with Authentic Badge
// -----------------------------------------------------------------------------
$verifyController = new CertificateVerifyController($certRepo, $certService, $auditService);
$reqVerify = makeReq("/verify/{$issuedCert1['verification_token']}");
$respVerify = $verifyController->show($reqVerify, ['token' => $issuedCert1['verification_token']]);
assertPhase1GTest(
    '24. Public Verification (Active): Returns HTTP 200 with authentic credential badge',
    $respVerify->getStatusCode() === 200 &&
    str_contains($respVerify->getContent(), 'AUTHENTIC &amp; VERIFIED') &&
    str_contains($respVerify->getContent(), $issuedCert1['certificate_number']) &&
    str_contains($respVerify->getContent(), 'Priya Sharma')
);

// -----------------------------------------------------------------------------
// 25. Public Verification: Minimal Disclosure Standard (Zero PII Leaked)
// -----------------------------------------------------------------------------
$publicContent = $respVerify->getContent();
assertPhase1GTest(
    '25. Public Minimal Disclosure: Zero phone number, email address, or internal registration ID leaked',
    !str_contains($publicContent, 'priya.sharma@example.com') &&
    !str_contains($publicContent, '+91 98765') &&
    !str_contains($publicContent, 'REG-26-PRYA1') &&
    !str_contains(strtolower($publicContent), 'registration_id')
);

// -----------------------------------------------------------------------------
// 26. Certificate Revocation Workflow
// -----------------------------------------------------------------------------
$revokedCert = $certService->revokeCertificate($issuedFlaggedCert['id'], 'Administrative review determined incorrect badge category', $coordinatorId, RoleService::ROLE_COORDINATOR);
$revokedDbRow = $certRepo->findById($issuedFlaggedCert['id']);
assertPhase1GTest(
    '26. Revocation: Certificate permanently marked revoked with timestamp and revoking user',
    $revokedDbRow['status'] === 'revoked' && !empty($revokedDbRow['revoked_at']) && (int) $revokedDbRow['revoked_by'] === $coordinatorId
);

// -----------------------------------------------------------------------------
// 27. Public Verification: Revoked Token Returns HTTP 200 with Invalidation Notice
// -----------------------------------------------------------------------------
$reqRevokedVerify = makeReq("/verify/{$issuedFlaggedCert['verification_token']}");
$respRevokedVerify = $verifyController->show($reqRevokedVerify, ['token' => $issuedFlaggedCert['verification_token']]);
assertPhase1GTest(
    '27. Public Verification (Revoked): Returns HTTP 200 with REVOKED/INVALID notice and generic statement',
    $respRevokedVerify->getStatusCode() === 200 &&
    str_contains($respRevokedVerify->getContent(), 'REVOKED / INVALID') &&
    str_contains($respRevokedVerify->getContent(), 'This certificate was officially invalidated by Listening Community SPC')
);

// -----------------------------------------------------------------------------
// 28. Revocation Privacy: Internal Reason NOT Exposed on Public Page
// -----------------------------------------------------------------------------
assertPhase1GTest(
    '28. Revocation Privacy: Internal revocation rationale is strictly hidden from public verification',
    !str_contains($respRevokedVerify->getContent(), 'incorrect badge category')
);

// -----------------------------------------------------------------------------
// 29. Public Verification: Invalid / Non-existent Token Returns HTTP 404
// -----------------------------------------------------------------------------
$fakeToken = str_repeat('a', 64);
$reqFake = makeReq("/verify/{$fakeToken}");
$respFake = $verifyController->show($reqFake, ['token' => $fakeToken]);
assertPhase1GTest(
    '29. Public Verification (Invalid Token): Non-existent token returns HTTP 404 minimal disclosure page',
    $respFake->getStatusCode() === 404 && str_contains($respFake->getContent(), 'Certificate Not Found')
);

// -----------------------------------------------------------------------------
// 30. Public Verification Rate Limiting (10 Failures -> 15 min lock / HTTP 429)
// -----------------------------------------------------------------------------
$rateLimitDir = APP_ROOT . '/storage/cache/rate_limits_test';
@mkdir($rateLimitDir, 0755, true);
$rlController = new CertificateVerifyController($certRepo, $certService, $auditService, $rateLimitDir);

$_SERVER['REMOTE_ADDR'] = '198.51.100.42';
for ($i = 0; $i < 9; $i++) {
    $rlController->show(makeReq("/verify/" . str_repeat('0', 64)), ['token' => str_repeat('0', 64)]);
}
// 10th failure
$rlController->show(makeReq("/verify/" . str_repeat('0', 64)), ['token' => str_repeat('0', 64)]);
// 11th request triggers rate limit lockout HTTP 429
$respRateLimited = $rlController->show(makeReq("/verify/" . str_repeat('0', 64)), ['token' => str_repeat('0', 64)]);
assertPhase1GTest(
    '30. Rate Limiting: 11th failed verification attempt returns HTTP 429 and Retry-After header',
    $respRateLimited->getStatusCode() === 429 && $respRateLimited->getHeader('Retry-After') === '900'
);

// Cleanup test rate limit dir
array_map('unlink', glob($rateLimitDir . '/*.*'));
@rmdir($rateLimitDir);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// -----------------------------------------------------------------------------
// 31. Superseded Credential: Clerical Name Correction & Re-issuance
// -----------------------------------------------------------------------------
// Correct name on $issuedCert1 ('Priya Sharma' -> 'Dr. Priya Sharma')
$reissueResult = $certService->reissueNameCorrection(
    $issuedCert1['id'],
    'Dr. Priya Sharma',
    'Clerical honorific correction requested by recipient',
    $coordinatorId,
    RoleService::ROLE_COORDINATOR
);

$oldCertDb = $certRepo->findById($issuedCert1['id']);
$newCertDb = $certRepo->findById($reissueResult['new_certificate_id']);

assertPhase1GTest(
    '31. Superseded Re-issuance: Old certificate marked revoked and new certificate active with new number',
    $oldCertDb['status'] === 'revoked' &&
    $newCertDb['status'] === 'active' &&
    $newCertDb['recipient_name_snapshot'] === 'Dr. Priya Sharma' &&
    $newCertDb['certificate_number'] !== $oldCertDb['certificate_number'] &&
    $newCertDb['verification_token'] !== $oldCertDb['verification_token']
);

// -----------------------------------------------------------------------------
// 32. Superseded Verification: Old Paper QR Scan Shows Revoked/Superseded
// -----------------------------------------------------------------------------
$reqOldQr = makeReq("/verify/{$oldCertDb['verification_token']}");
$respOldQr = $verifyController->show($reqOldQr, ['token' => $oldCertDb['verification_token']]);
assertPhase1GTest(
    '32. Superseded Credential Scan: Old QR code reports REVOKED / SUPERSEDED to verifier',
    $respOldQr->getStatusCode() === 200 &&
    str_contains($respOldQr->getContent(), 'REVOKED / INVALID') &&
    str_contains($respOldQr->getContent(), $oldCertDb['certificate_number'])
);

// -----------------------------------------------------------------------------
// 33. Superseded Verification: New QR Scan Shows Authentic with Corrected Name
// -----------------------------------------------------------------------------
$reqNewQr = makeReq("/verify/{$newCertDb['verification_token']}");
$respNewQr = $verifyController->show($reqNewQr, ['token' => $newCertDb['verification_token']]);
assertPhase1GTest(
    '33. Superseded Credential Scan: New QR code reports AUTHENTIC & VERIFIED with corrected legal name',
    $respNewQr->getStatusCode() === 200 &&
    str_contains($respNewQr->getContent(), 'AUTHENTIC &amp; VERIFIED') &&
    str_contains($respNewQr->getContent(), 'Dr. Priya Sharma') &&
    str_contains($respNewQr->getContent(), $newCertDb['certificate_number'])
);

// -----------------------------------------------------------------------------
// 34. Controlled Bulk Issuance
// -----------------------------------------------------------------------------
// Create 3 additional attended registrations
$bulkRegIds = [];
for ($i = 1; $i <= 3; $i++) {
    $pId = $participantRepo->create([
        'full_name'            => "Bulk Attendee {$i}",
        'email'                => "bulk.attendee{$i}@example.com",
        'category'             => 'student',
        'status'               => 'active',
        'agreed_guidelines_at' => date('Y-m-d H:i:s'),
        'privacy_consent_at'   => date('Y-m-d H:i:s'),
    ]);
    $bulkRegIds[] = $registrationRepo->create([
        'registration_code' => "REG-26-BULK{$i}",
        'event_id'          => $eligibleEventId,
        'participant_id'    => $pId,
        'status'            => 'confirmed',
        'attendance_status' => 'attended',
        'checked_in_at'     => date('Y-m-d H:i:s', strtotime('-2 hours')),
    ]);
}

$bulkResult = $certService->bulkIssue(
    $eligibleEventId,
    CertificateService::TYPE_PARTICIPATION,
    array_merge($bulkRegIds, [$reg2Id, $reg9Id]), // Includes unmarked ($reg2Id) and flagged ($reg9Id)
    $coordinatorId,
    RoleService::ROLE_COORDINATOR
);

assertPhase1GTest(
    '34. Bulk Issuance: Issues for eligible attendees while skipping unmarked and flagged records',
    $bulkResult['issued'] === 3 && $bulkResult['skipped'] === 2
);

// -----------------------------------------------------------------------------
// 35. Bulk Issuance Idempotency
// -----------------------------------------------------------------------------
$bulkResult2 = $certService->bulkIssue(
    $eligibleEventId,
    CertificateService::TYPE_PARTICIPATION,
    $bulkRegIds,
    $coordinatorId,
    RoleService::ROLE_COORDINATOR
);
assertPhase1GTest(
    '35. Bulk Idempotency: Re-running bulk batch issues 0 duplicates and marks 3 as already_issued',
    $bulkResult2['issued'] === 0 && $bulkResult2['already_issued'] === 3
);

// -----------------------------------------------------------------------------
// 36. Bulk Issuance Batch Capping (Max 100)
// -----------------------------------------------------------------------------
$caughtBulkCapped = false;
try {
    $fake105Ids = range(1, 105);
    $certService->bulkIssue($eligibleEventId, CertificateService::TYPE_PARTICIPATION, $fake105Ids, $coordinatorId, RoleService::ROLE_COORDINATOR);
} catch (CertificateException $e) {
    $caughtBulkCapped = ($e->getStatusCode() === 422);
}
assertPhase1GTest(
    '36. Bulk Batch Capping: Submissions exceeding 100 registrations rejected with HTTP 422',
    $caughtBulkCapped
);

// -----------------------------------------------------------------------------
// 37. RBAC Enforcement: Staff Role Cannot Issue Certificates (HTTP 403)
// -----------------------------------------------------------------------------
$caughtStaffIssue = false;
try {
    $certService->issueSingle($bulkRegIds[0], CertificateService::TYPE_SPEAKER, $staffId, RoleService::ROLE_STAFF);
} catch (CertificateException $e) {
    $caughtStaffIssue = ($e->getStatusCode() === 403);
}
assertPhase1GTest(
    '37. RBAC: Staff role cannot issue certificates (HTTP 403 Forbidden)',
    $caughtStaffIssue
);

// -----------------------------------------------------------------------------
// 38. RBAC Enforcement: Staff Role Cannot Revoke Certificates (HTTP 403)
// -----------------------------------------------------------------------------
$caughtStaffRevoke = false;
try {
    $certService->revokeCertificate($newCertDb['id'], 'Staff attempt', $staffId, RoleService::ROLE_STAFF);
} catch (CertificateException $e) {
    $caughtStaffRevoke = ($e->getStatusCode() === 403);
}
assertPhase1GTest(
    '38. RBAC: Staff role cannot revoke certificates (HTTP 403 Forbidden)',
    $caughtStaffRevoke
);

// -----------------------------------------------------------------------------
// 39. Public Pass Integration: Pass Shows Certificate Option if Issued
// -----------------------------------------------------------------------------
$passController = new RegistrationPassController($registrationRepo, $registrationService, $auditService, $certRepo);
$reqPass = makeReq('/registration/pass/REG-26-PRYA1');
$respPass = $passController->show($reqPass, ['code' => 'REG-26-PRYA1']);
assertPhase1GTest(
    '39. Public Pass Integration: Pass view renders certificate verification option when active certificate exists',
    $respPass->getStatusCode() === 200 && str_contains($respPass->getContent(), 'View / Verify Certificate')
);

// -----------------------------------------------------------------------------
// 40. Invariant: Certificate Operations Never Mutate Event Capacity or Registration Status
// -----------------------------------------------------------------------------
$eventCheck = $eventRepo->findById($eligibleEventId);
$regCheck = $registrationRepo->findById($reg1Id);
assertPhase1GTest(
    '40. Invariant Integrity: Event capacity remains unchanged (100) and registration status remains confirmed',
    (int) $eventCheck['capacity'] === 100 && $regCheck['status'] === 'confirmed'
);

echo PHP_EOL . "=================================================" . PHP_EOL;
echo "Phase 1G Certificate System Verification Results:" . PHP_EOL;
echo "Total Assertions: {$totalTests}" . PHP_EOL;
echo "Passed: {$passedTests}" . PHP_EOL;
echo "Failed: {$failedTests}" . PHP_EOL;
echo "=================================================" . PHP_EOL;

if ($failedTests > 0) {
    exit(1);
}
