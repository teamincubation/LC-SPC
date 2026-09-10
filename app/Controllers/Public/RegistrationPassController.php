<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\RegistrationRepository;
use App\Services\AuditService;
use App\Services\RegistrationService;

/**
 * Public Registration Pass Controller
 * Provides minimal-disclosure public pass lookup for attendees holding valid confirmed pass codes.
 */
class RegistrationPassController
{
    private RegistrationRepository $registrationRepo;
    private RegistrationService $registrationService;
    private AuditService $auditService;
    private string $rateLimitDir;

    public function __construct(
        ?RegistrationRepository $registrationRepo = null,
        ?RegistrationService $registrationService = null,
        ?AuditService $auditService = null,
        ?string $rateLimitDir = null
    ) {
        $this->registrationRepo = $registrationRepo ?? new RegistrationRepository();
        $this->registrationService = $registrationService ?? new RegistrationService();
        $this->auditService = $auditService ?? new AuditService();
        $this->rateLimitDir = $rateLimitDir ?? (defined('APP_ROOT') ? APP_ROOT . '/storage/cache/rate_limits' : dirname(__DIR__, 3) . '/storage/cache/rate_limits');
    }

    /**
     * Display minimal-disclosure attendance pass for confirmed registrations only.
     * GET /registration/pass/{code}
     */
    public function show(Request $request, array $vars): Response
    {
        $code = trim((string) ($vars['code'] ?? ''));
        $clientIp = $this->resolveClientIp($request);

        // 1. Rate Limiting Check
        if ($this->isRateLimited($clientIp)) {
            return Response::html(
                View::render('errors/429', [
                    'title'   => 'Too Many Requests',
                    'message' => 'Too many invalid pass lookup attempts. Please wait 15 minutes before trying again.',
                ]),
                429,
                ['Retry-After' => '900']
            );
        }

        // 2. Query Registration by code
        $registration = !empty($code) ? $this->registrationRepo->findByCode($code) : null;

        // 3. Strict Status Gate: Only 'confirmed' registrations are publicly accessible.
        // Pending, waitlisted, cancelled, or non-existent codes all return uniform HTTP 404.
        if ($registration === null || ($registration['status'] ?? '') !== 'confirmed') {
            $this->recordFailedAttempt($clientIp, $code);

            return Response::html(
                View::render('errors/404', [
                    'title'   => 'Attendance Pass Not Available',
                    'message' => 'The requested attendance pass is unavailable or the code is invalid.',
                ]),
                404
            );
        }

        // 4. Successful confirmed pass lookup: Clear failed attempts
        $this->clearFailedAttempts($clientIp);

        // 5. Format minimal operational disclosure
        $passData = $this->registrationService->formatPublicPass($registration);

        return Response::html(
            View::render('public/pass/show', [
                'title' => 'Attendance Pass — ' . ($passData['attendee_name'] ?? 'Attendee'),
                'pass'  => $passData,
            ])
        );
    }

    /**
     * Resolve client IP safely without blindly trusting spoofable client headers.
     */
    private function resolveClientIp(Request $request): string
    {
        $server = $_SERVER;
        $remoteAddr = $server['REMOTE_ADDR'] ?? '127.0.0.1';

        // Known trusted proxy ranges (e.g. localhost reverse proxy or Cloudflare)
        $trustedProxies = ['127.0.0.1', '::1'];

        if (in_array($remoteAddr, $trustedProxies, true)) {
            $forwarded = $server['HTTP_CF_CONNECTING_IP'] ?? $server['HTTP_X_FORWARDED_FOR'] ?? null;
            if ($forwarded) {
                $parts = explode(',', $forwarded);
                $candidate = trim($parts[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '127.0.0.1';
    }

    /**
     * Check whether client IP is currently rate-limited (10 failures in 5 mins -> 15 min lock).
     */
    private function isRateLimited(string $ip): bool
    {
        $filePath = $this->getRateLimitFilePath($ip);
        if (!file_exists($filePath)) {
            return false;
        }

        $data = @json_decode((string) file_get_contents($filePath), true);
        if (!is_array($data)) {
            return false;
        }

        $now = time();
        if (!empty($data['locked_until']) && $data['locked_until'] > $now) {
            return true;
        }

        return false;
    }

    /**
     * Record failed pass lookup attempt.
     */
    private function recordFailedAttempt(string $ip, string $attemptedCode): void
    {
        if (!is_dir($this->rateLimitDir)) {
            @mkdir($this->rateLimitDir, 0755, true);
        }

        $filePath = $this->getRateLimitFilePath($ip);
        $now = time();
        $data = [
            'failures'     => 0,
            'window_start' => $now,
            'locked_until' => 0,
        ];

        if (file_exists($filePath)) {
            $existing = @json_decode((string) file_get_contents($filePath), true);
            if (is_array($existing)) {
                // If window (5 mins = 300s) has expired, reset window
                if (($now - ($existing['window_start'] ?? 0)) > 300) {
                    $data['failures'] = 1;
                    $data['window_start'] = $now;
                } else {
                    $data['failures'] = ($existing['failures'] ?? 0) + 1;
                    $data['window_start'] = $existing['window_start'] ?? $now;
                }
            } else {
                $data['failures'] = 1;
            }
        } else {
            $data['failures'] = 1;
        }

        // 10 failed attempts locks for 15 minutes (900 seconds) so 11th attempt receives HTTP 429
        if ($data['failures'] >= 10) {
            $data['locked_until'] = $now + 900;

            // Log security audit burst with masked code
            $this->auditService->log(
                'auth.failed_pass_lookup',
                'security',
                null,
                [
                    'ip'                    => $ip,
                    'consecutive_failures' => $data['failures'],
                    'attempted_masked_code' => RegistrationService::maskCode($attemptedCode),
                ],
                null,
                'anonymous',
                $ip
            );
        }

        @file_put_contents($filePath, json_encode($data), LOCK_EX);
    }

    /**
     * Clear rate limit file upon successful confirmed pass lookup.
     */
    private function clearFailedAttempts(string $ip): void
    {
        $filePath = $this->getRateLimitFilePath($ip);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * Get rate limit file path for a given IP.
     */
    private function getRateLimitFilePath(string $ip): string
    {
        $hash = hash('sha256', $ip);
        return $this->rateLimitDir . '/pass_lookup_' . $hash . '.json';
    }
}
