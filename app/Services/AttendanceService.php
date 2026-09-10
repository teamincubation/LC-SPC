<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\CheckInException;
use App\Repositories\EventRepository;
use App\Repositories\RegistrationRepository;

/**
 * Attendance Management & Reconciliation Service
 * Coordinates attendance roster inspection, manual status corrections, reversals,
 * time-gated bulk-absent reconciliation, and privacy-shielded CSV exports.
 */
class AttendanceService
{
    private RegistrationRepository $registrationRepo;
    private EventRepository $eventRepo;
    private AuditService $auditService;
    private RegistrationService $registrationService;
    private ParticipantService $participantService;

    public function __construct(
        ?RegistrationRepository $registrationRepo = null,
        ?EventRepository $eventRepo = null,
        ?AuditService $auditService = null,
        ?RegistrationService $registrationService = null,
        ?ParticipantService $participantService = null
    ) {
        $this->registrationRepo = $registrationRepo ?? new RegistrationRepository();
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->auditService = $auditService ?? new AuditService();
        $this->registrationService = $registrationService ?? new RegistrationService();
        $this->participantService = $participantService ?? new ParticipantService();
    }

    /**
     * Retrieve full attendance roster data, metrics, and gate states for an event.
     *
     * @param int $eventId Event ID
     * @param array $filters Query filters ('attendance_status', 'search')
     * @param int $page Pagination page
     * @param int $perPage Items per page
     * @param string $userRole Role of viewing user
     * @return array Consolidated roster and metric envelope
     * @throws CheckInException
     */
    public function getEventAttendanceRoster(
        int $eventId,
        array $filters = [],
        int $page = 1,
        int $perPage = 50,
        string $userRole = RoleService::ROLE_VIEWER
    ): array {
        $event = $this->eventRepo->findById($eventId);
        if ($event === null || !empty($event['deleted_at'])) {
            throw new CheckInException("Event not found or has been deleted.", 404);
        }

        $pagination = $this->registrationRepo->getAttendanceRoster($eventId, $filters, $page, $perPage);

        // Apply Privacy Shield according to locked architecture:
        // - coordinator / super_admin: full permitted PII
        // - staff: masked PII (maskEmail, maskPhone, maskCode)
        // - viewer: de-identified roster ([De-identified Attendee], [De-identified], [De-identified], maskCode)
        $isPrivileged = RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR);
        foreach ($pagination['items'] as &$item) {
            if ($userRole === RoleService::ROLE_VIEWER) {
                $item['participant_name'] = '[De-identified Attendee]';
                $item['participant_email'] = '[De-identified]';
                $item['participant_phone'] = '[De-identified]';
                $item['registration_code'] = RegistrationService::maskCode($item['registration_code']);
            } elseif ($userRole === RoleService::ROLE_STAFF) {
                $item['participant_email'] = ParticipantService::maskEmail($item['participant_email'] ?? null);
                $item['participant_phone'] = ParticipantService::maskPhone($item['participant_phone'] ?? null);
                $item['registration_code'] = RegistrationService::maskCode($item['registration_code']);
            }
        }
        unset($item);

        // Calculate attendance KPIs and intake channel breakdowns
        $metrics = $this->registrationRepo->countAttendanceByEvent($eventId);
        $methodCounts = $this->registrationRepo->countCheckInMethodsByEvent($eventId);

        // Time gate: Bulk absent is strictly locked until event.end_time + 4 hours
        $endTime = strtotime((string) $event['end_time']);
        $bulkAbsentUnlockTime = ($endTime !== false) ? $endTime + (4 * 3600) : time() + 86400;
        $now = time();
        $bulkAbsentUnlocked = ($now > $bulkAbsentUnlockTime);
        $bulkAbsentAvailableAt = date('Y-m-d H:i', $bulkAbsentUnlockTime);

        return [
            'event'                     => $event,
            'roster'                    => $pagination,
            'metrics'                   => $metrics,
            'method_counts'             => $methodCounts,
            'bulk_absent_unlocked'      => $bulkAbsentUnlocked,
            'bulk_absent_available_at'  => $bulkAbsentAvailableAt,
            'is_privileged'             => $isPrivileged,
        ];
    }

    /**
     * Update an individual attendance record (manual check-in, excuse, absent, or reversal).
     *
     * @throws CheckInException
     */
    public function updateAttendanceStatus(
        int $regId,
        string $newStatus,
        string $reason,
        int $actorId,
        string $actorRole
    ): array {
        // Enforce RBAC: Coordinators and Super Admins only
        if (!RoleService::hasRole($actorRole, RoleService::ROLE_COORDINATOR)) {
            throw new CheckInException("You do not have permission to modify attendance records.", 403);
        }

        $validStatuses = ['unmarked', 'attended', 'absent', 'excused'];
        if (!in_array($newStatus, $validStatuses, true)) {
            throw new CheckInException("Invalid attendance status: '{$newStatus}'.", 422);
        }

        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new CheckInException("An operational reason is required for attendance status changes.", 422);
        }

        // Prohibit sensitive clinical / mental health keywords
        $this->registrationService->validateAdminNotes($cleanReason);

        $registration = $this->registrationRepo->findById($regId);
        if ($registration === null) {
            throw new CheckInException("Registration record not found.", 404);
        }

        $previousStatus = $registration['attendance_status'] ?? 'unmarked';
        if ($previousStatus === $newStatus) {
            return [
                'success' => true,
                'message' => "Attendance status is already '{$newStatus}'.",
                'status'  => $newStatus,
            ];
        }

        // Execute status mutation
        $this->registrationRepo->updateAttendanceStatus(
            $regId,
            $newStatus,
            $cleanReason,
            $actorId,
            'admin_manual'
        );

        // Audit Trail determination
        $code = $registration['registration_code'] ?? '';
        $maskedCode = RegistrationService::maskCode($code);

        $auditMetadata = [
            'event_id'         => (int) $registration['event_id'],
            'registration_id'  => $regId,
            'masked_code'      => $maskedCode,
            'from_status'      => $previousStatus,
            'to_status'        => $newStatus,
            'reason'           => $cleanReason,
        ];

        if ($newStatus === 'unmarked') {
            $action = 'attendance.reversed';
            $auditMetadata['previous_method'] = $registration['check_in_method'];
            $auditMetadata['previous_checkin'] = $registration['checked_in_at'];
        } elseif ($newStatus === 'absent') {
            $action = 'attendance.marked_absent';
        } elseif ($newStatus === 'excused') {
            $action = 'attendance.marked_excused';
        } else {
            // attended
            $action = ($previousStatus === 'unmarked') ? 'attendance.checkin' : 'attendance.corrected';
            $auditMetadata['method'] = 'admin_manual';
        }

        $this->auditService->log(
            $action,
            'event_registration',
            $regId,
            $auditMetadata,
            $actorId
        );

        return [
            'success'   => true,
            'message'   => "Attendance status successfully updated to '{$newStatus}'.",
            'status'    => $newStatus,
            'attendee'  => $registration['participant_name'],
        ];
    }

    /**
     * Bulk mark remaining unmarked confirmed registrations as 'absent'.
     * Strictly gated until event.end_time + 4 hours.
     *
     * @throws CheckInException
     */
    public function bulkMarkRemainingAbsent(int $eventId, int $actorId, string $actorRole): int
    {
        // Enforce RBAC
        if (!RoleService::hasRole($actorRole, RoleService::ROLE_COORDINATOR)) {
            throw new CheckInException("You do not have permission to perform bulk attendance reconciliation.", 403);
        }

        $event = $this->eventRepo->findById($eventId);
        if ($event === null || !empty($event['deleted_at'])) {
            throw new CheckInException("Event not found or has been deleted.", 404);
        }

        // Timing Gate: Locked until event.end_time + 4 hours
        $endTime = strtotime((string) $event['end_time']);
        $windowClose = ($endTime !== false) ? $endTime + (4 * 3600) : time() + 86400;

        if (time() <= $windowClose) {
            $availableAt = date('Y-m-d H:i', $windowClose);
            throw new CheckInException(
                "Bulk absent reconciliation is locked until {$availableAt} (event end time + 4 hours).",
                422
            );
        }

        $now = date('Y-m-d H:i:s');
        $count = $this->registrationRepo->markRemainingAbsent($eventId, $now);

        // Audit Trail
        $this->auditService->log(
            'attendance.bulk_absent',
            'event',
            $eventId,
            [
                'event_id'     => $eventId,
                'count_marked' => $count,
                'executed_at'  => $now,
            ],
            $actorId
        );

        return $count;
    }

    /**
     * Export complete attendance roster for an event as RFC 4180 compliant CSV.
     * Enforces privacy masking for staff and viewer roles.
     *
     * @throws CheckInException
     */
    public function exportAttendanceCsv(int $eventId, string $userRole): string
    {
        $event = $this->eventRepo->findById($eventId);
        if ($event === null || !empty($event['deleted_at'])) {
            throw new CheckInException("Event not found or has been deleted.", 404);
        }

        $registrations = $this->registrationRepo->getAllConfirmedForExport($eventId);
        $isPrivileged = RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR);

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            throw new CheckInException("Failed to initialize CSV export buffer.", 500);
        }

        // CSV Header
        $headers = [
            'Registration Code',
            'Attendee Name',
            'Category',
            'Organization',
            'Registration Status',
            'Attendance Status',
            'Checked In At',
            'Checked In By',
            'Check-In Method',
            'Contact Email',
            'Contact Phone',
        ];
        fputcsv($output, $headers, ',', '"', "\\");

        foreach ($registrations as $reg) {
            if ($userRole === RoleService::ROLE_VIEWER) {
                $code = RegistrationService::maskCode($reg['registration_code']);
                $name = '[De-identified Attendee]';
                $email = '[De-identified]';
                $phone = '[De-identified]';
            } elseif ($userRole === RoleService::ROLE_STAFF) {
                $code = RegistrationService::maskCode($reg['registration_code']);
                $name = $reg['participant_name'] ?? '';
                $email = ParticipantService::maskEmail($reg['participant_email'] ?? null);
                $phone = ParticipantService::maskPhone($reg['participant_phone'] ?? null);
            } else {
                $code = $reg['registration_code'];
                $name = $reg['participant_name'] ?? '';
                $email = $reg['participant_email'] ?? '';
                $phone = $reg['participant_phone'] ?? '';
            }

            $row = [
                $code,
                $name,
                $reg['participant_category'] ?? '',
                $reg['participant_organization'] ?? '',
                $reg['status'] ?? '',
                $reg['attendance_status'] ?? 'unmarked',
                $reg['checked_in_at'] ?? '',
                $reg['checked_in_by_name'] ?? '',
                $reg['check_in_method'] ?? '',
                $email,
                $phone,
            ];

            fputcsv($output, $row, ',', '"', "\\");
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent !== false ? $csvContent : '';
    }
}
