<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\PermissionService;
use App\Services\RoleService;
use Closure;

/**
 * Granular Permission Enforcement Middleware
 * Validates module/action permission with automatic Super Admin bypass.
 */
class PermissionMiddleware implements MiddlewareInterface
{
    private string $requiredPermission;
    private PermissionService $permissionService;

    public function __construct(string $requiredPermission, ?PermissionService $permissionService = null)
    {
        $this->requiredPermission = $requiredPermission;
        $this->permissionService = $permissionService ?? new PermissionService();
    }

    public function handle(Request $request, Closure $next): Response
    {
        Session::start();
        $userId = (int) Session::get('_auth_user_id', 0);
        $userRole = (string) Session::get('_auth_user_role', '');

        if ($userId <= 0 || !$this->permissionService->hasPermission($userId, $this->requiredPermission, $userRole)) {
            if ($request->expectsJson() || str_starts_with($request->getPath(), '/api')) {
                return Response::json([
                    'success' => false,
                    'error'   => "Forbidden: Missing required permission [{$this->requiredPermission}].",
                    'code'    => 403,
                ], 403);
            }

            try {
                $html = View::render('errors/403', [
                    'title'              => '403 Forbidden',
                    'requiredPermission' => $this->requiredPermission,
                    'userRole'           => RoleService::getRoleLabel($userRole ?: 'anonymous'),
                ], 'layouts/admin');

                return Response::html($html, 403);
            } catch (\Throwable) {
                return Response::html(
                    '<!DOCTYPE html><html><head><title>403 Forbidden</title>' .
                    '<style>body{font-family:system-ui,sans-serif;padding:3rem;line-height:1.6;color:#1e293b;max-width:600px;margin:auto}' .
                    'h1{color:#dc2626}a{color:#2563eb;text-decoration:none}</style></head>' .
                    '<body><h1>403 Forbidden</h1>' .
                    '<p>You do not have the required permission (<strong>' . htmlspecialchars($this->requiredPermission) . '</strong>) to access this administrative module.</p>' .
                    '<p><a href="' . htmlspecialchars(url('/admin')) . '">&larr; Return to Dashboard</a></p>' .
                    '</body></html>',
                    403
                );
            }
        }

        return $next($request);
    }
}
