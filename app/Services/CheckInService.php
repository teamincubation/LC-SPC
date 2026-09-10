<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\CheckInException;
use App\Repositories\EventRepository;
use App\Repositories\RegistrationRepository;
use App\Repositories\UserRepository;

/**
 * Attendee Check-In Service
 * Orchestrates QR code & manual check-in verification according to the approved 9-step pipeline:
 * 1. Registration exists
 * 2. Registration belongs to intended event
 * 3. Registration status is confirmed (not cancelled/pending/waitlisted)
 * 4. Event is eligible
 * 5. Check whether attendance_status is already 'attended' (Idempotent short-circuit)
 * 6. Validate check-in timing window
 * 7. Enforce role window restrictions / require override reason
 * 8. Perform atomic conditional attendance update
 * 9. Create appropriate audit log
 */
class CheckInService
{
    private RegistrationRepository $registrationRepo;
    private EventRepository $eventRepo;
    private AuditService $auditService;
    private RegistrationService $registrationService;
    private UserRepository $userRepo;

    public function __construct(
        ?RegistrationRepository $registrationRepo = null,
        ?EventRepository $eventRepo = null,
        ?AuditService $auditService = null,
        ?RegistrationService $registrationService = null,
        ?UserRepository $userRepo = null
    ) {
        $this->registrationRepo = $registrationRepo ?? new RegistrationRepository();
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->auditService = $auditService ?? new AuditService();
        $this->registrationService = $registrationService ?? new RegistrationService();
        $this->userRepo = $userRepo ?? new UserRepository();
    }

    /**
     * Extract alphanumeric pass code from raw code or full pass URL.
     * Supported formats:
     * - REG-26-8A7D3
     * - https://teami.in/LC/registration/pass/REG-26-8A7D3
     * - http://localhost:8000/registration/pass/REG-26-8A7D3
     */
    public function extractCode(string $codeOrUrl): ?string
    {
        $trimmed = trim($codeOrUrl);
        $pattern = '/^(?:https?:\/\/[^\/]+(?:\/[^\/]+)*\/registration\/pass\/)?(REG-\d{2}-[23456789ABCDEFGHJKMNPQRSTVWXYZ]{5})$/i';

        if (preg_match($pattern, $trimmed, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    /**
     * Determine if a timestamp falls within the event's operational check-in window:
     * [event.start_time - 2 hours, event.end_time + 4 hours].
     */
    public function isWithinOperationalWindow(array $event, int $nowTimestamp): bool
    {
        $startTime = strtotime((string) $event['start_time']);
        $endTime = strtotime((string) $event['end_time']);

        if ($startTime === false || $endTime === false) {
            return false;
        }

        $windowStart = $startTime - (2 * 3600); // 2 hours before
        $windowEnd = $endTime + (4 * 3600);     // 4 hours after

        return ($nowTimestamp >= $windowStart && $nowTimestamp <= $windowEnd);
    }

    /**
     * Execute attendee check-in following the locked 9-step validation sequence.
     *
     * @param string $codeOrUrl Raw pass code or scanned pass URL
     * @param int $consoleEventId The event ID active in the check-in console
     * @param int $staffUserId The authenticated staff member ID executing check-in
     * @param string $method Check-in intake method ('qr_scan' or 'admin_manual')
     * @param string|null $overrideReason Operational reason if checking in outside window (coordinator+)
     * @param string $userRole The role of the user executing the check-in ('staff', 'coordinator', 'super_admin')
     * @return array Verification payload
     * @throws CheckInException
     */
    public function checkIn(
        string $codeOrUrl,
        int $consoleEventId,
        int $staffUserId,
        string $method = 'qr_scan',
        ?string $overrideReason = null,
        string $userRole = RoleService::ROLE_STAFF
    ): array {
        // Enforce method invariant: self_verified is strictly disabled in Phase 1F MVP
        if ($method === 'self_verified') {
            throw new CheckInException("Self-verified check-in is not permitted.", 422);
        }

        if (!in_array($method, ['qr_scan', 'admin_manual'], true)) {
            $method = 'admin_manual';
        }

        // ---------------------------------------------------------------------
        // STEP 1: REGISTRATION EXISTS
        // ---------------------------------------------------------------------
        $code = $this->extractCode($codeOrUrl);
        if ($code === null) {
            throw new CheckInException("Invalid registration pass code format.", 422);
        }

        $registration = $this->registrationRepo->findByCode($code);
        if ($registration === null) {
            throw new CheckInException("Pass Code Not Found", 404);
        }

        // ---------------------------------------------------------------------
        // STEP 2: REGISTRATION BELONGS TO INTENDED EVENT
        // ---------------------------------------------------------------------
        $regEventId = (int) $registration['event_id'];
        if ($regEventId !== $consoleEventId) {
            $eventTitle = $registration['event_title'] ?? 'another event';
            throw new CheckInException(
                "Pass is for a different event: '{$eventTitle}'. Not valid for current event.",
                422,
                [
                    'pass_event_id'    => $regEventId,
                    'console_event_id' => $consoleEventId,
                    'pass_event_title' => $eventTitle,
                ]
            );
        }

        // ---------------------------------------------------------------------
        // STEP 3: REGISTRATION STATUS IS CONFIRMED
        // (Rejects cancelled, pending, and waitlisted registrations)
        // ---------------------------------------------------------------------
        $status = $registration['status'] ?? '';
        if ($status === 'cancelled') {
            throw new CheckInException("Registration is Cancelled - Entry Denied", 403);
        }

        if ($status === 'pending') {
            throw new CheckInException("Registration Pending Approval - Entry Denied", 403);
        }

        if ($status === 'waitlisted') {
            throw new CheckInException("Registration is Waitlisted - No Pass Issued", 403);
        }

        if ($status !== 'confirmed') {
            throw new CheckInException("Registration status is '{$status}' - Entry Denied", 403);
        }

        // ---------------------------------------------------------------------
        // STEP 4: EVENT IS ELIGIBLE
        // ---------------------------------------------------------------------
        $event = $this->eventRepo->findById($consoleEventId);
        if ($event === null || !empty($event['deleted_at'])) {
            throw new CheckInException("Event not found or has been deleted.", 404);
        }

        $eventStatus = $event['status'] ?? '';
        if ($eventStatus === 'draft') {
            throw new CheckInException("Event is in draft status and not eligible for attendance.", 403);
        }

        if ($eventStatus === 'cancelled') {
            throw new CheckInException("Event has been cancelled. Check-in is prohibited.", 403);
        }

        if (!in_array($eventStatus, ['published', 'ongoing', 'completed'], true)) {
            throw new CheckInException("Event is not open for check-in.", 403);
        }

        // ---------------------------------------------------------------------
        // STEP 5: CHECK WHETHER ATTENDANCE STATUS IS ALREADY 'ATTENDED'
        // (Idempotent short-circuit before timing window or mutation)
        // ---------------------------------------------------------------------
        if (($registration['attendance_status'] ?? '') === 'attended') {
            $formattedTime = !empty($registration['checked_in_at'])
                ? date('h:i A', strtotime((string) $registration['checked_in_at']))
                : 'earlier';
            $checkerName = $registration['checked_in_by_name'] ?? 'Desk Staff';

            return [
                'success'            => true,
                'already_checked_in' => true,
                'status'             => 'attended',
                'attendee_name'      => $registration['participant_name'],
                'category'           => $registration['participant_category'],
                'event_title'        => $registration['event_title'],
                'checked_in_at'      => $registration['checked_in_at'],
                'checked_in_by_name' => $checkerName,
                'check_in_method'    => $registration['check_in_method'],
                'masked_code'        => RegistrationService::maskCode($code),
                'message'            => "Already verified at {$formattedTime} by {$checkerName}.",
            ];
        }

        // Absent and excused registrations cannot be transitioned to attended through check-in flow.
        // Corrections remain coordinator attendance-management operations only.
        if (in_array($registration['attendance_status'] ?? '', ['absent', 'excused'], true)) {
            $currStatus = (string) $registration['attendance_status'];
            throw new CheckInException(
                "Registration is already marked as '{$currStatus}'. Status cannot be changed through check-in.",
                422,
                ['attendance_status' => $currStatus]
            );
        }

        // ---------------------------------------------------------------------
        // STEP 6: VALIDATE THE CHECK-IN TIMING WINDOW
        // ---------------------------------------------------------------------
        $nowTimestamp = time();
        $isWithinWindow = $this->isWithinOperationalWindow($event, $nowTimestamp);

        // ---------------------------------------------------------------------
        // STEP 7: ENFORCE ROLE TIMING RESTRICTIONS / OPERATIONAL OVERRIDE REASON
        // ---------------------------------------------------------------------
        $isOverride = false;
        $cleanOverrideReason = null;

        if (!$isWithinWindow) {
            // Staff role is strictly blocked outside operational window
            if (!RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR)) {
                throw new CheckInException(
                    "Check-in desk is closed. Event is outside the operational window (2 hours before start to 4 hours after end).",
                    403
                );
            }

            // Coordinator/Super Admin may override with mandatory operational reason
            $cleanOverrideReason = trim((string) $overrideReason);
            if ($cleanOverrideReason === '') {
                throw new CheckInException(
                    "An operational reason is required for out-of-window check-in override.",
                    422,
                    ['override_reason' => 'An operational reason is required.']
                );
            }

            // Prohibit sensitive clinical / mental health keywords in override reason
            $this->registrationService->validateAdminNotes($cleanOverrideReason);
            $isOverride = true;
        }

        // ---------------------------------------------------------------------
        // STEP 8: PERFORM ATOMIC CONDITIONAL ATTENDANCE UPDATE
        // ---------------------------------------------------------------------
        $now = date('Y-m-d H:i:s');
        $affected = $this->registrationRepo->updateAttendanceAtomic(
            (int) $registration['id'],
            $method,
            $staffUserId,
            $now
        );

        // If rowCount === 0, handle race condition (another desk just checked them in)
        if ($affected === 0) {
            $fresh = $this->registrationRepo->findByCode($code);
            if (($fresh['attendance_status'] ?? '') === 'attended') {
                $checkerName = $fresh['checked_in_by_name'] ?? 'Desk Staff';
                $formattedTime = !empty($fresh['checked_in_at'])
                    ? date('h:i A', strtotime((string) $fresh['checked_in_at']))
                    : 'just now';

                return [
                    'success'            => true,
                    'already_checked_in' => true,
                    'status'             => 'attended',
                    'attendee_name'      => $fresh['participant_name'],
                    'category'           => $fresh['participant_category'],
                    'event_title'        => $fresh['event_title'],
                    'checked_in_at'      => $fresh['checked_in_at'],
                    'checked_in_by_name' => $checkerName,
                    'check_in_method'    => $fresh['check_in_method'],
                    'masked_code'        => RegistrationService::maskCode($code),
                    'message'            => "Already verified at {$formattedTime} by {$checkerName}.",
                ];
            }

            if (in_array($fresh['attendance_status'] ?? '', ['absent', 'excused'], true)) {
                throw new CheckInException(
                    "Registration is already marked as '{$fresh['attendance_status']}'. Status cannot be changed through check-in.",
                    422,
                    ['attendance_status' => $fresh['attendance_status']]
                );
            }

            throw new CheckInException("Unable to update check-in status. Registration state may have changed.", 409);
        }

        // ---------------------------------------------------------------------
        // STEP 9: CREATE THE APPROPRIATE AUDIT LOG
        // ---------------------------------------------------------------------
        $auditMetadata = [
            'event_id'        => $consoleEventId,
            'registration_id' => (int) $registration['id'],
            'masked_code'     => RegistrationService::maskCode($code),
            'method'          => $method,
            'checked_in_at'   => $now,
        ];

        if ($isOverride) {
            $auditMetadata['out_of_window'] = true;
            $auditMetadata['override_reason'] = $cleanOverrideReason;
            $auditMetadata['window_start'] = date('Y-m-d H:i:s', strtotime((string) $event['start_time']) - 7200);
            $auditMetadata['window_end'] = date('Y-m-d H:i:s', strtotime((string) $event['end_time']) + 14400);
        }

        $this->auditService->log(
            'attendance.checkin',
            'event_registration',
            (int) $registration['id'],
            $auditMetadata,
            $staffUserId
        );

        // Fetch staff name for display
        $staffUser = $this->userRepo->findById($staffUserId);
        $staffName = $staffUser['name'] ?? 'Desk Staff';

        return [
            'success'            => true,
            'already_checked_in' => false,
            'status'             => 'attended',
            'attendee_name'      => $registration['participant_name'],
            'category'           => $registration['participant_category'],
            'event_title'        => $registration['event_title'],
            'checked_in_at'      => $now,
            'checked_in_by_name' => $staffName,
            'check_in_method'    => $method,
            'masked_code'        => RegistrationService::maskCode($code),
            'message'            => "Verified! Welcome, " . $registration['participant_name'],
        ];
    }
}
