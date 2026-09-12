<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Core\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\EventFormRepository;
use App\Repositories\EventRepository;
use App\Repositories\RegistrationRepository;
use App\Services\AuditService;
use App\Services\EventFormService;
use App\Services\GeofenceService;
use DateTime;
use Throwable;

/**
 * Public Self-Service Check-In Controller
 * Primary check-in workflow for participants at the event venue:
 * Event -> Check-in URL -> Mobile lookup -> Event timing validation -> Optional geofence -> Confirmed Check-in.
 */
class PublicCheckInController
{
    private EventRepository $eventRepo;
    private EventFormRepository $formRepo;
    private RegistrationRepository $regRepo;
    private EventFormService $formService;
    private GeofenceService $geofenceService;
    private AuditService $auditService;
    private string $rateLimitDir;

    public function __construct(
        ?EventRepository $eventRepo = null,
        ?EventFormRepository $formRepo = null,
        ?RegistrationRepository $regRepo = null,
        ?EventFormService $formService = null,
        ?GeofenceService $geofenceService = null,
        ?AuditService $auditService = null,
        ?string $rateLimitDir = null
    ) {
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->formRepo = $formRepo ?? new EventFormRepository();
        $this->regRepo = $regRepo ?? new RegistrationRepository();
        $this->formService = $formService ?? new EventFormService();
        $this->geofenceService = $geofenceService ?? new GeofenceService();
        $this->auditService = $auditService ?? new AuditService();
        $this->rateLimitDir = $rateLimitDir ?? (defined('APP_ROOT') ? APP_ROOT . '/storage/cache/rate_limits' : dirname(__DIR__, 3) . '/storage/cache/rate_limits');
    }

    /**
     * Display the public check-in interface for an event.
     * GET /check-in/{slug}
     */
    public function show(Request $request, array $vars): Response
    {
        $slug = trim((string) ($vars['slug'] ?? ''));
        $event = $this->eventRepo->findBySlug($slug);

        if (!$event) {
            // Check if slug matches form slug
            $form = $this->formRepo->findBySlug($slug);
            if ($form) {
                $event = $this->eventRepo->findById((int) $form['event_id']);
            }
        }

        if (!$event || !empty($event['deleted_at'])) {
            return Response::html(View::render('errors/404', [
                'title'   => 'Event Not Found',
                'message' => 'The check-in portal for this event could not be found.',
            ]), 404);
        }

        // Timing validation status
        $timing = $this->evaluateTiming($event);

        // Check if geofencing is required
        $hasGeofence = !empty($event['latitude']) && !empty($event['longitude']) && !empty($event['geofence_radius_meters']);

        $qrSvg = $this->formService->getPublicCheckInQrSvg($event['slug'], 200);

        return Response::html(View::render('public/checkin/index', [
            'title'       => 'Check-in — ' . $event['title'],
            'event'       => $event,
            'timing'      => $timing,
            'hasGeofence' => $hasGeofence,
            'qrSvg'       => $qrSvg,
            'errors'      => Session::getFlash('errors', []),
            'old'         => Session::getFlash('old', []),
        ], 'layouts/public'));
    }

    /**
     * Process check-in verification via Mobile Number, timing, and optional geofence.
     * POST /check-in/{slug}
     */
    public function process(Request $request, array $vars): Response
    {
        $slug = trim((string) ($vars['slug'] ?? ''));
        $event = $this->eventRepo->findBySlug($slug);

        if (!$event) {
            $form = $this->formRepo->findBySlug($slug);
            if ($form) {
                $event = $this->eventRepo->findById((int) $form['event_id']);
            }
        }

        if (!$event || !empty($event['deleted_at'])) {
            return Response::html(View::render('errors/404', [
                'title'   => 'Event Not Found',
                'message' => 'The check-in portal for this event could not be found.',
            ]), 404);
        }

        $eventId = (int) $event['id'];

        $clientIp = $this->resolveClientIp($request);

        // 1. IP Rate Limiting: Maximum 5 failed/lookup attempts per IP in 15 minutes (900 seconds)
        if ($this->isRateLimited($clientIp, 'checkin_ip', 5, 900)) {
            return Response::html(
                View::render('errors/429', [
                    'title'   => 'Too Many Requests',
                    'message' => 'Too many check-in attempts from your IP address. Please wait 15 minutes before trying again.',
                ]),
                429,
                ['Retry-After' => '900']
            );
        }

        // 2. Event Timing Validation
        $timing = $this->evaluateTiming($event);
        if (!$timing['can_checkin']) {
            Session::flash('error', $timing['message']);
            return Response::redirect(url("/check-in/{$event['slug']}"));
        }

        // 3. Mobile Number Verification
        $countryCode = trim((string) $request->post('country_code', '+91'));
        $rawPhone = trim((string) $request->post('phone', ''));
        $cleanDigits = preg_replace('/[^\d]/', '', $rawPhone);

        // Record lookup attempt against client IP
        $this->recordAttempt($clientIp, 'checkin_ip', 5, 900);

        if (empty($cleanDigits) || strlen($cleanDigits) < 7 || strlen($cleanDigits) > 15) {
            Session::flash('error', 'Please enter a valid WhatsApp / Mobile number.');
            Session::flash('old', $request->all());
            return Response::redirect(url("/check-in/{$event['slug']}"));
        }

        $phoneNormalized = $countryCode . $cleanDigits;

        // Lookup registration by event and phone
        $registration = $this->regRepo->findByEventAndPhone($eventId, $phoneNormalized);

        if (!$registration) {
            // Fallback: try lookup by exact digits
            $stmt = Database::getConnection()->prepare("
                SELECT r.*, p.full_name, p.email, p.phone
                FROM `event_registrations` r
                JOIN `participants` p ON r.participant_id = p.id
                WHERE r.`event_id` = :eid AND (r.`phone_normalized` LIKE :digits OR p.`phone` LIKE :digits2)
                LIMIT 1
            ");
            $stmt->execute([
                ':eid'     => $eventId,
                ':digits'  => "%{$cleanDigits}",
                ':digits2' => "%{$cleanDigits}",
            ]);
            $registration = $stmt->fetch() ?: null;
        }

        if (!$registration || $registration['status'] === 'cancelled') {
            Session::flash('error', "No active registration found for this mobile number for this event. Please verify your number or register first.");
            Session::flash('old', $request->all());
            return Response::redirect(url("/check-in/{$event['slug']}"));
        }

        // Check if already checked in
        if (($registration['attendance_status'] ?? '') === 'attended') {
            $this->clearAttempts($clientIp, 'checkin_ip');
            return Response::html(View::render('public/checkin/success', [
                'title'        => 'Already Checked In',
                'event'        => $event,
                'registration' => $registration,
                'already'      => true,
                'attendedAt'   => $registration['attended_at'],
            ]));
        }

        // 4. Optional Geofence Verification
        $geofenceResult = ['configured' => false, 'inside' => true, 'distance_meters' => null];
        $hasGeofence = !empty($event['latitude']) && !empty($event['longitude']) && !empty($event['geofence_radius_meters']);

        if ($hasGeofence) {
            $userLat = $request->post('latitude') !== null && $request->post('latitude') !== '' ? (float) $request->post('latitude') : null;
            $userLng = $request->post('longitude') !== null && $request->post('longitude') !== '' ? (float) $request->post('longitude') : null;

            $geofenceResult = $this->geofenceService->verifyProximity(
                $userLat,
                $userLng,
                (float) $event['latitude'],
                (float) $event['longitude'],
                (int) $event['geofence_radius_meters']
            );

            if (!$geofenceResult['inside']) {
                Session::flash('error', $geofenceResult['message']);
                Session::flash('old', $request->all());
                return Response::redirect(url("/check-in/{$event['slug']}"));
            }
        }

        // 5. Record Check-In
        $now = date('Y-m-d H:i:s');
        $this->regRepo->updateCheckin((int) $registration['id'], [
            'attendance_status'         => 'attended',
            'check_in_method'           => 'mobile_verification',
            'attended_at'               => $now,
            'checkin_latitude'          => $request->post('latitude'),
            'checkin_longitude'         => $request->post('longitude'),
            'checkin_distance_meters'   => $geofenceResult['distance_meters'],
            'checkin_geofence_verified' => $hasGeofence && $geofenceResult['inside'] ? 1 : 0,
        ]);

        // Clear rate-limit attempts on legitimate successful check-in
        $this->clearAttempts($clientIp, 'checkin_ip');

        // Audit log
        $this->auditService->log(
            'event.checkin',
            'event_registration',
            (int) $registration['id'],
            [
                'event_id'          => $eventId,
                'event_title'       => $event['title'],
                'participant_name'  => $registration['full_name'] ?? 'Attendee',
                'phone_normalized'  => $phoneNormalized,
                'method'            => 'mobile_verification',
                'geofence_verified' => $hasGeofence && $geofenceResult['inside'],
                'distance_meters'   => $geofenceResult['distance_meters'],
            ]
        );

        $freshRegistration = $this->regRepo->findById((int) $registration['id']);

        return Response::html(View::render('public/checkin/success', [
            'title'        => 'Check-in Successful',
            'event'        => $event,
            'registration' => $freshRegistration ?? $registration,
            'already'      => false,
            'attendedAt'   => $now,
            'distance'     => $geofenceResult['distance_meters'],
        ], 'layouts/public'));
    }

    /**
     * Route handler alias for POST /check-in/{slug}
     */
    public function submit(Request $request, array $vars): Response
    {
        return $this->process($request, $vars);
    }

    /**
     * Evaluate check-in window based on event timing configuration.
     */
    private function evaluateTiming(array $event): array
    {
        $now = time();
        $startTime = strtotime((string) $event['start_time']);
        $endTime = strtotime((string) $event['end_time']);

        // Check if custom check-in start date/time is configured
        $checkinStart = null;
        if (!empty($event['checkin_start_date'])) {
            $dateStr = $event['checkin_start_date'];
            $timeStr = !empty($event['checkin_start_time']) ? $event['checkin_start_time'] : '00:00:00';
            $checkinStart = strtotime("{$dateStr} {$timeStr}");
        } else {
            // Default: check-in opens 2 hours prior to event start time
            $checkinStart = $startTime - 7200;
        }

        if ($event['status'] === 'cancelled') {
            return [
                'can_checkin' => false,
                'message'     => 'This event has been cancelled. Check-in is not available.',
            ];
        }

        if ($now < $checkinStart) {
            $opensFormatted = date('M d, Y h:i A', $checkinStart);
            return [
                'can_checkin' => false,
                'message'     => "Check-in is not open yet. Check-in opens on {$opensFormatted}.",
            ];
        }

        if ($now > $endTime) {
            return [
                'can_checkin' => false,
                'message'     => 'This event has concluded. Check-in is closed.',
            ];
        }

        return [
            'can_checkin' => true,
            'message'     => 'Check-in is currently open.',
        ];
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
}
