<?php

declare(strict_types=1);

/**
 * Phase 1C — Event Management Automated Test Suite
 * Run via: php tests/test_phase1c_events.php
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
use App\Repositories\AuditLogRepository;
use App\Repositories\CampaignRepository;
use App\Repositories\EventRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\CampaignService;
use App\Services\EventService;
use App\Services\RoleService;

// Colors for terminal output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertEventTest(string $description, bool $condition, string $details = ''): void
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

echo "=== LC-SPC Phase 1C Event Management Verification Suite ===" . PHP_EOL . PHP_EOL;

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
$auditRepo = new AuditLogRepository();
$auditService = new AuditService($auditRepo, $userRepo);
$eventService = new EventService($eventRepo, $campaignRepo, $userRepo, $auditService);

// -----------------------------------------------------------------------------
// Seed Test Users (RBAC Matrix Actors)
// -----------------------------------------------------------------------------
$superAdminId = $userRepo->create([
    'name'          => 'Super Admin Test',
    'email'         => 'superadmin@teami.in',
    'password_hash' => Security::hashPassword('SuperAdminPass123!'),
    'role'          => 'super_admin',
    'status'        => 'active',
]);

$coordinatorId = $userRepo->create([
    'name'          => 'Coordinator Test',
    'email'         => 'coordinator@teami.in',
    'password_hash' => Security::hashPassword('CoordinatorPass123!'),
    'role'          => 'coordinator',
    'status'        => 'active',
]);

$staffId = $userRepo->create([
    'name'          => 'Staff Test',
    'email'         => 'staff@teami.in',
    'password_hash' => Security::hashPassword('StaffPass123!'),
    'role'          => 'staff',
    'status'        => 'active',
]);

$viewerId = $userRepo->create([
    'name'          => 'Viewer Test',
    'email'         => 'viewer@teami.in',
    'password_hash' => Security::hashPassword('ViewerPass123!'),
    'role'          => 'viewer',
    'status'        => 'active',
]);

$inactiveUserId = $userRepo->create([
    'name'          => 'Inactive User Test',
    'email'         => 'inactive@teami.in',
    'password_hash' => Security::hashPassword('InactivePass123!'),
    'role'          => 'coordinator',
    'status'        => 'suspended',
]);

// -----------------------------------------------------------------------------
// Seed Test Campaigns
// -----------------------------------------------------------------------------
$campaignAId = $campaignRepo->create([
    'title'       => 'Suicide Prevention Campaign 2026',
    'slug'        => 'spc-2026',
    'theme'       => 'Words and Beyond',
    'description' => 'Flagship initiative for mental health and awareness',
    'start_date'  => '2026-09-01',
    'end_date'    => '2026-10-31',
    'status'      => 'active',
    'created_by'  => $superAdminId,
]);

$campaignBId = $campaignRepo->create([
    'title'       => 'Campus Listening Drive 2026',
    'slug'        => 'cld-2026',
    'theme'       => 'Listen First',
    'description' => 'Secondary campaign targeting student circles',
    'start_date'  => '2026-10-01',
    'end_date'    => '2026-11-30',
    'status'      => 'active',
    'created_by'  => $coordinatorId,
]);

$deletedCampaignId = $campaignRepo->create([
    'title'       => 'Soft Deleted Campaign',
    'slug'        => 'deleted-camp',
    'start_date'  => '2026-01-01',
    'end_date'    => '2026-02-01',
    'status'      => 'archived',
    'created_by'  => $superAdminId,
]);
$campaignRepo->softDelete($deletedCampaignId);

echo PHP_EOL . "1. Testing Event Creation & Schema Validation..." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 1: Event creation with valid required fields
// -----------------------------------------------------------------------------
$event1 = $eventService->createEvent([
    'campaign_id' => $campaignAId,
    'title'       => 'Active Listening Workshop',
    'category'    => 'workshop',
    'format'      => 'in_person',
    'venue_name'  => 'Main Campus Hall A',
    'start_time'  => '2026-09-15 09:00:00',
    'end_time'    => '2026-09-15 12:00:00',
    'status'      => 'published',
], $superAdminId);

assertEventTest(
    "1. Event creation with valid required fields succeeds and persists to database",
    !empty($event1['id']) &&
    $event1['title'] === 'Active Listening Workshop' &&
    $event1['slug'] === 'active-listening-workshop' &&
    $event1['status'] === 'published' &&
    $event1['capacity'] === 0
);

// -----------------------------------------------------------------------------
// Test 2: Event creation with optional fields (venue, coordinator, online link, capacity)
// -----------------------------------------------------------------------------
$event2 = $eventService->createEvent([
    'campaign_id'           => $campaignAId,
    'coordinator_id'        => $coordinatorId,
    'title'                 => 'Hybrid Facilitator Seminar',
    'slug'                  => 'hybrid-seminar-2026',
    'category'              => 'seminar',
    'description'           => 'Intensive facilitation seminar with live streaming',
    'format'                => 'hybrid',
    'venue_name'            => 'Auditorium 2',
    'venue_address'         => '123 University Blvd, Block C',
    'online_meeting_url'    => 'https://meet.google.com/abc-defg-hij',
    'start_time'            => '2026-09-20 14:00:00',
    'end_time'              => '2026-09-20 17:00:00',
    'capacity'              => 50,
    'registration_deadline' => '2026-09-19 23:59:00',
    'requires_approval'     => 1,
    'status'                => 'draft',
], $coordinatorId);

assertEventTest(
    "2. Event creation with optional fields (coordinator, address, online link, capacity, deadline, approval) succeeds",
    !empty($event2['id']) &&
    $event2['coordinator_id'] === $coordinatorId &&
    $event2['format'] === 'hybrid' &&
    $event2['capacity'] === 50 &&
    $event2['requires_approval'] === 1 &&
    $event2['online_meeting_url'] === 'https://meet.google.com/abc-defg-hij'
);

// -----------------------------------------------------------------------------
// Test 3: Standalone event creation with nullable campaign_id (V2 Feature)
// -----------------------------------------------------------------------------
$standaloneEvent = $eventService->createEvent([
    'title'      => 'Standalone Community Circle',
    'category'   => 'listening_circle',
    'format'     => 'in_person',
    'venue_name' => 'Community Hall X',
    'start_time' => '2026-09-15 09:00:00',
    'end_time'   => '2026-09-15 12:00:00',
], $superAdminId);

assertEventTest(
    "3. Standalone event creation without campaign_id succeeds in Event-Centric V2",
    !empty($standaloneEvent['id']) && $standaloneEvent['campaign_id'] === null
);

// -----------------------------------------------------------------------------
// Test 4: Event creation failure on non-existent campaign_id
// -----------------------------------------------------------------------------
$caughtNonExistentCampaign = false;
try {
    $eventService->createEvent([
        'campaign_id' => 99999,
        'title'       => 'Non-existent Campaign Event',
        'category'    => 'workshop',
        'format'      => 'in_person',
        'venue_name'  => 'Hall X',
        'start_time'  => '2026-09-15 09:00:00',
        'end_time'    => '2026-09-15 12:00:00',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtNonExistentCampaign = isset($e->errors['campaign_id']);
}
assertEventTest(
    "4. Event creation failure on non-existent campaign_id",
    $caughtNonExistentCampaign
);

// -----------------------------------------------------------------------------
// Test 5: Event creation failure on soft-deleted campaign_id
// -----------------------------------------------------------------------------
$caughtDeletedCampaign = false;
try {
    $eventService->createEvent([
        'campaign_id' => $deletedCampaignId,
        'title'       => 'Soft Deleted Campaign Event',
        'category'    => 'workshop',
        'format'      => 'in_person',
        'venue_name'  => 'Hall X',
        'start_time'  => '2026-09-15 09:00:00',
        'end_time'    => '2026-09-15 12:00:00',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtDeletedCampaign = isset($e->errors['campaign_id']);
}
assertEventTest(
    "5. Event creation failure on soft-deleted campaign_id",
    $caughtDeletedCampaign
);

// -----------------------------------------------------------------------------
// Test 6: Event creation failure on missing title
// -----------------------------------------------------------------------------
$caughtMissingTitle = false;
try {
    $eventService->createEvent([
        'campaign_id' => $campaignAId,
        'title'       => '',
        'category'    => 'workshop',
        'format'      => 'in_person',
        'venue_name'  => 'Hall X',
        'start_time'  => '2026-09-15 09:00:00',
        'end_time'    => '2026-09-15 12:00:00',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtMissingTitle = isset($e->errors['title']);
}
assertEventTest(
    "6. Event creation failure on missing title",
    $caughtMissingTitle
);

// -----------------------------------------------------------------------------
// Test 7: Event creation failure on short title (<3 chars)
// -----------------------------------------------------------------------------
$caughtShortTitle = false;
try {
    $eventService->createEvent([
        'campaign_id' => $campaignAId,
        'title'       => 'AB',
        'category'    => 'workshop',
        'format'      => 'in_person',
        'venue_name'  => 'Hall X',
        'start_time'  => '2026-09-15 09:00:00',
        'end_time'    => '2026-09-15 12:00:00',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtShortTitle = isset($e->errors['title']);
}
assertEventTest(
    "7. Event creation failure on short title (<3 chars)",
    $caughtShortTitle
);

// -----------------------------------------------------------------------------
// Test 8: Event creation failure on missing start_time
// -----------------------------------------------------------------------------
$caughtMissingStartTime = false;
try {
    $eventService->createEvent([
        'campaign_id' => $campaignAId,
        'title'       => 'Valid Title Test',
        'category'    => 'workshop',
        'format'      => 'in_person',
        'venue_name'  => 'Hall X',
        'start_time'  => '',
        'end_time'    => '2026-09-15 12:00:00',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtMissingStartTime = isset($e->errors['start_time']);
}
assertEventTest(
    "8. Event creation failure on missing start_time",
    $caughtMissingStartTime
);

// -----------------------------------------------------------------------------
// Test 9: Event creation failure on missing end_time
// -----------------------------------------------------------------------------
$caughtMissingEndTime = false;
try {
    $eventService->createEvent([
        'campaign_id' => $campaignAId,
        'title'       => 'Valid Title Test',
        'category'    => 'workshop',
        'format'      => 'in_person',
        'venue_name'  => 'Hall X',
        'start_time'  => '2026-09-15 09:00:00',
        'end_time'    => '',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtMissingEndTime = isset($e->errors['end_time']);
}
assertEventTest(
    "9. Event creation failure on missing end_time",
    $caughtMissingEndTime
);

// -----------------------------------------------------------------------------
// Test 10: Event creation failure when end_time <= start_time
// -----------------------------------------------------------------------------
$caughtInvertedTime = false;
try {
    $eventService->createEvent([
        'campaign_id' => $campaignAId,
        'title'       => 'Valid Title Test',
        'category'    => 'workshop',
        'format'      => 'in_person',
        'venue_name'  => 'Hall X',
        'start_time'  => '2026-09-15 14:00:00',
        'end_time'    => '2026-09-15 13:00:00',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtInvertedTime = isset($e->errors['end_time']);
}
assertEventTest(
    "10. Event creation failure when end_time <= start_time",
    $caughtInvertedTime
);

// -----------------------------------------------------------------------------
// Test 11: Event creation failure when registration_deadline > start_time
// -----------------------------------------------------------------------------
$caughtLateDeadline = false;
try {
    $eventService->createEvent([
        'campaign_id'           => $campaignAId,
        'title'                 => 'Valid Title Test',
        'category'              => 'workshop',
        'format'                => 'in_person',
        'venue_name'            => 'Hall X',
        'start_time'            => '2026-09-15 10:00:00',
        'end_time'              => '2026-09-15 12:00:00',
        'registration_deadline' => '2026-09-15 11:00:00',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtLateDeadline = isset($e->errors['registration_deadline']);
}
assertEventTest(
    "11. Event creation failure when registration_deadline > start_time",
    $caughtLateDeadline
);

// -----------------------------------------------------------------------------
// Test 12: In-person event format requiring venue_name
// -----------------------------------------------------------------------------
$caughtMissingVenue = false;
try {
    $eventService->createEvent([
        'campaign_id' => $campaignAId,
        'title'       => 'In Person No Venue Event',
        'category'    => 'workshop',
        'format'      => 'in_person',
        'venue_name'  => '',
        'start_time'  => '2026-09-15 10:00:00',
        'end_time'    => '2026-09-15 12:00:00',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtMissingVenue = isset($e->errors['venue_name']);
}
assertEventTest(
    "12. In-person event format requires venue_name",
    $caughtMissingVenue
);

// -----------------------------------------------------------------------------
// Test 13: Online event format requiring valid online_meeting_url
// -----------------------------------------------------------------------------
$caughtMissingOnlineUrl = false;
try {
    $eventService->createEvent([
        'campaign_id'        => $campaignAId,
        'title'              => 'Online Missing URL Event',
        'category'           => 'listening_circle',
        'format'             => 'online',
        'online_meeting_url' => '',
        'start_time'         => '2026-09-16 10:00:00',
        'end_time'           => '2026-09-16 12:00:00',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtMissingOnlineUrl = isset($e->errors['online_meeting_url']);
}

$caughtInvalidOnlineUrl = false;
try {
    $eventService->createEvent([
        'campaign_id'        => $campaignAId,
        'title'              => 'Online Bad URL Event',
        'category'           => 'listening_circle',
        'format'             => 'online',
        'online_meeting_url' => 'not-a-valid-url',
        'start_time'         => '2026-09-16 10:00:00',
        'end_time'           => '2026-09-16 12:00:00',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtInvalidOnlineUrl = isset($e->errors['online_meeting_url']);
}

assertEventTest(
    "13. Online event format requires valid http/https online_meeting_url",
    $caughtMissingOnlineUrl && $caughtInvalidOnlineUrl
);

// -----------------------------------------------------------------------------
// Test 14: Hybrid event format accepting both venue_name and online_meeting_url
// -----------------------------------------------------------------------------
$hybridEvent = $eventService->createEvent([
    'campaign_id'        => $campaignAId,
    'title'              => 'Hybrid Community Gathering',
    'category'           => 'listening_circle',
    'format'             => 'hybrid',
    'venue_name'         => 'Community Center Hall 1',
    'online_meeting_url' => 'https://zoom.us/j/123456789',
    'start_time'         => '2026-09-17 18:00:00',
    'end_time'           => '2026-09-17 20:00:00',
], $superAdminId);

assertEventTest(
    "14. Hybrid event format successfully accepts both venue_name and online_meeting_url",
    !empty($hybridEvent['id']) &&
    $hybridEvent['format'] === 'hybrid' &&
    $hybridEvent['venue_name'] === 'Community Center Hall 1' &&
    $hybridEvent['online_meeting_url'] === 'https://zoom.us/j/123456789'
);

echo PHP_EOL . "2. Testing Capacity Validation Rule (Clarified)..." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 15: Capacity validation:
//  - Blank/empty capacity = 0 (unlimited)
//  - Explicit capacity 0 = unlimited
//  - Positive integer (>0) = capped capacity
//  - Negative, non-numeric, or invalid values = rejected
// -----------------------------------------------------------------------------
// 15a: Blank string capacity normalized to 0
$eventCapBlank = $eventService->createEvent([
    'campaign_id' => $campaignAId,
    'title'       => 'Capacity Blank Test Event',
    'category'    => 'workshop',
    'format'      => 'in_person',
    'venue_name'  => 'Room 101',
    'start_time'  => '2026-09-18 10:00:00',
    'end_time'    => '2026-09-18 12:00:00',
    'capacity'    => '',
], $superAdminId);

// 15b: Explicit 0 string normalized to 0
$eventCapZeroStr = $eventService->createEvent([
    'campaign_id' => $campaignAId,
    'title'       => 'Capacity Zero Str Test Event',
    'category'    => 'workshop',
    'format'      => 'in_person',
    'venue_name'  => 'Room 102',
    'start_time'  => '2026-09-18 10:00:00',
    'end_time'    => '2026-09-18 12:00:00',
    'capacity'    => '0',
], $superAdminId);

// 15c: Positive integer capped
$eventCapPositive = $eventService->createEvent([
    'campaign_id' => $campaignAId,
    'title'       => 'Capacity Positive Test Event',
    'category'    => 'workshop',
    'format'      => 'in_person',
    'venue_name'  => 'Room 103',
    'start_time'  => '2026-09-18 10:00:00',
    'end_time'    => '2026-09-18 12:00:00',
    'capacity'    => 75,
], $superAdminId);

// 15d: Negative integer rejected
$caughtNegativeCap = false;
try {
    $eventService->createEvent([
        'campaign_id' => $campaignAId,
        'title'       => 'Capacity Negative Test Event',
        'category'    => 'workshop',
        'format'      => 'in_person',
        'venue_name'  => 'Room 104',
        'start_time'  => '2026-09-18 10:00:00',
        'end_time'    => '2026-09-18 12:00:00',
        'capacity'    => -10,
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtNegativeCap = isset($e->errors['capacity']);
}

// 15e: Non-numeric rejected
$caughtNonNumericCap = false;
try {
    $eventService->createEvent([
        'campaign_id' => $campaignAId,
        'title'       => 'Capacity Non Numeric Test Event',
        'category'    => 'workshop',
        'format'      => 'in_person',
        'venue_name'  => 'Room 105',
        'start_time'  => '2026-09-18 10:00:00',
        'end_time'    => '2026-09-18 12:00:00',
        'capacity'    => 'unlimited',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtNonNumericCap = isset($e->errors['capacity']);
}

assertEventTest(
    "15. Capacity validation: blank/0=unlimited (0), positive int=capped, negative/non-numeric rejected",
    $eventCapBlank['capacity'] === 0 &&
    $eventCapZeroStr['capacity'] === 0 &&
    $eventCapPositive['capacity'] === 75 &&
    $caughtNegativeCap &&
    $caughtNonNumericCap
);

echo PHP_EOL . "3. Testing Slug Generation, Collision & Scoping..." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 16: Auto-generated slug from title within campaign
// -----------------------------------------------------------------------------
$eventAutoSlug = $eventService->createEvent([
    'campaign_id' => $campaignAId,
    'title'       => 'Peer Support & Crisis De-escalation!',
    'category'    => 'training',
    'format'      => 'in_person',
    'venue_name'  => 'Room 201',
    'start_time'  => '2026-09-22 10:00:00',
    'end_time'    => '2026-09-22 13:00:00',
], $superAdminId);

assertEventTest(
    "16. Auto-generated slug sanitized from title within campaign",
    $eventAutoSlug['slug'] === 'peer-support-crisis-de-escalation'
);

// -----------------------------------------------------------------------------
// Test 17: Custom slug validated and preserved
// -----------------------------------------------------------------------------
$eventCustomSlug = $eventService->createEvent([
    'campaign_id' => $campaignAId,
    'title'       => 'Annual Pledge Drive 2026',
    'slug'        => 'my-custom-pledge-slug',
    'category'    => 'pledge_drive',
    'format'      => 'in_person',
    'venue_name'  => 'Auditorium Quad',
    'start_time'  => '2026-09-23 10:00:00',
    'end_time'    => '2026-09-23 18:00:00',
], $superAdminId);

assertEventTest(
    "17. Custom slug validated and preserved",
    $eventCustomSlug['slug'] === 'my-custom-pledge-slug'
);

// -----------------------------------------------------------------------------
// Test 18: Duplicate slug rejected within same campaign
// -----------------------------------------------------------------------------
$caughtDuplicateSlugInSameCampaign = false;
try {
    $eventService->createEvent([
        'campaign_id' => $campaignAId,
        'title'       => 'Another Pledge Event Same Slug',
        'slug'        => 'my-custom-pledge-slug',
        'category'    => 'pledge_drive',
        'format'      => 'in_person',
        'venue_name'  => 'Auditorium Quad',
        'start_time'  => '2026-09-24 10:00:00',
        'end_time'    => '2026-09-24 18:00:00',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtDuplicateSlugInSameCampaign = isset($e->errors['slug']);
}

assertEventTest(
    "18. Duplicate slug rejected within same campaign",
    $caughtDuplicateSlugInSameCampaign
);

// -----------------------------------------------------------------------------
// Test 19: Duplicate slug rejected across different campaigns (Global Uniqueness in V2)
// -----------------------------------------------------------------------------
$caughtGlobalDuplicateSlug = false;
try {
    $eventService->createEvent([
        'campaign_id' => $campaignBId, // Campaign B!
        'title'       => 'Pledge Drive for Campus',
        'slug'        => 'my-custom-pledge-slug', // Same slug as in Campaign A!
        'category'    => 'pledge_drive',
        'format'      => 'in_person',
        'venue_name'  => 'Student Center',
        'start_time'  => '2026-10-10 10:00:00',
        'end_time'    => '2026-10-10 18:00:00',
    ], $coordinatorId);
} catch (InvalidArgumentException $e) {
    $caughtGlobalDuplicateSlug = isset($e->errors['slug']);
}

assertEventTest(
    "19. Duplicate slug rejected globally across different campaigns (V2 global unique slug requirement)",
    $caughtGlobalDuplicateSlug
);

echo PHP_EOL . "4. Testing Coordinator Verification & Assignment..." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 20: Valid coordinator assignment (active user with eligible role)
// -----------------------------------------------------------------------------
$eventWithCoord = $eventService->createEvent([
    'campaign_id'    => $campaignAId,
    'coordinator_id' => $coordinatorId,
    'title'          => 'Staff Facilitation Training',
    'category'       => 'training',
    'format'         => 'in_person',
    'venue_name'     => 'Training Room 3',
    'start_time'     => '2026-09-25 10:00:00',
    'end_time'       => '2026-09-25 13:00:00',
], $superAdminId);

assertEventTest(
    "20. Valid coordinator assignment (active user with eligible coordinator/staff role)",
    !empty($eventWithCoord['id']) &&
    $eventWithCoord['coordinator_id'] === $coordinatorId
);

// -----------------------------------------------------------------------------
// Test 21: Invalid coordinator rejected (inactive user, non-existent user, or ineligible role)
// -----------------------------------------------------------------------------
$caughtInactiveCoord = false;
try {
    $eventService->createEvent([
        'campaign_id'    => $campaignAId,
        'coordinator_id' => $inactiveUserId, // suspended user
        'title'          => 'Inactive Coordinator Event',
        'category'       => 'training',
        'format'         => 'in_person',
        'venue_name'     => 'Training Room 3',
        'start_time'     => '2026-09-25 10:00:00',
        'end_time'       => '2026-09-25 13:00:00',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtInactiveCoord = isset($e->errors['coordinator_id']);
}

$caughtViewerCoord = false;
try {
    $eventService->createEvent([
        'campaign_id'    => $campaignAId,
        'coordinator_id' => $viewerId, // viewer is not in eligible roles
        'title'          => 'Viewer Coordinator Event',
        'category'       => 'training',
        'format'         => 'in_person',
        'venue_name'     => 'Training Room 3',
        'start_time'     => '2026-09-25 10:00:00',
        'end_time'       => '2026-09-25 13:00:00',
    ], $superAdminId);
} catch (InvalidArgumentException $e) {
    $caughtViewerCoord = isset($e->errors['coordinator_id']);
}

assertEventTest(
    "21. Invalid coordinator rejected (inactive or ineligible role)",
    $caughtInactiveCoord && $caughtViewerCoord
);

echo PHP_EOL . "5. Testing Updates, Collisions & Lifecycle..." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 22: Event update with valid data
// -----------------------------------------------------------------------------
$updatedEvent = $eventService->updateEvent($event1['id'], [
    'campaign_id' => $campaignAId,
    'title'       => 'Active Listening Masterclass (Updated)',
    'slug'        => $event1['slug'], // keep same slug
    'category'    => 'workshop',
    'format'      => 'in_person',
    'venue_name'  => 'Main Campus Grand Auditorium',
    'start_time'  => '2026-09-15 09:30:00',
    'end_time'    => '2026-09-15 13:30:00',
    'capacity'    => 100,
    'status'      => 'published',
], $superAdminId);

assertEventTest(
    "22. Event update with valid data succeeds",
    $updatedEvent['title'] === 'Active Listening Masterclass (Updated)' &&
    $updatedEvent['venue_name'] === 'Main Campus Grand Auditorium' &&
    $updatedEvent['capacity'] === 100
);

// -----------------------------------------------------------------------------
// Test 23: Event update slug collision check
// -----------------------------------------------------------------------------
$caughtUpdateCollision = false;
try {
    // Attempt to rename event2 slug to event1's slug in same campaign
    $eventService->updateEvent($event2['id'], [
        'campaign_id' => $campaignAId,
        'title'       => $event2['title'],
        'slug'        => $event1['slug'],
        'category'    => $event2['category'],
        'format'      => $event2['format'],
        'venue_name'  => $event2['venue_name'],
        'start_time'  => $event2['start_time'],
        'end_time'    => $event2['end_time'],
        'status'      => $event2['status'],
    ], $coordinatorId);
} catch (InvalidArgumentException $e) {
    $caughtUpdateCollision = isset($e->errors['slug']);
}

assertEventTest(
    "23. Event update slug collision check prevents taking another event's slug in same campaign",
    $caughtUpdateCollision
);

// -----------------------------------------------------------------------------
// Test 24: Event lifecycle transitions (draft -> published -> ongoing -> completed -> cancelled)
// -----------------------------------------------------------------------------
$lifecycleEvent = $eventService->createEvent([
    'campaign_id' => $campaignAId,
    'title'       => 'Lifecycle Transition Test Session',
    'category'    => 'workshop',
    'format'      => 'in_person',
    'venue_name'  => 'Room L1',
    'start_time'  => '2026-09-28 10:00:00',
    'end_time'    => '2026-09-28 12:00:00',
    'status'      => 'draft',
], $superAdminId);

$st1 = $eventService->updateStatus($lifecycleEvent['id'], 'published', $superAdminId);
$ev1 = $eventRepo->findById($lifecycleEvent['id']);
$st2 = $eventService->updateStatus($lifecycleEvent['id'], 'ongoing', $superAdminId);
$ev2 = $eventRepo->findById($lifecycleEvent['id']);
$st3 = $eventService->updateStatus($lifecycleEvent['id'], 'completed', $superAdminId);
$ev3 = $eventRepo->findById($lifecycleEvent['id']);
$st4 = $eventService->updateStatus($lifecycleEvent['id'], 'cancelled', $superAdminId);
$ev4 = $eventRepo->findById($lifecycleEvent['id']);

assertEventTest(
    "24. Event lifecycle transitions (draft -> published -> ongoing -> completed -> cancelled)",
    $st1 && $ev1['status'] === 'published' &&
    $st2 && $ev2['status'] === 'ongoing' &&
    $st3 && $ev3['status'] === 'completed' &&
    $st4 && $ev4['status'] === 'cancelled'
);

echo PHP_EOL . "6. Testing Soft Delete & Restoration..." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 25: Event soft-delete sets deleted_at and excludes from active roster
// -----------------------------------------------------------------------------
$deleteSuccess = $eventService->softDeleteEvent($lifecycleEvent['id'], $superAdminId);
$activeList = $eventRepo->all(false, ['campaign_id' => $campaignAId]);
$activeIds = array_column($activeList, 'id');
$rawDeletedEvent = $eventRepo->findById($lifecycleEvent['id'], true);

assertEventTest(
    "25. Event soft-delete sets deleted_at and excludes event from active roster",
    $deleteSuccess &&
    !in_array($lifecycleEvent['id'], $activeIds, true) &&
    !empty($rawDeletedEvent['deleted_at'])
);

// -----------------------------------------------------------------------------
// Test 26: Event restore clears deleted_at and returns to active roster
// -----------------------------------------------------------------------------
$restoreSuccess = $eventService->restoreEvent($lifecycleEvent['id'], $superAdminId);
$restoredActiveList = $eventRepo->all(false, ['campaign_id' => $campaignAId]);
$restoredIds = array_column($restoredActiveList, 'id');
$rawRestoredEvent = $eventRepo->findById($lifecycleEvent['id'], false);

assertEventTest(
    "26. Event restore clears deleted_at and returns event to active roster",
    $restoreSuccess &&
    in_array($lifecycleEvent['id'], $restoredIds, true) &&
    $rawRestoredEvent !== null &&
    empty($rawRestoredEvent['deleted_at'])
);

echo PHP_EOL . "7. Testing RBAC Privilege Gates & Middleware Enforcement..." . PHP_EOL;

// Helper to simulate request through router pipeline
function simulateEventRequest(string $method, string $uri, ?int $authUserId, ?string $authUserRole, array $postData = [], bool $includeCsrf = true): Response
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

// -----------------------------------------------------------------------------
// Test 27: RBAC: super_admin can create, update, delete, restore
// -----------------------------------------------------------------------------
$saCreateForm = simulateEventRequest('GET', '/admin/events/create', $superAdminId, 'super_admin');
$saDelete = simulateEventRequest('POST', "/admin/events/{$lifecycleEvent['id']}/delete", $superAdminId, 'super_admin');
$saRestore = simulateEventRequest('POST', "/admin/events/{$lifecycleEvent['id']}/restore", $superAdminId, 'super_admin');

assertEventTest(
    "27. RBAC: super_admin can access create form (200), delete (302 redirect), and restore (302 redirect)",
    $saCreateForm->getStatusCode() === 200 &&
    $saDelete->getStatusCode() === 302 &&
    $saRestore->getStatusCode() === 302
);

// -----------------------------------------------------------------------------
// Test 28: RBAC: coordinator can create and update, but NOT delete or restore
// -----------------------------------------------------------------------------
$coordCreateForm = simulateEventRequest('GET', '/admin/events/create', $coordinatorId, 'coordinator');
$coordDelete = simulateEventRequest('POST', "/admin/events/{$lifecycleEvent['id']}/delete", $coordinatorId, 'coordinator');
$coordRestore = simulateEventRequest('POST', "/admin/events/{$lifecycleEvent['id']}/restore", $coordinatorId, 'coordinator');

assertEventTest(
    "28. RBAC: coordinator can access create form (200), but delete (403) and restore (403) are denied",
    $coordCreateForm->getStatusCode() === 200 &&
    $coordDelete->getStatusCode() === 403 &&
    $coordRestore->getStatusCode() === 403
);

// -----------------------------------------------------------------------------
// Test 29: RBAC: staff and viewer cannot create or update (read-only)
// -----------------------------------------------------------------------------
$viewerRoster = simulateEventRequest('GET', '/admin/events', $viewerId, 'viewer');
$viewerCreate = simulateEventRequest('POST', '/admin/events', $viewerId, 'viewer', ['title' => 'Viewer Hack']);
$viewerEdit = simulateEventRequest('POST', "/admin/events/{$lifecycleEvent['id']}", $viewerId, 'viewer', ['title' => 'Viewer Hack Edit']);

$staffRoster = simulateEventRequest('GET', '/admin/events', $staffId, 'staff');
$staffCreate = simulateEventRequest('POST', '/admin/events', $staffId, 'staff', ['title' => 'Staff Hack']);
$staffEdit = simulateEventRequest('POST', "/admin/events/{$lifecycleEvent['id']}", $staffId, 'staff', ['title' => 'Staff Hack Edit']);

assertEventTest(
    "29. RBAC: staff and viewer have read access (200) but cannot create (403) or update (403)",
    $viewerRoster->getStatusCode() === 200 &&
    $viewerCreate->getStatusCode() === 403 &&
    $viewerEdit->getStatusCode() === 403 &&
    $staffRoster->getStatusCode() === 200 &&
    $staffCreate->getStatusCode() === 403 &&
    $staffEdit->getStatusCode() === 403
);

echo PHP_EOL . "8. Testing Forensic Audit Logging..." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 30: Audit logging: audit logs created for create, update, status change, delete, and restore
// -----------------------------------------------------------------------------
$eventAuditLogs = Database::fetchAll("SELECT * FROM audit_logs WHERE entity_type = 'event' AND entity_id = :id ORDER BY id ASC", [':id' => $lifecycleEvent['id']]);
$recordedEventActions = array_column($eventAuditLogs, 'action');

assertEventTest(
    "30. Audit logging: audit logs created for create, update, status change, delete, and restore",
    in_array('event.create', $recordedEventActions, true) &&
    in_array('event.status_change', $recordedEventActions, true) &&
    in_array('event.delete', $recordedEventActions, true) &&
    in_array('event.restore', $recordedEventActions, true)
);

// -----------------------------------------------------------------------------
// 9. Verifying Database Test Hygiene (Zero Lingering Test Rows)
// -----------------------------------------------------------------------------
echo PHP_EOL . "9. Verifying Database Test Hygiene (Zero Lingering Test Rows)..." . PHP_EOL;

// Clean up all test records
Database::execute("DELETE FROM events");
Database::execute("DELETE FROM campaigns");
Database::execute("DELETE FROM audit_logs");
Database::execute("DELETE FROM users");

$eventRowCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM events")['total'] ?? -1);
$campaignRowCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM campaigns")['total'] ?? -1);
$auditRowCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM audit_logs")['total'] ?? -1);
$userRowCount = (int) (Database::fetch("SELECT COUNT(*) AS total FROM users")['total'] ?? -1);

assertEventTest(
    "Database 'events' table retains exactly 0 business/test rows",
    $eventRowCount === 0,
    "Expected 0 rows, found {$eventRowCount}"
);

assertEventTest(
    "Database 'campaigns' table retains exactly 0 business/test rows",
    $campaignRowCount === 0,
    "Expected 0 rows, found {$campaignRowCount}"
);

assertEventTest(
    "Database 'audit_logs' table retains exactly 0 business/test rows",
    $auditRowCount === 0,
    "Expected 0 rows, found {$auditRowCount}"
);

assertEventTest(
    "Database 'users' table retains exactly 0 business/test rows",
    $userRowCount === 0,
    "Expected 0 rows, found {$userRowCount}"
);

echo PHP_EOL . "----------------------------------------------------" . PHP_EOL;
echo "Phase 1C Tests Passed: {$passedTests} / {$totalTests}" . PHP_EOL;

if ($failedTests > 0) {
    echo "{$red}WARNING: {$failedTests} tests failed!{$reset}" . PHP_EOL;
    exit(1);
} else {
    echo "{$green}All Phase 1C Event Management tests passed successfully!{$reset}" . PHP_EOL;
    exit(0);
}
