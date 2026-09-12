<?php

declare(strict_types=1);

/**
 * Phase 1H — Public Campaign, Event Discovery & Public Event Registration Test Suite
 * Run via: php tests/test_phase1h_public_registration.php
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Controllers\Admin\RegistrationController;
use App\Controllers\HomeController;
use App\Controllers\Public\PublicCampaignController;
use App\Controllers\Public\PublicEventController;
use App\Controllers\Public\PublicRegistrationController;
use App\Controllers\Public\RegistrationPassController;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
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

function assertPhase1HTest(string $description, bool $condition, string $details = ''): void
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

function makeReq(string $uri, string $method = 'GET', array $query = [], array $post = [], array $server = []): Request
{
    $serverVars = array_merge([
        'REQUEST_METHOD' => strtoupper($method),
        'REQUEST_URI'    => $uri,
        'REMOTE_ADDR'    => '127.0.0.1',
    ], $server);

    return new Request($query, $post, [], $serverVars);
}

Env::load(APP_ROOT . '/.env');
Config::load(APP_ROOT . '/config');

echo "=== LC-SPC Phase 1H Public Discovery & Registration Verification Suite ===" . PHP_EOL . PHP_EOL;

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
        venue_name VARCHAR(191) NULL,
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
        created_by INTEGER NULL,
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
$campaignRepo = new CampaignRepository();
$eventRepo = new EventRepository();
$participantRepo = new ParticipantRepository();
$registrationRepo = new RegistrationRepository();
$auditRepo = new AuditLogRepository();
$auditService = new AuditService($auditRepo, $userRepo);
$participantService = new ParticipantService($participantRepo, $auditService);
$registrationService = new RegistrationService($registrationRepo, $eventRepo, $participantRepo, $participantService, $auditService);

// Seed an administrator user for testing admin actions vs public anonymous actions
$adminId = $userRepo->create([
    'name'          => 'Coordinator Admin',
    'email'         => 'admin.coord@example.com',
    'password_hash' => password_hash('Pass12345!', PASSWORD_BCRYPT),
    'role'          => RoleService::ROLE_COORDINATOR,
    'status'        => 'active',
]);

// Clear rate limits directory before test run
$testRateLimitDir = APP_ROOT . '/storage/cache/rate_limits_test';
if (is_dir($testRateLimitDir)) {
    array_map('unlink', glob("{$testRateLimitDir}/*.*"));
} else {
    @mkdir($testRateLimitDir, 0755, true);
}

// =============================================================================
// 1. ACTOR ID GUARDRAIL TESTS (A through G)
// =============================================================================
echo "--- 1. Testing Actor ID Normalization Guardrails ---" . PHP_EOL;

// Test A: actorId = NULL -> actor_id = NULL, actor_type = anonymous
$logIdA = $auditService->log('test.action_null', 'system', 101, ['detail' => 'null actor'], null, 'admin', '127.0.0.1', 'PHPTest');
$rowA = $testPdo->query("SELECT * FROM audit_logs WHERE id = {$logIdA}")->fetch(PDO::FETCH_ASSOC);
assertPhase1HTest("Guardrail A: actorId = NULL stores actor_id = NULL and actor_type = 'anonymous'", 
    $rowA['actor_id'] === null && $rowA['actor_type'] === 'anonymous');

// Test B: actorId = 0 -> actor_id = NULL, actor_type = anonymous
$logIdB = $auditService->log('test.action_zero', 'system', 102, ['detail' => 'zero actor'], 0, 'admin', '127.0.0.1', 'PHPTest');
$rowB = $testPdo->query("SELECT * FROM audit_logs WHERE id = {$logIdB}")->fetch(PDO::FETCH_ASSOC);
assertPhase1HTest("Guardrail B: actorId = 0 normalizes to actor_id = NULL and actor_type = 'anonymous'", 
    $rowB['actor_id'] === null && $rowB['actor_type'] === 'anonymous');

// Test C: actorId = -1 -> actor_id = NULL, actor_type = anonymous
$logIdC = $auditService->log('test.action_negative', 'system', 103, ['detail' => 'negative actor'], -1, 'admin', '127.0.0.1', 'PHPTest');
$rowC = $testPdo->query("SELECT * FROM audit_logs WHERE id = {$logIdC}")->fetch(PDO::FETCH_ASSOC);
assertPhase1HTest("Guardrail C: actorId = -1 normalizes to actor_id = NULL and actor_type = 'anonymous'", 
    $rowC['actor_id'] === null && $rowC['actor_type'] === 'anonymous');

// Test D: actorId = valid positive user ID -> actor_id remains valid user ID, actor_type remains admin
$logIdD = $auditService->log('test.action_positive', 'system', 104, ['detail' => 'positive admin'], $adminId, 'admin', '127.0.0.1', 'PHPTest');
$rowD = $testPdo->query("SELECT * FROM audit_logs WHERE id = {$logIdD}")->fetch(PDO::FETCH_ASSOC);
assertPhase1HTest("Guardrail D: actorId = {$adminId} preserves positive ID and actor_type = 'admin'", 
    (int) $rowD['actor_id'] === $adminId && $rowD['actor_type'] === 'admin');

// Test G: AuditLogRepository defense-in-depth: direct call with actor_id = 0 produces NULL
$logIdDirect = $auditRepo->create([
    'actor_id'    => 0,
    'actor_type'  => 'admin',
    'action'      => 'direct.test',
    'entity_type' => 'test',
    'entity_id'   => 1,
    'ip_address'  => '127.0.0.1',
]);
$rowDirect = $testPdo->query("SELECT * FROM audit_logs WHERE id = {$logIdDirect}")->fetch(PDO::FETCH_ASSOC);
assertPhase1HTest("Guardrail G: AuditLogRepository::create defense-in-depth ensures actor_id = NULL for non-positive inputs", 
    $rowDirect['actor_id'] === null && $rowDirect['actor_type'] === 'anonymous');

// =============================================================================
// 2. SEEDING TEST DATA FOR PUBLIC DISCOVERY
// =============================================================================
echo PHP_EOL . "--- 2. Seeding Public Discovery Data ---" . PHP_EOL;

// Active Campaign
$campActiveId = $campaignRepo->create([
    'title'       => 'Safe Spaces 2026',
    'slug'        => 'safe-spaces-2026',
    'theme'       => 'Empathetic Listening on Campus',
    'description' => 'Fostering peer listening communities across regional colleges.',
    'start_date'  => date('Y-m-d', strtotime('-10 days')),
    'end_date'    => date('Y-m-d', strtotime('+30 days')),
    'status'      => 'active',
    'created_by'  => $adminId,
]);

// Completed Campaign
$campCompletedId = $campaignRepo->create([
    'title'       => 'Listening Circle 2025',
    'slug'        => 'listening-circle-2025',
    'theme'       => 'Breaking the Silence',
    'description' => 'Past successful annual campaign initiative.',
    'start_date'  => date('Y-m-d', strtotime('-400 days')),
    'end_date'    => date('Y-m-d', strtotime('-360 days')),
    'status'      => 'completed',
    'created_by'  => $adminId,
]);

// Draft Campaign (Should remain hidden from public directory)
$campDraftId = $campaignRepo->create([
    'title'       => 'Internal Draft Drive',
    'slug'        => 'internal-draft-drive',
    'theme'       => 'Under review',
    'description' => 'Not yet approved for public viewing.',
    'start_date'  => date('Y-m-d', strtotime('+10 days')),
    'end_date'    => date('Y-m-d', strtotime('+40 days')),
    'status'      => 'draft',
    'created_by'  => $adminId,
]);

// Event 1: Open Published Workshop under Active Campaign
$eventOpenId = $eventRepo->create([
    'campaign_id'           => $campActiveId,
    'title'                 => 'Peer Listening Fundamentals',
    'slug'                  => 'peer-listening-fundamentals',
    'category'              => 'workshop',
    'format'                => 'in_person',
    'venue_name'            => 'Auditorium Hall B',
    'venue_address'         => '12 University Road, New Delhi',
    'start_time'            => date('Y-m-d H:i:s', strtotime('+5 days 10:00:00')),
    'end_time'              => date('Y-m-d H:i:s', strtotime('+5 days 13:00:00')),
    'capacity'              => 50,
    'requires_approval'     => 0,
    'status'                => 'published',
    'coordinator_id'        => $adminId,
]);

// Event 2: Online Webinar under Active Campaign
$eventOnlineId = $eventRepo->create([
    'campaign_id'           => $campActiveId,
    'title'                 => 'Digital Safe Spaces Webinar',
    'slug'                  => 'digital-safe-spaces-webinar',
    'category'              => 'webinar',
    'format'                => 'online',
    'online_meeting_url'    => 'https://meet.example.com/spc-2026',
    'start_time'            => date('Y-m-d H:i:s', strtotime('+7 days 17:00:00')),
    'end_time'              => date('Y-m-d H:i:s', strtotime('+7 days 18:30:00')),
    'capacity'              => 0, // unlimited
    'requires_approval'     => 0,
    'status'                => 'published',
    'coordinator_id'        => $adminId,
]);

// Event 3: Requires Approval Event
$eventApprovalId = $eventRepo->create([
    'campaign_id'           => $campActiveId,
    'title'                 => 'Advanced Listening Circles',
    'slug'                  => 'advanced-listening-circles',
    'category'              => 'listening_circle',
    'format'                => 'in_person',
    'venue_name'            => 'Seminar Room 3',
    'start_time'            => date('Y-m-d H:i:s', strtotime('+12 days 14:00:00')),
    'end_time'              => date('Y-m-d H:i:s', strtotime('+12 days 17:00:00')),
    'capacity'              => 20,
    'requires_approval'     => 1,
    'status'                => 'published',
    'coordinator_id'        => $adminId,
]);

// Event 4: Full Capacity Event (capacity = 1, already 1 confirmed)
$eventFullId = $eventRepo->create([
    'campaign_id'           => $campActiveId,
    'title'                 => 'Capped Workshop',
    'slug'                  => 'capped-workshop',
    'category'              => 'workshop',
    'format'                => 'in_person',
    'venue_name'            => 'Studio 1',
    'start_time'            => date('Y-m-d H:i:s', strtotime('+15 days 11:00:00')),
    'end_time'              => date('Y-m-d H:i:s', strtotime('+15 days 13:00:00')),
    'capacity'              => 1,
    'requires_approval'     => 0,
    'status'                => 'published',
    'coordinator_id'        => $adminId,
]);

// Pre-fill 1 confirmed seat for Event 4
$existingPartId = $participantRepo->create([
    'full_name'            => 'Pre Registered User',
    'email'                => 'prereg@example.com',
    'category'             => 'community',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
    'status'               => 'active',
]);
$registrationService->registerParticipant($eventFullId, $existingPartId, null, $adminId);

// Event 5: Past Deadline Event
$eventPastDeadlineId = $eventRepo->create([
    'campaign_id'           => $campActiveId,
    'title'                 => 'Closed Registration Event',
    'slug'                  => 'closed-registration-event',
    'category'              => 'seminar',
    'format'                => 'in_person',
    'start_time'            => date('Y-m-d H:i:s', strtotime('+20 days 10:00:00')),
    'end_time'              => date('Y-m-d H:i:s', strtotime('+20 days 12:00:00')),
    'registration_deadline' => date('Y-m-d H:i:s', strtotime('-1 day')),
    'capacity'              => 50,
    'status'                => 'published',
    'coordinator_id'        => $adminId,
]);

// Event 6: Draft Event (Should be hidden from public discovery)
$eventDraftId = $eventRepo->create([
    'campaign_id'           => $campActiveId,
    'title'                 => 'Internal Draft Event',
    'slug'                  => 'internal-draft-event',
    'category'              => 'workshop',
    'format'                => 'in_person',
    'start_time'            => date('Y-m-d H:i:s', strtotime('+25 days 10:00:00')),
    'end_time'              => date('Y-m-d H:i:s', strtotime('+25 days 12:00:00')),
    'capacity'              => 50,
    'status'                => 'draft',
    'coordinator_id'        => $adminId,
]);

// Build Router with all routes
$router = new Router();
require APP_ROOT . '/app/routes.php';

// =============================================================================
// 3. PUBLIC DISCOVERY ROUTE TESTS
// =============================================================================
echo PHP_EOL . "--- 3. Testing Public Campaign & Event Discovery ---" . PHP_EOL;

// GET /
$reqHome = makeReq('/');
$resHome = $router->dispatch($reqHome);
assertPhase1HTest("GET / returns HTTP 200", $resHome->getStatusCode() === 200);
assertPhase1HTest("GET / includes active campaign and upcoming events in landing markup", 
    str_contains($resHome->getContent(), 'Safe Spaces 2026') && str_contains($resHome->getContent(), 'Peer Listening Fundamentals'));

// GET /campaigns
$reqCampIndex = makeReq('/campaigns');
$resCampIndex = $router->dispatch($reqCampIndex);
assertPhase1HTest("GET /campaigns returns HTTP 200", $resCampIndex->getStatusCode() === 200);
assertPhase1HTest("GET /campaigns displays active and archived campaigns", 
    str_contains($resCampIndex->getContent(), 'Safe Spaces 2026') && str_contains($resCampIndex->getContent(), 'Listening Circle 2025'));
assertPhase1HTest("GET /campaigns strictly hides draft campaigns", 
    !str_contains($resCampIndex->getContent(), 'Internal Draft Drive'));

// GET /campaigns/{slug}
$reqCampShow = makeReq('/campaigns/safe-spaces-2026');
$resCampShow = $router->dispatch($reqCampShow);
assertPhase1HTest("GET /campaigns/safe-spaces-2026 returns HTTP 200 with campaign profile", 
    $resCampShow->getStatusCode() === 200 && str_contains($resCampShow->getContent(), 'Empathetic Listening on Campus'));
assertPhase1HTest("GET /campaigns/safe-spaces-2026 lists its published upcoming events", 
    str_contains($resCampShow->getContent(), 'Peer Listening Fundamentals'));

// GET /campaigns/{draft_slug} returns 404
$resCampDraft = $router->dispatch(makeReq('/campaigns/internal-draft-drive'));
assertPhase1HTest("GET /campaigns/internal-draft-drive returns HTTP 404 (draft visibility gate)", $resCampDraft->getStatusCode() === 404);

// GET /campaigns/nonexistent returns 404
$resCamp404 = $router->dispatch(makeReq('/campaigns/nonexistent-slug-xyz'));
assertPhase1HTest("GET /campaigns/nonexistent returns HTTP 404", $resCamp404->getStatusCode() === 404);

// GET /events
$reqEventsIndex = makeReq('/events');
$resEventsIndex = $router->dispatch($reqEventsIndex);
assertPhase1HTest("GET /events returns HTTP 200", $resEventsIndex->getStatusCode() === 200);
assertPhase1HTest("GET /events lists published events", 
    str_contains($resEventsIndex->getContent(), 'Peer Listening Fundamentals') && str_contains($resEventsIndex->getContent(), 'Digital Safe Spaces Webinar'));
assertPhase1HTest("GET /events strictly hides draft events", 
    !str_contains($resEventsIndex->getContent(), 'Internal Draft Event'));

// GET /events with filter category=webinar
$resFilterCat = $router->dispatch(makeReq('/events', 'GET', ['category' => 'webinar']));
assertPhase1HTest("GET /events?category=webinar filters correctly", 
    str_contains($resFilterCat->getContent(), 'Digital Safe Spaces Webinar') && !str_contains($resFilterCat->getContent(), 'Peer Listening Fundamentals'));

// GET /events/{campaign_slug}/{event_slug}
$resEventShow = $router->dispatch(makeReq('/events/safe-spaces-2026/peer-listening-fundamentals'));
assertPhase1HTest("GET /events/safe-spaces-2026/peer-listening-fundamentals returns HTTP 200", $resEventShow->getStatusCode() === 200);
assertPhase1HTest("GET /events/... displays user-friendly availability status 'Registration Open'", 
    str_contains($resEventShow->getContent(), 'Registration Open'));

// GET /events/... on full event displays 'Waitlist'
$resEventFullShow = $router->dispatch(makeReq('/events/safe-spaces-2026/capped-workshop'));
assertPhase1HTest("GET /events/... on capped event displays 'Waitlist' status", 
    str_contains($resEventFullShow->getContent(), 'Waitlist'));

// GET /events/{campaign_slug}/{draft_event_slug} returns 404
$resEventDraft = $router->dispatch(makeReq('/events/safe-spaces-2026/internal-draft-event'));
assertPhase1HTest("GET /events/... on draft event returns HTTP 404", $resEventDraft->getStatusCode() === 404);

// =============================================================================
// 4. PUBLIC REGISTRATION FORM & SUBMISSION TESTS
// =============================================================================
echo PHP_EOL . "--- 4. Testing Public Registration Execution & Field Validation ---" . PHP_EOL;

// Form view
$resForm = $router->dispatch(makeReq('/events/safe-spaces-2026/peer-listening-fundamentals/register'));
assertPhase1HTest("GET /events/.../register returns HTTP 200 registration form", $resForm->getStatusCode() === 200);

// Closed event redirects
$resPastForm = $router->dispatch(makeReq('/events/safe-spaces-2026/closed-registration-event/register'));
assertPhase1HTest("GET /events/.../register on closed event redirects to detail view", $resPastForm->getStatusCode() === 302);

// CSRF token generation
Session::start();
$csrfToken = Security::csrfToken();

// Public registration controller instance for rate limit isolation
$pubRegController = new PublicRegistrationController(
    $eventRepo,
    $registrationRepo,
    $participantService,
    $registrationService,
    $auditService,
    $testRateLimitDir
);

// SUBMISSION 1: Required fields only (NO EMAIL, NO PHONE)
$postDataNoContact = [
    '_csrf_token'       => $csrfToken,
    'full_name'         => 'Ananya Sen',
    'category'          => 'student',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
    'website'           => '', // empty honeypot
];
$reqReg1 = makeReq('/events/safe-spaces-2026/peer-listening-fundamentals/register', 'POST', [], $postDataNoContact, ['REMOTE_ADDR' => '10.0.0.1']);
$resReg1 = $pubRegController->register($reqReg1, ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'peer-listening-fundamentals']);
assertPhase1HTest("Public registration without email/phone succeeds with HTTP 302 redirect", $resReg1->getStatusCode() === 302);

// Extract registration code from redirect location
$redirectUrl1 = $resReg1->getHeader('Location');
preg_match('#/registration/confirmed/(REG-[0-9]{2}-[23456789ABCDEFGHJKMNPQRSTVWXYZ]{5})#', $redirectUrl1, $mCode1);
$code1 = $mCode1[1] ?? '';
assertPhase1HTest("Public registration assigns valid Crockford Base32 registration code ({$code1})", !empty($code1));

// Verify database participant record has NULL email and NULL phone
$savedPart1 = $testPdo->query("SELECT * FROM participants WHERE full_name = 'Ananya Sen'")->fetch(PDO::FETCH_ASSOC);
assertPhase1HTest("Participant record saved with email = NULL and phone = NULL", 
    $savedPart1 !== false && $savedPart1['email'] === null && $savedPart1['phone'] === null);
assertPhase1HTest("Participant record has immutable server-side consent timestamps", 
    !empty($savedPart1['agreed_guidelines_at']) && !empty($savedPart1['privacy_consent_at']));

// Verify registration record
$savedReg1 = $registrationRepo->findByCode($code1);
assertPhase1HTest("Registration record has status = 'confirmed'", 
    $savedReg1 !== null && $savedReg1['status'] === 'confirmed');

// Verify Guardrail E: Public registration audit log record has actor_id = NULL and actor_type = anonymous
$auditRow1 = $testPdo->query("SELECT * FROM audit_logs WHERE action = 'registration.create' AND entity_id = {$savedReg1['id']}")->fetch(PDO::FETCH_ASSOC);
assertPhase1HTest("Guardrail E: Public registration creates audit record with actor_id = NULL and actor_type = 'anonymous' (NO FK VIOLATION)", 
    $auditRow1 !== false && $auditRow1['actor_id'] === null && $auditRow1['actor_type'] === 'anonymous');

// Guardrail F: Existing admin registration maintains valid admin actor ID
$adminParticipant = $participantService->createParticipant([
    'full_name'         => 'Admin Invited Guest',
    'category'          => 'student',
    'agreed_guidelines' => 1,
    'privacy_consent'   => 1,
], $adminId);
$adminRegResult = $registrationService->registerParticipant($eventOpenId, (int) $adminParticipant['id'], 'Invited by admin', $adminId);
$adminRegAudit = $testPdo->query("SELECT * FROM audit_logs WHERE action = 'registration.create' AND entity_id = {$adminRegResult['registration']['id']}")->fetch(PDO::FETCH_ASSOC);
assertPhase1HTest("Guardrail F: Existing admin registration preserves valid admin actor ID and admin actor_type", 
    $adminRegAudit !== false && (int) $adminRegAudit['actor_id'] === $adminId && $adminRegAudit['actor_type'] === 'admin');

// SUBMISSION 2: With optional email and phone
$postDataWithContact = [
    '_csrf_token'       => $csrfToken,
    'full_name'         => 'Rohan Verma',
    'category'          => 'professional',
    'email'             => 'rohan.verma@example.com',
    'phone'             => '+91 9876543210',
    'organization_name' => 'City Hospital',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
    'website'           => '',
];
$reqReg2 = makeReq('/events/safe-spaces-2026/peer-listening-fundamentals/register', 'POST', [], $postDataWithContact, ['REMOTE_ADDR' => '10.0.0.2']);
$resReg2 = $pubRegController->register($reqReg2, ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'peer-listening-fundamentals']);
assertPhase1HTest("Public registration with optional email and phone succeeds with HTTP 302", $resReg2->getStatusCode() === 302);
$savedPart2 = $testPdo->query("SELECT * FROM participants WHERE email = 'rohan.verma@example.com'")->fetch(PDO::FETCH_ASSOC);
assertPhase1HTest("Participant record saved with email and phone values", 
    $savedPart2 !== false && $savedPart2['phone'] === '+91 9876543210' && $savedPart2['organization_name'] === 'City Hospital');

// =============================================================================
// 5. INPUT VALIDATION EDGE CASES
// =============================================================================
echo PHP_EOL . "--- 5. Testing Validation & Edge Case Rejections ---" . PHP_EOL;

// Missing full name
$resFailName = $pubRegController->register(makeReq('/events/...', 'POST', [], [
    'full_name'         => '',
    'category'          => 'student',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
], ['REMOTE_ADDR' => '10.0.0.3']), ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'peer-listening-fundamentals']);
$flashErrors = Session::getFlash('errors', []);
assertPhase1HTest("Missing full_name is rejected and redirects back", 
    $resFailName->getStatusCode() === 302 && isset($flashErrors['full_name']));

// Missing guidelines agreement
$resFailGuide = $pubRegController->register(makeReq('/events/...', 'POST', [], [
    'full_name'         => 'Test User',
    'category'          => 'student',
    'agreed_guidelines' => '0',
    'privacy_consent'   => '1',
], ['REMOTE_ADDR' => '10.0.0.4']), ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'peer-listening-fundamentals']);
$flashErrors = Session::getFlash('errors', []);
assertPhase1HTest("Missing agreed_guidelines is rejected", 
    $resFailGuide->getStatusCode() === 302 && isset($flashErrors['agreed_guidelines']));

// Missing privacy consent
$resFailPriv = $pubRegController->register(makeReq('/events/...', 'POST', [], [
    'full_name'         => 'Test User',
    'category'          => 'student',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '0',
], ['REMOTE_ADDR' => '10.0.0.5']), ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'peer-listening-fundamentals']);
$flashErrors = Session::getFlash('errors', []);
assertPhase1HTest("Missing privacy_consent is rejected", 
    $resFailPriv->getStatusCode() === 302 && isset($flashErrors['privacy_consent']));

// Invalid email format
$resFailEmail = $pubRegController->register(makeReq('/events/...', 'POST', [], [
    'full_name'         => 'Test User',
    'category'          => 'student',
    'email'             => 'invalid-not-an-email',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
], ['REMOTE_ADDR' => '10.0.0.6']), ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'peer-listening-fundamentals']);
$flashErrors = Session::getFlash('errors', []);
assertPhase1HTest("Invalid email syntax is rejected", 
    $resFailEmail->getStatusCode() === 302 && isset($flashErrors['email']));

// Invalid phone format
$resFailPhone = $pubRegController->register(makeReq('/events/...', 'POST', [], [
    'full_name'         => 'Test User',
    'category'          => 'student',
    'phone'             => 'abcd!@#$%',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
], ['REMOTE_ADDR' => '10.0.0.7']), ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'peer-listening-fundamentals']);
$flashErrors = Session::getFlash('errors', []);
assertPhase1HTest("Invalid phone syntax is rejected", 
    $resFailPhone->getStatusCode() === 302 && isset($flashErrors['phone']));

// =============================================================================
// 6. CAPACITY, WAITLIST & APPROVAL LIFECYCLE
// =============================================================================
echo PHP_EOL . "--- 6. Testing Capacity, Waitlist & Requires Approval ---" . PHP_EOL;

// Full capacity event -> automatically waitlisted
$resWaitlist = $pubRegController->register(makeReq('/events/...', 'POST', [], [
    'full_name'         => 'Waitlisted Candidate',
    'category'          => 'student',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
], ['REMOTE_ADDR' => '10.0.0.8']), ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'capped-workshop']);
$locWaitlist = $resWaitlist->getHeader('Location');
preg_match('#/registration/confirmed/(REG-[0-9]{2}-[23456789ABCDEFGHJKMNPQRSTVWXYZ]{5})#', (string) $locWaitlist, $mCodeWait);
$codeWait = $mCodeWait[1] ?? '';
$regWait = $registrationRepo->findByCode($codeWait);
assertPhase1HTest("Event at capacity automatically assigns status = 'waitlisted'", 
    $regWait !== null && $regWait['status'] === 'waitlisted');

// Event with requires_approval = 1 -> automatically pending
$resPending = $pubRegController->register(makeReq('/events/...', 'POST', [], [
    'full_name'         => 'Pending Candidate',
    'category'          => 'professional',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
], ['REMOTE_ADDR' => '10.0.0.9']), ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'advanced-listening-circles']);
$locPending = $resPending->getHeader('Location');
preg_match('#/registration/confirmed/(REG-[0-9]{2}-[23456789ABCDEFGHJKMNPQRSTVWXYZ]{5})#', $locPending, $mCodePen);
$codePen = $mCodePen[1] ?? '';
$regPen = $registrationRepo->findByCode($codePen);
assertPhase1HTest("Event with requires_approval = 1 automatically assigns status = 'pending'", 
    $regPen !== null && $regPen['status'] === 'pending');

// =============================================================================
// 7. IDEMPOTENCY & DEDUPLICATION
// =============================================================================
echo PHP_EOL . "--- 7. Testing Idempotency & Deduplication ---" . PHP_EOL;

// Re-registering identical participant for same event returns existing registration code (idempotent)
$resDup = $pubRegController->register(makeReq('/events/...', 'POST', [], [
    'full_name'         => 'Rohan Verma',
    'category'          => 'professional',
    'email'             => 'rohan.verma@example.com',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
], ['REMOTE_ADDR' => '10.0.0.10']), ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'peer-listening-fundamentals']);
$locDup = $resDup->getHeader('Location');
preg_match('#/registration/confirmed/(REG-[0-9]{2}-[23456789ABCDEFGHJKMNPQRSTVWXYZ]{5})#', $locDup, $mCodeDup);
$codeDup = $mCodeDup[1] ?? '';
$allRegsRohan = $testPdo->query("SELECT COUNT(*) FROM event_registrations WHERE participant_id = {$savedPart2['id']} AND event_id = {$eventOpenId}")->fetchColumn();
assertPhase1HTest("Repeat registration returns existing pass code and does not create duplicate row", 
    $codeDup !== '' && (int) $allRegsRohan === 1);

// Shared email with different name creates a new distinct participant record
$resSharedEmail = $pubRegController->register(makeReq('/events/...', 'POST', [], [
    'full_name'         => 'Sunita Verma', // Different name, shared email
    'category'          => 'community',
    'email'             => 'rohan.verma@example.com',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
], ['REMOTE_ADDR' => '10.0.0.11']), ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'peer-listening-fundamentals']);
$partSunita = $testPdo->query("SELECT * FROM participants WHERE full_name = 'Sunita Verma'")->fetch(PDO::FETCH_ASSOC);
assertPhase1HTest("Distinct name with shared email creates a distinct participant ID (never silently merged)", 
    $partSunita !== false && (int) $partSunita['id'] !== (int) $savedPart2['id']);

// =============================================================================
// 8. SECURITY, HONEYPOT & RATE LIMITING
// =============================================================================
echo PHP_EOL . "--- 8. Testing Anti-Spam, Honeypot & Rate Limiting ---" . PHP_EOL;

// Honeypot trap: filled 'website' field is silently dropped (redirects without creating records)
$partCountBefore = (int) $testPdo->query("SELECT COUNT(*) FROM participants")->fetchColumn();
$resHoneypot = $pubRegController->register(makeReq('/events/...', 'POST', [], [
    'full_name'         => 'Spam Bot',
    'category'          => 'student',
    'website'           => 'http://spamsite.example.com',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
], ['REMOTE_ADDR' => '192.168.1.100']), ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'peer-listening-fundamentals']);
$partCountAfter = (int) $testPdo->query("SELECT COUNT(*) FROM participants")->fetchColumn();
assertPhase1HTest("Honeypot trap: non-empty website field drops submission without inserting database rows", 
    $partCountBefore === $partCountAfter);

// CSRF check on public POST routes via Router dispatch
$reqNoCsrf = makeReq('/events/safe-spaces-2026/peer-listening-fundamentals/register', 'POST', [], [
    'full_name' => 'No Csrf User',
]);
$resNoCsrf = $router->dispatch($reqNoCsrf);
assertPhase1HTest("POST /events/.../register without CSRF token is rejected with HTTP 403 Forbidden", 
    $resNoCsrf->getStatusCode() === 403);

// Registration IP Rate Limiting: 6th submission from same IP triggers HTTP 429
$rateLimitIp = '198.51.100.55';
for ($i = 1; $i <= 5; $i++) {
    $pubRegController->register(makeReq('/events/...', 'POST', [], [
        'full_name'         => "Burst User {$i}",
        'category'          => 'student',
        'agreed_guidelines' => '1',
        'privacy_consent'   => '1',
    ], ['REMOTE_ADDR' => $rateLimitIp]), ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'peer-listening-fundamentals']);
}
$resRateLimited = $pubRegController->register(makeReq('/events/...', 'POST', [], [
    'full_name'         => "Burst User 6",
    'category'          => 'student',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
], ['REMOTE_ADDR' => $rateLimitIp]), ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'peer-listening-fundamentals']);
assertPhase1HTest("Submitting > 5 registrations in 15 minutes triggers HTTP 429 Too Many Requests", 
    $resRateLimited->getStatusCode() === 429);

// Security Rule 6: Public controller does NOT accept or trust actorId from user input
$resTamperActor = $pubRegController->register(makeReq('/events/...', 'POST', [], [
    '_csrf_token'       => $csrfToken,
    'full_name'         => 'Tamper Attempt',
    'category'          => 'student',
    'actor_id'          => 9999, // Attempted user ID injection
    'actor_type'        => 'super_admin',
    'agreed_guidelines' => '1',
    'privacy_consent'   => '1',
], ['REMOTE_ADDR' => '10.0.0.12']), ['campaign_slug' => 'safe-spaces-2026', 'event_slug' => 'peer-listening-fundamentals']);
$partTamper = $testPdo->query("SELECT * FROM participants WHERE full_name = 'Tamper Attempt'")->fetch(PDO::FETCH_ASSOC);
$auditTamper = $testPdo->query("SELECT * FROM audit_logs WHERE action = 'participant.create' AND entity_id = {$partTamper['id']}")->fetch(PDO::FETCH_ASSOC);
assertPhase1HTest("Security Rule 6: Injected actor_id is ignored and audit record logs actor_id = NULL", 
    $auditTamper['actor_id'] === null && $auditTamper['actor_type'] === 'anonymous');

// =============================================================================
// 9. CONFIRMATION & SELF-SERVICE STATUS LOOKUP
// =============================================================================
echo PHP_EOL . "--- 9. Testing Confirmation & Self-Service Status Lookup ---" . PHP_EOL;

// GET /registration/confirmed/{code}
$reqConf = makeReq("/registration/confirmed/{$code1}");
$resConf = $router->dispatch($reqConf);
assertPhase1HTest("GET /registration/confirmed/{$code1} returns HTTP 200", $resConf->getStatusCode() === 200);
assertPhase1HTest("Confirmation view displays attendee name, event title, and registration code", 
    str_contains($resConf->getContent(), 'Ananya Sen') && str_contains($resConf->getContent(), $code1));
assertPhase1HTest("Confirmation view contains valid navigation path to /registration/pass/{code}", 
    str_contains($resConf->getContent(), "/registration/pass/{$code1}"));

// Minimal operational disclosure: NEVER expose internal IDs, admin notes, or contact data
assertPhase1HTest("Confirmation view does NOT expose internal participant ID or registration ID", 
    !str_contains($resConf->getContent(), "participant_id") && !str_contains($resConf->getContent(), "registration_id"));

// Invalid code on confirmation returns 404
$resConfInvalid = $router->dispatch(makeReq('/registration/confirmed/REG-26-INVALID'));
assertPhase1HTest("GET /registration/confirmed/INVALID returns HTTP 404", $resConfInvalid->getStatusCode() === 404);

// GET /registration/status
$resStatusPage = $router->dispatch(makeReq('/registration/status'));
assertPhase1HTest("GET /registration/status returns HTTP 200 search form", $resStatusPage->getStatusCode() === 200);

// POST /registration/status with valid code
$resStatusCheck = $pubRegController->checkStatus(makeReq('/registration/status', 'POST', [], ['code' => $code1], ['REMOTE_ADDR' => '10.0.0.13']));
assertPhase1HTest("POST /registration/status with valid code returns status card with 'Confirmed' badge", 
    $resStatusCheck->getStatusCode() === 200 && str_contains($resStatusCheck->getContent(), 'Confirmed'));

// POST /registration/status with pending code
$resStatusCheckPen = $pubRegController->checkStatus(makeReq('/registration/status', 'POST', [], ['code' => $codePen], ['REMOTE_ADDR' => '10.0.0.14']));
assertPhase1HTest("POST /registration/status with pending code returns 'Pending Approval' badge", 
    str_contains($resStatusCheckPen->getContent(), 'Pending Approval'));

// POST /registration/status with waitlisted code
$resStatusCheckWait = $pubRegController->checkStatus(makeReq('/registration/status', 'POST', [], ['code' => $codeWait], ['REMOTE_ADDR' => '10.0.0.15']));
assertPhase1HTest("POST /registration/status with waitlisted code returns 'Waitlisted' badge", 
    str_contains($resStatusCheckWait->getContent(), 'Waitlisted'));

// POST /registration/status with non-existent code returns generic error without enumeration
$resStatusCheckNon = $pubRegController->checkStatus(makeReq('/registration/status', 'POST', [], ['code' => 'REG-26-ZZZZZ'], ['REMOTE_ADDR' => '10.0.0.16']));
assertPhase1HTest("POST /registration/status with non-existent code returns generic failure message", 
    str_contains($resStatusCheckNon->getContent(), 'No registration was found'));

// Status lookup IP rate limiting (10 attempts)
$statusRateIp = '198.51.100.99';
for ($i = 1; $i <= 10; $i++) {
    $pubRegController->checkStatus(makeReq('/registration/status', 'POST', [], ['code' => 'REG-26-ZZZZZ'], ['REMOTE_ADDR' => $statusRateIp]));
}
$resStatusLimited = $pubRegController->checkStatus(makeReq('/registration/status', 'POST', [], ['code' => 'REG-26-ZZZZZ'], ['REMOTE_ADDR' => $statusRateIp]));
assertPhase1HTest("POST /registration/status locks after 10 attempts and returns HTTP 429", 
    $resStatusLimited->getStatusCode() === 429);

// =============================================================================
// 10. HOSTINGER /LC/ SUBDIRECTORY COMPATIBILITY
// =============================================================================
echo PHP_EOL . "--- 10. Testing Hostinger /LC/ Subdirectory Path Resolution ---" . PHP_EOL;

Config::set('app.base_path', '/LC');

$resProdCamp = $router->dispatch(makeReq('/LC/campaigns'));
assertPhase1HTest("Hostinger '/LC/campaigns' dispatches to PublicCampaignController (HTTP 200)", $resProdCamp->getStatusCode() === 200);

$resProdEvents = $router->dispatch(makeReq('/LC/events'));
assertPhase1HTest("Hostinger '/LC/events' dispatches to PublicEventController (HTTP 200)", $resProdEvents->getStatusCode() === 200);

$resProdEventDetail = $router->dispatch(makeReq('/LC/events/safe-spaces-2026/peer-listening-fundamentals'));
assertPhase1HTest("Hostinger '/LC/events/{camp}/{event}' dispatches cleanly (HTTP 200)", $resProdEventDetail->getStatusCode() === 200);

$resProdStatus = $router->dispatch(makeReq('/LC/registration/status'));
assertPhase1HTest("Hostinger '/LC/registration/status' dispatches cleanly (HTTP 200)", $resProdStatus->getStatusCode() === 200);

$resProdConfirmed = $router->dispatch(makeReq("/LC/registration/confirmed/{$code1}"));
assertPhase1HTest("Hostinger '/LC/registration/confirmed/{code}' dispatches cleanly (HTTP 200)", $resProdConfirmed->getStatusCode() === 200);

// Guardrail G: MySQL FK Integrity across all audit log entries created during entire test suite
$invalidActorRows = (int) $testPdo->query("SELECT COUNT(*) FROM audit_logs WHERE actor_id IS NOT NULL AND actor_id <= 0")->fetchColumn();
assertPhase1HTest("Guardrail G: MySQL FK integrity - zero invalid/non-positive actor_id values inserted across test suite", 
    $invalidActorRows === 0);

// Reset base path to '/'
Config::set('app.base_path', '/');

// Clean up test cache dir
if (is_dir($testRateLimitDir)) {
    array_map('unlink', glob("{$testRateLimitDir}/*.*"));
    @rmdir($testRateLimitDir);
}

// =============================================================================
// Summary
// =============================================================================
echo PHP_EOL . "=================================================" . PHP_EOL;
echo "Phase 1H Public Discovery & Registration Verification Results:" . PHP_EOL;
echo "Total Assertions: {$totalTests}" . PHP_EOL;
echo "Passed: {$passedTests}" . PHP_EOL;
echo "Failed: {$failedTests}" . PHP_EOL;
echo "=================================================" . PHP_EOL;

if ($failedTests > 0) {
    exit(1);
}
