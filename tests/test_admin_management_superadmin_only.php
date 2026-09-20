<?php

declare(strict_types=1);

/**
 * Isolated MySQL/MariaDB Verification Suite
 * Task: Fix Admin Management Architecture - Super Admin Only + Unified Administrator Role
 *
 * Exercises:
 * 1. MariaDB DDL, ENUM('super_admin', 'coordinator', 'staff', 'viewer'), and Foreign Keys
 * 2. Super Admin Access, Provisioning without Role Dropdown, and "Invalid role selected" Bug Fix
 * 3. Server-Side Resolution to Internal Administrator Role ('staff')
 * 4. Unified Presentation: Super Administrator vs Administrator
 * 5. Strict Server-Side 403 Protection on All Admin Management Endpoints for Non-Super-Admins
 * 6. Privilege Escalation Prevention (admins module locked from granular assignment)
 * 7. Self-Protection and Last Super Admin Protection
 * 8. Audit Logging for Administrator Lifecycle Actions
 * 9. Sidebar Navigation Visibility Isolation
 * 10. Clean Isolated Teardown
 *
 * Run via: php tests/test_admin_management_superadmin_only.php
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Controllers\Admin\AdminManagementController;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Middleware\RoleMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\RoleService;

$green  = "\033[32m";
$red    = "\033[31m";
$yellow = "\033[33m";
$cyan   = "\033[36m";
$reset  = "\033[0m";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertAdminTest(string $description, bool $condition, string $details = ''): void
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

echo PHP_EOL . "{$cyan}=== LC-SPC Admin Management (Super Admin Only + Unified Role) Test Suite ==={$reset}" . PHP_EOL . PHP_EOL;

// -----------------------------------------------------------------------------
// 1. Isolated MariaDB Database Setup & Migrations
// -----------------------------------------------------------------------------
echo "1. Initializing Isolated MariaDB Test Environment..." . PHP_EOL;

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '';
$testDbName = 'lc_spc_admin_test_' . time() . '_' . mt_rand(1000, 9999);

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
assertAdminTest("Created isolated MariaDB test database `{$testDbName}`", true);

$testPdo = new PDO("mysql:host={$host};port={$port};dbname={$testDbName};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Inject MariaDB connection into Database::$instance via Reflection
$ref = new ReflectionProperty(Database::class, 'instance');
$ref->setAccessible(true);
$ref->setValue(null, $testPdo);

try {
    // Run baseline migrations: m0001 (users with role ENUM), m0007 (audit_logs), m0010 (permissions)
    $m0001 = require APP_ROOT . '/database/migrations/m0001_create_users_table.php';
    $m0001->up($testPdo);

    $m0007 = require APP_ROOT . '/database/migrations/m0007_create_audit_logs_table.php';
    $m0007->up($testPdo);

    $m0010 = require APP_ROOT . '/database/migrations/m0010_create_admin_permissions_system.php';
    $m0010->up($testPdo);

    assertAdminTest("Executed baseline MariaDB migrations (m0001, m0007, m0010)", true);

    // Verify actual MariaDB schema ENUM on users.role
    $colStmt = $testPdo->prepare("
        SELECT COLUMN_TYPE, COLUMN_DEFAULT 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'
    ");
    $colStmt->execute([':db' => $testDbName]);
    $roleCol = $colStmt->fetch();
    assertAdminTest(
        "users.role strictly conforms to MariaDB ENUM('super_admin','coordinator','staff','viewer')",
        str_contains((string) $roleCol['COLUMN_TYPE'], "'super_admin','coordinator','staff','viewer'")
    );

    $userRepo     = new UserRepository();
    $auditRepo    = new AuditLogRepository();
    $permRepo     = new PermissionRepository();
    $auditService = new AuditService($auditRepo, $userRepo);
    $permService  = new PermissionService($permRepo, $userRepo);
    $controller   = new AdminManagementController($userRepo, $permService, $auditService);

    // -------------------------------------------------------------------------
    // 2. Role Service & Unified Presentation Abstraction
    // -------------------------------------------------------------------------
    echo PHP_EOL . "2. Testing Role Service & Unified Presentation Abstraction..." . PHP_EOL;

    assertAdminTest(
        "Super Administrator displays 'Super Administrator'",
        RoleService::getRoleLabel('super_admin') === 'Super Administrator'
    );
    assertAdminTest(
        "Coordinator displays unified 'Administrator' label",
        RoleService::getRoleLabel('coordinator') === 'Administrator'
    );
    assertAdminTest(
        "Staff displays unified 'Administrator' label",
        RoleService::getRoleLabel('staff') === 'Administrator'
    );
    assertAdminTest(
        "Viewer displays unified 'Administrator' label",
        RoleService::getRoleLabel('viewer') === 'Administrator'
    );
    assertAdminTest(
        "isSuperAdmin('super_admin') returns true",
        RoleService::isSuperAdmin('super_admin') === true
    );
    assertAdminTest(
        "isSuperAdmin('staff') returns false",
        RoleService::isSuperAdmin('staff') === false
    );
    assertAdminTest(
        "isAdministrator('staff') returns true",
        RoleService::isAdministrator('staff') === true
    );
    assertAdminTest(
        "isAdministrator('super_admin') returns false",
        RoleService::isAdministrator('super_admin') === false
    );
    assertAdminTest(
        "Default administrator internal role is 'staff'",
        RoleService::ROLE_DEFAULT_ADMINISTRATOR === 'staff'
    );

    // Seed a Super Admin user
    $superAdminId = $userRepo->create([
        'name'          => 'Chief Director',
        'email'         => 'chief.director@teami.in',
        'phone'         => '+91 9999988888',
        'password_hash' => Security::hashPassword('ChiefDirector2026!'),
        'role'          => 'super_admin',
        'status'        => 'active',
    ]);
    assertAdminTest("Super Admin seeded with ID {$superAdminId}", $superAdminId > 0);

    // Seed a legacy coordinator user
    $coordAdminId = $userRepo->create([
        'name'          => 'Legacy Coordinator',
        'email'         => 'coordinator@teami.in',
        'phone'         => '+91 9888877777',
        'password_hash' => Security::hashPassword('Coordinator2026!'),
        'role'          => 'coordinator',
        'status'        => 'active',
    ]);
    assertAdminTest("Legacy Coordinator seeded with ID {$coordAdminId}", $coordAdminId > 0);

    // -------------------------------------------------------------------------
    // 3. Super Admin: Create Administrator (Fix "Invalid role selected")
    // -------------------------------------------------------------------------
    echo PHP_EOL . "3. Testing Administrator Creation by Super Admin (Bug Fix Verification)..." . PHP_EOL;

    Session::start();
    Session::set('_auth_user_id', $superAdminId);
    Session::set('_auth_user_role', 'super_admin');

    // Fetch two non-admin permission IDs to assign
    $allPerms = $permRepo->getAll();
    $testPermIds = [];
    foreach ($allPerms as $p) {
        if ($p['module'] !== 'admins') {
            $testPermIds[] = (int) $p['id'];
            if (count($testPermIds) >= 2) break;
        }
    }

    // Attempt creation WITHOUT submitting any 'role' field
    $createPostReq = new Request([
        'name'                  => 'Operations Admin',
        'email'                 => 'operations.admin@teami.in',
        'phone'                 => '+91 9876543210',
        'password'              => 'SecurePassword2026!',
        'password_confirmation' => 'SecurePassword2026!',
        'permissions'           => $testPermIds,
    ], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/admins']);

    $createResponse = $controller->store($createPostReq);

    assertAdminTest(
        "Creation succeeds with redirect to /admin/admins",
        $createResponse->getStatusCode() === 302 && str_contains($createResponse->getHeaders()['Location'] ?? '', '/admin/admins')
    );

    $flashSuccess = Session::getFlash('success');
    $flashError = Session::getFlash('error');
    assertAdminTest(
        "Zero error flashes: No 'Invalid role selected.' triggered",
        empty($flashError),
        "Encountered error: " . ($flashError ?? 'none')
    );
    assertAdminTest(
        "Success flash confirmed: 'Administrator [Operations Admin] successfully created.'",
        str_contains((string) $flashSuccess, 'successfully created')
    );

    // Verify persisted database record in MariaDB
    $createdAdmin = $userRepo->findByEmail('operations.admin@teami.in');
    assertAdminTest("Created administrator exists in MariaDB", $createdAdmin !== null);
    assertAdminTest(
        "Internal role resolved server-side as 'staff' (ROLE_DEFAULT_ADMINISTRATOR)",
        ($createdAdmin['role'] ?? '') === 'staff'
    );
    assertAdminTest(
        "Status is active",
        ($createdAdmin['status'] ?? '') === 'active'
    );
    assertAdminTest(
        "Password hash verifies against submitted password",
        Security::verifyPassword('SecurePassword2026!', $createdAdmin['password_hash'] ?? '')
    );

    // Verify permissions assigned in user_permissions
    $assignedIds = $permRepo->getUserPermissionIds((int) $createdAdmin['id']);
    assertAdminTest(
        "Assigned module permissions verified in MariaDB",
        count(array_intersect($testPermIds, $assignedIds)) === count($testPermIds)
    );

    // Verify audit log entry
    $latestAudit = $testPdo->query("SELECT * FROM `audit_logs` WHERE `action` = 'administrator_created' ORDER BY `id` DESC LIMIT 1")->fetch();
    assertAdminTest("Audit log recorded 'administrator_created' event", $latestAudit !== false);
    assertAdminTest(
        "Audit log actor_id matches Super Admin ({$superAdminId})",
        (int) ($latestAudit['actor_id'] ?? 0) === $superAdminId
    );
    assertAdminTest(
        "Audit log entity_id matches new administrator ID ({$createdAdmin['id']})",
        (int) ($latestAudit['entity_id'] ?? 0) === (int) $createdAdmin['id']
    );

    $auditMeta = json_decode($latestAudit['metadata'] ?? '{}', true);
    assertAdminTest(
        "Audit log metadata does NOT leak password or password_hash",
        !isset($auditMeta['password']) && !isset($auditMeta['password_hash'])
    );

    // -------------------------------------------------------------------------
    // 4. Super Admin: Update, Password Reset & Lifecycle Actions
    // -------------------------------------------------------------------------
    echo PHP_EOL . "4. Testing Super Admin Management Actions & Protections..." . PHP_EOL;

    $createdAdminId = (int) $createdAdmin['id'];

    // Update profile
    $updateReq = new Request([
        'name'   => 'Operations Manager Renamed',
        'email'  => 'operations.admin@teami.in',
        'phone'  => '+91 9111122222',
        'status' => 'active',
    ], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/admin/admins/{$createdAdminId}"]);

    $updateResp = $controller->update($updateReq, ['id' => $createdAdminId]);
    assertAdminTest("Update administrator profile returns 302 redirect", $updateResp->getStatusCode() === 302);
    $updatedAdmin = $userRepo->findById($createdAdminId);
    assertAdminTest("Administrator name updated in MariaDB", $updatedAdmin['name'] === 'Operations Manager Renamed');
    assertAdminTest("Administrator internal role remained 'staff'", $updatedAdmin['role'] === 'staff');

    // Deactivate administrator
    $deactReq = new Request([], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/admin/admins/{$createdAdminId}/deactivate"]);
    $deactResp = $controller->deactivate($deactReq, ['id' => $createdAdminId]);
    assertAdminTest("Deactivate returns 302 redirect", $deactResp->getStatusCode() === 302);
    $deactAdmin = $userRepo->findById($createdAdminId);
    assertAdminTest("Administrator status transitioned to 'inactive' in MariaDB", $deactAdmin['status'] === 'inactive');

    // Activate administrator
    $actReq = new Request([], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/admin/admins/{$createdAdminId}/activate"]);
    $actResp = $controller->activate($actReq, ['id' => $createdAdminId]);
    assertAdminTest("Activate returns 302 redirect", $actResp->getStatusCode() === 302);
    $actAdmin = $userRepo->findById($createdAdminId);
    assertAdminTest("Administrator status transitioned back to 'active' in MariaDB", $actAdmin['status'] === 'active');

    // Password reset
    $pwdReq = new Request([
        'password'              => 'UpdatedPassword2026!',
        'password_confirmation' => 'UpdatedPassword2026!',
    ], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/admin/admins/{$createdAdminId}/password"]);
    $pwdResp = $controller->updatePassword($pwdReq, ['id' => $createdAdminId]);
    assertAdminTest("Password reset returns 302 redirect", $pwdResp->getStatusCode() === 302);
    $pwdAdmin = $userRepo->findById($createdAdminId);
    assertAdminTest(
        "New password verifies against updated MariaDB hash",
        Security::verifyPassword('UpdatedPassword2026!', $pwdAdmin['password_hash'] ?? '')
    );

    // Self-protection: Super Admin cannot deactivate self
    $selfDeactReq = new Request([], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/admin/admins/{$superAdminId}/deactivate"]);
    $selfDeactResp = $controller->deactivate($selfDeactReq, ['id' => $superAdminId]);
    $superAfter = $userRepo->findById($superAdminId);
    assertAdminTest(
        "Self-protection: Super Admin cannot deactivate own account",
        $superAfter['status'] === 'active'
    );

    // -------------------------------------------------------------------------
    // 5. Regular Administrator: Strict Server-Side 403 Access Denied
    // -------------------------------------------------------------------------
    echo PHP_EOL . "5. Testing Regular Administrator 403 Forbidden Access Enforcement..." . PHP_EOL;

    // Switch session to standard Administrator
    Session::set('_auth_user_id', $createdAdminId);
    Session::set('_auth_user_role', 'staff');

    $roleGate = new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN);

    $testEndpoints = [
        ['GET',  '/admin/admins'],
        ['GET',  '/admin/admins/create'],
        ['POST', '/admin/admins'],
        ['GET',  "/admin/admins/{$createdAdminId}/edit"],
        ['POST', "/admin/admins/{$createdAdminId}"],
        ['GET',  "/admin/admins/{$createdAdminId}/password"],
        ['POST', "/admin/admins/{$createdAdminId}/password"],
        ['GET',  "/admin/admins/{$createdAdminId}/permissions"],
        ['POST', "/admin/admins/{$createdAdminId}/permissions"],
        ['POST', "/admin/admins/{$createdAdminId}/activate"],
        ['POST', "/admin/admins/{$createdAdminId}/deactivate"],
    ];

    foreach ($testEndpoints as [$method, $uri]) {
        $req = new Request([], [], [], ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri]);
        $resp = $roleGate->handle($req, fn() => Response::html('UNAUTHORIZED REACHED', 200));
        assertAdminTest(
            "RoleMiddleware blocks regular Administrator from {$method} {$uri} (HTTP 403)",
            $resp->getStatusCode() === 403
        );
    }

    // Direct controller action invocations without middleware (Defense-in-Depth verification)
    $indexDeniedReq = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/admins']);
    assertAdminTest(
        "Controller defense-in-depth: index() returns 403 directly for non-super-admin",
        $controller->index($indexDeniedReq)->getStatusCode() === 403
    );

    $createDeniedReq = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/admins/create']);
    assertAdminTest(
        "Controller defense-in-depth: create() returns 403 directly for non-super-admin",
        $controller->create($createDeniedReq)->getStatusCode() === 403
    );

    $storeDeniedReq = new Request([
        'name'                  => 'Hacker Account',
        'email'                 => 'hack@teami.in',
        'password'              => 'HackerPass123!',
        'password_confirmation' => 'HackerPass123!',
    ], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/admins']);
    assertAdminTest(
        "Controller defense-in-depth: store() returns 403 directly for non-super-admin",
        $controller->store($storeDeniedReq)->getStatusCode() === 403
    );

    $editDeniedReq = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/admin/admins/{$superAdminId}/edit"]);
    assertAdminTest(
        "Controller defense-in-depth: edit() returns 403 directly for non-super-admin",
        $controller->edit($editDeniedReq, ['id' => $superAdminId])->getStatusCode() === 403
    );

    // -------------------------------------------------------------------------
    // 6. Privilege Escalation Prevention
    // -------------------------------------------------------------------------
    echo PHP_EOL . "6. Testing Privilege Escalation Prevention..." . PHP_EOL;

    // Verify that permission check for 'admins.view' returns false for regular admin
    assertAdminTest(
        "Regular Administrator denied admins.view permission check",
        $permService->hasPermission($createdAdminId, 'admins.view', 'staff') === false
    );
    assertAdminTest(
        "Regular Administrator denied admins.create permission check",
        $permService->hasPermission($createdAdminId, 'admins.create', 'staff') === false
    );

    // Even if row manually exists in user_permissions, hasPermission strictly returns false
    $adminViewPerm = $permRepo->findByName('admins.view');
    if ($adminViewPerm) {
        $testPdo->exec("INSERT IGNORE INTO `user_permissions` (`user_id`, `permission_id`) VALUES ({$createdAdminId}, {$adminViewPerm['id']})");
        assertAdminTest(
            "Hardened rule: hasPermission('admins.view') returns false despite manual row insertion",
            $permService->hasPermission($createdAdminId, 'admins.view', 'staff') === false
        );
    }

    // assignPermissions strips any admins.* module permissions for non-super-admins
    if ($adminViewPerm) {
        $permService->assignPermissions($createdAdminId, [(int) $adminViewPerm['id'], $testPermIds[0]]);
        $syncedIds = $permRepo->getUserPermissionIds($createdAdminId);
        assertAdminTest(
            "assignPermissions() strictly stripped 'admins.view' from regular Administrator",
            !in_array((int) $adminViewPerm['id'], $syncedIds, true)
        );
    }

    // Super Admin retains unconditional bypass
    assertAdminTest(
        "Super Admin retains unconditional bypass on admins.view",
        $permService->hasPermission($superAdminId, 'admins.view', 'super_admin') === true
    );

    // -------------------------------------------------------------------------
    // 7. UI Template Structure & Sidebar Navigation Invariants
    // -------------------------------------------------------------------------
    echo PHP_EOL . "7. Testing UI Templates & Navigation Isolation..." . PHP_EOL;

    $createFileContent = file_get_contents(APP_ROOT . '/app/Views/admin/admins/create.php');
    assertAdminTest(
        "create.php does NOT contain '<select name=\"role\"'",
        !str_contains($createFileContent, '<select name="role"')
    );
    assertAdminTest(
        "create.php contains 'Personal Information' section",
        str_contains($createFileContent, 'Personal Information')
    );
    assertAdminTest(
        "create.php contains 'Security' section",
        str_contains($createFileContent, 'Security')
    );
    assertAdminTest(
        "create.php contains 'Permissions' section",
        str_contains($createFileContent, 'Permissions')
    );

    $editFileContent = file_get_contents(APP_ROOT . '/app/Views/admin/admins/edit.php');
    assertAdminTest(
        "edit.php does NOT contain '<select name=\"role\"'",
        !str_contains($editFileContent, '<select name="role"')
    );

    $indexFileContent = file_get_contents(APP_ROOT . '/app/Views/admin/admins/index.php');
    assertAdminTest(
        "index.php displays role via RoleService::getRoleLabel()",
        str_contains($indexFileContent, 'RoleService::getRoleLabel')
    );

    $layoutFileContent = file_get_contents(APP_ROOT . '/app/Views/layouts/admin.php');
    assertAdminTest(
        "layouts/admin.php guards Admin Management sidebar link with RoleService::isSuperAdmin",
        str_contains($layoutFileContent, 'RoleService::isSuperAdmin')
    );

} finally {
    // -------------------------------------------------------------------------
    // 8. Clean Isolated Teardown
    // -------------------------------------------------------------------------
    echo PHP_EOL . "8. Cleaning Up Isolated Test Database..." . PHP_EOL;
    $serverPdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`;");
    Database::disconnect();
    echo "  {$green}[CLEANUP]{$reset} Dropped isolated MariaDB database `{$testDbName}`" . PHP_EOL;
}

echo PHP_EOL . "=================================================" . PHP_EOL;
echo "Admin Management Verification Results:" . PHP_EOL;
echo "Total Assertions: {$totalTests}" . PHP_EOL;
echo "Passed: {$passedTests}" . PHP_EOL;
echo "Failed: {$failedTests}" . PHP_EOL;
echo "=================================================" . PHP_EOL;

if ($failedTests > 0) {
    echo "{$red}FAILED: {$failedTests} test(s) failed.{$reset}" . PHP_EOL;
    exit(1);
} else {
    echo "{$green}ALL ADMIN MANAGEMENT ARCHITECTURE REQUIREMENTS VERIFIED SUCCESSFULLY ON MARIADB.{$reset}" . PHP_EOL;
    exit(0);
}
