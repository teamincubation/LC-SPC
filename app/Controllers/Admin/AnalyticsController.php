<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\EventRepository;
use App\Repositories\RegistrationRepository;
use App\Services\PermissionService;
use App\Services\RegistrationMetadataService;

/**
 * Registration & Attendance Analytics Controller
 * Provides checked-in vs non-checked-in segmentation, geographic/device breakdowns,
 * timeline histograms, and privacy-masked exports.
 */
class AnalyticsController
{
    private EventRepository $eventRepo;
    private RegistrationRepository $regRepo;
    private PermissionService $permissionService;

    public function __construct(
        ?EventRepository $eventRepo = null,
        ?RegistrationRepository $regRepo = null,
        ?PermissionService $permissionService = null
    ) {
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->regRepo = $regRepo ?? new RegistrationRepository();
        $this->permissionService = $permissionService ?? new PermissionService();
    }

    /**
     * Analytics Overview listing all events.
     * GET /admin/analytics
     */
    public function index(Request $request): Response
    {
        $user = Session::get('user');
        $userId = (int) ($user['id'] ?? 0);

        if (!$this->permissionService->can($userId, 'analytics', 'view')) {
            Session::flash('error', 'You do not have permission to view analytics.');
            return Response::redirect(url('/admin'));
        }

        $events = $this->eventRepo->all();

        // Calculate summary metrics across events
        $eventsData = [];
        $totalAllRegistrations = 0;
        $totalAllCheckedIn = 0;

        foreach ($events as $evt) {
            $eId = (int) $evt['id'];
            $stats = $this->getEventStats($eId);
            $eventsData[] = array_merge($evt, $stats);

            $totalAllRegistrations += $stats['total_registrations'];
            $totalAllCheckedIn += $stats['checked_in_count'];
        }

        $overallAttendanceRate = $totalAllRegistrations > 0 
            ? round(($totalAllCheckedIn / $totalAllRegistrations) * 100, 1) 
            : 0.0;

        return Response::html(View::render('admin/analytics/index', [
            'title'                  => 'Registration & Attendance Analytics',
            'events'                 => $eventsData,
            'totalAllRegistrations'  => $totalAllRegistrations,
            'totalAllCheckedIn'      => $totalAllCheckedIn,
            'overallAttendanceRate'  => $overallAttendanceRate,
            'user'                   => $user,
        ]));
    }

    /**
     * Granular Event Analytics Dashboard.
     * GET /admin/analytics/event/{id}
     */
    public function eventAnalytics(Request $request, array $vars): Response
    {
        $user = Session::get('user');
        $userId = (int) ($user['id'] ?? 0);

        if (!$this->permissionService->can($userId, 'analytics', 'view')) {
            Session::flash('error', 'You do not have permission to view analytics.');
            return Response::redirect(url('/admin'));
        }

        $eventId = (int) ($vars['id'] ?? 0);
        $event = $this->eventRepo->findById($eventId);

        if (!$event) {
            Session::flash('error', 'Event not found.');
            return Response::redirect(url('/admin/analytics'));
        }

        $stats = $this->getEventStats($eventId);

        // Hourly Check-in Timeline
        $timelineSql = "
            SELECT DATE_FORMAT(`attended_at`, '%H:00') AS `hour_slot`, COUNT(*) AS `checkin_count`
            FROM `event_registrations`
            WHERE `event_id` = :eid AND `attendance_status` = 'attended' AND `attended_at` IS NOT NULL
            GROUP BY `hour_slot`
            ORDER BY `hour_slot` ASC
        ";
        $hourlyTimeline = Database::fetchAll($timelineSql, [':eid' => $eventId]);

        // Geographic Breakdown (from participants place or registration custom data)
        $geoSql = "
            SELECT COALESCE(NULLIF(p.`place`, ''), 'Unknown / Unspecified') AS `location`, COUNT(*) AS `count`
            FROM `event_registrations` er
            JOIN `participants` p ON er.`participant_id` = p.`id`
            WHERE er.`event_id` = :eid AND er.`status` != 'cancelled'
            GROUP BY `location`
            ORDER BY `count` DESC
            LIMIT 10
        ";
        $geoBreakdown = Database::fetchAll($geoSql, [':eid' => $eventId]);

        // Device & Browser Technical Breakdown (from registration_metadata)
        $deviceSql = "
            SELECT COALESCE(rm.`device_type`, 'Unknown') AS `device`, COUNT(*) AS `count`
            FROM `event_registrations` er
            LEFT JOIN `registration_metadata` rm ON er.`id` = rm.`registration_id`
            WHERE er.`event_id` = :eid AND er.`status` != 'cancelled'
            GROUP BY `device`
            ORDER BY `count` DESC
        ";
        $deviceBreakdown = Database::fetchAll($deviceSql, [':eid' => $eventId]);

        $browserSql = "
            SELECT COALESCE(rm.`browser`, 'Unknown') AS `browser`, COUNT(*) AS `count`
            FROM `event_registrations` er
            LEFT JOIN `registration_metadata` rm ON er.`id` = rm.`registration_id`
            WHERE er.`event_id` = :eid AND er.`status` != 'cancelled'
            GROUP BY `browser`
            ORDER BY `count` DESC
            LIMIT 6
        ";
        $browserBreakdown = Database::fetchAll($browserSql, [':eid' => $eventId]);

        // Detailed Participant Roster with segmentation
        $filter = $request->get('filter', 'all');
        $rosterSql = "
            SELECT er.*, p.`full_name`, p.`phone`, p.`email`, p.`place`,
                   rm.`ip_address`, rm.`device_type`, rm.`browser`, rm.`isp`
            FROM `event_registrations` er
            JOIN `participants` p ON er.`participant_id` = p.`id`
            LEFT JOIN `registration_metadata` rm ON er.`id` = rm.`registration_id`
            WHERE er.`event_id` = :eid
        ";

        if ($filter === 'checked_in') {
            $rosterSql .= " AND er.`attendance_status` = 'attended'";
        } elseif ($filter === 'not_checked_in') {
            $rosterSql .= " AND er.`attendance_status` != 'attended' AND er.`status` != 'cancelled'";
        } elseif ($filter === 'waitlisted') {
            $rosterSql .= " AND er.`status` = 'waitlisted'";
        }

        $rosterSql .= " ORDER BY er.`created_at` DESC LIMIT 200";
        $roster = Database::fetchAll($rosterSql, [':eid' => $eventId]);

        $canExport = $this->permissionService->can($userId, 'analytics', 'export');

        return Response::html(View::render('admin/analytics/event', [
            'title'            => 'Analytics: ' . $event['title'],
            'event'            => $event,
            'stats'            => $stats,
            'hourlyTimeline'   => $hourlyTimeline,
            'geoBreakdown'     => $geoBreakdown,
            'deviceBreakdown'  => $deviceBreakdown,
            'browserBreakdown' => $browserBreakdown,
            'roster'           => $roster,
            'currentFilter'    => $filter,
            'canExport'        => $canExport,
            'user'             => $user,
        ]));
    }

    /**
     * Export Analytics Roster to CSV with privacy protection.
     * GET /admin/analytics/event/{id}/export
     */
    public function exportCsv(Request $request, array $vars): Response
    {
        $user = Session::get('user');
        $userId = (int) ($user['id'] ?? 0);

        if (!$this->permissionService->can($userId, 'analytics', 'export')) {
            Session::flash('error', 'You do not have permission to export analytics data.');
            return Response::redirect(url('/admin/analytics'));
        }

        $eventId = (int) ($vars['id'] ?? 0);
        $event = $this->eventRepo->findById($eventId);

        if (!$event) {
            return Response::redirect(url('/admin/analytics'));
        }

        $sql = "
            SELECT er.`registration_code`, p.`full_name`, er.`phone_normalized`, p.`place`,
                   er.`status` AS `registration_status`, er.`attendance_status`, er.`attended_at`,
                   er.`check_in_method`, er.`checkin_distance_meters`,
                   rm.`ip_address`, rm.`device_type`, rm.`browser`, rm.`created_at` AS `registered_at`
            FROM `event_registrations` er
            JOIN `participants` p ON er.`participant_id` = p.`id`
            LEFT JOIN `registration_metadata` rm ON er.`id` = rm.`registration_id`
            WHERE er.`event_id` = :eid
            ORDER BY er.`created_at` ASC
        ";
        $rows = Database::fetchAll($sql, [':eid' => $eventId]);

        $filename = 'analytics_' . preg_replace('/[^a-zA-Z0-9]+/', '_', strtolower($event['title'])) . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM
        fputs($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Registration Code',
            'Full Name',
            'Mobile Number',
            'Place',
            'Registration Status',
            'Attendance Status',
            'Check-in Timestamp',
            'Check-in Method',
            'Geofence Distance (m)',
            'Masked IP',
            'Device',
            'Browser',
            'Registered At',
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['registration_code'],
                $r['full_name'],
                $r['phone_normalized'] ?: 'N/A',
                $r['place'] ?: 'N/A',
                ucfirst((string) $r['registration_status']),
                ucfirst((string) $r['attendance_status']),
                $r['attended_at'] ?: 'Not Checked In',
                $r['check_in_method'] ?: 'N/A',
                $r['checkin_distance_meters'] !== null ? $r['checkin_distance_meters'] : 'N/A',
                RegistrationMetadataService::maskIp($r['ip_address']),
                $r['device_type'] ?: 'Unknown',
                $r['browser'] ?: 'Unknown',
                $r['registered_at'] ?: 'N/A',
            ]);
        }

        fclose($out);
        exit;
    }

    /**
     * Compute core counts and attendance rate for an event.
     */
    private function getEventStats(int $eventId): array
    {
        $sql = "
            SELECT 
                COUNT(*) AS total_registrations,
                SUM(CASE WHEN `attendance_status` = 'attended' THEN 1 ELSE 0 END) AS checked_in_count,
                SUM(CASE WHEN `attendance_status` != 'attended' AND `status` != 'cancelled' THEN 1 ELSE 0 END) AS non_checked_in_count,
                SUM(CASE WHEN `status` = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_count,
                SUM(CASE WHEN `status` = 'waitlisted' THEN 1 ELSE 0 END) AS waitlisted_count,
                SUM(CASE WHEN `status` = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
            FROM `event_registrations`
            WHERE `event_id` = :eid
        ";
        $row = Database::fetch($sql, [':eid' => $eventId]) ?: [];

        $total = (int) ($row['total_registrations'] ?? 0);
        $checkedIn = (int) ($row['checked_in_count'] ?? 0);
        $confirmed = (int) ($row['confirmed_count'] ?? 0);

        $attendanceRate = $total > 0 ? round(($checkedIn / $total) * 100, 1) : 0.0;

        return [
            'total_registrations'   => $total,
            'checked_in_count'      => $checkedIn,
            'non_checked_in_count'  => (int) ($row['non_checked_in_count'] ?? 0),
            'confirmed_count'       => $confirmed,
            'waitlisted_count'      => (int) ($row['waitlisted_count'] ?? 0),
            'cancelled_count'       => (int) ($row['cancelled_count'] ?? 0),
            'attendance_rate'       => $attendanceRate,
        ];
    }
}
