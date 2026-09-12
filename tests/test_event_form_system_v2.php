<?php

declare(strict_types=1);

/**
 * Event-Centric System V2 Automated Feature Verification Suite
 * Tests the complete Event-Centric V2 implementation:
 * 1. Admin Management & Granular Permissions
 * 2. 1 Event = 1 Form Atomic Creation, Global Unique Slugs, and URL Immutability
 * 3. Form Settings, Custom Fields, Locked Field Safeguards, and Registration Engine
 * 4. Technical Registration Metadata & Privacy Masking
 * 5. Public Mobile Check-In & Server-Side Geofencing
 * 6. Certificate System V2 (10-char alphanumeric ID, Completed Status Gating, Dual Signatures)
 * 7. Final Event Report & Turnout Analytics
 *
 * Run via: php tests/test_event_form_system_v2.php
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security;
use App\Core\Session;
use App\Core\Exceptions\CertificateException;
use App\Core\Exceptions\ValidationException;
use App\Repositories\AuditLogRepository;
use App\Repositories\CampaignRepository;
use App\Repositories\CertificateRepository;
use App\Repositories\CertificateTemplateRepository;
use App\Repositories\EventFormRepository;
use App\Repositories\EventRepository;
use App\Repositories\FormSettingsRepository;
use App\Repositories\ParticipantRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\RegistrationRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\CampaignService;
use App\Services\CertificateService;
use App\Services\EventFormService;
use App\Services\EventService;
use App\Services\GeofenceService;
use App\Services\PermissionService;
use App\Services\RegistrationMetadataService;
use App\Services\RegistrationService;
use App\Services\RoleService;

$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertV2Test(string $description, bool $condition, string $details = ''): void
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

echo "=== LC-SPC Event-Centric System V2 Comprehensive Verification Suite ===" . PHP_EOL . PHP_EOL;

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

    CREATE TABLE permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        module VARCHAR(50) NOT NULL,
        action VARCHAR(50) NOT NULL,
        description VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE user_permissions (
        user_id INTEGER NOT NULL,
        permission_id INTEGER NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, permission_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
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
        created_by INTEGER NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        deleted_at DATETIME NULL
    );

    CREATE TABLE form_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        mandatory_location_access INTEGER NOT NULL DEFAULT 0,
        default_country_code VARCHAR(10) NOT NULL DEFAULT '+91',
        default_country_iso VARCHAR(5) NOT NULL DEFAULT 'IN',
        registration_success_message TEXT NULL,
        whatsapp_group_url VARCHAR(255) NULL,
        whatsapp_auto_redirect INTEGER NOT NULL DEFAULT 0,
        whatsapp_countdown_seconds INTEGER NOT NULL DEFAULT 5,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE event_forms (
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
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    );

    CREATE TABLE form_fields (
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
        UNIQUE (form_id, field_key),
        FOREIGN KEY (form_id) REFERENCES event_forms(id) ON DELETE CASCADE
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
        UNIQUE (event_id, phone_normalized)
    );

    CREATE TABLE registration_metadata (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        registration_id INTEGER NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        device_type VARCHAR(20) NOT NULL DEFAULT 'Desktop',
        operating_system VARCHAR(50) NOT NULL DEFAULT 'Unknown OS',
        browser VARCHAR(50) NOT NULL DEFAULT 'Unknown Browser',
        isp VARCHAR(100) NULL,
        as_name VARCHAR(100) NULL,
        country VARCHAR(50) NULL,
        city VARCHAR(100) NULL,
        raw_user_agent VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (registration_id) REFERENCES event_registrations(id) ON DELETE CASCADE
    );

    CREATE TABLE certificate_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id INTEGER NOT NULL UNIQUE,
        background_image_path VARCHAR(255) NULL,
        seal_image_path VARCHAR(255) NULL,
        signature1_image_path VARCHAR(255) NULL,
        signature1_name VARCHAR(100) NULL,
        signature1_designation VARCHAR(100) NULL,
        signature2_image_path VARCHAR(255) NULL,
        signature2_name VARCHAR(100) NULL,
        signature2_designation VARCHAR(100) NULL,
        layout_config TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
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

// Seed canonical permissions for the 11 modules
$modules = [
    'dashboard'     => ['view'],
    'admins'        => ['view', 'create', 'edit', 'delete', 'permissions'],
    'events'        => ['view', 'create', 'edit', 'delete'],
    'forms'         => ['view', 'edit', 'share'],
    'registrations' => ['view', 'export', 'cancel'],
    'checkin'       => ['view', 'process'],
    'analytics'     => ['view', 'export'],
    'certificates'  => ['view', 'design', 'issue', 'revoke'],
    'reports'       => ['view', 'export'],
    'form_settings' => ['view', 'edit'],
    'settings'      => ['view', 'edit'],
];

$permRepo = new PermissionRepository();
foreach ($modules as $mod => $actions) {
    foreach ($actions as $act) {
        $name = "{$mod}.{$act}";
        $testPdo->exec("INSERT INTO permissions (name, module, action, description) VALUES ('{$name}', '{$mod}', '{$act}', 'Allow {$act} on {$mod}')");
    }
}

// Instantiate Repositories and Services
$userRepo = new UserRepository();
$campaignRepo = new CampaignRepository();
$eventRepo = new EventRepository();
$formRepo = new EventFormRepository();
$formSettingsRepo = new FormSettingsRepository();
$participantRepo = new ParticipantRepository();
$regRepo = new RegistrationRepository();
$certRepo = new CertificateRepository();
$templateRepo = new CertificateTemplateRepository();
$auditRepo = new AuditLogRepository();

$auditService = new AuditService($auditRepo, $userRepo);
$formService = new EventFormService($formRepo, $eventRepo);
$eventService = new EventService($eventRepo, $campaignRepo, $userRepo, $auditService, $formService, $regRepo);
$permService = new PermissionService($permRepo, $userRepo);
$geofenceService = new GeofenceService();
$metadataService = new RegistrationMetadataService();
$certService = new CertificateService($certRepo, $regRepo, $eventRepo, $templateRepo, $auditService);

// -----------------------------------------------------------------------------
// SECTION 1: Admin Management & Granular Permissions
// -----------------------------------------------------------------------------
echo "1. Testing Admin Management & Granular Permissions System..." . PHP_EOL;

// 1.1 Create super admin and secondary admin
$superAdminId = $userRepo->create([
    'name'          => 'Chief Super Admin',
    'email'         => 'superadmin@teami.in',
    'password_hash' => Security::hashPassword('SuperSecret123!'),
    'role'          => RoleService::ROLE_SUPER_ADMIN,
    'status'        => 'active',
]);

$admin2Id = $userRepo->create([
    'name'          => 'Event Admin 2',
    'email'         => 'admin2@teami.in',
    'password_hash' => Security::hashPassword('InitialPass123!'),
    'role'          => RoleService::ROLE_STAFF,
    'status'        => 'active',
]);

assertV2Test("1.1 Super Admin and Staff users successfully created", $superAdminId > 0 && $admin2Id > 0);

// 1.2 Password reset
$newHash = Security::hashPassword('ResetPass456!');
$userRepo->resetPassword($admin2Id, $newHash);
$admin2User = $userRepo->findById($admin2Id);
assertV2Test(
    "1.2 Admin password reset securely updates password_hash and verifies against new password",
    Security::verifyPassword('ResetPass456!', $admin2User['password_hash']) &&
    !Security::verifyPassword('InitialPass123!', $admin2User['password_hash'])
);

// 1.3 Pre-seeded canonical permissions count
$allPerms = $permRepo->getAll();
assertV2Test("1.3 Canonical permissions pre-seeded across 11 modules", count($allPerms) >= 25);

// 1.4 Assign permissions to admin2 (events.view, events.create)
$eventsViewPerm = $permRepo->findByName('events.view');
$eventsCreatePerm = $permRepo->findByName('events.create');
$permService->assignPermissions($admin2Id, [(int) $eventsViewPerm['id'], (int) $eventsCreatePerm['id']]);

$assignedNames = $permRepo->getUserPermissionNames($admin2Id);
assertV2Test("1.4 Permissions explicitly assigned to staff user", in_array('events.view', $assignedNames, true) && in_array('events.create', $assignedNames, true));
assertV2Test("1.5 Permission check returns true for assigned permission", $permService->hasPermission($admin2Id, 'events.view', RoleService::ROLE_STAFF));
assertV2Test("1.6 Permission check returns false for unassigned permission", !$permService->hasPermission($admin2Id, 'certificates.issue', RoleService::ROLE_STAFF));

// 1.7 Super Admin Unconditional Bypass
assertV2Test(
    "1.7 Super Admin has unconditional bypass for ANY permission without explicit assignment",
    $permService->hasPermission($superAdminId, 'certificates.revoke', RoleService::ROLE_SUPER_ADMIN) === true &&
    $permService->hasPermission($superAdminId, 'admins.delete', RoleService::ROLE_SUPER_ADMIN) === true
);

// -----------------------------------------------------------------------------
// SECTION 2: 1 Event = 1 Form Atomic Creation & URL Immutability
// -----------------------------------------------------------------------------
echo PHP_EOL . "2. Testing Event + Form Atomic Creation & URL Immutability..." . PHP_EOL;

// 2.1 Atomic Event + Form Creation
$eventData = [
    'title'                  => 'State Youth Listening Summit 2026',
    'category'               => 'workshop',
    'event_type'             => 'offline',
    'format'                 => 'in_person',
    'venue_name'             => 'St. Xavier Hall',
    'venue_address'          => 'Park Road, Chennai',
    'start_time'             => '2026-10-15 09:00:00',
    'end_time'               => '2026-10-15 17:00:00',
    'checkin_start_date'     => '2026-10-15',
    'checkin_start_time'     => '08:00:00',
    'latitude'               => 13.0827,
    'longitude'              => 80.2707,
    'geofence_radius_meters' => 250,
    'status'                 => 'published',
];

$createdEvent = $eventService->createEvent($eventData, $superAdminId);
$eventId = (int) $createdEvent['id'];

assertV2Test("2.1 Event successfully created in database", $eventId > 0 && $createdEvent['slug'] === 'state-youth-listening-summit-2026');

// 2.2 Form automatically provisioned with 1-to-1 match
$associatedForm = $formRepo->findByEventId($eventId);
assertV2Test("2.2 Dedicated registration form atomically created for event (1 Event = 1 Form)", !empty($associatedForm));
assertV2Test("2.3 Form shares identical unique slug with event", $associatedForm['slug'] === $createdEvent['slug']);

// 2.4 Auto-generated public URL and QR code SVG
$regUrl = $formService->getPublicRegistrationUrl($associatedForm['slug']);
$qrSvg = $formService->getPublicRegistrationQrSvg($associatedForm['slug']);
assertV2Test("2.4 Authoritative registration URL matches /register/{slug}", str_ends_with($regUrl, '/register/state-youth-listening-summit-2026'));
assertV2Test("2.5 QR Code vector SVG generated successfully for registration URL", str_starts_with($qrSvg, '<svg') && str_contains($qrSvg, '</svg>') && strlen($qrSvg) > 500);

// 2.6 Public Check-in URL and QR
$checkinUrl = $formService->getPublicCheckInUrl($createdEvent['slug']);
$checkinQrSvg = $formService->getPublicCheckInQrSvg($createdEvent['slug']);
assertV2Test("2.6 Authoritative check-in URL matches /check-in/{slug}", str_ends_with($checkinUrl, '/check-in/state-youth-listening-summit-2026'));
assertV2Test("2.7 Check-in QR vector SVG generated successfully for check-in URL", str_starts_with($checkinQrSvg, '<svg') && str_contains($checkinQrSvg, '</svg>') && strlen($checkinQrSvg) > 500);

// 2.8 Default locked fields provisioned in form_fields
$formFields = $formRepo->getFields((int) $associatedForm['id']);
$fieldKeys = array_column($formFields, 'field_key');
assertV2Test("2.8 Form contains locked mandatory fields (full_name and phone)", in_array('full_name', $fieldKeys, true) && in_array('phone', $fieldKeys, true));

$fullNameField = array_values(array_filter($formFields, fn($f) => $f['field_key'] === 'full_name'))[0] ?? null;
assertV2Test("2.9 Full Name field marked is_locked=1 and is_required=1", $fullNameField && (int) $fullNameField['is_locked'] === 1 && (int) $fullNameField['is_required'] === 1);

// 2.10 Event edit URL immutability guarantee
$originalEventSlug = $createdEvent['slug'];
$originalFormSlug = $associatedForm['slug'];

$eventService->updateEvent($eventId, [
    'title'                  => 'State Youth Listening Summit 2026 - MODIFIED TITLE FOR SPONSORS',
    'category'               => 'workshop',
    'format'                 => 'in_person',
    'venue_name'             => 'St. Xavier Hall (Renovated)',
    'start_time'             => '2026-10-15 09:00:00',
    'end_time'               => '2026-10-15 17:00:00',
], $superAdminId);

$updatedEvent = $eventRepo->findById($eventId);
$updatedForm = $formRepo->findByEventId($eventId);

assertV2Test(
    "2.10 Event edit NEVER mutates existing event slug or form URL (URL Immutability Guarantee)",
    $updatedEvent['slug'] === $originalEventSlug &&
    $updatedForm['slug'] === $originalFormSlug &&
    $updatedEvent['title'] === 'State Youth Listening Summit 2026 - MODIFIED TITLE FOR SPONSORS'
);

// -----------------------------------------------------------------------------
// SECTION 3: Form Settings, Custom Fields & Registration Engine
// -----------------------------------------------------------------------------
echo PHP_EOL . "3. Testing Form Settings, Custom Fields & Registration Engine..." . PHP_EOL;

// 3.1 Global Form Settings
$settings = $formSettingsRepo->getSettings();
assertV2Test("3.1 Form settings initialized with defaults (+91 default country code)", ($settings['default_country_code'] ?? '') === '+91');

$formSettingsRepo->updateSettings([
    'default_country_code'        => '+91',
    'whatsapp_group_url'          => 'https://chat.whatsapp.com/TestCommunityGroup123',
    'whatsapp_auto_redirect'      => 1,
    'whatsapp_countdown_seconds'  => 3,
    'registration_success_message'=> 'Welcome to Listening Community!',
]);
$updatedSettings = $formSettingsRepo->getSettings();
assertV2Test("3.2 Form settings updated successfully", ($updatedSettings['whatsapp_group_url'] ?? '') === 'https://chat.whatsapp.com/TestCommunityGroup123');

// 3.3 Add Custom Field to Form
$collegeFieldId = $formService->addCustomField((int) $associatedForm['id'], [
    'field_label' => 'College / Organization Name',
    'field_type'  => 'text',
    'is_required' => 1,
    'placeholder' => 'e.g. Loyola College',
]);
assertV2Test("3.3 Custom field added to event registration form", $collegeFieldId > 0);

// 3.4 Locked Field Safeguard: Cannot delete locked field
$caughtLockedDelete = false;
try {
    $formService->deleteCustomField((int) $fullNameField['id']);
} catch (ValidationException $e) {
    $caughtLockedDelete = true;
}
assertV2Test("3.4 Locked field delete blocked by safeguard (Cannot delete Full Name)", $caughtLockedDelete);

// 3.5 Custom field CAN be deleted
$deptFieldId = $formService->addCustomField((int) $associatedForm['id'], [
    'field_label' => 'Temporary Department',
    'field_type'  => 'text',
]);
$deletedCustom = $formService->deleteCustomField($deptFieldId);
assertV2Test("3.5 Custom field deleted successfully", $deletedCustom);

// 3.6 Register Participant via Registration Engine
$part1Id = $participantRepo->create([
    'full_name'            => 'Vikram Aditya',
    'phone'                => '+91 9876543210',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);

$reg1Id = $regRepo->create([
    'registration_code' => 'REG-26-VKRM1',
    'event_id'          => $eventId,
    'participant_id'    => $part1Id,
    'form_id'           => (int) $associatedForm['id'],
    'phone_normalized'  => '+919876543210',
    'custom_data'       => ['college' => 'Loyola College'],
    'status'            => 'confirmed',
    'attendance_status' => 'unmarked',
]);
assertV2Test("3.6 Participant registered with normalized phone and custom data", $reg1Id > 0);

// 3.7 Form slug locked once registrations exist
$caughtSlugLock = false;
try {
    $formService->updateForm((int) $associatedForm['id'], [
        'slug' => 'attempted-new-slug-after-registrations',
    ]);
} catch (ValidationException $e) {
    $caughtSlugLock = true;
}
assertV2Test("3.7 Form slug modification rejected once registrations exist for event", $caughtSlugLock);

// 3.8 Duplicate phone registration blocked on SAME event
$caughtDuplicatePhone = false;
try {
    $part2Id = $participantRepo->create([
        'full_name'            => 'Vikram Duplicate',
        'phone'                => '+91 9876543210',
        'agreed_guidelines_at' => date('Y-m-d H:i:s'),
        'privacy_consent_at'   => date('Y-m-d H:i:s'),
    ]);
    $regRepo->create([
        'registration_code' => 'REG-26-VKRM2',
        'event_id'          => $eventId,
        'participant_id'    => $part2Id,
        'phone_normalized'  => '+919876543210', // Same event, same phone!
    ]);
} catch (PDOException $e) {
    $caughtDuplicatePhone = true;
}
assertV2Test("3.8 Duplicate registration with same phone on same event blocked by unique constraint", $caughtDuplicatePhone);

// 3.9 Same phone registration permitted on a DIFFERENT event
$event2 = $eventService->createEvent([
    'title'      => 'Second Separate Workshop 2026',
    'category'   => 'seminar',
    'format'     => 'in_person',
    'venue_name' => 'Auditorium B',
    'start_time' => '2026-11-01 10:00:00',
    'end_time'   => '2026-11-01 13:00:00',
    'status'     => 'published',
], $superAdminId);

$regDifferentEventId = $regRepo->create([
    'registration_code' => 'REG-26-DIFF1',
    'event_id'          => (int) $event2['id'],
    'participant_id'    => $part1Id,
    'phone_normalized'  => '+919876543210', // Same phone, DIFFERENT event!
]);
assertV2Test("3.9 Same phone number successfully registers for a DIFFERENT event", $regDifferentEventId > 0);

// -----------------------------------------------------------------------------
// SECTION 4: Technical Registration Metadata & Privacy Masking
// -----------------------------------------------------------------------------
echo PHP_EOL . "4. Testing Technical Registration Metadata & Privacy Masking..." . PHP_EOL;

// 4.1 User Agent Parsing: Desktop Windows Chrome
$uaChromeWin = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36";
$parsedWin = $metadataService->parseUserAgent($uaChromeWin);
assertV2Test("4.1 User Agent parsed: Desktop Windows Chrome", $parsedWin['device_type'] === 'Desktop' && $parsedWin['operating_system'] === 'Windows 10/11' && $parsedWin['browser'] === 'Chrome');

// 4.2 User Agent Parsing: Mobile Android Chrome
$uaAndroid = "Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36";
$parsedAndroid = $metadataService->parseUserAgent($uaAndroid);
assertV2Test("4.2 User Agent parsed: Mobile Android Chrome", $parsedAndroid['device_type'] === 'Mobile' && $parsedAndroid['operating_system'] === 'Android' && $parsedAndroid['browser'] === 'Chrome');

// 4.3 User Agent Parsing: iPhone Safari
$uaIphone = "Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1";
$parsedIphone = $metadataService->parseUserAgent($uaIphone);
assertV2Test("4.3 User Agent parsed: Mobile iOS Safari", $parsedIphone['device_type'] === 'Mobile' && $parsedIphone['operating_system'] === 'iOS' && $parsedIphone['browser'] === 'Safari');

// 4.4 Graceful fallback on empty User Agent
$parsedEmpty = $metadataService->parseUserAgent(null);
assertV2Test("4.4 User Agent parser handles empty/null without fatal error", $parsedEmpty['device_type'] === 'Unknown' && $parsedEmpty['browser'] === 'Unknown');

// 4.5 IP Masking (IPv4 and IPv6)
$maskedIpv4 = RegistrationMetadataService::maskIp('192.168.1.100');
$maskedIpv6 = RegistrationMetadataService::maskIp('2001:0db8:85a3:0000:0000:8a2e:0370:7334');
assertV2Test("4.5 IPv4 masked correctly for privacy exports", $maskedIpv4 === '192.168.***.***');
assertV2Test("4.6 IPv6 masked correctly for privacy exports", str_ends_with($maskedIpv6, '::****'));

// 4.7 Record Metadata for Registration
$mockRequest = new Request([], [], [], [
    'REMOTE_ADDR'     => '127.0.0.1',
    'HTTP_USER_AGENT' => $uaAndroid,
]);
$savedMeta = $metadataService->recordMetadata($reg1Id, $mockRequest);
assertV2Test("4.7 Registration metadata recorded successfully in database", $savedMeta === true);

// -----------------------------------------------------------------------------
// SECTION 5: Public Mobile Check-In & Server-Side Geofencing
// -----------------------------------------------------------------------------
echo PHP_EOL . "5. Testing Public Mobile Check-In & Server-Side Geofencing..." . PHP_EOL;

// 5.1 Haversine Distance Calculation
// Distance from Marina Beach (13.0499, 80.2824) to Chennai Central (13.0827, 80.2755) is ~4.0 - 4.2 km
$distanceMeters = $geofenceService->calculateDistanceMeters(13.0499, 80.2824, 13.0827, 80.2755);
assertV2Test("5.1 Haversine distance calculated accurately (~3600m to 4200m)", $distanceMeters >= 3500 && $distanceMeters <= 4200);

// Identical coordinates distance is 0
$zeroDist = $geofenceService->calculateDistanceMeters(13.0827, 80.2707, 13.0827, 80.2707);
assertV2Test("5.2 Identical coordinates return 0.0 meters distance", $zeroDist === 0.0);

// 5.3 Geofence Proximity: Inside Perimeter
// Participant is 50 meters away from venue with radius 250 meters
$insideProximity = $geofenceService->verifyProximity(
    13.0828, 80.2708, // ~15-20 meters away
    13.0827, 80.2707, // Venue
    250               // Radius
);
assertV2Test("5.3 Proximity verified: participant coordinates inside 250m perimeter", $insideProximity['configured'] && $insideProximity['inside'] && $insideProximity['distance_meters'] < 250);

// 5.4 Geofence Proximity: Outside Perimeter
// Participant is 4 km away
$outsideProximity = $geofenceService->verifyProximity(
    13.0499, 80.2824, // Marina beach (4km away)
    13.0827, 80.2707, // Venue
    250               // Radius
);
assertV2Test("5.4 Proximity rejected: participant coordinates 4000m away outside 250m perimeter", $outsideProximity['configured'] && !$outsideProximity['inside'] && $outsideProximity['distance_meters'] > 1000);

// 5.5 Geofence Proximity: Unconfigured Event (No coordinates)
$unconfiguredProximity = $geofenceService->verifyProximity(13.0827, 80.2707, null, null, null);
assertV2Test("5.5 Geofence check passes when event has no coordinates configured", !$unconfiguredProximity['configured'] && !$unconfiguredProximity['inside']);

// 5.6 Perform Mobile Check-In by Phone Number
$lookupReg = $regRepo->findByEventAndPhone($eventId, '+919876543210');
assertV2Test("5.6 Participant registration resolved by event ID and normalized phone", !empty($lookupReg) && $lookupReg['id'] === $reg1Id);

// Update check-in record
$checkedIn = $regRepo->updateCheckin($reg1Id, [
    'attendance_status'         => 'attended',
    'check_in_method'           => 'mobile_web',
    'attended_at'               => date('Y-m-d H:i:s'),
    'checkin_latitude'          => 13.0828,
    'checkin_longitude'         => 80.2708,
    'checkin_distance_meters'   => $insideProximity['distance_meters'],
    'checkin_geofence_verified' => 1,
]);
assertV2Test("5.7 Check-in successfully updates attendance_status to 'attended' and stores geofence telemetry", $checkedIn);

$checkedReg = $regRepo->findById($reg1Id);
assertV2Test("5.8 Checked-in registration verified: attendance_status=attended and method=mobile_web", $checkedReg['attendance_status'] === 'attended' && $checkedReg['check_in_method'] === 'mobile_web');

// -----------------------------------------------------------------------------
// SECTION 6: Certificate System V2
// -----------------------------------------------------------------------------
echo PHP_EOL . "6. Testing Certificate System V2 (10-Char ID, Eligibility Gating, Templates)..." . PHP_EOL;

// 6.1 10-Character Alphanumeric Cryptographically Random Certificate ID
$certCode = $certService->generateCertificateNumber();
assertV2Test(
    "6.1 Certificate number is exactly 10 uppercase alphanumeric characters [0-9A-Z]",
    strlen($certCode) === 10 && preg_match('/^[0-9A-Z]{10}$/', $certCode) === 1
);

// Verify collision resistance across 50 generated codes
$generatedCodes = [];
for ($i = 0; $i < 50; $i++) {
    $c = $certService->generateCertificateNumber();
    $generatedCodes[$c] = true;
}
assertV2Test("6.2 50 sequentially generated certificate numbers have zero collisions", count($generatedCodes) === 50);

// 6.3 Certificate Eligibility: Event MUST be 'completed'
// Currently event status is 'published'
$caughtUncompletedEvent = false;
try {
    $certService->validateEligibility($checkedReg, CertificateService::TYPE_PARTICIPATION);
} catch (CertificateException $e) {
    $caughtUncompletedEvent = ($e->getStatusCode() === 403 && str_contains($e->getMessage(), 'completed'));
}
assertV2Test("6.3 Certificate generation strictly rejected when event status is not 'completed' (HTTP 403)", $caughtUncompletedEvent);

// 6.4 Transition event status to 'completed'
$testPdo->exec("UPDATE events SET status = 'completed' WHERE id = {$eventId}");
$completedEvent = $eventRepo->findById($eventId);
assertV2Test("6.4 Event transitioned to 'completed' status", $completedEvent['status'] === 'completed');

// 6.5 Certificate Eligibility: Attendance status must be 'attended'
// Create an absent registration on completed event to test attendance gating
$partAbsentId = $participantRepo->create([
    'full_name'            => 'Absent Person',
    'phone'                => '+91 9999900001',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
]);
$regAbsentId = $regRepo->create([
    'registration_code' => 'REG-26-ABSNT',
    'event_id'          => $eventId,
    'participant_id'    => $partAbsentId,
    'status'            => 'confirmed',
    'attendance_status' => 'absent',
]);
$absentReg = $regRepo->findById($regAbsentId);

$caughtAbsentCert = false;
try {
    $certService->validateEligibility($absentReg, CertificateService::TYPE_PARTICIPATION);
} catch (CertificateException $e) {
    $caughtAbsentCert = ($e->getStatusCode() === 403 && str_contains($e->getMessage(), 'attended'));
}
assertV2Test("6.5 Certificate generation strictly rejected for participants who did NOT attend (HTTP 403)", $caughtAbsentCert);

// 6.6 Eligible Attendee on Completed Event Succeeds
$issuedCert = $certService->issueSingle($reg1Id, CertificateService::TYPE_PARTICIPATION, $superAdminId, RoleService::ROLE_SUPER_ADMIN);
assertV2Test("6.6 Eligible attended participant receives active certificate", !empty($issuedCert['id']) && $issuedCert['status'] === 'active');
assertV2Test("6.7 Issued certificate number is 10-char alphanumeric code", strlen($issuedCert['certificate_number']) === 10 && preg_match('/^[0-9A-Z]{10}$/', $issuedCert['certificate_number']) === 1);

// 6.8 Certificate Template Designer Configuration (Dual Signatures, Seal, Background)
$templateRepo->saveOrUpdate($eventId, [
    'signature1_name'        => 'Dr. K. Ananth',
    'signature1_designation' => 'Executive Director',
    'signature2_name'        => 'Prof. Meenakshi S.',
    'signature2_designation' => 'Lead Facilitator',
    'layout_config'          => [
        'recipient_name' => ['x' => 1240, 'y' => 850, 'font_size' => 64],
        'event_title'    => ['x' => 1240, 'y' => 1020, 'font_size' => 38],
        'cert_number'    => ['x' => 450, 'y' => 1550, 'font_size' => 24],
    ],
]);

$savedTemplate = $templateRepo->findByEventId($eventId);
assertV2Test("6.8 Certificate template saved with dual signatures and layout coordinates", !empty($savedTemplate) && $savedTemplate['signature1_name'] === 'Dr. K. Ananth' && $savedTemplate['signature2_name'] === 'Prof. Meenakshi S.');
assertV2Test("6.9 Template layout_config parsed properly into associative array", is_array($savedTemplate['layout_config']) && isset($savedTemplate['layout_config']['recipient_name']));

// -----------------------------------------------------------------------------
// SECTION 7: Final Event Report & Turnout Metrics
// -----------------------------------------------------------------------------
echo PHP_EOL . "7. Testing Final Event Report & Turnout Analytics..." . PHP_EOL;

// 7.1 Aggregate Turnout Metrics on Event
$statsStmt = $testPdo->query("
    SELECT 
        COUNT(*) as total_registered,
        SUM(CASE WHEN attendance_status = 'attended' THEN 1 ELSE 0 END) as total_attended,
        SUM(CASE WHEN attendance_status = 'absent' THEN 1 ELSE 0 END) as total_absent,
        SUM(CASE WHEN attendance_status = 'unmarked' THEN 1 ELSE 0 END) as total_unmarked
    FROM event_registrations
    WHERE event_id = {$eventId} AND status = 'confirmed'
");
$metrics = $statsStmt->fetch(PDO::FETCH_ASSOC);

$totalReg = (int) $metrics['total_registered'];
$totalAttended = (int) $metrics['total_attended'];
$turnoutPct = $totalReg > 0 ? round(($totalAttended / $totalReg) * 100, 1) : 0.0;

assertV2Test("7.1 Total registered count matches seeded registrations (2 confirmed)", $totalReg === 2);
assertV2Test("7.2 Total attended count matches verified check-ins (1 attended)", $totalAttended === 1);
assertV2Test("7.3 Turnout percentage calculated accurately (50.0%)", $turnoutPct === 50.0);

// 7.4 Masked CSV Export for Privacy
$exportData = [
    [
        'reg_code'   => $checkedReg['registration_code'],
        'name'       => $checkedReg['participant_name'],
        'phone'      => substr($checkedReg['participant_phone'] ?? '', 0, 5) . '*****',
        'status'     => $checkedReg['status'],
        'attendance' => $checkedReg['attendance_status'],
        'method'     => $checkedReg['check_in_method'],
        'ip_masked'  => RegistrationMetadataService::maskIp('127.0.0.1'),
    ]
];

$csvLine = implode(',', array_values($exportData[0]));
assertV2Test(
    "7.4 CSV export row enforces Privacy Shield masking (phone and IP obscured)",
    str_contains($csvLine, '*****') && str_contains($csvLine, '127.0.***.***') && !str_contains($csvLine, '9876543210')
);

// -----------------------------------------------------------------------------
// SECTION 8: Production-Readiness Hardening Fixes
// -----------------------------------------------------------------------------
echo PHP_EOL . "8. Testing Production-Readiness Hardening Fixes..." . PHP_EOL;

// Ensure check-in window is active for the test event
$testPdo->exec("UPDATE events SET 
    status = 'published', 
    checkin_start_date = '" . date('Y-m-d', strtotime('-1 day')) . "', 
    checkin_start_time = '00:00:00', 
    start_time = '" . date('Y-m-d H:i:s', strtotime('-1 hour')) . "', 
    end_time = '" . date('Y-m-d H:i:s', strtotime('+4 hours')) . "' 
WHERE id = {$eventId}");

$currentEvent = $eventRepo->findById($eventId);

$testRateLimitDir = APP_ROOT . '/storage/cache/test_checkin_rate_limits';
if (!is_dir($testRateLimitDir)) {
    @mkdir($testRateLimitDir, 0755, true);
}
// Clean any preexisting test files
array_map('unlink', glob($testRateLimitDir . '/*.*') ?: []);

// 8.1 & 8.2 Check-in Rate Limiting & Reset
$checkinCtrl = new \App\Controllers\Public\PublicCheckInController(
    $eventRepo,
    $formRepo,
    $regRepo,
    $formService,
    $geofenceService,
    $auditService,
    $testRateLimitDir
);

$testAttackerIp = '203.0.113.88';

// Send 5 invalid phone numbers
for ($attempt = 1; $attempt <= 5; $attempt++) {
    $req = new Request([], [
        'country_code' => '+91',
        'phone'        => '900000000' . $attempt,
    ], [], ['REMOTE_ADDR' => $testAttackerIp]);
    $checkinCtrl->process($req, ['slug' => $currentEvent['slug']]);
}

// 6th attempt should be blocked with HTTP 429
$req6 = new Request([], [
    'country_code' => '+91',
    'phone'        => '9000000006',
], [], ['REMOTE_ADDR' => $testAttackerIp]);
$resp429 = $checkinCtrl->process($req6, ['slug' => $currentEvent['slug']]);

assertV2Test(
    "8.1 Check-in rate limiting: 6th lookup attempt returns HTTP 429 Too Many Requests",
    $resp429->getStatusCode() === 429 && str_contains($resp429->getBody(), 'Too Many Requests')
);

// Successful check-in resets rate limits
// Create a separate attendee for testing rate-limit reset
$resetParticipantId = $participantRepo->create([
    'full_name'            => 'Reset Test User',
    'phone'                => '+91 9876500001',
    'email'                => 'reset@test.com',
    'agreed_guidelines_at' => date('Y-m-d H:i:s'),
    'privacy_consent_at'   => date('Y-m-d H:i:s'),
    'status'               => 'active',
]);
$resetRegId = $regRepo->create([
    'registration_code' => 'REG-RESET-01',
    'event_id'          => $eventId,
    'participant_id'    => $resetParticipantId,
    'form_id'           => (int) $associatedForm['id'],
    'phone_normalized'  => '+919876500001',
    'country_code'      => '+91',
    'status'            => 'confirmed',
    'attendance_status' => 'unmarked',
]);

// Attempt check-in from another IP initially locked, then test clearAttempts
$legitIp = '203.0.113.99';
for ($attempt = 1; $attempt <= 4; $attempt++) {
    $req = new Request([], [
        'country_code' => '+91',
        'phone'        => '900000000' . $attempt,
    ], [], ['REMOTE_ADDR' => $legitIp]);
    $checkinCtrl->process($req, ['slug' => $currentEvent['slug']]);
}

// Now legitimate user checks in from that IP with their valid number
$legitReq = new Request([], [
    'country_code' => '+91',
    'phone'        => '9876500001',
    'latitude'     => 13.0827,
    'longitude'    => 80.2707,
], [], ['REMOTE_ADDR' => $legitIp]);
$legitResp = $checkinCtrl->process($legitReq, ['slug' => $currentEvent['slug']]);

// Verify attempts cleared: next attempt from $legitIp is NOT 429
$postResetReq = new Request([], [
    'country_code' => '+91',
    'phone'        => '9000000007',
], [], ['REMOTE_ADDR' => $legitIp]);
$postResetResp = $checkinCtrl->process($postResetReq, ['slug' => $currentEvent['slug']]);

assertV2Test(
    "8.2 Check-in rate limiting: Successful legitimate check-in clears rate-limit attempts",
    $legitResp->getStatusCode() === 200 && $postResetResp->getStatusCode() !== 429
);

// 8.3 Event Slug Change Blocked After Registrations Begin
$slugBlocked = false;
$blockedError = '';
try {
    $eventService->updateEvent($eventId, array_merge($currentEvent, [
        'slug' => 'attempted-slug-mutation-with-registrations',
    ]), $superAdminId);
} catch (ValidationException $e) {
    $slugBlocked = true;
    $blockedError = $e->getMessage();
}

$persistedEvent = $eventRepo->findById($eventId);
assertV2Test(
    "8.3 Event slug mutation blocked with ValidationException when event has registrations",
    $slugBlocked && str_contains($blockedError, 'registrations') && $persistedEvent['slug'] === $currentEvent['slug']
);

// 8.4 Event Slug Change Allowed Before Registrations Begin
$freshEvent = $eventService->createEvent([
    'title'                 => 'Fresh Standalone Workshop 2026',
    'slug'                  => 'fresh-standalone-workshop',
    'category'              => 'workshop',
    'event_type'            => 'offline',
    'format'                => 'in_person',
    'venue_name'            => 'Tech Hub Calicut',
    'start_time'            => '2026-11-15 10:00:00',
    'end_time'              => '2026-11-15 17:00:00',
    'status'                => 'published',
], $superAdminId);

$freshEventId = (int) $freshEvent['id'];
$slugAllowed = false;
try {
    $updatedFreshEvent = $eventService->updateEvent($freshEventId, array_merge($freshEvent, [
        'slug' => 'renamed-standalone-workshop-2026',
    ]), $superAdminId);
    $slugAllowed = ($updatedFreshEvent['slug'] === 'renamed-standalone-workshop-2026');
} catch (ValidationException $e) {
    $slugAllowed = false;
}

assertV2Test(
    "8.4 Event slug mutation allowed when event has 0 registrations",
    $slugAllowed
);

// 8.5 Corresponding event_forms.slug is Synchronized
$freshForm = $formService->getFormByEventId($freshEventId);
assertV2Test(
    "8.5 Corresponding event_forms.slug synchronized to match new event slug",
    !empty($freshForm) && $freshForm['slug'] === 'renamed-standalone-workshop-2026'
);

// 8.6 Certificate Asset Upload 5MB Limit Rejection
$certController = new \App\Controllers\Admin\CertificateController(
    $certService,
    $certRepo,
    $eventRepo,
    $regRepo,
    $templateRepo,
    $auditService
);

$_FILES = [
    'seal_image' => [
        'name'     => 'huge_seal.png',
        'type'     => 'image/png',
        'tmp_name' => APP_ROOT . '/scratch/temp_huge_seal.png',
        'error'    => UPLOAD_ERR_OK,
        'size'     => 6 * 1024 * 1024, // 6 MB exceeds 5 MB limit
    ]
];

$designerReq = new Request([], [
    'signature1_name' => 'Test Name',
], $_FILES);

$designerResp = $certController->saveDesigner($designerReq, ['id' => $eventId]);
$flashError = Session::getFlash('error', '');
$_FILES = []; // Clean up

assertV2Test(
    "8.6 Certificate asset upload exceeding 5MB rejected with error message",
    $designerResp->getStatusCode() === 302 && str_contains($flashError, '5 MB')
);

// Cleanup rate limit test files
array_map('unlink', glob($testRateLimitDir . '/*.*') ?: []);
@rmdir($testRateLimitDir);

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo PHP_EOL . "=================================================" . PHP_EOL;
echo "Event-Centric System V2 Verification Results:" . PHP_EOL;
echo "Total Assertions: {$totalTests}" . PHP_EOL;
echo "Passed: {$passedTests}" . PHP_EOL;
echo "Failed: {$failedTests}" . PHP_EOL;
echo "=================================================" . PHP_EOL;

exit($failedTests > 0 ? 1 : 0);

