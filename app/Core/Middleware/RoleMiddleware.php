<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\RoleService;
use Closure;

/**
 * Role-Based Access Control (RBAC) Middleware
 * Enforces minimum role rank in the hierarchy:
 * super_admin (40) > coordinator (30) > staff (20) > viewer (10)
 */
class RoleMiddleware implements MiddlewareInterface
{
    private string $requiredRole;

    public function __construct(string $requiredRole = RoleService::ROLE_VIEWER)
    {
        $this->requiredRole = $requiredRole;
    }

    public function handle(Request $request, Closure $next): Response
    {
        Session::start();
        $userRole = (string) Session::get('_auth_user_role', '');

        if (empty($userRole) || !RoleService::hasRole($userRole, $this->requiredRole)) {
            if ($request->expectsJson() || str_starts_with($request->getPath(), '/api')) {
                return Response::json([
                    'success' => false,
                    'error'   => 'Forbidden: Insufficient privileges for this action.',
                    'code'    => 403,
                ], 403);
            }

            // Render 403 view
            try {
                $html = View::render('errors/403', [
                    'title'        => '403 Forbidden',
                    'requiredRole' => RoleService::getRoleLabel($this->requiredRole),
                    'userRole'     => RoleService::getRoleLabel($userRole ?: 'anonymous'),
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
