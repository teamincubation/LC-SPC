<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;
use Closure;

/**
 * Authentication Middleware
 * Enforces authenticated session on protected routes.
 * Redirects unauthenticated web requests to /login or returns 401 JSON.
 */
class AuthMiddleware implements MiddlewareInterface
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->authService->getCurrentUser();

        if ($user === null) {
            if ($request->expectsJson() || str_starts_with($request->getPath(), '/api')) {
                return Response::json([
                    'success' => false,
                    'error'   => 'Unauthenticated. Please log in.',
                    'code'    => 401,
                ], 401);
            }

            Session::start();
            // Record intended URL for seamless redirect after login
            if ($request->getMethod() === 'GET') {
                Session::set('_intended_url', $request->getUri());
            }

            Session::flash('error', 'Please log in to access the administrative portal.');
            return Response::redirect(url('/login'));
        }

        return $next($request);
    }

    public function __invoke(Request $request, Closure $next): Response
    {
        return $this->handle($request, $next);
    }
}
