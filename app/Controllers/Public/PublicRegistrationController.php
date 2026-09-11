<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\EventRepository;
use App\Repositories\RegistrationRepository;
use App\Services\AuditService;
use App\Services\ParticipantService;
use App\Services\RegistrationService;
use Throwable;

/**
 * Public Registration Controller
 * Coordinates public event registration, submission defense, post-registration confirmation,
 * and Crockford Base32 registration code self-service status lookup.
 */
class PublicRegistrationController
{
    private EventRepository $eventRepo;
    private RegistrationRepository $registrationRepo;
    private ParticipantService $participantService;
    private RegistrationService $registrationService;
    private AuditService $auditService;
    private string $rateLimitDir;

    public function __construct(
        ?EventRepository $eventRepo = null,
        ?RegistrationRepository $registrationRepo = null,
        ?ParticipantService $participantService = null,
        ?RegistrationService $registrationService = null,
        ?AuditService $auditService = null,
        ?string $rateLimitDir = null
    ) {
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->registrationRepo = $registrationRepo ?? new RegistrationRepository();
        $this->participantService = $participantService ?? new ParticipantService();
        $this->registrationService = $registrationService ?? new RegistrationService();
        $this->auditService = $auditService ?? new AuditService();
        $this->rateLimitDir = $rateLimitDir ?? (defined('APP_ROOT') ? APP_ROOT . '/storage/cache/rate_limits' : dirname(__DIR__, 3) . '/storage/cache/rate_limits');
    }

    /**
     * Render the public registration form.
     * GET /events/{campaign_slug}/{event_slug}/register
     */
    public function showRegister(Request $request, array $vars): Response
    {
        $campaignSlug = trim((string) ($vars['campaign_slug'] ?? ''));
        $eventSlug = trim((string) ($vars['event_slug'] ?? ''));

        $event = $this->eventRepo->findPublicByCampaignAndSlug($campaignSlug, $eventSlug);
        if (!$event) {
            return $this->notFound('Event Not Found', 'The requested event is not available.');
        }

        // Event Eligibility Verification
        $now = time();
        $startTime = strtotime((string) $event['start_time']);
        $deadlineTime = !empty($event['registration_deadline']) ? strtotime((string) $event['registration_deadline']) : null;

        if ($event['status'] !== 'published' || $startTime <= $now || ($deadlineTime !== null && $deadlineTime < $now)) {
            Session::flash('warning', 'Registration for this event is currently closed.');
            return Response::redirect(url('/events/' . rawurlencode($campaignSlug) . '/' . rawurlencode($eventSlug)));
        }

        $capacity = (int) $event['capacity'];
        $confirmedCount = (int) ($event['confirmed_count'] ?? 0);
        $isWaitlist = $capacity > 0 && $confirmedCount >= $capacity;

        return Response::html(
            View::render('public/registration/form', [
                'title'       => 'Register — ' . $event['title'],
                'event'       => $event,
                'isWaitlist'  => $isWaitlist,
                'errors'      => Session::getFlash('errors', []),
                'old'         => Session::getFlash('old', []),
            ])
        );
    }

    /**
     * Process public registration submission.
     * POST /events/{campaign_slug}/{event_slug}/register
     */
    public function register(Request $request, array $vars): Response
    {
        $campaignSlug = trim((string) ($vars['campaign_slug'] ?? ''));
        $eventSlug = trim((string) ($vars['event_slug'] ?? ''));

        $clientIp = $this->resolveClientIp($request);

        // 1. Honeypot check (hidden field 'website')
        $honeypot = trim((string) $request->post('website', ''));
        if ($honeypot !== '') {
            // Silently drop spam submissions without creating database records
            return Response::redirect(url('/events/' . rawurlencode($campaignSlug) . '/' . rawurlencode($eventSlug)));
        }

        // 2. IP Rate Limiting (max 5 submissions per IP in 15 minutes)
        if ($this->isRateLimited($clientIp, 'reg_ip', 5, 900)) {
            return Response::html(
                View::render('errors/429', [
                    'title'   => 'Too Many Requests',
                    'message' => 'Too many registration attempts. Please wait 15 minutes before submitting again.',
                ]),
                429,
                ['Retry-After' => '900']
            );
        }
        $this->recordAttempt($clientIp, 'reg_ip', 5, 900);

        // 3. Resolve Event
        $event = $this->eventRepo->findPublicByCampaignAndSlug($campaignSlug, $eventSlug);
        if (!$event) {
            return $this->notFound('Event Not Found', 'The requested event is not available.');
        }

        // 4. Server-Side Input Validation
        $fullName = trim((string) $request->post('full_name', ''));
        $category = trim((string) $request->post('category', ''));
        $email = trim((string) $request->post('email', ''));
        $phone = trim((string) $request->post('phone', ''));
        $organizationName = trim((string) $request->post('organization_name', ''));
        $agreedGuidelines = (int) $request->post('agreed_guidelines', 0);
        $privacyConsent = (int) $request->post('privacy_consent', 0);

        $errors = [];

        // Full Name (Mandatory)
        if ($fullName === '') {
            $errors['full_name'] = 'Full name is required.';
        } elseif (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 150) {
            $errors['full_name'] = 'Full name must be between 2 and 150 characters.';
        }

        // Category (Mandatory)
        if (!in_array($category, ParticipantService::ALLOWED_CATEGORIES, true)) {
            $errors['category'] = 'Please select a valid attendee category.';
        }

        // Guidelines & Privacy Affirmations (Mandatory)
        if ($agreedGuidelines !== 1) {
            $errors['agreed_guidelines'] = 'You must agree to the Community Guidelines to register.';
        }
        if ($privacyConsent !== 1) {
            $errors['privacy_consent'] = 'You must consent to the Privacy Notice to register.';
        }

        // Email (Optional)
        if ($email !== '') {
            if (mb_strlen($email) > 191 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please provide a valid email address.';
            }
        }

        // Phone (Optional)
        if ($phone !== '') {
            if (mb_strlen($phone) < 7 || mb_strlen($phone) > 25 || !preg_match('/^[0-9+\-\s()]{7,25}$/', $phone)) {
                $errors['phone'] = 'Please provide a valid phone number (7 to 25 digits).';
            }
        }

        // Organization Name (Optional)
        if ($organizationName !== '' && mb_strlen($organizationName) > 191) {
            $errors['organization_name'] = 'Organization / Institution name may not exceed 191 characters.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', [
                'full_name'         => $fullName,
                'category'          => $category,
                'email'             => $email,
                'phone'             => $phone,
                'organization_name' => $organizationName,
            ]);

            return Response::redirect(url('/events/' . rawurlencode($campaignSlug) . '/' . rawurlencode($eventSlug) . '/register'));
        }

        // 5. Domain Service Orchestration (Security Rule 6: actor context derived server-side as 0)
        try {
            $participantData = [
                'full_name'            => $fullName,
                'category'             => $category,
                'email'                => $email !== '' ? $email : null,
                'phone'                => $phone !== '' ? $phone : null,
                'organization_name'    => $organizationName !== '' ? $organizationName : null,
                'agreed_guidelines'    => 1,
                'privacy_consent'      => 1,
            ];

            // Resolve existing participant or create a new distinct record
            $participant = $this->participantService->resolveOrCreateParticipant($participantData, 0);

            // Register participant via existing transaction-locked RegistrationService
            $result = $this->registrationService->registerParticipant((int) $event['id'], (int) $participant['id'], null, 0);

            $registration = $result['registration'] ?? null;
            if (!$registration || empty($registration['registration_code'])) {
                throw new \RuntimeException('Failed to obtain registration credentials.');
            }

            // Post-Redirect-Get (PRG) pattern prevents double submissions
            return Response::redirect(url('/registration/confirmed/' . rawurlencode((string) $registration['registration_code'])));
        } catch (Throwable $e) {
            Session::flash('errors', ['general' => $e->getMessage()]);
            Session::flash('old', [
                'full_name'         => $fullName,
                'category'          => $category,
                'email'             => $email,
                'phone'             => $phone,
                'organization_name' => $organizationName,
            ]);

            return Response::redirect(url('/events/' . rawurlencode($campaignSlug) . '/' . rawurlencode($eventSlug) . '/register'));
        }
    }

    /**
     * Render registration confirmation receipt.
     * GET /registration/confirmed/{code}
     */
    public function confirmation(Request $request, array $vars): Response
    {
        $code = trim((string) ($vars['code'] ?? ''));
        if ($code === '') {
            return $this->notFound('Confirmation Not Found', 'The requested registration code was not found.');
        }

        $registration = $this->registrationRepo->findByCode($code);
        if (!$registration) {
            return $this->notFound('Confirmation Not Found', 'The requested registration code was not found.');
        }

        // Minimal Operational Disclosure (Guardrail 5: zero IDs, admin notes, or private contact details)
        $confirmationData = [
            'attendee_name'     => $registration['participant_name'] ?? 'Attendee',
            'event_title'       => $registration['event_title'] ?? 'Event',
            'event_slug'        => $registration['event_slug'] ?? '',
            'campaign_title'    => $registration['campaign_title'] ?? 'Campaign',
            'campaign_slug'     => $registration['campaign_slug'] ?? '',
            'start_time'        => $registration['start_time'] ?? '',
            'end_time'          => $registration['end_time'] ?? '',
            'format'            => $registration['format'] ?? 'in_person',
            'venue_name'        => $registration['venue_name'] ?? null,
            'venue_address'     => $registration['venue_address'] ?? null,
            'online_meeting_url'=> $registration['online_meeting_url'] ?? null,
            'status'            => $registration['status'] ?? 'confirmed',
            'registration_code' => $registration['registration_code'],
            'can_access_pass'   => ($registration['status'] ?? '') === 'confirmed',
        ];

        return Response::html(
            View::render('public/registration/confirmation', [
                'title'        => 'Registration Confirmed — ' . $confirmationData['attendee_name'],
                'confirmation' => $confirmationData,
            ])
        );
    }

    /**
     * Render the self-service registration status search page.
     * GET /registration/status
     */
    public function showStatus(Request $request): Response
    {
        return Response::html(
            View::render('public/registration/status', [
                'title'  => 'Check Registration Status — Listening Community',
                'status' => null,
                'error'  => Session::getFlash('status_error'),
                'code'   => Session::getFlash('status_code', ''),
            ])
        );
    }

    /**
     * Look up registration status by registration code.
     * POST /registration/status
     */
    public function checkStatus(Request $request): Response
    {
        $code = strtoupper(trim((string) $request->post('code', '')));
        $clientIp = $this->resolveClientIp($request);

        // 1. IP Rate Limiting (max 10 lookups in 15 minutes)
        if ($this->isRateLimited($clientIp, 'status_ip', 10, 900)) {
            return Response::html(
                View::render('errors/429', [
                    'title'   => 'Too Many Requests',
                    'message' => 'Too many status lookup attempts. Please wait 15 minutes before trying again.',
                ]),
                429,
                ['Retry-After' => '900']
            );
        }

        // 2. Strict Crockford Base32 Format Validation (REG-YY-XXXXX)
        if (!preg_match('/^REG-[0-9]{2}-[23456789ABCDEFGHJKMNPQRSTVWXYZ]{5}$/i', $code)) {
            $this->recordAttempt($clientIp, 'status_ip', 10, 900);
            return Response::html(
                View::render('public/registration/status', [
                    'title'  => 'Check Registration Status — Listening Community',
                    'status' => null,
                    'error'  => 'Please enter a valid registration code in the format: REG-YY-XXXXX',
                    'code'   => $code,
                ])
            );
        }

        // 3. Database Lookup
        $registration = $this->registrationRepo->findByCode($code);

        // Generic Failure Response (prevents code enumeration)
        if (!$registration) {
            $this->recordAttempt($clientIp, 'status_ip', 10, 900);
            return Response::html(
                View::render('public/registration/status', [
                    'title'  => 'Check Registration Status — Listening Community',
                    'status' => null,
                    'error'  => 'No registration was found for the provided registration code. Please double-check your pass code.',
                    'code'   => $code,
                ])
            );
        }

        // Successful lookup: clear failed attempts
        $this->clearAttempts($clientIp, 'status_ip');

        // Minimal Operational Disclosure
        $statusCard = [
            'attendee_name'     => $registration['participant_name'] ?? 'Attendee',
            'event_title'       => $registration['event_title'] ?? 'Event',
            'campaign_title'    => $registration['campaign_title'] ?? 'Campaign',
            'start_time'        => $registration['start_time'] ?? '',
            'end_time'          => $registration['end_time'] ?? '',
            'format'            => $registration['format'] ?? 'in_person',
            'venue_name'        => $registration['venue_name'] ?? null,
            'status'            => $registration['status'] ?? 'confirmed',
            'registration_code' => $registration['registration_code'],
            'can_access_pass'   => ($registration['status'] ?? '') === 'confirmed',
        ];

        return Response::html(
            View::render('public/registration/status', [
                'title'  => 'Registration Status — ' . $statusCard['registration_code'],
                'status' => $statusCard,
                'error'  => null,
                'code'   => $code,
            ])
        );
    }

    /**
     * File-based rate limiter: check if client IP has exceeded maximum allowed attempts.
     */
    private function isRateLimited(string $ip, string $prefix, int $maxAttempts, int $windowSec): bool
    {
        $filePath = $this->getRateLimitFilePath($ip, $prefix);
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

        // If window expired and not locked, reset
        if (($now - ($data['window_start'] ?? 0)) > $windowSec) {
            return false;
        }

        return ($data['attempts'] ?? 0) >= $maxAttempts;
    }

    /**
     * Record attempt for client IP.
     */
    private function recordAttempt(string $ip, string $prefix, int $threshold = 5, int $lockSec = 900): void
    {
        if (!is_dir($this->rateLimitDir)) {
            @mkdir($this->rateLimitDir, 0755, true);
        }

        $filePath = $this->getRateLimitFilePath($ip, $prefix);
        $now = time();
        $data = [
            'attempts'     => 0,
            'window_start' => $now,
            'locked_until' => 0,
        ];

        if (file_exists($filePath)) {
            $existing = @json_decode((string) file_get_contents($filePath), true);
            if (is_array($existing)) {
                if (($now - ($existing['window_start'] ?? 0)) > 900) {
                    $data['attempts'] = 1;
                    $data['window_start'] = $now;
                } else {
                    $data['attempts'] = ($existing['attempts'] ?? 0) + 1;
                    $data['window_start'] = $existing['window_start'] ?? $now;
                }
            } else {
                $data['attempts'] = 1;
            }
        } else {
            $data['attempts'] = 1;
        }

        // Lock when threshold reached
        if ($data['attempts'] >= $threshold) {
            $data['locked_until'] = $now + $lockSec;
        }

        @file_put_contents($filePath, json_encode($data), LOCK_EX);
    }

    /**
     * Clear attempts file upon successful action.
     */
    private function clearAttempts(string $ip, string $prefix): void
    {
        $filePath = $this->getRateLimitFilePath($ip, $prefix);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    private function getRateLimitFilePath(string $ip, string $prefix): string
    {
        $hash = hash('sha256', $ip);
        return $this->rateLimitDir . '/' . $prefix . '_' . $hash . '.json';
    }

    private function resolveClientIp(Request $request): string
    {
        return $request->ip();
    }

    private function notFound(string $title, string $message): Response
    {
        return Response::html(
            View::render('errors/404', [
                'title'   => $title,
                'message' => $message,
            ]),
            404
        );
    }
}
