<?php

declare(strict_types=1);

/**
 * Focused Verification Suite:
 * Task: Super Admin Activity Visibility + Public Certificate Footer Cleanup
 *
 * Exercises:
 * 1. RoleService::isSuperAdmin canonical checks
 * 2. Super Administrator Dashboard view rendering (Recent System Activity is PRESENT)
 * 3. Normal Administrator Dashboard view rendering (Recent System Activity is COMPLETELY ABSENT)
 * 4. Public Layout footer cleanup:
 *    - Privacy Policy, Terms of Use, Contact completely removed
 *    - Em dash (U+2014) and &mdash; completely absent
 *    - Non-em-dash separator " | " present
 *    - Centered classes and styling structure verified
 * 5. Layouts/Main footer cleanup (no em dash)
 * 6. Isolated Local MariaDB End-to-End Authorization QA:
 *    - Super Administrator accesses /admin: Recent System Activity visible, audit logs visible
 *    - Normal Administrator accesses /admin: Recent System Activity completely absent from HTML
 *    - Normal Administrator accesses Certificate Platform pages: HTTP 200 OK
 *    - Normal Administrator blocked from Admin Management: HTTP 403 Forbidden
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Controllers\Admin\DashboardController;
use App\Core\Config;
use App\Core\Database;
use App\Core\Middleware\PermissionMiddleware;
use App\Core\Middleware\RoleMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;
use App\Repositories\V3CertificateTemplateRepository;
use App\Services\RoleService;

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertCondition(bool $condition, string $description): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$description}\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$description}\n";
    }
}

echo "\n=== 1. RoleService::isSuperAdmin Canonical Checks ===\n";
assertCondition(RoleService::isSuperAdmin('super_admin') === true, "RoleService::isSuperAdmin('super_admin') is true");
assertCondition(RoleService::isSuperAdmin('staff') === false, "RoleService::isSuperAdmin('staff') is false");
assertCondition(RoleService::isSuperAdmin('admin') === false, "RoleService::isSuperAdmin('admin') is false");
assertCondition(RoleService::isSuperAdmin('coordinator') === false, "RoleService::isSuperAdmin('coordinator') is false");
assertCondition(RoleService::isSuperAdmin('viewer') === false, "RoleService::isSuperAdmin('viewer') is false");
assertCondition(RoleService::isSuperAdmin('') === false, "RoleService::isSuperAdmin('') is false");

echo "\n=== 2. Super Administrator Dashboard View Unit Rendering ===\n";
$superAdminUser = [
    'id' => 1,
    'full_name' => 'Super Admin Tester',
    'email' => 'superadmin@example.com',
    'role' => 'super_admin',
];

$dummyMetrics = [
    'total_certificates' => 10,
    'total_templates' => 2,
    'recent_batches' => [],
];

$dummyLogs = [
    [
        'action' => 'auth.login',
        'entity_type' => 'user',
        'entity_id' => 1,
        'user_name' => 'Super Admin',
        'created_at' => date('Y-m-d H:i:s'),
    ],
];

$adminRoleSlug = (string) ($superAdminUser['role'] ?? '');
$isSuperAdmin = RoleService::isSuperAdmin($adminRoleSlug);

$superAdminHtml = View::render('admin/dashboard/index', [
    'title'         => 'Administrative Overview',
    'breadcrumb'    => 'Dashboard',
    'user'          => $superAdminUser,
    'adminRoleSlug' => $adminRoleSlug,
    'isSuperAdmin'  => $isSuperAdmin,
    'roleLabel'     => RoleService::getRoleLabel($adminRoleSlug),
    'roleBadge'     => RoleService::getBadgeClass($adminRoleSlug),
    'metrics'       => $dummyMetrics,
    'recentLogs'    => $dummyLogs,
    'activeNav'     => 'dashboard',
]);

assertCondition(str_contains($superAdminHtml, 'Recent System Activity'), "Super Admin HTML contains 'Recent System Activity'");
assertCondition(str_contains($superAdminHtml, 'Audit Trail'), "Super Admin HTML contains 'Audit Trail'");
assertCondition(str_contains($superAdminHtml, 'auth.login'), "Super Admin HTML renders recent audit log entry");

echo "\n=== 3. Normal Administrator Dashboard View Unit Rendering ===\n";
$normalAdminUser = [
    'id' => 2,
    'full_name' => 'Regular Admin Tester',
    'email' => 'admin@example.com',
    'role' => 'staff',
];

$normalAdminRoleSlug = (string) ($normalAdminUser['role'] ?? '');
$normalIsSuperAdmin = RoleService::isSuperAdmin($normalAdminRoleSlug);
$normalRecentLogs = $normalIsSuperAdmin ? $dummyLogs : [];

$normalAdminHtml = View::render('admin/dashboard/index', [
    'title'         => 'Administrative Overview',
    'breadcrumb'    => 'Dashboard',
    'user'          => $normalAdminUser,
    'adminRoleSlug' => $normalAdminRoleSlug,
    'isSuperAdmin'  => $normalIsSuperAdmin,
    'roleLabel'     => RoleService::getRoleLabel($normalAdminRoleSlug),
    'roleBadge'     => RoleService::getBadgeClass($normalAdminRoleSlug),
    'metrics'       => $dummyMetrics,
    'recentLogs'    => $normalRecentLogs,
    'activeNav'     => 'dashboard',
]);

assertCondition(!str_contains($normalAdminHtml, 'Recent System Activity'), "Normal Admin HTML completely omits 'Recent System Activity'");
assertCondition(!str_contains($normalAdminHtml, 'Audit Trail'), "Normal Admin HTML completely omits 'Audit Trail'");
assertCondition(!str_contains($normalAdminHtml, 'auth.login'), "Normal Admin HTML completely omits audit log records");
assertCondition(str_contains($normalAdminHtml, 'System Operational') && str_contains($normalAdminHtml, 'Welcome'), "Normal Admin HTML contains general dashboard content");

echo "\n=== 4. Public Layout Footer Cleanup Verification ===\n";
$publicLayoutHtml = View::render('layouts/public', [
    'title' => 'Find Your Certificate',
    'content' => '<div class="test-content">Certificate search form</div>',
]);

assertCondition(!str_contains($publicLayoutHtml, 'Privacy Policy'), "Public layout does NOT contain 'Privacy Policy'");
assertCondition(!str_contains($publicLayoutHtml, 'Terms of Use'), "Public layout does NOT contain 'Terms of Use'");
assertCondition(!str_contains($publicLayoutHtml, 'Contact'), "Public layout does NOT contain 'Contact'");
assertCondition(!str_contains($publicLayoutHtml, 'auth-footer-links'), "Public layout does NOT contain 'auth-footer-links'");
assertCondition(!str_contains($publicLayoutHtml, 'auth-footer-link'), "Public layout does NOT contain 'auth-footer-link'");
assertCondition(!str_contains($publicLayoutHtml, '&mdash;'), "Public layout does NOT contain '&mdash;'");
assertCondition(!str_contains($publicLayoutHtml, "\u{2014}"), "Public layout does NOT contain U+2014 em dash");
assertCondition(str_contains($publicLayoutHtml, ' | '), "Public layout contains ' | ' separator");
assertCondition(str_contains($publicLayoutHtml, 'auth-footer-inner'), "Public layout contains 'auth-footer-inner'");
assertCondition(str_contains($publicLayoutHtml, 'auth-footer-copy'), "Public layout contains 'auth-footer-copy'");
assertCondition(str_contains($publicLayoutHtml, 'All rights reserved.'), "Public layout contains copyright notice");

echo "\n=== 5. Layouts/Main Footer Verification ===\n";
$mainLayoutHtml = View::render('layouts/main', [
    'content' => '<p>Test</p>',
]);
assertCondition(!str_contains($mainLayoutHtml, '&mdash;'), "Main layout does NOT contain '&mdash;' in footer");
assertCondition(!str_contains($mainLayoutHtml, "\u{2014}"), "Main layout does NOT contain U+2014 em dash");

echo "\n=== 6. Isolated Local MariaDB End-to-End Authorization QA ===\n";
$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '';
$testDbName = 'lc_spc_qa_' . time() . '_' . mt_rand(1000, 9999);

$serverPdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$testDbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

$testPdo = new PDO("mysql:host={$host};port={$port};dbname={$testDbName};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$ref = new ReflectionProperty(Database::class, 'instance');
$ref->setAccessible(true);
$ref->setValue(null, $testPdo);

// Run migrations
$m0001 = require APP_ROOT . '/database/migrations/m0001_create_users_table.php';
$m0001->up($testPdo);

$m0007 = require APP_ROOT . '/database/migrations/m0007_create_audit_logs_table.php';
$m0007->up($testPdo);

$m0010 = require APP_ROOT . '/database/migrations/m0010_create_admin_permissions_system.php';
$m0010->up($testPdo);

$m0014 = require APP_ROOT . '/database/migrations/m0014_create_certificate_platform_v3_tables.php';
$m0014->up($testPdo);

$userRepo = new UserRepository();
$permRepo = new PermissionRepository();
$templateRepo = new V3CertificateTemplateRepository();

$testTemplateId = $templateRepo->create([
    'name' => 'QA Template',
    'certificate_type' => 'participation',
    'status' => 'active',
    'layout_config' => json_encode(['elements' => []]),
    'required_variables' => json_encode(['name', 'phone']),
]);

$dbSuperAdminId = $userRepo->create([
    'name' => 'QA Super Admin',
    'email' => 'superadmin_qa@teami.in',
    'password_hash' => Security::hashPassword('SuperSafe123!'),
    'role' => 'super_admin',
    'status' => 'active',
]);

$dbNormalAdminId = $userRepo->create([
    'name' => 'QA Normal Admin',
    'email' => 'normaladmin_qa@teami.in',
    'password_hash' => Security::hashPassword('AdminSafe123!'),
    'role' => 'staff',
    'status' => 'active',
]);

$testPdo->prepare("
    INSERT INTO audit_logs (actor_id, actor_type, action, entity_type, entity_id, ip_address, user_agent, metadata, created_at)
    VALUES (?, 'admin', ?, ?, ?, ?, ?, ?, NOW())
")->execute([$dbSuperAdminId, 'auth.login', 'user', $dbSuperAdminId, '127.0.0.1', 'QA Client', json_encode(['method' => 'web'])]);

$stmt = $testPdo->prepare("SELECT id FROM permissions WHERE name = ?");
$getPermId = function (string $name) use ($stmt): int {
    $stmt->execute([$name]);
    return (int) $stmt->fetchColumn();
};

$v3Perms = [
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
foreach ($v3Perms as $pName) {
    $pId = $getPermId($pName);
    if ($pId > 0) {
        $allPermIds[] = $pId;
    }
}
$permRepo->syncUserPermissions($dbNormalAdminId, $allPermIds);

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

// Super Administrator via DashboardController
Session::start();
Session::set('_auth_user_id', $dbSuperAdminId);
Session::set('_auth_user_role', 'super_admin');

$controller = new DashboardController();
$req = new Request([], [], [], [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/admin',
    'HTTP_ACCEPT'    => 'text/html',
]);
$superResp = $controller->index($req);
assertCondition($superResp->getStatusCode() === 200, "Super Admin GET /admin returns HTTP 200");
$superHtml = $superResp->getBody();
assertCondition(str_contains($superHtml, 'Recent System Activity'), "Super Admin: 'Recent System Activity' is VISIBLE on dashboard");
assertCondition(str_contains($superHtml, 'auth.login'), "Super Admin: Audit log entries are VISIBLE");

// Normal Administrator via DashboardController
Session::start();
Session::set('_auth_user_id', $dbNormalAdminId);
Session::set('_auth_user_role', 'staff');

$normalResp = $controller->index($req);
assertCondition($normalResp->getStatusCode() === 200, "Normal Admin GET /admin returns HTTP 200");
$normalHtml = $normalResp->getBody();
assertCondition(!str_contains($normalHtml, 'Recent System Activity'), "Normal Admin: 'Recent System Activity' is COMPLETELY ABSENT from rendered UI/HTML");
assertCondition(!str_contains($normalHtml, 'auth.login'), "Normal Admin: Audit logs are COMPLETELY ABSENT from rendered UI/HTML");
assertCondition(!str_contains($normalHtml, '/admin/admins'), "Normal Admin: Admin Management link is HIDDEN from sidebar");

// Verify all previously authorized certificate-platform pages continue to work
assertCondition(
    $testGate($dbNormalAdminId, 'staff', ['certificates.manage', 'settings.view'], '/admin/certificate-settings')->getStatusCode() === 200,
    "Normal Admin accesses Certificate Settings -> HTTP 200 OK"
);
assertCondition(
    $testGate($dbNormalAdminId, 'staff', ['certificates.create', 'certificates.manage'], '/admin/certificates/generate')->getStatusCode() === 200,
    "Normal Admin accesses Certificate Generation -> HTTP 200 OK"
);
assertCondition(
    $testGate($dbNormalAdminId, 'staff', ['certificates.view', 'certificates.manage'], '/admin/certificate-templates')->getStatusCode() === 200,
    "Normal Admin accesses Certificate Templates -> HTTP 200 OK"
);
assertCondition(
    $testGate($dbNormalAdminId, 'staff', ['certificates.edit', 'certificates.manage'], "/admin/certificate-templates/{$testTemplateId}/designer")->getStatusCode() === 200,
    "Normal Admin accesses Certificate Template Designer -> HTTP 200 OK"
);
assertCondition(
    $testGate($dbNormalAdminId, 'staff', ['certificates.view', 'certificates.manage'], '/admin/certificates')->getStatusCode() === 200,
    "Normal Admin accesses Certificate Repository -> HTTP 200 OK"
);

// Verify Admin Management remains protected by Super Admin-only authorization
$superAdminGate = new RoleMiddleware(RoleService::ROLE_SUPER_ADMIN);
$adminReq = new Request([], [], [], [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/admin/admins',
    'HTTP_ACCEPT'    => 'text/html',
]);
$adminMgmtResp = $superAdminGate->handle($adminReq, fn() => Response::html('AUTHORIZED', 200));
assertCondition($adminMgmtResp->getStatusCode() === 403, "Normal Admin blocked from Admin Management (/admin/admins) -> HTTP 403 Forbidden");

$adminCreateReq = new Request([], [], [], [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/admin/admins/create',
    'HTTP_ACCEPT'    => 'text/html',
]);
$adminCreateResp = $superAdminGate->handle($adminCreateReq, fn() => Response::html('AUTHORIZED', 200));
assertCondition($adminCreateResp->getStatusCode() === 403, "Normal Admin blocked from Admin Management (/admin/admins/create) -> HTTP 403 Forbidden");

// Teardown
$serverPdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`;");

echo "\n==================================================\n";
echo "SUMMARY: {$passedTests} / {$totalTests} tests passed.\n";
if ($failedTests > 0) {
    echo "FAILED: {$failedTests} test(s) failed.\n";
    exit(1);
}
echo "ALL TESTS PASSED!\n";
exit(0);
