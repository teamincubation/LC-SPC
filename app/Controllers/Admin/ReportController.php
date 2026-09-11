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
}
