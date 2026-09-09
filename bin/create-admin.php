#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * First-Admin Secure Bootstrap CLI Utility
 *
 * Usage:
 *   php bin/create-admin.php
 *
 * Security Policies:
 * - Executable exclusively via CLI.
 * - Zero hardcoded or default passwords.
 * - Password input concealed from terminal where supported.
 * - Minimum 12 characters with strict complexity requirements.
 * - Enforces unique email verification.
 * - Direct Argon2id/Bcrypt password hashing via App\Core\Security.
 * - Dispatches 'user.create' security audit event.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Access Denied: This utility must be executed exclusively via CLI." . PHP_EOL;
    exit(1);
}

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Core\App;
use App\Core\Database;
use App\Core\Security;
use App\Repositories\UserRepository;
use App\Services\AuditService;

App::bootstrap(APP_ROOT);

echo "==================================================" . PHP_EOL;
echo "  LC-SPC — Administrative Bootstrap Provisioner   " . PHP_EOL;
echo "==================================================" . PHP_EOL . PHP_EOL;

// 1. Verify database connectivity
try {
    $dbHealth = Database::checkHealth();
    if (($dbHealth['status'] ?? '') !== 'connected') {
        echo "[ERROR] Cannot connect to database: " . ($dbHealth['message'] ?? 'Unknown error') . PHP_EOL;
        exit(1);
    }
} catch (\Throwable $e) {
    echo "[ERROR] Database connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$userRepo = new UserRepository();
$auditService = new AuditService();

// 2. Check for existing super_admin accounts
$existingAdmins = $userRepo->countSuperAdmins();
if ($existingAdmins > 0) {
    echo "[WARNING] Found {$existingAdmins} active super_admin account(s) already provisioned." . PHP_EOL;
    echo "Do you wish to create an additional super_admin? (y/N): ";
    $confirm = trim((string) fgets(STDIN));
    if (strtolower($confirm) !== 'y' && strtolower($confirm) !== 'yes') {
        echo "Bootstrap cancelled." . PHP_EOL;
        exit(0);
    }
    echo PHP_EOL;
}

// 3. Prompt for Administrator Name
$name = '';
while (mb_strlen(trim($name)) < 2) {
    echo "Enter Administrator Full Name: ";
    $name = trim((string) fgets(STDIN));
    if (mb_strlen($name) < 2) {
        echo " [!] Name must be at least 2 characters." . PHP_EOL;
    }
}

// 4. Prompt for Administrator Email
$email = '';
while (empty($email)) {
    echo "Enter Administrator Email: ";
    $inputEmail = strtolower(trim((string) fgets(STDIN)));

    if (!filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
        echo " [!] Please enter a valid email address (e.g. admin@teami.in)." . PHP_EOL;
        continue;
    }

    if ($userRepo->emailExists($inputEmail)) {
        echo " [!] Error: An account with email '{$inputEmail}' already exists." . PHP_EOL;
        continue;
    }

    $email = $inputEmail;
}

/**
 * Safely read a concealed password from terminal.
 */
function readConcealedPassword(string $prompt): string
{
    echo $prompt;

    // Unix / Linux / macOS: disable terminal echo
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty -echo 2>/dev/null');
        $password = fgets(STDIN);
        system('stty echo 2>/dev/null');
        echo PHP_EOL;
        return trim((string) $password);
    }

    // Windows: attempt hidden input via PowerShell or fallback
    try {
        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "$p = Read-Host -AsSecureString; $BSTR = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($p); [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($BSTR)"';
        $handle = popen($cmd, 'r');
        if ($handle) {
            $password = fgets($handle);
            pclose($handle);
            echo PHP_EOL;
            if ($password !== false && trim((string) $password) !== '') {
                return trim((string) $password);
            }
        }
    } catch (\Throwable) {
        // Continue to standard input fallback
    }

    // Fallback: standard input
    $password = fgets(STDIN);
    return trim((string) $password);
}

// 5. Prompt for Password with Complexity Validation
$password = '';
while (empty($password)) {
    $candidate = readConcealedPassword("Enter Secure Password (min 12 chars, upper, lower, digit, symbol): ");

    if (mb_strlen($candidate) < 12) {
        echo " [!] Password must be at least 12 characters long." . PHP_EOL;
        continue;
    }

    if (!preg_match('/[A-Z]/', $candidate)) {
        echo " [!] Password must contain at least one uppercase letter (A-Z)." . PHP_EOL;
        continue;
    }

    if (!preg_match('/[a-z]/', $candidate)) {
        echo " [!] Password must contain at least one lowercase letter (a-z)." . PHP_EOL;
        continue;
    }

    if (!preg_match('/[0-9]/', $candidate)) {
        echo " [!] Password must contain at least one numeric digit (0-9)." . PHP_EOL;
        continue;
    }

    if (!preg_match('/[^a-zA-Z0-9]/', $candidate)) {
        echo " [!] Password must contain at least one special character (!@#$%^&* etc.)." . PHP_EOL;
        continue;
    }

    $confirmation = readConcealedPassword("Confirm Password: ");
    if ($candidate !== $confirmation) {
        echo " [!] Passwords do not match. Please try again." . PHP_EOL;
        continue;
    }

    $password = $candidate;
}

// 6. Hash password securely (Argon2id/Bcrypt)
$passwordHash = Security::hashPassword($password);

// Clear plaintext variable from memory immediately
unset($password, $candidate, $confirmation);

// 7. Insert user record into users table
try {
    $userId = $userRepo->create([
        'name'          => $name,
        'email'         => $email,
        'password_hash' => $passwordHash,
        'role'          => 'super_admin',
        'phone'         => null,
        'status'        => 'active',
        'failed_logins' => 0,
    ]);

    // 8. Record audit log entry
    $auditService->log(
        'user.create',
        'user',
        $userId,
        [
            'email'        => $email,
            'role'         => 'super_admin',
            'bootstrapped' => true,
            'channel'      => 'cli',
        ],
        $userId,
        'system',
        '127.0.0.1',
        'CLI Bootstrap'
    );

    echo PHP_EOL;
    echo "==================================================" . PHP_EOL;
    echo " [SUCCESS] Super Administrator provisioned successfully!" . PHP_EOL;
    echo "==================================================" . PHP_EOL;
    echo "  User ID: " . $userId . PHP_EOL;
    echo "  Name:    " . $name . PHP_EOL;
    echo "  Email:   " . $email . PHP_EOL;
    echo "  Role:    super_admin" . PHP_EOL;
    echo "  Status:  active" . PHP_EOL;
    echo "==================================================" . PHP_EOL;
    echo "You may now sign in at: " . url('/login') . PHP_EOL . PHP_EOL;

    exit(0);
} catch (\Throwable $e) {
    echo "[ERROR] Failed to create administrator: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
