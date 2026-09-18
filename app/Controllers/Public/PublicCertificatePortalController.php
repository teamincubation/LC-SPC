<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\V3CertificateRepository;
use App\Repositories\V3CertificateTemplateRepository;
use App\Services\CertificateRenderer;
use App\Services\CsvValidationService;
use Throwable;

/**
 * Public Certificate Portal & Verification Controller (V3)
 * Provides public certificate search by phone number or Certificate ID,
 * public verification view with 1:1 canvas render, and public downloads (PDF/JPG).
 * Enforces strict IP rate-limiting, phone normalization, anti-enumeration, and zero-PII leak.
 */
class PublicCertificatePortalController
{
    private V3CertificateRepository $certRepo;
    private V3CertificateTemplateRepository $templateRepo;
    private string $rateLimitDir;

    public function __construct(
        ?V3CertificateRepository $certRepo = null,
        ?V3CertificateTemplateRepository $templateRepo = null,
        ?string $rateLimitDir = null
    ) {
        $this->certRepo = $certRepo ?? new V3CertificateRepository();
        $this->templateRepo = $templateRepo ?? new V3CertificateTemplateRepository();
        $baseDir = dirname(__DIR__, 3);
        $this->rateLimitDir = $rateLimitDir ?? ($baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'rate_limits');

        if (!is_dir($this->rateLimitDir)) {
            @mkdir($this->rateLimitDir, 0755, true);
        }
    }

    /**
     * Public Certificate Search Portal.
     * GET /certificates
     */
    public function index(Request $request): Response
    {
        $clientIp = $this->resolveClientIp($request);
        $phoneInput = trim((string) $request->input('phone', ''));
        $certIdInput = trim((string) $request->input('certificate_id', ''));

        $mode = 'phone';
        $results = null;
        $searched = false;
        $errorMessage = null;

        if ($phoneInput !== '') {
            $searched = true;
            $mode = 'phone';

            // Rate-limit phone search: max 10 searches per 60 seconds per IP
            if ($this->isRateLimited($clientIp, 'phone_search', 10, 60)) {
                return Response::html(View::render('errors/429', [
                    'title'   => 'Too Many Requests',
                    'message' => 'Too many searches requested. Please wait 1 minute before trying again.',
                ], 'layouts/public'), 429, ['Retry-After' => '60']);
            }

            $normalizedPhone = CsvValidationService::normalizePhone($phoneInput);
            if (empty($normalizedPhone)) {
                // Anti-enumeration: Return generic message even for invalid phone formatting
                $results = [];
                $errorMessage = 'No active certificates found for the provided phone number.';
            } else {
                // Mandatory Rule: Return ONLY active certificates
                $activeCerts = $this->certRepo->findActiveByPhone($normalizedPhone);

                // Anti-enumeration & PII minimization: Mask phone and return public fields only
                $results = array_map(function ($c) {
                    return [
                        'certificate_id'     => $c['certificate_id'],
                        'verification_token' => $c['verification_token'],
                        'recipient_name'     => $c['name'],
                        'event_title'        => $c['event_title'] ?: 'Listening Community Certification',
                        'issue_date'         => $c['date'] ?: date('Y-m-d', strtotime($c['created_at'])),
                    ];
                }, $activeCerts);

                if (empty($results)) {
                    $errorMessage = 'No active certificates found for the provided phone number.';
                }
            }
        } elseif ($certIdInput !== '') {
            $searched = true;
            $mode = 'cert_id';

            // Rate-limit ID search: max 20 per 60 seconds per IP
            if ($this->isRateLimited($clientIp, 'id_search', 20, 60)) {
                return Response::html(View::render('errors/429', [
                    'title'   => 'Too Many Requests',
                    'message' => 'Too many lookup requests. Please wait 1 minute before trying again.',
                ], 'layouts/public'), 429, ['Retry-After' => '60']);
            }

            $cert = $this->certRepo->findByCertificateId($certIdInput);

            // Mandatory Rule: return active only; if invalid or missing, return generic error
            if ($cert && $cert['status'] === 'active') {
                return Response::redirect(url('/certificates/verify/' . $cert['verification_token']));
            } else {
                $results = [];
                $errorMessage = 'No active certificate found matching the provided Certificate ID.';
            }
        }

        return Response::html(View::render('public/certificates/search', [
            'title'        => 'Verify & Download Certificate',
            'mode'         => $mode,
            'phoneInput'   => $phoneInput,
            'certIdInput'  => $certIdInput,
            'searched'     => $searched,
            'results'      => $results,
            'errorMessage' => $errorMessage,
        ], 'layouts/public'));
    }

    /**
     * Dedicated Public Verification Page.
     * GET /certificates/verify/{token}
     */
    public function verify(Request $request, array $vars): Response
    {
        $token = trim((string) ($vars['token'] ?? ''));
        $clientIp = $this->resolveClientIp($request);

        // Rate-limit verification views: max 60 per minute
        if ($this->isRateLimited($clientIp, 'verify_view', 60, 60)) {
            return Response::html(View::render('errors/429', [
                'title'   => 'Too Many Requests',
                'message' => 'Too many verification attempts. Please wait a moment before trying again.',
            ], 'layouts/public'), 429, ['Retry-After' => '60']);
        }

        // Validate token hex format
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return Response::html(View::render('errors/404', [
                'title'   => 'Certificate Not Found',
                'message' => 'The requested credential verification link is invalid or has expired.',
            ], 'layouts/public'), 404);
        }

        $cert = $this->certRepo->findByToken($token);
        if (!$cert) {
            return Response::html(View::render('errors/404', [
                'title'   => 'Certificate Not Found',
                'message' => 'No credential was found matching this verification token.',
            ], 'layouts/public'), 404);
        }

        $isRevoked = in_array($cert['status'], ['invalid', 'revoked'], true);

        // Sanitize verification payload: Zero internal IDs, zero admin reasons leaked to the public!
        $verificationData = [
            'certificate_id'     => $cert['certificate_id'],
            'verification_token' => $cert['verification_token'],
            'recipient_name'     => $cert['name'],
            'event_title'        => $cert['event_title'] ?: 'Listening Community Certification',
            'issue_date'         => $cert['date'] ?: date('Y-m-d', strtotime($cert['created_at'])),
            'is_revoked'         => $isRevoked,
            'status_label'       => $isRevoked ? 'OFFICIALLY REVOKED / INVALID CREDENTIAL' : 'OFFICIALLY VERIFIED & ACTIVE CREDENTIAL',
        ];

        return Response::html(View::render('public/certificates/verify', [
            'title'        => 'Verify Certificate: ' . $cert['certificate_id'],
            'verification' => $verificationData,
            'token'        => $token,
        ], 'layouts/public'));
    }

    /**
     * Public High-Resolution PDF Download.
     * GET /certificates/{token}/pdf
     */
    public function downloadPdf(Request $request, array $vars): Response
    {
        $token = trim((string) ($vars['token'] ?? ''));
        $clientIp = $this->resolveClientIp($request);

        if ($this->isRateLimited($clientIp, 'pdf_download', 30, 60)) {
            return Response::html('Rate limit exceeded', 429);
        }

        $cert = $this->certRepo->findByToken($token);
        if (!$cert) {
            return Response::html('Certificate not found', 404);
        }

        $pdfContent = '';
        $baseDir = dirname(__DIR__, 3);
        $fullPath = $cert['pdf_path'] ? $baseDir . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cert['pdf_path']), DIRECTORY_SEPARATOR) : '';

        if (!empty($fullPath) && file_exists($fullPath)) {
            $pdfContent = (string) file_get_contents($fullPath);
        } else {
            // Render on demand
            $template = $this->templateRepo->findById((int) $cert['template_id']);
            if ($template) {
                $snapshot = json_decode((string) ($cert['snapshot_data'] ?? '{}'), true) ?: [];
                $certData = array_merge($snapshot, [
                    'name'               => $cert['name'],
                    'phone'              => $cert['phone'],
                    'certificate_number' => $cert['certificate_id'],
                    'verification_token' => $cert['verification_token'],
                    'event_title'        => $cert['event_title'],
                    'date'               => $cert['date'],
                ]);
                $isRevoked = in_array($cert['status'], ['invalid', 'revoked'], true);
                $pdfContent = CertificateRenderer::renderPdf($template, $certData, $isRevoked);
            }
        }

        if (empty($pdfContent)) {
            return Response::html('Certificate PDF unavailable', 500);
        }

        $filename = "LC_Certificate_{$cert['certificate_id']}.pdf";
        return new Response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'public, max-age=3600',
        ]);
    }

    /**
     * Public High-Resolution Image Download (JPEG).
     * GET /certificates/{token}/image
     */
    public function downloadImage(Request $request, array $vars): Response
    {
        $token = trim((string) ($vars['token'] ?? ''));
        $clientIp = $this->resolveClientIp($request);

        if ($this->isRateLimited($clientIp, 'img_download', 30, 60)) {
            return Response::html('Rate limit exceeded', 429);
        }

        $cert = $this->certRepo->findByToken($token);
        if (!$cert) {
            return Response::html('Certificate not found', 404);
        }

        $imgContent = '';
        $baseDir = dirname(__DIR__, 3);
        $fullPath = $cert['image_path'] ? $baseDir . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cert['image_path']), DIRECTORY_SEPARATOR) : '';

        if (!empty($fullPath) && file_exists($fullPath)) {
            $imgContent = (string) file_get_contents($fullPath);
        } else {
            // Render on demand
            $template = $this->templateRepo->findById((int) $cert['template_id']);
            if ($template) {
                $snapshot = json_decode((string) ($cert['snapshot_data'] ?? '{}'), true) ?: [];
                $certData = array_merge($snapshot, [
                    'name'               => $cert['name'],
                    'phone'              => $cert['phone'],
                    'certificate_number' => $cert['certificate_id'],
                    'verification_token' => $cert['verification_token'],
                    'event_title'        => $cert['event_title'],
                    'date'               => $cert['date'],
                ]);
                $isRevoked = in_array($cert['status'], ['invalid', 'revoked'], true);
                $imgContent = CertificateRenderer::renderJpg($template, $certData, $isRevoked);
            }
        }

        if (empty($imgContent)) {
            return Response::html('Certificate image unavailable', 500);
        }

        $filename = "LC_Certificate_{$cert['certificate_id']}.jpg";
        return new Response($imgContent, 200, [
            'Content-Type'        => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'public, max-age=3600',
        ]);
    }

    /**
     * File-based IP rate-limiting utility.
     */
    private function isRateLimited(string $ip, string $action, int $maxAttempts = 10, int $windowSeconds = 60): bool
    {
        $hash = md5($ip . '_' . $action);
        $file = $this->rateLimitDir . DIRECTORY_SEPARATOR . "rate_{$hash}.json";

        $now = time();
        $attempts = [];

        if (file_exists($file)) {
            $data = @json_decode((string) file_get_contents($file), true) ?: [];
            // Keep attempts within the current rolling window
            $attempts = array_filter($data, fn($ts) => ($now - $ts) < $windowSeconds);
        }

        if (count($attempts) >= $maxAttempts) {
            return true;
        }

        $attempts[] = $now;
        @file_put_contents($file, json_encode($attempts), LOCK_EX);
        return false;
    }

    private function resolveClientIp(Request $request): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            $ip = $request->header(str_replace('HTTP_', '', strtolower(str_replace('_', '-', $header))));
            if (!empty($ip)) {
                $parts = explode(',', $ip);
                return trim($parts[0]);
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
