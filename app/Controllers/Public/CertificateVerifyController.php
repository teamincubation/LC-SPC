<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\CertificateRepository;
use App\Services\AuditService;
use App\Services\CertificateService;

/**
 * Public Certificate Verification Controller
 * Provides minimal-disclosure public verification for issued certificates via 256-bit QR tokens.
 * Features strict rate limiting, enumeration resistance, and generic institutional revocation notices.
 */
class CertificateVerifyController
{
    private CertificateRepository $certRepo;
    private CertificateService $certService;
    private AuditService $auditService;
    private string $rateLimitDir;

    public function __construct(
        ?CertificateRepository $certRepo = null,
        ?CertificateService $certService = null,
        ?AuditService $auditService = null,
        ?string $rateLimitDir = null
    ) {
        $this->certRepo = $certRepo ?? new CertificateRepository();
        $this->certService = $certService ?? new CertificateService();
        $this->auditService = $auditService ?? new AuditService();
        $this->rateLimitDir = $rateLimitDir ?? (defined('APP_ROOT') ? APP_ROOT . '/storage/cache/rate_limits' : dirname(__DIR__, 3) . '/storage/cache/rate_limits');
    }

    /**
     * Verify certificate publicly by token.
     * GET /verify/{token}
     * GET /certificate/verify/{token}
     */
    public function show(Request $request, array $vars): Response
    {
        $token = trim((string) ($vars['token'] ?? ''));
        $clientIp = $this->resolveClientIp($request);

        // 1. Rate Limiting Check (10 failures -> 15 min lock)
        if ($this->isRateLimited($clientIp)) {
            return Response::html(
                View::render('errors/429', [
                    'title'   => 'Too Many Requests',
                    'message' => 'Too many invalid verification attempts. Please wait 15 minutes before trying again.',
                ]),
                429,
                ['Retry-After' => '900']
            );
        }

        // Validate token format (64-character hex)
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            $this->recordFailedAttempt($clientIp, $token);

            return Response::html(
                View::render('errors/404', [
                    'title'   => 'Certificate Not Found',
                    'message' => 'The requested certificate verification token is invalid or does not exist.',
                ]),
                404
            );
        }

        // 2. Query Certificate by verification token
        $cert = $this->certRepo->findByToken($token);

        // 3. Strict 404 on non-existent token
        if ($cert === null) {
            $this->recordFailedAttempt($clientIp, $token);

            return Response::html(
                View::render('errors/404', [
                    'title'   => 'Certificate Not Found',
                    'message' => 'The requested certificate verification token is invalid or does not exist.',
                ]),
                404
            );
        }

        // 4. Successful verification: Clear rate-limit failures
        $this->clearFailedAttempts($clientIp);

        // Audit verification lookup without logging raw token or contact PII
        $this->auditService->log(
            'certificate.verify',
            'certificates',
            (int) $cert['id'],
            [
                'certificate_number' => $cert['certificate_number'],
                'status'             => $cert['status'],
                'is_revoked'         => ($cert['status'] === 'revoked'),
            ],
            null,
            'public'
        );

        // 5. Format minimal operational disclosure
        $verificationData = $this->certService->formatPublicVerification($cert);

        return Response::html(
            View::render('public/certificates/verify', [
                'title'        => ($verificationData['is_revoked'] ? 'Revoked Certificate — ' : 'Verified Certificate — ') . $verificationData['certificate_number'],
                'verification' => $verificationData,
                'token'        => $token,
            ])
        );
    }

    /**
     * Safely resolve client IP address.
     */
    private function resolveClientIp(Request $request): string
    {
        $server = $_SERVER;
        $remoteAddr = $server['REMOTE_ADDR'] ?? '127.0.0.1';

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
     * Check whether client IP is currently rate-limited.
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
     * Record failed verification attempt.
     */
    private function recordFailedAttempt(string $ip, string $attemptedToken): void
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

        // 10 failed attempts triggers 15 min lock (900s)
        if ($data['failures'] >= 10) {
            $data['locked_until'] = $now + 900;

            $this->auditService->log(
                'security.failed_certificate_verify',
                'security',
                null,
                [
                    'ip'                   => $ip,
                    'consecutive_failures' => $data['failures'],
                    'attempted_token'      => CertificateService::maskToken($attemptedToken),
                ],
                null,
                'anonymous',
                $ip
            );
        }

        @file_put_contents($filePath, json_encode($data), LOCK_EX);
    }

    /**
     * Clear rate limit on successful verification.
     */
    private function clearFailedAttempts(string $ip): void
    {
        $filePath = $this->getRateLimitFilePath($ip);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    private function getRateLimitFilePath(string $ip): string
    {
        $hash = hash('sha256', $ip);
        return $this->rateLimitDir . '/cert_verify_' . $hash . '.json';
    }
}
