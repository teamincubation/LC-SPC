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
use App\Services\AttendanceService;
use App\Services\RoleService;
use Throwable;

/**
 * Event Attendance Management Controller
 * Manages event roster inspection, status corrections/reversals,
 * time-gated bulk-absent reconciliation, and privacy-shielded CSV exports.
 */
class AttendanceController
{
    private AttendanceService $attendanceService;
    private EventRepository $eventRepo;

    public function __construct(
        ?AttendanceService $attendanceService = null,
        ?EventRepository $eventRepo = null
    ) {
        $this->attendanceService = $attendanceService ?? new AttendanceService();
        $this->eventRepo = $eventRepo ?? new EventRepository();
    }

    /**
     * Display the Attendance Roster for an event.
     * GET /admin/events/{id}/attendance
     * Role: viewer+
     */
    public function index(Request $request, array $vars): Response
    {
        $eventId = (int) ($vars['id'] ?? 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_VIEWER);

        $filters = [
            'attendance_status' => $request->query('attendance_status', 'all'),
            'search'            => $request->query('search') ? trim((string) $request->query('search')) : null,
        ];

        $page = max(1, (int) $request->query('page', 1));

        try {
            $data = $this->attendanceService->getEventAttendanceRoster(
                $eventId,
                $filters,
                $page,
                50,
                $userRole
            );

            return Response::html(
                View::render('admin/attendance/index', [
                    'title'                    => 'Attendance — ' . $data['event']['title'],
                    'event'                    => $data['event'],
                    'roster'                   => $data['roster']['items'],
                    'pagination'               => $data['roster'],
                    'metrics'                  => $data['metrics'],
                    'methodCounts'             => $data['method_counts'],
                    'bulkAbsentUnlocked'       => $data['bulk_absent_unlocked'],
                    'bulkAbsentAvailableAt'    => $data['bulk_absent_available_at'],
                    'filters'                  => $filters,
                    'userRole'                 => $userRole,
                    'isCoordinator'            => RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR),
                ], 'layouts/admin')
            );
        } catch (CheckInException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect(url('/admin/events'));
        }
    }

    /**
     * Update individual attendance status (Coordinator+).
     * POST /admin/events/{id}/attendance/update
     * Role: coordinator+
     */
    public function updateStatus(Request $request, array $vars): Response
    {
        $eventId = (int) ($vars['id'] ?? 0);
        $regId = (int) $request->input('registration_id', 0);
        $newStatus = trim((string) $request->input('attendance_status', ''));
        $reason = trim((string) $request->input('reason', ''));

        $userId = (int) Session::get('_auth_user_id', 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_COORDINATOR);

        try {
            $result = $this->attendanceService->updateAttendanceStatus(
                $regId,
                $newStatus,
                $reason,
                $userId,
                $userRole
            );

            if ($request->expectsJson()) {
                return Response::json($result, 200);
            }

            Session::flash('success', $result['message']);
            return Response::redirect(url('/admin/events/' . $eventId . '/attendance'));
        } catch (CheckInException $e) {
            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'error' => $e->getMessage()], $e->getStatusCode());
            }

            Session::flash('error', $e->getMessage());
            return Response::redirect(url('/admin/events/' . $eventId . '/attendance'));
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'error' => $e->getMessage(), 'errors' => $e->errors], 422);
            }

            Session::flash('error', $e->getMessage());
            return Response::redirect(url('/admin/events/' . $eventId . '/attendance'));
        } catch (Throwable $e) {
            Logger::error('Attendance update status exception', [
                'event_id'  => $eventId,
                'reg_id'    => $regId,
                'exception' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'error' => 'An unexpected error occurred.'], 500);
            }

            Session::flash('error', 'An unexpected error occurred while updating attendance.');
            return Response::redirect(url('/admin/events/' . $eventId . '/attendance'));
        }
    }

    /**
     * Bulk mark remaining unmarked attendees as absent (Coordinator+).
     * Strictly locked until event.end_time + 4 hours.
     * POST /admin/events/{id}/attendance/bulk-absent
     * Role: coordinator+
     */
    public function bulkMarkAbsent(Request $request, array $vars): Response
    {
        $eventId = (int) ($vars['id'] ?? 0);
        $userId = (int) Session::get('_auth_user_id', 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_COORDINATOR);

        try {
            $count = $this->attendanceService->bulkMarkRemainingAbsent($eventId, $userId, $userRole);

            $msg = $count > 0
                ? "Successfully marked {$count} remaining unverified attendees as absent."
                : "No remaining unverified attendees were found.";

            if ($request->expectsJson()) {
                return Response::json(['success' => true, 'count' => $count, 'message' => $msg], 200);
            }

            Session::flash('success', $msg);
            return Response::redirect(url('/admin/events/' . $eventId . '/attendance'));
        } catch (CheckInException $e) {
            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'error' => $e->getMessage()], $e->getStatusCode());
            }

            Session::flash('error', $e->getMessage());
            return Response::redirect(url('/admin/events/' . $eventId . '/attendance'));
        } catch (Throwable $e) {
            Logger::error('Bulk mark absent exception', [
                'event_id'  => $eventId,
                'exception' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'error' => 'An unexpected server error occurred.'], 500);
            }

            Session::flash('error', 'An error occurred during bulk absent processing.');
            return Response::redirect(url('/admin/events/' . $eventId . '/attendance'));
        }
    }

    /**
     * Export attendance roster as CSV with role-appropriate PII masking.
     * GET /admin/events/{id}/attendance/export
     * Role: staff+
     */
    public function exportCsv(Request $request, array $vars): Response
    {
        $eventId = (int) ($vars['id'] ?? 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_STAFF);

        try {
            $csvContent = $this->attendanceService->exportAttendanceCsv($eventId, $userRole);
            $filename = 'attendance-event-' . $eventId . '-' . date('Ymd-His') . '.csv';

            return new Response(
                $csvContent,
                200,
                [
                    'Content-Type'        => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Pragma'              => 'no-cache',
                    'Expires'             => '0',
                ]
            );
        } catch (CheckInException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect(url('/admin/events/' . $eventId . '/attendance'));
        }
    }
}
