<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use Closure;

/**
 * Guest Middleware
 * Prevents authenticated administrators from re-accessing login forms.
 * Redirects authenticated users to /admin.
 */
class GuestMiddleware implements MiddlewareInterface
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->authService->isAuthenticated()) {
            return Response::redirect(url('/admin'));
        }

        return $next($request);
    }

    public function __invoke(Request $request, Closure $next): Response
    {
        return $this->handle($request, $next);
    }
}
