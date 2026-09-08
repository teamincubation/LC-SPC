<?php

declare(strict_types=1);

/**
 * Automated Verification Suite for LC-SPC Production Foundation
 * Run via: php tests/test_suite.php
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Core\App;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Middleware\CsrfMiddleware;
use App\Database\MigrationRunner;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security;
use App\Core\Session;

// Colors for terminal output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertTest(string $description, bool $condition, string $details = ''): void
{
    global $totalTests, $passedTests, $failedTests, $green, $red, $reset;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$description}" . PHP_EOL;
    } else {
        $failedTests++;
        echo "  [FAIL] {$description}" . PHP_EOL;
        if ($details) {
            echo "         Details: {$details}" . PHP_EOL;
        }
    }
}

Env::load(APP_ROOT . '/.env');
Config::load(APP_ROOT . '/config');

// Initialize session before any output/headers are sent to test clean secure start
$cleanSessionStarted = false;
if (!headers_sent()) {
    Session::start();
    $cleanSessionStarted = Session::isStarted();
    if ($cleanSessionStarted) {
        Session::set('audit_test_key', 'phase0_hardened');
    }
}

echo "=== LC-SPC Phase 0 Foundation Verification Suite ===" . PHP_EOL;

// -----------------------------------------------------------------------------
// 1. Environment & Configuration System
// -----------------------------------------------------------------------------
echo PHP_EOL . "1. Testing Env & Config System..." . PHP_EOL;

assertTest(
    "Env loads APP_NAME accurately",
    Env::get('APP_NAME') === 'Listening Community SPC',
    "Actual: " . var_export(Env::get('APP_NAME'), true)
);

assertTest(
    "Config::get dot-notation retrieves app.name",
    Config::get('app.name') === 'Listening Community SPC'
);

assertTest(
    "Dedicated database name matches u806388046_LC",
    Config::get('database.database') === 'u806388046_LC'
);

assertTest(
    "Dedicated database user matches u806388046_LC_SPC",
    Config::get('database.username') === 'u806388046_LC_SPC'
);

assertTest(
    "Security session name configured as LCSPC_SESSION",
    Config::get('security.session.name') === 'LCSPC_SESSION'
);

// -----------------------------------------------------------------------------
// 2. Subdirectory & Base Path Router Tests
// -----------------------------------------------------------------------------
echo PHP_EOL . "2. Testing Router Subdirectory Path Resolution..." . PHP_EOL;
$router = new Router();
$router->get('/', fn() => 'ROOT_MATCHED');
$router->get('/health', fn() => 'HEALTH_MATCHED');
$router->get('/events/{id}', fn($req, $params) => 'EVENT_' . ($params['id'] ?? ''));

// Test Local: Base path is '/'
Config::set('app.base_path', '/');
assertTest("Local root '/' resolves to '/'", $router->normalizePath('/') === '/');
assertTest("Local '/health' resolves to '/health'", $router->normalizePath('/health') === '/health');

$reqLocalRoot = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
$resLocalRoot = $router->dispatch($reqLocalRoot);
assertTest("Local '/' dispatch returns ROOT_MATCHED", $resLocalRoot->getContent() === 'ROOT_MATCHED');

$reqLocalHealth = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health']);
$resLocalHealth = $router->dispatch($reqLocalHealth);
assertTest("Local '/health' dispatch returns HEALTH_MATCHED", $resLocalHealth->getContent() === 'HEALTH_MATCHED');

// Test Hostinger Production: Base path is '/LC'
Config::set('app.base_path', '/LC');
assertTest("Hostinger '/LC' normalizes to '/'", $router->normalizePath('/LC') === '/');
assertTest("Hostinger '/LC/' normalizes to '/'", $router->normalizePath('/LC/') === '/');
assertTest("Hostinger '/LC/health' normalizes to '/health'", $router->normalizePath('/LC/health') === '/health');
assertTest("Hostinger '/LC/events/42' normalizes to '/events/42'", $router->normalizePath('/LC/events/42') === '/events/42');

$reqProdRoot = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/LC']);
$resProdRoot = $router->dispatch($reqProdRoot);
assertTest("Hostinger '/LC' dispatch returns ROOT_MATCHED", $resProdRoot->getContent() === 'ROOT_MATCHED');

$reqProdHealth = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/LC/health']);
$resProdHealth = $router->dispatch($reqProdHealth);
assertTest("Hostinger '/LC/health' dispatch returns HEALTH_MATCHED", $resProdHealth->getContent() === 'HEALTH_MATCHED');

$reqProdParam = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/LC/events/999']);
$resProdParam = $router->dispatch($reqProdParam);
assertTest("Hostinger route parameter '/LC/events/999' extracts id=999", $resProdParam->getContent() === 'EVENT_999');

// Test 404 on non-existent route
$req404 = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/LC/non-existent-page']);
$res404 = $router->dispatch($req404);
assertTest("Unknown route '/LC/non-existent-page' returns HTTP 404", $res404->getStatusCode() === 404);

// -----------------------------------------------------------------------------
// 3. Security, CSRF, and Escaping Tests
// -----------------------------------------------------------------------------
echo PHP_EOL . "3. Testing Security, CSRF & Escaping..." . PHP_EOL;

$token1 = Security::csrfToken();
assertTest("CSRF token is generated and non-empty (64 chars hex)", strlen($token1) === 64);
assertTest("CSRF validation succeeds with correct token", Security::validateCsrfToken($token1) === true);
assertTest("CSRF validation fails with invalid token", Security::validateCsrfToken('tampered-token') === false);
assertTest("CSRF validation fails with empty token", Security::validateCsrfToken('') === false);

$rawXss = '<script>alert("XSS")</script>';
$escaped = Security::escape($rawXss);
assertTest("Output escaping prevents XSS", !str_contains($escaped, '<script>') && str_contains($escaped, '&lt;script&gt;'));

$plainPass = 'P@ssw0rdSecure!2026';
$hash = Security::hashPassword($plainPass);
assertTest("Password hash is created", str_starts_with($hash, '$2y$'));
assertTest("Password verify succeeds on correct password", Security::verifyPassword($plainPass, $hash));
assertTest("Password verify fails on wrong password", !Security::verifyPassword('WrongPass', $hash));

// Test state-changing POST request rejection through CsrfMiddleware
$csrfRouter = new Router();
$csrfRouter->use(CsrfMiddleware::class);
$csrfRouter->post('/submit', fn() => 'SUBMIT_SUCCESS');

$reqPostNoCsrf = new Request([], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/submit']);
$resPostNoCsrf = $csrfRouter->dispatch($reqPostNoCsrf);
assertTest("State-changing POST without CSRF token is rejected with HTTP 403", $resPostNoCsrf->getStatusCode() === 403);

$validToken = Security::csrfToken();
$reqPostWithCsrf = new Request([], ['_csrf_token' => $validToken], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/submit']);
$resPostWithCsrf = $csrfRouter->dispatch($reqPostWithCsrf);
assertTest("State-changing POST with valid CSRF token is accepted (HTTP 200)", $resPostWithCsrf->getStatusCode() === 200 && $resPostWithCsrf->getContent() === 'SUBMIT_SUCCESS');

// Test Permissions-Policy Header Configuration
$permissionsPolicy = (string) Config::get('security.headers.Permissions-Policy', '');
assertTest(
    "Permissions-Policy does NOT contain 'geolocation=()'",
    !str_contains($permissionsPolicy, 'geolocation=()'),
    "Actual: {$permissionsPolicy}"
);
assertTest(
    "Permissions-Policy allows geolocation for application origin: 'geolocation=(self)'",
    str_contains($permissionsPolicy, 'geolocation=(self)'),
    "Actual: {$permissionsPolicy}"
);
assertTest(
    "Permissions-Policy does NOT allow geolocation for arbitrary third-party origins (*)",
    !str_contains($permissionsPolicy, 'geolocation=*'),
    "Actual: {$permissionsPolicy}"
);

// Test Session Security Configuration Attributes
$sessionConf = Config::get('security.session', []);
assertTest("Session security HttpOnly is enabled (true)", ($sessionConf['httponly'] ?? false) === true);
assertTest("Session security SameSite is configured to Lax", ($sessionConf['samesite'] ?? '') === 'Lax');
assertTest("Session name is configured as LCSPC_SESSION", ($sessionConf['name'] ?? '') === 'LCSPC_SESSION');
assertTest("Session lifetime is configured (7200s)", ($sessionConf['lifetime'] ?? 0) === 7200);

$sessionStorageDir = APP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
assertTest("Dedicated session storage directory exists", is_dir($sessionStorageDir));

// Test Session Lifecycle and Destruction
assertTest("Session::start initializes active session when headers not sent", $cleanSessionStarted === true);
assertTest("Session::get retrieves stored session data", Session::get('audit_test_key') === 'phase0_hardened');

// Simulate session cookie being set
$_COOKIE[session_name()] = 'test_session_id_123';
assertTest("Session cookie is set prior to destruction", isset($_COOKIE[session_name()]));

Session::destroy();
assertTest("Session::destroy marks session as not started", Session::isStarted() === false);
assertTest("Session::destroy clears \$_SESSION array", empty($_SESSION));
assertTest("Session::destroy unsets the session cookie from \$_COOKIE", !isset($_COOKIE[session_name()]));
assertTest("Session::destroy leaves session inactive in PHP runtime", session_status() !== PHP_SESSION_ACTIVE);

// Test Session::start() safety when headers have already been sent
assertTest("Headers have already been sent by CLI output stream", headers_sent() === true);
Session::start();
assertTest("Session::start() safely aborts when headers already sent", Session::isStarted() === false);

// -----------------------------------------------------------------------------
// 4. View Helpers Test
// -----------------------------------------------------------------------------
echo PHP_EOL . "4. Testing View Helpers (url, asset, e)..." . PHP_EOL;
Config::set('app.base_path', '/LC');
assertTest("url('/health') in production produces '/LC/health'", url('/health') === '/LC/health');
assertTest("url('/') in production produces '/LC/'", url('/') === '/LC/');
assertTest("asset('css/app.css') in production produces '/LC/assets/css/app.css'", asset('css/app.css') === '/LC/assets/css/app.css');

Config::set('app.base_path', '/');
assertTest("url('/health') in local produces '/health'", url('/health') === '/health');
assertTest("asset('css/app.css') in local produces '/assets/css/app.css'", asset('css/app.css') === '/assets/css/app.css');

// -----------------------------------------------------------------------------
// 5. Database Abstraction & Transactions (In-Memory SQLite PDO test)
// -----------------------------------------------------------------------------
echo PHP_EOL . "5. Testing Database Abstraction (Prepared Statements, Transactions & Rollback)..." . PHP_EOL;

$testPdo = new PDO('sqlite::memory:');
$testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Test migration runner with SQLite PDO
$runner = new MigrationRunner($testPdo, APP_ROOT . '/database/migrations');
$runner->ensureMigrationTable();

$executedBefore = $runner->getExecutedMigrations();
assertTest("Migrations table initialized successfully", is_array($executedBefore));
assertTest("Next batch number returns 1 initially", $runner->getNextBatchNumber() === 1);

// Test transaction and rollback
$testPdo->exec("CREATE TABLE test_ledger (id INTEGER PRIMARY KEY, item TEXT)");

$testPdo->beginTransaction();
$testPdo->exec("INSERT INTO test_ledger (item) VALUES ('Rollback item')");
$testPdo->rollBack();

$countStmt = $testPdo->query("SELECT COUNT(*) FROM test_ledger");
assertTest("Transaction rollback successfully reverts uncommitted rows", (int) $countStmt->fetchColumn() === 0);
$countStmt->closeCursor();

// Test transaction commit
$testPdo->beginTransaction();
$testPdo->exec("INSERT INTO test_ledger (item) VALUES ('Committed item')");
$testPdo->commit();

$countStmt2 = $testPdo->query("SELECT COUNT(*) FROM test_ledger");
assertTest("Transaction commit successfully persists committed rows", (int) $countStmt2->fetchColumn() === 1);
$countStmt2->closeCursor();

// -----------------------------------------------------------------------------
// 6. Health Controller Output Tests
// -----------------------------------------------------------------------------
echo PHP_EOL . "6. Testing Health Controller Security & Formatting..." . PHP_EOL;
$healthController = new HealthController();
$healthRequest = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health']);
$healthResponse = $healthController->index($healthRequest);

$healthJson = json_decode($healthResponse->getContent(), true);
assertTest("Health endpoint returns JSON format", is_array($healthJson));
assertTest("Health check includes application identifier 'LC-SPC'", ($healthJson['application'] ?? '') === 'LC-SPC');
assertTest("Health response DOES NOT leak database host", !isset($healthJson['host']));
assertTest("Health response DOES NOT leak database password", !isset($healthJson['password']));
assertTest("Health response DOES NOT leak server paths", !isset($healthJson['path']));
assertTest("Health response DOES NOT leak env variables", !isset($healthJson['env']));

// -----------------------------------------------------------------------------
// 7. Apache Security Rules Verification
// -----------------------------------------------------------------------------
echo PHP_EOL . "7. Verifying Apache Security Protection Rules..." . PHP_EOL;
$htaccessRoot = file_get_contents(APP_ROOT . '/.htaccess');
$htaccessPublic = file_get_contents(APP_ROOT . '/public/.htaccess');
$htaccessStorage = file_get_contents(APP_ROOT . '/storage/.htaccess');

assertTest("Root .htaccess disables directory indexes (-Indexes)", str_contains($htaccessRoot, 'Options -Indexes'));
assertTest("Root .htaccess blocks .env direct access", str_contains($htaccessRoot, '^\.env.*$') || str_contains($htaccessRoot, '\.env'));
assertTest("Root .htaccess blocks app/ and config/ access", str_contains($htaccessRoot, 'app|config|database|storage|vendor'));
assertTest("Root .htaccess blocks .sql and .log files", str_contains($htaccessRoot, 'sql') && str_contains($htaccessRoot, 'log'));
assertTest("Storage .htaccess explicitly denies all access", str_contains($htaccessStorage, 'Require all denied') || str_contains($htaccessStorage, 'Deny from all'));

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo PHP_EOL . "----------------------------------------------------" . PHP_EOL;
echo "Tests Passed: {$passedTests} / {$totalTests}" . PHP_EOL;
if ($failedTests > 0) {
    echo "Tests Failed: {$failedTests}" . PHP_EOL;
    exit(1);
} else {
    echo "ALL TESTS PASSED SUCCESSFULLY." . PHP_EOL;
    exit(0);
}
