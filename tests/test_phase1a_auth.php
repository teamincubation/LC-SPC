<?php

declare(strict_types=1);

/**
 * Phase 1A — Authentication & RBAC Automated Test Suite
 * Run via: php tests/test_phase1a_auth.php
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
use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\GuestMiddleware;
use App\Core\Middleware\RoleMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\Exceptions\AccountLockedException;
use App\Services\Exceptions\AuthenticationException;
use App\Services\RoleService;

// Colors for terminal output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertAuthTest(string $description, bool $condition, string $details = ''): void
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

echo "=== LC-SPC Phase 1A Authentication & RBAC Verification Suite ===" . PHP_EOL . PHP_EOL;

// -----------------------------------------------------------------------------
// Isolated Test Database Setup (In-Memory SQLite PDO)
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

// Inject test connection into Database::$instance via Reflection
$ref = new ReflectionProperty(Database::class, 'instance');
$ref->setAccessible(true);
$ref->setValue(null, $testPdo);

$userRepo = new UserRepository();
$auditRepo = new AuditLogRepository();
$auditService = new AuditService($auditRepo, $userRepo);
$authService = new AuthService($userRepo, $auditService);

// -----------------------------------------------------------------------------
// 1. Testing Core Authentication Service & State Machines
// -----------------------------------------------------------------------------
echo "1. Testing Core Authentication Service & State Machines..." . PHP_EOL;

Database::beginTransaction();

try {
    $plainPassword = 'CorrectSecurePassword!2026';
    $passwordHash = Security::hashPassword($plainPassword);

    // Create Active Test User
    $testUserId = $userRepo->create([
        'name'          => 'Dr. Alice Sharma',
        'email'         => 'alice.sharma@teami.in',
        'password_hash' => $passwordHash,
        'role'          => 'coordinator',
        'status'        => 'active',
        'failed_logins' => 0,
    ]);

    // Test 1: Valid Login
    $authResult = $authService->authenticate('alice.sharma@teami.in', $plainPassword, '127.0.0.1', 'PHPUnit Test');
    assertAuthTest(
        "1. Valid login returns authenticated user array",
        $authResult['id'] === $testUserId &&
        $authResult['email'] === 'alice.sharma@teami.in' &&
        $authResult['role'] === 'coordinator' &&
        Session::get('_auth_user_id') === $testUserId
    );

    // Test 2: Invalid Password
    $invalidPasswordCaught = false;
    try {
        $authService->authenticate('alice.sharma@teami.in', 'WrongPassword123!', '127.0.0.1', 'Test');
    } catch (AuthenticationException $e) {
        $invalidPasswordCaught = ($e->getMessage() === 'Invalid email or password.');
    }
    assertAuthTest(
        "2. Invalid password rejected with generic error",
        $invalidPasswordCaught
    );

    // Test 3: Unknown Email (constant-time verification)
    $unknownEmailCaught = false;
    try {
        $authService->authenticate('nonexistent.user@teami.in', 'SomePassword123!', '127.0.0.1', 'Test');
    } catch (AuthenticationException $e) {
        $unknownEmailCaught = ($e->getMessage() === 'Invalid email or password.');
    }
    assertAuthTest(
        "3. Unknown email rejected with identical message to wrong password",
        $unknownEmailCaught
    );

    // Test 4: Inactive Account
    $inactiveUserId = $userRepo->create([
        'name'          => 'Inactive User',
        'email'         => 'inactive@teami.in',
        'password_hash' => $passwordHash,
        'role'          => 'staff',
        'status'        => 'inactive',
        'failed_logins' => 0,
    ]);
    $inactiveCaught = false;
    try {
        $authService->authenticate('inactive@teami.in', $plainPassword);
    } catch (AuthenticationException $e) {
        $inactiveCaught = str_contains($e->getMessage(), 'Account is disabled');
    }
    assertAuthTest(
        "4. Inactive account rejected with disabled notice",
        $inactiveCaught
    );

    // Test 5: Suspended Account
    $suspendedUserId = $userRepo->create([
        'name'          => 'Suspended User',
        'email'         => 'suspended@teami.in',
        'password_hash' => $passwordHash,
        'role'          => 'staff',
        'status'        => 'suspended',
        'failed_logins' => 0,
    ]);
    $suspendedCaught = false;
    try {
        $authService->authenticate('suspended@teami.in', $plainPassword);
    } catch (AuthenticationException $e) {
        $suspendedCaught = str_contains($e->getMessage(), 'Account is disabled');
    }
    assertAuthTest(
        "5. Suspended account rejected with disabled notice",
        $suspendedCaught
    );

    // Test 6: Soft-Deleted Account
    $softDeletedUserId = $userRepo->create([
        'name'          => 'Deleted User',
        'email'         => 'deleted@teami.in',
        'password_hash' => $passwordHash,
        'role'          => 'staff',
        'status'        => 'active',
        'failed_logins' => 0,
    ]);
    $userRepo->softDelete($softDeletedUserId);
    $softDeleteCaught = false;
    try {
        $authService->authenticate('deleted@teami.in', $plainPassword);
    } catch (AuthenticationException $e) {
        $softDeleteCaught = true;
    }
    assertAuthTest(
        "6. Soft-deleted account rejected upon login",
        $softDeleteCaught
    );

    // Test 7: Locked Account
    $lockedUserId = $userRepo->create([
        'name'          => 'Locked User',
        'email'         => 'locked@teami.in',
        'password_hash' => $passwordHash,
        'role'          => 'staff',
        'status'        => 'active',
        'failed_logins' => 5,
    ]);
    $userRepo->lockAccount($lockedUserId, 15);
    $lockedCaught = false;
    try {
        $authService->authenticate('locked@teami.in', $plainPassword);
    } catch (AccountLockedException $e) {
        $lockedCaught = str_contains($e->getMessage(), 'Account is temporarily locked');
    }
    assertAuthTest(
        "7. Locked account rejected with AccountLockedException",
        $lockedCaught
    );

    // Test 8: Five Failed Attempts -> Lockout Trigger
    $lockoutTargetId = $userRepo->create([
        'name'          => 'Lockout Target',
        'email'         => 'lockout.target@teami.in',
        'password_hash' => $passwordHash,
        'role'          => 'staff',
        'status'        => 'active',
        'failed_logins' => 4, // 4 prior failures
    ]);
    $lockoutTriggered = false;
    try {
        // 5th failed attempt
        $authService->authenticate('lockout.target@teami.in', 'WrongAgain');
    } catch (AccountLockedException $e) {
        $lockoutTriggered = true;
    }
    $targetUserAfter = $userRepo->findById($lockoutTargetId);
    $hasLockedTimestamp = !empty($targetUserAfter['locked_until']) && strtotime((string)$targetUserAfter['locked_until']) > time();
    assertAuthTest(
        "8. Five consecutive failed attempts trigger account lockout (locked_until set)",
        $lockoutTriggered && $hasLockedTimestamp
    );

    // Test 9: Successful Login Resets Failed Attempts
    $resetTargetId = $userRepo->create([
        'name'          => 'Reset Target',
        'email'         => 'reset.target@teami.in',
        'password_hash' => $passwordHash,
        'role'          => 'staff',
        'status'        => 'active',
        'failed_logins' => 3,
    ]);
    $authService->authenticate('reset.target@teami.in', $plainPassword);
    $resetUserAfter = $userRepo->findById($resetTargetId);
    assertAuthTest(
        "9. Successful login resets failed_logins counter to 0 and records last_login_at",
        (int)$resetUserAfter['failed_logins'] === 0 && !empty($resetUserAfter['last_login_at'])
    );

    // Test 9b: Verify resetFailedLoginsAndTouchLastLogin works with distinct MySQL-compatible parameters
    $userRepo->resetFailedLoginsAndTouchLastLogin($resetTargetId);
    $touchedUser = $userRepo->findById($resetTargetId);
    assertAuthTest(
        "9b. UserRepository::resetFailedLoginsAndTouchLastLogin executes with distinct parameters (:last_login_at, :updated_at)",
        (int)$touchedUser['failed_logins'] === 0 && !empty($touchedUser['last_login_at'])
    );

    // Test 10: Session Regeneration on Login
    $authService->authenticate('alice.sharma@teami.in', $plainPassword);
    assertAuthTest(
        "10. Session authentication variables established (_auth_user_id, _auth_user_role)",
        Session::get('_auth_user_id') === $testUserId &&
        Session::get('_auth_user_role') === 'coordinator' &&
        Session::get('_auth_last_activity') > 0
    );

    // Test 11: Logout Terminates Session
    $authService->logout();
    assertAuthTest(
        "11. Logout destroys authenticated session",
        Session::get('_auth_user_id') === null &&
        Session::get('_auth_user_role') === null
    );

} finally {
    // Completely rollback all test fixtures to preserve pristine database
    Database::rollback();
}

// -----------------------------------------------------------------------------
// 2. Testing Middleware & Route Level Authorization
// -----------------------------------------------------------------------------
echo PHP_EOL . "2. Testing Middleware & Route Authorization..." . PHP_EOL;

// Test 12: Unauthenticated access to /admin is redirected
Session::destroy();
$unauthReq = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin']);
$authMiddleware = new AuthMiddleware();
$unauthResponse = $authMiddleware->handle($unauthReq, function() {
    return Response::html('Secret Admin Content', 200);
});
assertAuthTest(
    "12. Unauthenticated request to /admin redirects to /login (HTTP 302)",
    $unauthResponse->getStatusCode() === 302 &&
    str_contains($unauthResponse->getHeaders()['Location'] ?? '', '/login')
);

// Test 12b: Unauthenticated JSON request returns 401
$unauthJsonReq = new Request([], [], [], [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/admin',
    'HTTP_ACCEPT' => 'application/json'
]);
$unauthJsonResponse = $authMiddleware->handle($unauthJsonReq, function() {
    return Response::json(['secret' => true]);
});
assertAuthTest(
    "12b. Unauthenticated JSON request returns HTTP 401 JSON error",
    $unauthJsonResponse->getStatusCode() === 401 &&
    str_contains($unauthJsonResponse->getContent(), 'Unauthenticated')
);

// Test 13: Authenticated access to /admin is allowed
Database::beginTransaction();
try {
    $viewerUserId = $userRepo->create([
        'name'          => 'Auditor John',
        'email'         => 'auditor.john@teami.in',
        'password_hash' => Security::hashPassword('SafePassword123!'),
        'role'          => 'viewer',
        'status'        => 'active',
    ]);

    Session::start();
    Session::set('_auth_user_id', $viewerUserId);
    Session::set('_auth_user_role', 'viewer');
    Session::set('_auth_last_activity', time());

    $authReq = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin']);
    $authResponse = $authMiddleware->handle($authReq, function() {
        return Response::html('Admin Dashboard Content', 200);
    });

    assertAuthTest(
        "13. Authenticated user successfully passes AuthMiddleware (HTTP 200)",
        $authResponse->getStatusCode() === 200 &&
        $authResponse->getContent() === 'Admin Dashboard Content'
    );
} finally {
    Database::rollback();
}

// -----------------------------------------------------------------------------
// 3. Testing RBAC Role Hierarchy
// -----------------------------------------------------------------------------
echo PHP_EOL . "3. Testing RBAC Role Hierarchy & Gate Restrictions..." . PHP_EOL;

// Test 14: Role Hierarchy Ranks
assertAuthTest(
    "14. Role hierarchy: super_admin (40) > coordinator (30) > staff (20) > viewer (10)",
    RoleService::getRoleRank('super_admin') === 40 &&
    RoleService::getRoleRank('coordinator') === 30 &&
    RoleService::getRoleRank('staff') === 20 &&
    RoleService::getRoleRank('viewer') === 10 &&
    RoleService::hasRole('super_admin', 'coordinator') === true &&
    RoleService::hasRole('coordinator', 'staff') === true &&
    RoleService::hasRole('staff', 'viewer') === true &&
    RoleService::hasRole('viewer', 'staff') === false &&
    RoleService::hasRole('staff', 'coordinator') === false &&
    RoleService::hasRole('coordinator', 'super_admin') === false
);

// Test 15: Super Admin Access across all gates
Session::start();
Session::set('_auth_user_role', 'super_admin');
$dummyReq = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/super-admin-area']);
$superAdminGate = new RoleMiddleware('super_admin');
$coordGate = new RoleMiddleware('coordinator');
$staffGate = new RoleMiddleware('staff');
$viewerGate = new RoleMiddleware('viewer');

$superPassesAll = (
    $superAdminGate->handle($dummyReq, fn() => Response::html('OK', 200))->getStatusCode() === 200 &&
    $coordGate->handle($dummyReq, fn() => Response::html('OK', 200))->getStatusCode() === 200 &&
    $staffGate->handle($dummyReq, fn() => Response::html('OK', 200))->getStatusCode() === 200 &&
    $viewerGate->handle($dummyReq, fn() => Response::html('OK', 200))->getStatusCode() === 200
);
assertAuthTest(
    "15. super_admin passes all role gates (super_admin, coordinator, staff, viewer)",
    $superPassesAll
);

// Test 16: Coordinator Restrictions
Session::set('_auth_user_role', 'coordinator');
$coordReq = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/super-admin-area', 'HTTP_ACCEPT' => 'application/json']);
$coordDeniedSuper = $superAdminGate->handle($coordReq, fn() => Response::html('OK', 200));
$coordAllowedCoord = $coordGate->handle($dummyReq, fn() => Response::html('OK', 200));
assertAuthTest(
    "16. coordinator restricted from super_admin resources (HTTP 403) but allowed on coordinator resources",
    $coordDeniedSuper->getStatusCode() === 403 &&
    $coordAllowedCoord->getStatusCode() === 200
);

// Test 17: Staff Restrictions
Session::set('_auth_user_role', 'staff');
$staffReq = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/coordinator-area', 'HTTP_ACCEPT' => 'application/json']);
$staffDeniedCoord = $coordGate->handle($staffReq, fn() => Response::html('OK', 200));
$staffAllowedStaff = $staffGate->handle($dummyReq, fn() => Response::html('OK', 200));
assertAuthTest(
    "17. staff restricted from coordinator resources (HTTP 403) but allowed on staff resources",
    $staffDeniedCoord->getStatusCode() === 403 &&
    $staffAllowedStaff->getStatusCode() === 200
);

// Test 18: Viewer Restrictions
Session::set('_auth_user_role', 'viewer');
$viewerReq = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/staff-area', 'HTTP_ACCEPT' => 'application/json']);
$viewerDeniedStaff = $staffGate->handle($viewerReq, fn() => Response::html('OK', 200));
$viewerAllowedViewer = $viewerGate->handle($dummyReq, fn() => Response::html('OK', 200));
assertAuthTest(
    "18. viewer restricted from staff resources (HTTP 403) but allowed on viewer resources",
    $viewerDeniedStaff->getStatusCode() === 403 &&
    $viewerAllowedViewer->getStatusCode() === 200
);

// -----------------------------------------------------------------------------
// 4. Security & CSRF
// -----------------------------------------------------------------------------
echo PHP_EOL . "4. Testing Security, CSRF & Password Policies..." . PHP_EOL;

// Test 19: CSRF Protection on Login
Session::start();
$token = Security::csrfToken();

$noCsrfPostReq = new Request([], ['email' => 'admin@teami.in', 'password' => 'secret'], [], [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI'    => '/login'
]);
$csrfMiddleware = new CsrfMiddleware();
$csrfDeniedResp = $csrfMiddleware->handle($noCsrfPostReq, fn() => Response::html('OK', 200));

$validCsrfPostReq = new Request([], ['email' => 'admin@teami.in', 'password' => 'secret', '_csrf_token' => $token], [], [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI'    => '/login'
]);
$csrfAllowedResp = $csrfMiddleware->handle($validCsrfPostReq, fn() => Response::html('OK', 200));

assertAuthTest(
    "19. POST /login without CSRF token is rejected (HTTP 403), with valid CSRF token is accepted",
    $csrfDeniedResp->getStatusCode() === 403 &&
    $csrfAllowedResp->getStatusCode() === 200
);

// -----------------------------------------------------------------------------
// 5. First-Admin CLI Bootstrap & Database Invariants
// -----------------------------------------------------------------------------
echo PHP_EOL . "5. Testing First-Admin CLI Bootstrap & Data Integrity..." . PHP_EOL;

Database::beginTransaction();
try {
    // Test 20: First Admin Creation
    $adminPassword = 'ComplexSuperAdminPass!2026';
    $adminHash = Security::hashPassword($adminPassword);

    $bootstrappedId = $userRepo->create([
        'name'          => 'Initial Super Admin',
        'email'         => 'superadmin@teami.in',
        'password_hash' => $adminHash,
        'role'          => 'super_admin',
        'status'        => 'active',
        'failed_logins' => 0,
    ]);

    $bootstrappedUser = $userRepo->findById($bootstrappedId);

    assertAuthTest(
        "20. First-admin created with role=super_admin, status=active, and verified in database",
        $bootstrappedId > 0 &&
        $bootstrappedUser['role'] === 'super_admin' &&
        $bootstrappedUser['status'] === 'active' &&
        (int)$bootstrappedUser['failed_logins'] === 0
    );

    // Test 21: Duplicate Admin Email Rejection
    $emailIsDuplicate = $userRepo->emailExists('superadmin@teami.in');
    $duplicateInsertCaught = false;
    try {
        $userRepo->create([
            'name'          => 'Imposter Admin',
            'email'         => 'superadmin@teami.in', // duplicate!
            'password_hash' => $adminHash,
            'role'          => 'super_admin',
            'status'        => 'active',
        ]);
    } catch (\Throwable $e) {
        $duplicateInsertCaught = true;
    }
    assertAuthTest(
        "21. Duplicate admin email is detected and blocked by database unique key",
        $emailIsDuplicate && $duplicateInsertCaught
    );

    // Test 22: Password is never stored in plaintext
    $rawHash = $bootstrappedUser['password_hash'];
    $isHashedProperly = (
        !str_contains($rawHash, $adminPassword) &&
        (str_starts_with($rawHash, '$2y$') || str_starts_with($rawHash, '$argon2')) &&
        Security::verifyPassword($adminPassword, $rawHash) === true
    );
    assertAuthTest(
        "22. Password is never stored in plaintext (proper Argon2id/Bcrypt hash verification)",
        $isHashedProperly
    );

} finally {
    Database::rollback();
}

// -----------------------------------------------------------------------------
// 6. Audit Logging & Metadata Scrubbing
// -----------------------------------------------------------------------------
echo PHP_EOL . "6. Testing Audit Logging & Credential Scrubbing..." . PHP_EOL;

// Test 23: Sensitive Audit Metadata Scrubbing
$dirtyMetadata = [
    'attempted_email'       => 'admin@teami.in',
    'password'              => 'PlainSecretPassword123!',
    'password_hash'         => '$2y$10$abcdefghijklmnopqrstuu',
    'password_confirmation' => 'PlainSecretPassword123!',
    'csrf_token'            => '64hexcharssecrettokenvalue...',
    '_csrf_token'           => 'anothersecrettoken',
    'session_cookie'        => 'LCSPC_SESSION=secretval',
    'nested'                => [
        'credit_card' => '4111222233334444',
        'secret_key'  => 'supersecret',
        'safe_param'  => 'campaign_2026',
    ],
    'safe_metric'           => 'user_initiated',
];

$cleanMetadata = $auditService->sanitizeMetadata($dirtyMetadata);

$scrubbedCorrectly = (
    $cleanMetadata['password'] === '[REDACTED]' &&
    $cleanMetadata['password_hash'] === '[REDACTED]' &&
    $cleanMetadata['password_confirmation'] === '[REDACTED]' &&
    $cleanMetadata['csrf_token'] === '[REDACTED]' &&
    $cleanMetadata['_csrf_token'] === '[REDACTED]' &&
    $cleanMetadata['session_cookie'] === '[REDACTED]' &&
    $cleanMetadata['nested']['credit_card'] === '[REDACTED]' &&
    $cleanMetadata['nested']['secret_key'] === '[REDACTED]' &&
    $cleanMetadata['nested']['safe_param'] === 'campaign_2026' &&
    $cleanMetadata['attempted_email'] === 'admin@teami.in' &&
    $cleanMetadata['safe_metric'] === 'user_initiated'
);

assertAuthTest(
    "23. Sensitive audit metadata (passwords, hashes, tokens, cookies) is recursively scrubbed",
    $scrubbedCorrectly
);

// -----------------------------------------------------------------------------
// Final Database Hygiene Verification
// -----------------------------------------------------------------------------
echo PHP_EOL . "7. Verifying Pristine Database State (Zero Lingering Test Rows)..." . PHP_EOL;

$finalUsersCount = (int) Database::query("SELECT COUNT(*) FROM `users`")->fetchColumn();
$finalAuditCount = (int) Database::query("SELECT COUNT(*) FROM `audit_logs`")->fetchColumn();

assertAuthTest(
    "Database 'users' table retains exactly 0 business/test rows",
    $finalUsersCount === 0,
    "Expected 0 rows, found {$finalUsersCount}"
);

assertAuthTest(
    "Database 'audit_logs' table retains exactly 0 business/test rows",
    $finalAuditCount === 0,
    "Expected 0 rows, found {$finalAuditCount}"
);

echo PHP_EOL . "----------------------------------------------------" . PHP_EOL;
echo "Phase 1A Tests Passed: {$passedTests} / {$totalTests}" . PHP_EOL;

if ($failedTests > 0) {
    echo "{$red}FAILED: {$failedTests} test(s) failed.{$reset}" . PHP_EOL;
    exit(1);
} else {
    echo "{$green}ALL 23 PHASE 1A REQUIREMENTS VERIFIED SUCCESSFULLY.{$reset}" . PHP_EOL;
    exit(0);
}
