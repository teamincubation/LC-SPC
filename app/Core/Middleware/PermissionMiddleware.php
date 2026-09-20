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
 * Granular Permission Authorization Middleware
 * Enforces action-level permissions via PermissionService.
 * Super Administrators bypass all checks unconditionally.
 * Normal Administrators are validated against required permission(s) using OR evaluation.
 */
class PermissionMiddleware implements MiddlewareInterface
{
    /** @var array<string> */
    private array $requiredPermissions;
    private PermissionService $permissionService;

    /**
     * @param string|array<string> $requiredPermission Single permission or array of accepted permissions (any match grants access).
     */
    public function __construct(string|array $requiredPermission, ?PermissionService $permissionService = null)
    {
        $this->requiredPermissions = is_array($requiredPermission) ? array_values($requiredPermission) : [$requiredPermission];
        $this->permissionService = $permissionService ?? new PermissionService();
    }

    public function handle(Request $request, Closure $next): Response
    {
        Session::start();
        $userId = (int) Session::get('_auth_user_id', 0);
        $userRole = (string) Session::get('_auth_user_role', '');

        if ($userId <= 0 || empty($userRole)) {
            if ($request->expectsJson() || str_starts_with($request->getPath(), '/api')) {
                return Response::json([
                    'success' => false,
                    'error'   => 'Unauthorized.',
                    'code'    => 401,
                ], 401);
            }

            return Response::redirect(url('/login'));
        }

        // Super Administrator retains unconditional full access across all active modules
        if (RoleService::isSuperAdmin($userRole)) {
            return $next($request);
        }

        // Check if the administrator possesses at least one of the accepted permissions (OR evaluation)
        $hasAccess = false;
        foreach ($this->requiredPermissions as $permission) {
            if ($this->permissionService->hasPermission($userId, $permission, $userRole)) {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            if ($request->expectsJson() || str_starts_with($request->getPath(), '/api')) {
                return Response::json([
                    'success' => false,
                    'error'   => 'Forbidden: Insufficient permissions for this action.',
                    'code'    => 403,
                ], 403);
            }

            try {
                $html = View::render('errors/403', [
                    'title'               => '403 Forbidden',
                    'requiredPermissions' => $this->requiredPermissions,
                    'userRole'            => RoleService::getRoleLabel($userRole ?: 'anonymous'),
                ], 'layouts/admin');

                return Response::html($html, 403);
            } catch (\Throwable) {
                return Response::html(
                    '<!DOCTYPE html><html><head><title>403 Forbidden</title>' .
                    '<style>body{font-family:system-ui,sans-serif;padding:3rem;line-height:1.6;color:#1e293b;max-width:600px;margin:auto}' .
                    'h1{color:#dc2626}a{color:#2563eb;text-decoration:none}</style></head>' .
                    '<body><h1>403 Forbidden</h1>' .
                    '<p>You do not have the required permissions to access this administrative resource.</p>' .
                    '<p><a href="' . htmlspecialchars(url('/admin')) . '">&larr; Return to Dashboard</a></p>' .
                    '</body></html>',
                    403
                );
            }
        }

        return $next($request);
    }

    public function __invoke(Request $request, Closure $next): Response
    {
        return $this->handle($request, $next);
    }
}
