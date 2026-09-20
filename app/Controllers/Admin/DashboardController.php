<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditLogRepository;
use App\Repositories\V3CertificateRepository;
use App\Services\AuthService;
use App\Services\RoleService;

/**
 * Administrative Dashboard Controller (V3 Automated Certificate Platform)
 * High-level overview of certificate metrics, active templates, recent batches, and system logs.
 */
class DashboardController extends Controller
{
    private AuthService $authService;
    private V3CertificateRepository $certRepo;
    private AuditLogRepository $auditRepo;

    public function __construct(
        ?AuthService $authService = null,
        ?V3CertificateRepository $certRepo = null,
        ?AuditLogRepository $auditRepo = null
    ) {
        $this->authService = $authService ?? new AuthService();
        $this->certRepo = $certRepo ?? new V3CertificateRepository();
        $this->auditRepo = $auditRepo ?? new AuditLogRepository();
    }

    /**
     * Show administrative certificate overview dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();
        $adminRoleSlug = (string) ($user['role'] ?? '');
        $isSuperAdmin = RoleService::isSuperAdmin($adminRoleSlug);
        $metrics = $this->certRepo->getDashboardMetrics();
        $recentLogs = $isSuperAdmin ? $this->auditRepo->getRecent(6) : [];

        return $this->render('admin/dashboard/index', [
            'title'         => 'Administrative Overview',
            'breadcrumb'    => 'Dashboard',
            'user'          => $user,
            'adminRoleSlug' => $adminRoleSlug,
            'isSuperAdmin'  => $isSuperAdmin,
            'roleLabel'     => RoleService::getRoleLabel($adminRoleSlug ?: 'viewer'),
            'roleBadge'     => RoleService::getBadgeClass($adminRoleSlug ?: 'viewer'),
            'metrics'       => $metrics,
            'recentLogs'    => $recentLogs,
            'activeNav'     => 'dashboard',
        ], 'layouts/admin');
    }
}
