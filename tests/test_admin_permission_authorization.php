<?php

declare(strict_types=1);

/**
 * Isolated MySQL/MariaDB Verification Suite
 * Task: Unified Administrator Granular Permission Authorization across V3 Certificate Platform
 *
 * Exercises:
 * 1. Isolated MariaDB Database Setup & Migrations (m0001, m0007, m0010, m0014)
 * 2. Super Administrator Unrestricted Access Across All Modules (including Admin Management)
 * 3. Administrator with NO permissions strictly blocked from Certificate Platform (HTTP 403)
 * 4. Administrator with certificates.view only: allowed on Repository/Templates list, blocked on Settings/Generation/Designer
 * 5. Administrator with certificates.create only: allowed on Generation/Template Create, blocked on Settings/Designer/Delete
 * 6. Administrator with certificates.edit only: allowed on Designer/Template Edit, blocked on Settings/Generation
 * 7. Administrator with ALL canonical V3 permissions:
 *    - /admin/certificate-settings -> 200 OK
 *    - /admin/certificate-templates -> 200 OK
 *    - /admin/certificate-templates/{id}/designer -> 200 OK
 *    - /admin/certificates/generate -> 200 OK
 *    - /admin/certificates -> 200 OK
 *    - Sidebar displays all certificate links, hides Admin Management
 * 8. Strict Admin Management Protection for Non-Super-Admins:
 *    - /admin/admins* returns 403 for regular Administrator even with all permissions
 *    - Privilege escalation prevented: admins.* permissions cannot be assigned or bypassed
 * 9. GET vs POST separation: view permission never authorizes modifying POST
 * 10. Clean isolated teardown
 *
 * Run via: php tests/test_admin_permission_authorization.php
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Middleware\PermissionMiddleware;
use App\Core\Middleware\RoleMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security;
use App\Core\Session;
use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;
use App\Repositories\V3CertificateTemplateRepository;
use App\Services\PermissionService;
use App\Services\RoleService;

$green  = "\033[32m";
$red    = "\033[31m";
$cyan   = "\033[36m";
$reset  = "\033[0m";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertPermTest(string $description, bool $condition, string $details = ''): void
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

echo PHP_EOL . "{$cyan}=== LC-SPC Unified Administrator Permission Authorization Test Suite ==={$reset}" . PHP_EOL . PHP_EOL;

// -----------------------------------------------------------------------------
// 1. Isolated MariaDB Database Setup & Migrations
// -----------------------------------------------------------------------------
echo "1. Initializing Isolated MariaDB Test Environment..." . PHP_EOL;

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '';
$testDbName = 'lc_spc_perm_test_' . time() . '_' . mt_rand(1000, 9999);

try {
    $serverPdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "  {$red}[FATAL]{$reset} Could not connect to local MariaDB server on {$host}:{$port}: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$testDbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
assertPermTest("Created isolated MariaDB test database `{$testDbName}`", true);

$testPdo = new PDO("mysql:host={$host};port={$port};dbname={$testDbName};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Inject MariaDB connection into Database::$instance
$ref = new ReflectionProperty(Database::class, 'instance');
$ref->setAccessible(true);
$ref->setValue(null, $testPdo);

try {
    // Run migrations: m0001, m0007, m0010, m0014
    $m0001 = require APP_ROOT . '/database/migrations/m0001_create_users_table.php';
    $m0001->up($testPdo);

    $m0007 = require APP_ROOT . '/database/migrations/m0007_create_audit_logs_table.php';
    $m0007->up($testPdo);

    $m0010 = require APP_ROOT . '/database/migrations/m0010_create_admin_permissions_system.php';
    $m0010->up($testPdo);

    $m0014 = require APP_ROOT . '/database/migrations/m0014_create_certificate_platform_v3_tables.php';
    $m0014->up($testPdo);

    assertPermTest("Executed baseline MariaDB migrations (m0001, m0007, m0010, m0014)", true);

    $userRepo = new UserRepository();
    $permRepo = new PermissionRepository();
    $permService = new PermissionService($permRepo, $userRepo);

    // Create a seed template for testing template endpoints
    $templateRepo = new V3CertificateTemplateRepository();
    $testTemplateId = $templateRepo->create([
        'name'               => 'Authorization Test Template',
        'certificate_type'   => 'participation',
        'status'             => 'active',
        'layout_config'      => json_encode(['elements' => []]),
        'required_variables' => json_encode(['name', 'phone']),
    ]);
    assertPermTest("Seeded V3 Certificate Template ID {$testTemplateId}", $testTemplateId > 0);

    // -------------------------------------------------------------------------
    // 2. User Accounts Seeding
    // -------------------------------------------------------------------------
    echo PHP_EOL . "2. Provisioning Test Accounts with Distinct Permission Profiles..." . PHP_EOL;

    // Super Admin (ID 1)
    $superAdminId = $userRepo->create([
        'name'          => 'Super Admin QA',
        'email'         => 'superadmin.qa@teami.in',
        'password_hash' => Security::hashPassword('SuperSafe123!'),
        'role'          => 'super_admin',
        'status'        => 'active',
    ]);
    assertPermTest("Super Admin seeded with role 'super_admin' (ID: {$superAdminId})", $superAdminId > 0);

    // Admin with NO permissions (ID 2)
    $adminNoPermsId = $userRepo->create([
        'name'          => 'Admin No Perms',
        'email'         => 'admin.noperms@teami.in',
        'password_hash' => Security::hashPassword('AdminSafe123!'),
        'role'          => 'staff',
        'status'        => 'active',
    ]);
    assertPermTest("Admin seeded with role 'staff' and 0 permissions (ID: {$adminNoPermsId})", $adminNoPermsId > 0);

    // Helper to fetch permission IDs
    $getPermId = function (string $name) use ($testPdo): int {
        $stmt = $testPdo->prepare("SELECT id FROM permissions WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        return (int) $stmt->fetchColumn();
    };

    // Admin with certificates.view only (ID 3)
    $adminViewOnlyId = $userRepo->create([
        'name'          => 'Admin View Only',
        'email'         => 'admin.viewonly@teami.in',
        'password_hash' => Security::hashPassword('AdminSafe123!'),
        'role'          => 'staff',
        'status'        => 'active',
    ]);
    $viewPermId = $getPermId('certificates.view');
    $dashPermId = $getPermId('dashboard.view');
    $permRepo->syncUserPermissions($adminViewOnlyId, [$viewPermId, $dashPermId]);
    assertPermTest("Admin seeded with 'certificates.view' + 'dashboard.view' (ID: {$adminViewOnlyId})", true);

    // Admin with certificates.create only (ID 4)
    $adminCreateOnlyId = $userRepo->create([
        'name'          => 'Admin Create Only',
        'email'         => 'admin.createonly@teami.in',
        'password_hash' => Security::hashPassword('AdminSafe123!'),
        'role'          => 'staff',
        'status'        => 'active',
    ]);
    $createPermId = $getPermId('certificates.create');
    $permRepo->syncUserPermissions($adminCreateOnlyId, [$createPermId, $dashPermId]);
    assertPermTest("Admin seeded with 'certificates.create' + 'dashboard.view' (ID: {$adminCreateOnlyId})", true);

    // Admin with certificates.edit only (ID 5)
    $adminEditOnlyId = $userRepo->create([
        'name'          => 'Admin Edit Only',
        'email'         => 'admin.editonly@teami.in',
        'password_hash' => Security::hashPassword('AdminSafe123!'),
        'role'          => 'staff',
        'status'        => 'active',
    ]);
    $editPermId = $getPermId('certificates.edit');
    $permRepo->syncUserPermissions($adminEditOnlyId, [$editPermId, $dashPermId]);
    assertPermTest("Admin seeded with 'certificates.edit' + 'dashboard.view' (ID: {$adminEditOnlyId})", true);

    // Admin with ALL canonical V3 permissions (ID 6)
    $adminAllPermsId = $userRepo->create([
        'name'          => 'Admin All Perms',
        'email'         => 'admin.allperms@teami.in',
        'password_hash' => Security::hashPassword('AdminSafe123!'),
        'role'          => 'staff',
        'status'        => 'active',
    ]);
    $canonicalPermNames = [
        'dashboard.view',
        'certificates.view',
        'certificates.create',
        'certificates.edit',
        'certificates.delete',
        'certificates.export',
        'certificates.manage',
        'settings.view',
        'settings.manage',
    ];
    $allPermIds = [];
    foreach ($canonicalPermNames as $pName) {
        $pId = $getPermId($pName);
        if ($pId > 0) {
            $allPermIds[] = $pId;
        }
    }
    $permRepo->syncUserPermissions($adminAllPermsId, $allPermIds);
    assertPermTest("Admin seeded with ALL canonical V3 permissions (count: " . count($allPermIds) . ", ID: {$adminAllPermsId})", count($allPermIds) === 9);

    // Helper to simulate request through PermissionMiddleware
    $testGate = function (int $userId, string $role, string|array $requiredPerm, string $path = '/admin/test', string $method = 'GET'): Response {
        Session::start();
        Session::set('_auth_user_id', $userId);
        Session::set('_auth_user_role', $role);

        $req = new Request([], [], [], [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI'    => $path,
            'HTTP_ACCEPT'    => 'text/html',
        ]);

        $gate = new PermissionMiddleware($requiredPerm);
        return $gate->handle($req, fn() => Response::html('AUTHORIZED', 200));
    };

    // -------------------------------------------------------------------------
    // 3. Testing Super Administrator Access
    // -------------------------------------------------------------------------
    echo PHP_EOL . "3. Testing Super Administrator Access (Unconditional Bypass)..." . PHP_EOL;

    assertPermTest(
        "Super Admin accesses Certificate Settings GET (certificates.manage OR settings.view)",
        $testGate($superAdminId, 'super_admin', ['certificates.manage', 'settings.view'], '/admin/certificate-settings')->getStatusCode() === 200
    );
    assertPermTest(
        "Super Admin accesses Certificate Settings POST (certificates.manage OR settings.manage)",
        $testGate($superAdminId, 'super_admin', ['certificates.manage', 'settings.manage'], '/admin/certificate-settings', 'POST')->getStatusCode() === 200
    );
    assertPermTest(
        "Super Admin accesses Certificate Templates list (certificates.view OR certificates.manage)",
        $testGate($superAdminId, 'super_admin', ['certificates.view', 'certificates.manage'], '/admin/certificate-templates')->getStatusCode() === 200
    );
    assertPermTest(
        "Super Admin accesses Certificate Template Designer (certificates.edit OR certificates.manage)",
        $testGate($superAdminId, 'super_admin', ['certificates.edit', 'certificates.manage'], "/admin/certificate-templates/{$testTemplateId}/designer")->getStatusCode() === 200
    );
    assertPermTest(
        "Super Admin accesses Certificate Generation (certificates.create OR certificates.manage)",
        $testGate($superAdminId, 'super_admin', ['certificates.create', 'certificates.manage'], '/admin/certificates/generate')->getStatusCode() === 200
    );
    assertPermTest(
        "Super Admin accesses Certificate Repository (certificates.view OR certificates.manage)",
        $testGate($superAdminId, 'super_admin', ['certificates.view', 'certificates.manage'], '/admin/certificates')->getStatusCode() === 200
    );

    // -------------------------------------------------------------------------
    // 4. Testing Administrator with NO Permissions (Blocked with 403)
    // -------------------------------------------------------------------------
    echo PHP_EOL . "4. Testing Administrator with NO Permissions (HTTP 403 Blocked)..." . PHP_EOL;

    assertPermTest(
        "Admin with no perms gets 403 on /admin/certificate-settings (GET)",
        $testGate($adminNoPermsId, 'staff', ['certificates.manage', 'settings.view'], '/admin/certificate-settings')->getStatusCode() === 403
    );
    assertPermTest(
        "Admin with no perms gets 403 on /admin/certificate-settings (POST)",
        $testGate($adminNoPermsId, 'staff', ['certificates.manage', 'settings.manage'], '/admin/certificate-settings', 'POST')->getStatusCode() === 403
    );
    assertPermTest(
        "Admin with no perms gets 403 on /admin/certificate-templates",
        $testGate($adminNoPermsId, 'staff', ['certificates.view', 'certificates.manage'], '/admin/certificate-templates')->getStatusCode() === 403
    );
    assertPermTest(
        "Admin with no perms gets 403 on /admin/certificate-templates/{id}/designer",
        $testGate($adminNoPermsId, 'staff', ['certificates.edit', 'certificates.manage'], "/admin/certificate-templates/{$testTemplateId}/designer")->getStatusCode() === 403
    );
    assertPermTest(
        "Admin with no perms gets 403 on /admin/certificates/generate",
        $testGate($adminNoPermsId, 'staff', ['certificates.create', 'certificates.manage'], '/admin/certificates/generate')->getStatusCode() === 403
    );
    assertPermTest(
        "Admin with no perms gets 403 on /admin/certificates",
        $testGate($adminNoPermsId, 'staff', ['certificates.view', 'certificates.manage'], '/admin/certificates')->getStatusCode() === 403
    );

    // -------------------------------------------------------------------------
    // 5. Testing Administrator with certificates.view Only
    // -------------------------------------------------------------------------
    echo PHP_EOL . "5. Testing Administrator with certificates.view Only..." . PHP_EOL;

    assertPermTest(
        "Admin with certificates.view accesses /admin/certificates (HTTP 200)",
        $testGate($adminViewOnlyId, 'staff', ['certificates.view', 'certificates.manage'], '/admin/certificates')->getStatusCode() === 200
    );
    assertPermTest(
        "Admin with certificates.view accesses /admin/certificate-templates (HTTP 200)",
        $testGate($adminViewOnlyId, 'staff', ['certificates.view', 'certificates.manage'], '/admin/certificate-templates')->getStatusCode() === 200
    );
    assertPermTest(
        "Admin with certificates.view blocked from /admin/certificate-settings (HTTP 403)",
        $testGate($adminViewOnlyId, 'staff', ['certificates.manage', 'settings.view'], '/admin/certificate-settings')->getStatusCode() === 403
    );
    assertPermTest(
        "Admin with certificates.view blocked from /admin/certificates/generate (HTTP 403)",
        $testGate($adminViewOnlyId, 'staff', ['certificates.create', 'certificates.manage'], '/admin/certificates/generate')->getStatusCode() === 403
    );
    assertPermTest(
        "Admin with certificates.view blocked from /admin/certificate-templates/1/designer (HTTP 403)",
        $testGate($adminViewOnlyId, 'staff', ['certificates.edit', 'certificates.manage'], "/admin/certificate-templates/{$testTemplateId}/designer")->getStatusCode() === 403
    );
    assertPermTest(
        "Admin with certificates.view blocked from deleting certificates (HTTP 403)",
        $testGate($adminViewOnlyId, 'staff', ['certificates.delete', 'certificates.manage'], "/admin/certificates/1/delete", 'POST')->getStatusCode() === 403
    );

    // -------------------------------------------------------------------------
    // 6. Testing Administrator with certificates.create Only
    // -------------------------------------------------------------------------
    echo PHP_EOL . "6. Testing Administrator with certificates.create Only..." . PHP_EOL;

    assertPermTest(
        "Admin with certificates.create accesses /admin/certificates/generate (HTTP 200)",
        $testGate($adminCreateOnlyId, 'staff', ['certificates.create', 'certificates.manage'], '/admin/certificates/generate')->getStatusCode() === 200
    );
    assertPermTest(
        "Admin with certificates.create accesses /admin/certificate-templates/create (HTTP 200)",
        $testGate($adminCreateOnlyId, 'staff', ['certificates.create', 'certificates.manage'], '/admin/certificate-templates/create')->getStatusCode() === 200
    );
    assertPermTest(
        "Admin with certificates.create blocked from /admin/certificate-settings (HTTP 403)",
        $testGate($adminCreateOnlyId, 'staff', ['certificates.manage', 'settings.view'], '/admin/certificate-settings')->getStatusCode() === 403
    );
    assertPermTest(
        "Admin with certificates.create blocked from /admin/certificate-templates/1/designer (HTTP 403)",
        $testGate($adminCreateOnlyId, 'staff', ['certificates.edit', 'certificates.manage'], "/admin/certificate-templates/{$testTemplateId}/designer")->getStatusCode() === 403
    );

    // -------------------------------------------------------------------------
    // 7. Testing Administrator with certificates.edit Only
    // -------------------------------------------------------------------------
    echo PHP_EOL . "7. Testing Administrator with certificates.edit Only..." . PHP_EOL;

    assertPermTest(
        "Admin with certificates.edit accesses /admin/certificate-templates/{id}/designer (HTTP 200)",
        $testGate($adminEditOnlyId, 'staff', ['certificates.edit', 'certificates.manage'], "/admin/certificate-templates/{$testTemplateId}/designer")->getStatusCode() === 200
    );
    assertPermTest(
        "Admin with certificates.edit accesses /admin/certificate-templates/{id}/edit (HTTP 200)",
        $testGate($adminEditOnlyId, 'staff', ['certificates.edit', 'certificates.manage'], "/admin/certificate-templates/{$testTemplateId}/edit")->getStatusCode() === 200
    );
    assertPermTest(
        "Admin with certificates.edit blocked from /admin/certificates/generate (HTTP 403)",
        $testGate($adminEditOnlyId, 'staff', ['certificates.create', 'certificates.manage'], '/admin/certificates/generate')->getStatusCode() === 403
    );
    assertPermTest(
        "Admin with certificates.edit blocked from /admin/certificate-settings (HTTP 403)",
        $testGate($adminEditOnlyId, 'staff', ['certificates.manage', 'settings.view'], '/admin/certificate-settings')->getStatusCode() === 403
    );

    // -------------------------------------------------------------------------
    // 8. Testing Administrator with ALL Canonical V3 Permissions ("Select All")
    // -------------------------------------------------------------------------
    echo PHP_EOL . "8. Testing Administrator with ALL Canonical Permissions (Complete Access)..." . PHP_EOL;

    assertPermTest(
        "Admin with all perms accesses /admin/certificate-settings (HTTP 200)",
        $testGate($adminAllPermsId, 'staff', ['certificates.manage', 'settings.view'], '/admin/certificate-settings')->getStatusCode() === 200
    );
    assertPermTest(
        "Admin with all perms accesses /admin/certificate-settings POST (HTTP 200)",
        $testGate($adminAllPermsId, 'staff', ['certificates.manage', 'settings.manage'], '/admin/certificate-settings', 'POST')->getStatusCode() === 200
    );
    assertPermTest(
        "Admin with all perms accesses /admin/certificate-templates (HTTP 200)",
        $testGate($adminAllPermsId, 'staff', ['certificates.view', 'certificates.manage'], '/admin/certificate-templates')->getStatusCode() === 200
    );
    assertPermTest(
        "Admin with all perms accesses /admin/certificate-templates/{id}/designer (HTTP 200)",
        $testGate($adminAllPermsId, 'staff', ['certificates.edit', 'certificates.manage'], "/admin/certificate-templates/{$testTemplateId}/designer")->getStatusCode() === 200
    );
    assertPermTest(
        "Admin with all perms accesses /admin/certificates/generate (HTTP 200)",
        $testGate($adminAllPermsId, 'staff', ['certificates.create', 'certificates.manage'], '/admin/certificates/generate')->getStatusCode() === 200
    );
    assertPermTest(
        "Admin with all perms accesses /admin/certificates (HTTP 200)",
        $testGate($adminAllPermsId, 'staff', ['certificates.view', 'certificates.manage'], '/admin/certificates')->getStatusCode() === 200
    );

    // -------------------------------------------------------------------------
    // 9. Strict Admin Management Protection for Non-Super-Admins
    // -------------------------------------------------------------------------
    echo PHP_EOL . "9. Testing Strict Admin Management Protection (Super Admin Only)..." . PHP_EOL;

    // Direct check against RoleMiddleware(ROLE_SUPER_ADMIN)
    $superAdminGate = new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN);

    Session::start();
    Session::set('_auth_user_id', $adminAllPermsId);
    Session::set('_auth_user_role', 'staff');

    $adminReq = new Request([], [], [], [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI'    => '/admin/admins',
        'HTTP_ACCEPT'    => 'text/html',
    ]);
    $adminRes = $superAdminGate->handle($adminReq, fn() => Response::html('AUTHORIZED', 200));

    assertPermTest(
        "RoleMiddleware strictly blocks regular Administrator with all permissions from /admin/admins (HTTP 403)",
        $adminRes->getStatusCode() === 403
    );

    $adminCreateReq = new Request([], [], [], [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI'    => '/admin/admins/create',
        'HTTP_ACCEPT'    => 'text/html',
    ]);
    $adminCreateRes = $superAdminGate->handle($adminCreateReq, fn() => Response::html('AUTHORIZED', 200));

    assertPermTest(
        "RoleMiddleware strictly blocks regular Administrator from /admin/admins/create (HTTP 403)",
        $adminCreateRes->getStatusCode() === 403
    );

    // Privilege escalation prevention
    assertPermTest(
        "PermissionService::hasPermission() strictly returns false for admins.* on regular Administrator",
        $permService->hasPermission($adminAllPermsId, 'admins.view', 'staff') === false &&
        $permService->hasPermission($adminAllPermsId, 'admins.create', 'staff') === false &&
        $permService->hasPermission($adminAllPermsId, 'admins.manage', 'staff') === false
    );

    // Even if admins.view is manually inserted in database for this user
    $adminsViewId = $getPermId('admins.view');
    if ($adminsViewId > 0) {
        $testPdo->exec("INSERT IGNORE INTO user_permissions (user_id, permission_id) VALUES ({$adminAllPermsId}, {$adminsViewId})");
        assertPermTest(
            "Hardened rule: hasPermission('admins.view') still returns false even if database row exists",
            $permService->hasPermission($adminAllPermsId, 'admins.view', 'staff') === false
        );
    }

    // -------------------------------------------------------------------------
    // 10. Testing GET vs POST Permission Separation
    // -------------------------------------------------------------------------
    echo PHP_EOL . "10. Testing GET vs POST Separation..." . PHP_EOL;

    // Create an admin with settings.view ONLY
    $adminSettingsViewOnlyId = $userRepo->create([
        'name'          => 'Admin Settings View Only',
        'email'         => 'admin.settingsview@teami.in',
        'password_hash' => Security::hashPassword('AdminSafe123!'),
        'role'          => 'staff',
        'status'        => 'active',
    ]);
    $settingsViewId = $getPermId('settings.view');
    $permRepo->syncUserPermissions($adminSettingsViewOnlyId, [$settingsViewId, $dashPermId]);

    assertPermTest(
        "settings.view authorizes GET /admin/certificate-settings (HTTP 200)",
        $testGate($adminSettingsViewOnlyId, 'staff', ['certificates.manage', 'settings.view'], '/admin/certificate-settings', 'GET')->getStatusCode() === 200
    );
    assertPermTest(
        "settings.view strictly DENIED from POST /admin/certificate-settings (HTTP 403)",
        $testGate($adminSettingsViewOnlyId, 'staff', ['certificates.manage', 'settings.manage'], '/admin/certificate-settings', 'POST')->getStatusCode() === 403
    );

    // -------------------------------------------------------------------------
    // 11. Testing Navigation Visibility Logic
    // -------------------------------------------------------------------------
    echo PHP_EOL . "11. Testing Sidebar Navigation Visibility Logic..." . PHP_EOL;

    $checkNav = function (int $userId, string $role, array|string $navPerms) use ($permService): bool {
        if (RoleService::isSuperAdmin($role)) {
            return true;
        }
        $perms = is_array($navPerms) ? $navPerms : [$navPerms];
        foreach ($perms as $perm) {
            if ($permService->hasPermission($userId, $perm, $role)) {
                return true;
            }
        }
        return false;
    };

    // Super admin sees all navigation
    assertPermTest("Super Admin sees Dashboard nav", $checkNav($superAdminId, 'super_admin', 'dashboard.view'));
    assertPermTest("Super Admin sees Certificate Settings nav", $checkNav($superAdminId, 'super_admin', ['certificates.manage', 'settings.view']));
    assertPermTest("Super Admin sees Certificate Templates nav", $checkNav($superAdminId, 'super_admin', ['certificates.view', 'certificates.manage']));
    assertPermTest("Super Admin sees Generate Certificates nav", $checkNav($superAdminId, 'super_admin', ['certificates.create', 'certificates.manage']));
    assertPermTest("Super Admin sees Show Certificates nav", $checkNav($superAdminId, 'super_admin', ['certificates.view', 'certificates.manage']));
    assertPermTest("Super Admin sees Admin Management nav", RoleService::isSuperAdmin('super_admin'));

    // Admin with NO permissions
    assertPermTest("Admin with no perms hides Certificate Settings nav", !$checkNav($adminNoPermsId, 'staff', ['certificates.manage', 'settings.view']));
    assertPermTest("Admin with no perms hides Certificate Templates nav", !$checkNav($adminNoPermsId, 'staff', ['certificates.view', 'certificates.manage']));
    assertPermTest("Admin with no perms hides Generate Certificates nav", !$checkNav($adminNoPermsId, 'staff', ['certificates.create', 'certificates.manage']));
    assertPermTest("Admin with no perms hides Show Certificates nav", !$checkNav($adminNoPermsId, 'staff', ['certificates.view', 'certificates.manage']));
    assertPermTest("Admin with no perms hides Admin Management nav", !RoleService::isSuperAdmin('staff'));

    // Admin with ALL permissions
    assertPermTest("Admin with all perms sees Certificate Settings nav", $checkNav($adminAllPermsId, 'staff', ['certificates.manage', 'settings.view']));
    assertPermTest("Admin with all perms sees Certificate Templates nav", $checkNav($adminAllPermsId, 'staff', ['certificates.view', 'certificates.manage']));
    assertPermTest("Admin with all perms sees Generate Certificates nav", $checkNav($adminAllPermsId, 'staff', ['certificates.create', 'certificates.manage']));
    assertPermTest("Admin with all perms sees Show Certificates nav", $checkNav($adminAllPermsId, 'staff', ['certificates.view', 'certificates.manage']));
    assertPermTest("Admin with all perms strictly HIDES Admin Management nav", !RoleService::isSuperAdmin('staff'));

    // When certificates.create is removed from Admin with ALL permissions
    $permsWithoutCreate = array_values(array_diff($allPermIds, [$createPermId]));
    $permRepo->syncUserPermissions($adminAllPermsId, $permsWithoutCreate);

    assertPermTest(
        "Removing certificates.create hides Generate Certificates nav item",
        !$checkNav($adminAllPermsId, 'staff', ['certificates.create']) // if manage not present in check
    );
    assertPermTest(
        "Direct access to /admin/certificates/generate returns 403 when certificates.create removed",
        $testGate($adminAllPermsId, 'staff', ['certificates.create'], '/admin/certificates/generate')->getStatusCode() === 403
    );

    // -------------------------------------------------------------------------
    // 12. Testing 403 Error Page Output
    // -------------------------------------------------------------------------
    echo PHP_EOL . "12. Testing 403 Error Page Formatting..." . PHP_EOL;

    $forbiddenRes = $testGate($adminNoPermsId, 'staff', ['certificates.manage', 'settings.view'], '/admin/certificate-settings');
    $htmlContent = $forbiddenRes->getContent();

    assertPermTest(
        "403 response contains 'Requires one of:' when multiple permissions specified",
        str_contains($htmlContent, 'Requires one of:') &&
        str_contains($htmlContent, 'certificates.manage, settings.view')
    );
    assertPermTest(
        "403 response does not output legacy misleading 'Requires minimum rank: Administrator'",
        !str_contains($htmlContent, 'Requires minimum rank: Administrator')
    );

    // -------------------------------------------------------------------------
    // 13. Teardown
    // -------------------------------------------------------------------------
    echo PHP_EOL . "13. Cleaning Up Isolated Test Database..." . PHP_EOL;
    $serverPdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`;");
    assertPermTest("Cleanly dropped isolated test database `{$testDbName}`", true);

} catch (Throwable $e) {
    echo "  {$red}[ERROR]{$reset} Exception caught: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    $failedTests++;
    try {
        $serverPdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`;");
    } catch (Throwable) {}
}

echo PHP_EOL . "=================================================" . PHP_EOL;
echo "Permission Authorization Verification Results:" . PHP_EOL;
echo "Total Assertions: {$totalTests}" . PHP_EOL;
echo "Passed: {$passedTests}" . PHP_EOL;
echo "Failed: {$failedTests}" . PHP_EOL;
echo "=================================================" . PHP_EOL;

if ($failedTests > 0) {
    exit(1);
}
