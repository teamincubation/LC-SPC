<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Security;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Exceptions\AccountLockedException;
use App\Services\Exceptions\AuthenticationException;

/**
 * Authentication Service
 * Manages administrative authentication, session lifecycle, password verification,
 * timing-safe dummy hash evaluations, brute-force lockout, and auth audit logging.
 */
class AuthService
{
    /**
     * Pre-computed Bcrypt dummy hash used for unknown-user constant-time verification.
     */
    private const DUMMY_HASH = '$2y$10$abcdefghijklmnopqrstuu012345678901234567890123456789';

    /**
     * Maximum consecutive failed login attempts before lockout.
     */
    public const MAX_FAILED_ATTEMPTS = 5;

    /**
     * Lockout duration in minutes.
     */
    public const LOCKOUT_MINUTES = 15;

    /**
     * Idle session timeout in seconds (30 minutes).
     */
    public const IDLE_TIMEOUT_SECONDS = 1800;

    private UserRepository $userRepo;
    private AuditService $auditService;

    public function __construct(?UserRepository $userRepo = null, ?AuditService $auditService = null)
    {
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Authenticate an administrative user.
     *
     * @throws AuthenticationException on invalid credentials or inactive account
     * @throws AccountLockedException on account lockout
     */
    public function authenticate(string $email, string $password, ?string $ip = null, ?string $userAgent = null): array
    {
        $normalizedEmail = strtolower(trim($email));

        if (empty($normalizedEmail) || empty($password)) {
            $this->auditService->log('auth.failed_login', 'user', null, [
                'attempted_email' => $normalizedEmail,
                'reason'          => 'empty_credentials',
            ], null, 'anonymous', $ip, $userAgent);

            throw new AuthenticationException('Invalid email or password.');
        }

        $user = $this->userRepo->findByEmail($normalizedEmail);

        // 1. Unknown user handling: constant-time dummy hash verification
        if ($user === null) {
            Security::verifyPassword($password, self::DUMMY_HASH);

            $this->auditService->log('auth.failed_login', 'user', null, [
                'attempted_email' => $normalizedEmail,
                'reason'          => 'user_not_found',
            ], null, 'anonymous', $ip, $userAgent);

            throw new AuthenticationException('Invalid email or password.');
        }

        $userId = (int) $user['id'];

        // 2. Account status & soft-delete enforcement
        if (!empty($user['deleted_at']) || $user['status'] !== 'active') {
            $this->auditService->log('auth.failed_login', 'user', $userId, [
                'attempted_email' => $normalizedEmail,
                'reason'          => 'account_inactive_or_deleted',
                'status'          => $user['status'],
            ], null, 'anonymous', $ip, $userAgent);

            throw new AuthenticationException('Account is disabled. Please contact an administrator.');
        }

        // 3. Lockout evaluation
        if (!empty($user['locked_until'])) {
            $lockedUntilTimestamp = strtotime((string) $user['locked_until']);

            if ($lockedUntilTimestamp > time()) {
                $this->auditService->log('auth.failed_login', 'user', $userId, [
                    'attempted_email' => $normalizedEmail,
                    'reason'          => 'account_locked',
                    'locked_until'    => $user['locked_until'],
                ], null, 'anonymous', $ip, $userAgent);

                throw new AccountLockedException('Account is temporarily locked due to consecutive failed attempts. Please try again later.');
            }

            // Lock period has elapsed: clear expired lockout
            $this->userRepo->clearLockout($userId);
            $user['failed_logins'] = 0;
            $user['locked_until'] = null;
        }

        // 4. Password verification
        if (!Security::verifyPassword($password, (string) $user['password_hash'])) {
            $failedCount = $this->userRepo->incrementFailedLogins($userId);

            if ($failedCount >= self::MAX_FAILED_ATTEMPTS) {
                $this->userRepo->lockAccount($userId, self::LOCKOUT_MINUTES);

                $this->auditService->log('auth.lockout', 'user', $userId, [
                    'attempted_email' => $normalizedEmail,
                    'consecutive_failures' => $failedCount,
                    'locked_minutes' => self::LOCKOUT_MINUTES,
                ], null, 'anonymous', $ip, $userAgent);

                throw new AccountLockedException('Account is temporarily locked due to 5 consecutive failed attempts. Please try again in 15 minutes.');
            }

            $this->auditService->log('auth.failed_login', 'user', $userId, [
                'attempted_email' => $normalizedEmail,
                'consecutive_failures' => $failedCount,
                'reason' => 'invalid_password',
            ], null, 'anonymous', $ip, $userAgent);

            throw new AuthenticationException('Invalid email or password.');
        }

        // 5. Successful password match: reset counters and touch last_login_at
        $this->userRepo->resetFailedLoginsAndTouchLastLogin($userId);

        // 6. Automatic password rehashing if algorithmic cost parameters updated
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = Security::hashPassword($password);
            $this->userRepo->updatePasswordHash($userId, $newHash);
        }

        // 7. Secure session regeneration to prevent session fixation
        Session::start();
        Session::regenerate(true);

        // 8. Establish authenticated session
        Session::set('_auth_user_id', $userId);
        Session::set('_auth_user_name', $user['name']);
        Session::set('_auth_user_email', $user['email']);
        Session::set('_auth_user_role', $user['role']);
        Session::set('_auth_last_activity', time());

        // 9. Dispatch audit event
        $this->auditService->log('auth.login', 'user', $userId, [
            'email' => $user['email'],
            'role'  => $user['role'],
        ], $userId, 'admin', $ip, $userAgent);

        return [
            'id'     => $userId,
            'name'   => $user['name'],
            'email'  => $user['email'],
            'role'   => $user['role'],
            'phone'  => $user['phone'] ?? null,
            'status' => $user['status'],
        ];
    }

    /**
     * Terminate the authenticated session and dispatch audit log.
     */
    public function logout(?string $ip = null, ?string $userAgent = null): void
    {
        Session::start();
        $userId = Session::get('_auth_user_id');

        if ($userId !== null) {
            $this->auditService->log('auth.logout', 'user', (int) $userId, [
                'email' => Session::get('_auth_user_email'),
                'role'  => Session::get('_auth_user_role'),
            ], (int) $userId, 'admin', $ip, $userAgent);
        }

        Session::destroy();
    }

    /**
     * Retrieve the currently authenticated user record from database, verifying active status.
     */
    public function getCurrentUser(): ?array
    {
        Session::start();
        $userId = Session::get('_auth_user_id');

        if ($userId === null) {
            return null;
        }

        // Check session idle timeout
        $lastActivity = (int) Session::get('_auth_last_activity', 0);
        if ($lastActivity > 0 && (time() - $lastActivity) > self::IDLE_TIMEOUT_SECONDS) {
            $this->logout();
            return null;
        }

        // Update activity timestamp
        Session::set('_auth_last_activity', time());

        $user = $this->userRepo->findById((int) $userId);
        if ($user === null || $user['status'] !== 'active' || !empty($user['deleted_at'])) {
            $this->logout();
            return null;
        }

        return [
            'id'     => (int) $user['id'],
            'name'   => $user['name'],
            'email'  => $user['email'],
            'role'   => $user['role'],
            'phone'  => $user['phone'] ?? null,
            'status' => $user['status'],
        ];
    }

    /**
     * Check if client holds a valid, active authenticated session.
     */
    public function isAuthenticated(): bool
    {
        return $this->getCurrentUser() !== null;
    }
}
