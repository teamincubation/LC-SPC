<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\RoleService;

/**
 * Administrative Dashboard Controller
 * Serves the foundational authenticated overview in Phase 1A.
 */
class DashboardController extends Controller
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    /**
     * Show administrative overview dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();

        return $this->render('admin/dashboard/index', [
            'title'      => 'Administrative Overview',
            'breadcrumb' => 'Dashboard',
            'user'       => $user,
            'roleLabel'  => RoleService::getRoleLabel($user['role'] ?? 'viewer'),
            'roleBadge'  => RoleService::getBadgeClass($user['role'] ?? 'viewer'),
        ], 'layouts/admin');
    }
}
