<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditLogRepository;
use App\Repositories\CampaignRepository;
use App\Repositories\EventRepository;
use App\Repositories\ParticipantRepository;
use App\Repositories\RegistrationRepository;
use App\Services\AuthService;
use App\Services\RoleService;

/**
 * Administrative Dashboard Controller
 * Serves the foundational authenticated overview in Phase 1.
 */
class DashboardController extends Controller
{
    private AuthService $authService;
    private CampaignRepository $campaignRepo;
    private EventRepository $eventRepo;
    private ParticipantRepository $participantRepo;
    private RegistrationRepository $regRepo;
    private AuditLogRepository $auditRepo;

    public function __construct(
        ?AuthService $authService = null,
        ?CampaignRepository $campaignRepo = null,
        ?EventRepository $eventRepo = null,
        ?ParticipantRepository $participantRepo = null,
        ?RegistrationRepository $regRepo = null,
        ?AuditLogRepository $auditRepo = null
    ) {
        $this->authService = $authService ?? new AuthService();
        $this->campaignRepo = $campaignRepo ?? new CampaignRepository();
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->participantRepo = $participantRepo ?? new ParticipantRepository();
        $this->regRepo = $regRepo ?? new RegistrationRepository();
        $this->auditRepo = $auditRepo ?? new AuditLogRepository();
    }

    /**
     * Show administrative overview dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();

        $campCounts = $this->campaignRepo->countByStatus();
        $eventCounts = $this->eventRepo->countByStatus();
        $partCounts = $this->participantRepo->countByStatus();
        $regCounts = $this->regRepo->countByStatus();
        $recentLogs = $this->auditRepo->getRecent(5);

        return $this->render('admin/dashboard/index', [
            'title'       => 'Administrative Overview',
            'breadcrumb'  => 'Dashboard',
            'user'        => $user,
            'roleLabel'   => RoleService::getRoleLabel($user['role'] ?? 'viewer'),
            'roleBadge'   => RoleService::getBadgeClass($user['role'] ?? 'viewer'),
            'campCounts'  => $campCounts,
            'eventCounts' => $eventCounts,
            'partCounts'  => $partCounts,
            'regCounts'   => $regCounts,
            'recentLogs'  => $recentLogs,
        ], 'layouts/admin');
    }
}
