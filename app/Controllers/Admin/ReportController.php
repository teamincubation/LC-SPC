<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CampaignRepository;
use App\Repositories\CertificateRepository;
use App\Repositories\EventRepository;
use App\Repositories\ParticipantRepository;
use App\Repositories\RegistrationRepository;
use App\Services\AuthService;
use App\Services\RoleService;

/**
 * Administrative Reports & Analytics Controller
 * Serves institutional reporting metrics and export summaries.
 */
class ReportController extends Controller
{
    private AuthService $authService;
    private CampaignRepository $campaigns;
    private EventRepository $events;
    private ParticipantRepository $participants;
    private RegistrationRepository $registrations;
    private CertificateRepository $certificates;

    public function __construct(
        ?AuthService $authService = null,
        ?CampaignRepository $campaigns = null,
        ?EventRepository $events = null,
        ?ParticipantRepository $participants = null,
        ?RegistrationRepository $registrations = null,
        ?CertificateRepository $certificates = null
    ) {
        $this->authService = $authService ?? new AuthService();
        $this->campaigns = $campaigns ?? new CampaignRepository();
        $this->events = $events ?? new EventRepository();
        $this->participants = $participants ?? new ParticipantRepository();
        $this->registrations = $registrations ?? new RegistrationRepository();
        $this->certificates = $certificates ?? new CertificateRepository();
    }

    /**
     * Show reports overview page.
     */
    public function index(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();

        $campCounts = $this->campaigns->countByStatus();
        $eventCounts = $this->events->countByStatus();
        $partCounts = $this->participants->countByStatus();
        $regCounts = $this->registrations->countByStatus();
        $certMetrics = $this->certificates->getMetrics();
        $recentEvents = array_slice($this->events->all(false, []), 0, 5);

        return $this->render('admin/reports/index', [
            'title'         => 'Reports & Analytics',
            'breadcrumb'    => 'Reports',
            'user'          => $user,
            'roleLabel'     => RoleService::getRoleLabel($user['role'] ?? 'viewer'),
            'campCounts'    => $campCounts,
            'eventCounts'   => $eventCounts,
            'partCounts'    => $partCounts,
            'regCounts'     => $regCounts,
            'certMetrics'   => $certMetrics,
            'recentEvents'  => $recentEvents,
        ], 'layouts/admin');
    }

    /**
     * Final Event Report.
     * GET /admin/reports/event/{id}
     * Only available when Event status = 'completed'.
     */
    public function eventReport(Request $request, array $vars): Response
    {
        $user = $this->authService->getCurrentUser();
        $eventId = (int) ($vars['id'] ?? 0);

        $event = $this->events->findById($eventId);
        if (!$event) {
            \App\Core\Session::flash('error', 'Event not found.');
            return Response::redirect(url('/admin/reports'));
        }

        $isCompleted = ($event['status'] ?? '') === 'completed';

        $reportData = [
            'title'       => 'Final Event Report — ' . ($event['title'] ?? ''),
            'event'       => $event,
            'isCompleted' => $isCompleted,
            'user'        => $user,
        ];

        if ($isCompleted) {
            // Calculate Turnout Metrics
            $statsSql = "
                SELECT 
                    COUNT(*) AS total_registrations,
                    SUM(CASE WHEN `attendance_status` = 'attended' THEN 1 ELSE 0 END) AS checked_in_count,
                    SUM(CASE WHEN `attendance_status` != 'attended' OR `attendance_status` IS NULL THEN 1 ELSE 0 END) AS not_checked_in_count
                FROM `event_registrations`
                WHERE `event_id` = :eid
            ";
            $stats = \App\Core\Database::fetch($statsSql, [':eid' => $eventId]) ?: [
                'total_registrations'   => 0,
                'checked_in_count'      => 0,
                'not_checked_in_count'  => 0,
            ];

            $totalReg = (int) $stats['total_registrations'];
            $checkedIn = (int) $stats['checked_in_count'];
            $notCheckedIn = (int) $stats['not_checked_in_count'];
            $attendanceRate = $totalReg > 0 ? round(($checkedIn / $totalReg) * 100, 1) : 0.0;

            // Hourly Timeline
            $timelineSql = "
                SELECT DATE_FORMAT(`attended_at`, '%H:00') AS `hour_slot`, COUNT(*) AS `count`
                FROM `event_registrations`
                WHERE `event_id` = :eid AND `attendance_status` = 'attended' AND `attended_at` IS NOT NULL
                GROUP BY `hour_slot`
                ORDER BY `hour_slot` ASC
            ";
            $hourlyTimeline = \App\Core\Database::fetchAll($timelineSql, [':eid' => $eventId]);

            // Category Breakdown
            $catSql = "
                SELECT COALESCE(NULLIF(p.`category`, ''), 'General') AS `category`, COUNT(*) AS `count`
                FROM `event_registrations` er
                LEFT JOIN `participants` p ON er.`participant_id` = p.`id`
                WHERE er.`event_id` = :eid
                GROUP BY `category`
                ORDER BY `count` DESC
            ";
            $categories = \App\Core\Database::fetchAll($catSql, [':eid' => $eventId]);

            // Device Breakdown
            $deviceSql = "
                SELECT COALESCE(NULLIF(rm.`device_type`, ''), 'Unknown') AS `device_type`, COUNT(*) AS `count`
                FROM `event_registrations` er
                LEFT JOIN `registration_metadata` rm ON rm.`registration_id` = er.`id`
                WHERE er.`event_id` = :eid
                GROUP BY `device_type`
            ";
            $devices = \App\Core\Database::fetchAll($deviceSql, [':eid' => $eventId]);

            // Total Certificates Issued
            $certSql = "
                SELECT COUNT(*) AS cert_count
                FROM `certificates` c
                JOIN `event_registrations` er ON c.`registration_id` = er.`id`
                WHERE er.`event_id` = :eid AND c.`status` = 'active'
            ";
            $certRow = \App\Core\Database::fetch($certSql, [':eid' => $eventId]);
            $certCount = (int) ($certRow['cert_count'] ?? 0);

            // Participant Roster
            $rosterSql = "
                SELECT 
                    er.`id`,
                    er.`registration_code`,
                    COALESCE(p.`full_name`, 'Attendee') AS `full_name`,
                    COALESCE(p.`phone`, er.`phone_normalized`, '') AS `phone`,
                    COALESCE(p.`category`, 'General') AS `category`,
                    er.`attendance_status`,
                    er.`attended_at`,
                    er.`check_in_method`,
                    er.`checkin_geofence_verified`,
                    c.`certificate_number`
                FROM `event_registrations` er
                LEFT JOIN `participants` p ON er.`participant_id` = p.`id`
                LEFT JOIN `certificates` c ON c.`registration_id` = er.`id` AND c.`status` = 'active'
                WHERE er.`event_id` = :eid
                ORDER BY er.`attendance_status` DESC, er.`attended_at` ASC, er.`id` ASC
            ";
            $roster = \App\Core\Database::fetchAll($rosterSql, [':eid' => $eventId]);

            // Mask phone numbers for non-super_admin or general display
            foreach ($roster as &$row) {
                $p = $row['phone'];
                if (strlen($p) >= 10) {
                    $row['phone_masked'] = substr($p, 0, 3) . '*** ***' . substr($p, -3);
                } else {
                    $row['phone_masked'] = '***';
                }
            }
            unset($row);

            $reportData = array_merge($reportData, [
                'total_registrations'  => $totalReg,
                'checked_in_count'     => $checkedIn,
                'not_checked_in_count' => $notCheckedIn,
                'attendance_rate'      => $attendanceRate,
                'hourly_timeline'      => $hourlyTimeline,
                'categories'           => $categories,
                'devices'              => $devices,
                'certificates_count'   => $certCount,
                'roster'               => $roster,
            ]);
        }

        return $this->render('admin/reports/event', $reportData, 'layouts/admin');
    }

    /**
     * Export Final Event Report (CSV format with privacy masking).
     * GET /admin/reports/event/{id}/export
     */
    public function exportEventReport(Request $request, array $vars): Response
    {
        $user = $this->authService->getCurrentUser();
        $userId = (int) ($user['id'] ?? 0);

        $permService = new \App\Services\PermissionService();
        if (!$permService->can($userId, 'reports', 'export') && !in_array($user['role'] ?? '', ['super_admin', 'coordinator'], true)) {
            \App\Core\Session::flash('error', 'You do not have permission to export reports.');
            return Response::redirect(url('/admin/reports'));
        }

        $eventId = (int) ($vars['id'] ?? 0);
        $event = $this->events->findById($eventId);
        if (!$event || ($event['status'] ?? '') !== 'completed') {
            \App\Core\Session::flash('error', 'Final Event Report is only exportable after the event is completed.');
            return Response::redirect(url('/admin/reports'));
        }

        $rosterSql = "
            SELECT 
                er.`registration_code`,
                COALESCE(p.`full_name`, 'Attendee') AS `full_name`,
                COALESCE(p.`phone`, er.`phone_normalized`, '') AS `phone`,
                COALESCE(p.`category`, 'General') AS `category`,
                er.`attendance_status`,
                er.`attended_at`,
                er.`check_in_method`,
                er.`checkin_geofence_verified`,
                c.`certificate_number`
            FROM `event_registrations` er
            LEFT JOIN `participants` p ON er.`participant_id` = p.`id`
            LEFT JOIN `certificates` c ON c.`registration_id` = er.`id` AND c.`status` = 'active'
            WHERE er.`event_id` = :eid
            ORDER BY er.`attendance_status` DESC, er.`attended_at` ASC
        ";
        $roster = \App\Core\Database::fetchAll($rosterSql, [':eid' => $eventId]);

        $output = fopen('php://memory', 'r+');
        fputcsv($output, [
            'Registration Code',
            'Attendee Name',
            'Masked Mobile',
            'Category',
            'Attendance Status',
            'Checked-in Time',
            'Check-in Method',
            'Geofence Verified',
            'Certificate ID',
        ]);

        foreach ($roster as $row) {
            $rawPhone = $row['phone'] ?? '';
            $maskedPhone = strlen($rawPhone) >= 10 
                ? substr($rawPhone, 0, 3) . '*** ***' . substr($rawPhone, -3) 
                : '***';

            fputcsv($output, [
                $row['registration_code'] ?? '',
                $row['full_name'] ?? '',
                $maskedPhone,
                $row['category'] ?? '',
                $row['attendance_status'] ?? 'unmarked',
                $row['attended_at'] ?? 'N/A',
                $row['check_in_method'] ?? 'N/A',
                !empty($row['checkin_geofence_verified']) ? 'Yes' : 'No',
                $row['certificate_number'] ?? 'N/A',
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        $safeTitle = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) ($event['slug'] ?? 'event'));
        $filename = "Final_Event_Report_{$safeTitle}_" . date('Ymd') . ".csv";

        return new Response($csvContent, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store, no-cache',
        ]);
    }
}
