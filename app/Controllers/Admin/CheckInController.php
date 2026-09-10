<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\CheckInException;
use App\Core\Exceptions\ValidationException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\EventRepository;
use App\Repositories\RegistrationRepository;
use App\Services\AuditService;
use App\Services\CheckInService;
use App\Services\ParticipantService;
use App\Services\RegistrationService;
use App\Services\RoleService;
use Throwable;

/**
 * Check-In Console Controller
 * Provides mobile-first camera scanning, real-time manual attendee intake,
 * and JSON verification API for event desk operations.
 */
class CheckInController
{
    private CheckInService $checkInService;
    private EventRepository $eventRepo;
    private RegistrationRepository $registrationRepo;
    private AuditService $auditService;
    private string $rateLimitDir;

    public function __construct(
        ?CheckInService $checkInService = null,
        ?EventRepository $eventRepo = null,
        ?RegistrationRepository $registrationRepo = null,
        ?AuditService $auditService = null,
        ?string $rateLimitDir = null
    ) {
        $this->checkInService = $checkInService ?? new CheckInService();
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->registrationRepo = $registrationRepo ?? new RegistrationRepository();
        $this->auditService = $auditService ?? new AuditService();
        $this->rateLimitDir = $rateLimitDir ?? (defined('APP_ROOT') ? APP_ROOT . '/storage/cache/rate_limits' : dirname(__DIR__, 3) . '/storage/cache/rate_limits');
    }

    /**
     * Display Check-In Hub: lists active published and ongoing events.
     * GET /admin/checkin
     * Role: staff+
     */
    public function index(Request $request): Response
    {
        $allEvents = $this->eventRepo->all(false);

        // Filter events open for attendance (published, ongoing, completed within 48h)
        $activeEvents = [];
        $now = time();

        foreach ($allEvents as $ev) {
            $status = $ev['status'] ?? '';
            $endTime = strtotime((string) $ev['end_time']);

            if (in_array($status, ['published', 'ongoing'], true)) {
                $activeEvents[] = $ev;
            } elseif ($status === 'completed' && $endTime !== false && ($now - $endTime) <= (48 * 3600)) {
                $activeEvents[] = $ev;
            }
        }

        // Attach attendance KPI metrics to each active event
        foreach ($activeEvents as &$event) {
            $metrics = $this->registrationRepo->countAttendanceByEvent((int) $event['id']);
            $event['attendance_metrics'] = $metrics;
        }
        unset($event);

        return Response::html(
            View::render('admin/checkin/index', [
                'title'  => 'Check-In Console — Event Hub',
                'events' => $activeEvents,
            ], 'layouts/admin')
        );
    }

    /**
     * Display the Mobile-First Check-In Console for a specific event.
     * GET /admin/checkin/event/{id}
     * Role: staff+
     */
    public function eventConsole(Request $request, array $vars): Response
    {
        $eventId = (int) ($vars['id'] ?? 0);
        $event = $this->eventRepo->findById($eventId);

        if ($event === null || !empty($event['deleted_at'])) {
            Session::flash('error', 'Event not found or has been deleted.');
            return Response::redirect(url('/admin/checkin'));
        }

        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_STAFF);

        // Fetch full confirmed attendee roster for the live manual search tab
        $rosterData = $this->registrationRepo->getAttendanceRoster($eventId, ['attendance_status' => 'all'], 1, 500);
        $attendees = $rosterData['items'] ?? [];

        // Apply Privacy Shield masking for staff role
        if (!RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR)) {
            foreach ($attendees as &$attendee) {
                $attendee['participant_email'] = ParticipantService::maskEmail($attendee['participant_email'] ?? null);
                $attendee['participant_phone'] = ParticipantService::maskPhone($attendee['participant_phone'] ?? null);
            }
            unset($attendee);
        }

        // Real-time KPI metrics
        $metrics = $this->registrationRepo->countAttendanceByEvent($eventId);

        // Check operational window status
        $now = time();
        $isWithinWindow = $this->checkInService->isWithinOperationalWindow($event, $now);

        return Response::html(
            View::render('admin/checkin/console', [
                'title'            => 'Check-In Console — ' . $event['title'],
                'event'            => $event,
                'attendees'        => $attendees,
                'metrics'          => $metrics,
                'isWithinWindow'   => $isWithinWindow,
                'userRole'         => $userRole,
                'isCoordinator'    => RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR),
            ], 'layouts/admin')
        );
    }

    /**
     * AJAX Endpoint: Process attendee check-in verification.
     * POST /admin/checkin/verify
     * Role: staff+
     */
    public function verify(Request $request): Response
    {
        $eventId = (int) $request->input('event_id', 0);
        $code = trim((string) $request->input('code', ''));
        $method = trim((string) $request->input('method', 'qr_scan'));
        $overrideReason = $request->input('override_reason') !== null ? trim((string) $request->input('override_reason')) : null;

        $userId = (int) Session::get('_auth_user_id', 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_STAFF);
        $clientIp = $this->resolveClientIp($request);

        // Rate limiting: 60 requests per minute per user ID, 120 per IP
        if ($this->isRateLimited($userId, $clientIp)) {
            return Response::json([
                'success' => false,
                'error'   => 'Too many scan attempts. Please wait 60 seconds before scanning again.',
            ], 429, ['Retry-After' => '60']);
        }

        if ($eventId <= 0 || empty($code)) {
            return Response::json([
                'success' => false,
                'error'   => 'Missing event ID or pass code.',
            ], 422);
        }

        try {
            $result = $this->checkInService->checkIn(
                $code,
                $eventId,
                $userId,
                $method,
                $overrideReason,
                $userRole
            );

            return Response::json($result, 200);
        } catch (CheckInException $e) {
            return Response::json([
                'success' => false,
                'error'   => $e->getMessage(),
                'details' => $e->getDetails(),
            ], $e->getStatusCode());
        } catch (ValidationException $e) {
            return Response::json([
                'success' => false,
                'error'   => $e->getMessage(),
                'errors'  => $e->errors,
            ], 422);
        } catch (Throwable $e) {
            Logger::error('Check-in processing exception', [
                'event_id'  => $eventId,
                'exception' => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            return Response::json([
                'success' => false,
                'error'   => 'An unexpected server error occurred during check-in. Please try again.',
            ], 500);
        }
    }

    /**
     * Sliding window rate limiter for check-in scans.
     */
    private function isRateLimited(int $userId, string $ip): bool
    {
        if (!is_dir($this->rateLimitDir)) {
            @mkdir($this->rateLimitDir, 0755, true);
        }

        $now = time();
        $key = 'checkin_u' . $userId . '_' . md5($ip);
        $filePath = $this->rateLimitDir . DIRECTORY_SEPARATOR . $key . '.json';

        $data = ['count' => 0, 'window_start' => $now];

        if (file_exists($filePath)) {
            $existing = @json_decode((string) file_get_contents($filePath), true);
            if (is_array($existing)) {
                if (($now - ($existing['window_start'] ?? 0)) < 60) {
                    $data['count'] = ($existing['count'] ?? 0) + 1;
                    $data['window_start'] = $existing['window_start'] ?? $now;
                } else {
                    $data['count'] = 1;
                    $data['window_start'] = $now;
                }
            } else {
                $data['count'] = 1;
            }
        } else {
            $data['count'] = 1;
        }

        @file_put_contents($filePath, json_encode($data), LOCK_EX);

        // Maximum 60 requests per minute
        return $data['count'] > 60;
    }

    /**
     * Resolve client IP address safely.
     */
    private function resolveClientIp(Request $request): string
    {
        return $request->ip() ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }
}
