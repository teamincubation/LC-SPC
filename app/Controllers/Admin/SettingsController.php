<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\RoleService;

/**
 * Administrative Settings Controller
 * Serves institutional platform settings and configurations.
 */
class SettingsController extends Controller
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    /**
     * Show administrative settings overview.
     */
    public function index(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();

        return $this->render('admin/settings/index', [
            'title'      => 'System Settings',
            'breadcrumb' => 'Settings',
            'user'       => $user,
            'roleLabel'  => RoleService::getRoleLabel($user['role'] ?? 'viewer'),
            'appName'    => Config::get('app.name', 'LC-SPC'),
            'appEnv'     => Config::get('app.env', 'production'),
            'appUrl'     => Config::get('app.url', 'https://teami.in/LC'),
            'version'    => Config::get('app.version', '1.0.0'),
            'timezone'   => date_default_timezone_get(),
        ], 'layouts/admin');
    }
}
