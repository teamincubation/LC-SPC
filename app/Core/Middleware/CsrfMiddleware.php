<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use Closure;

/**
 * Cross-Site Request Forgery (CSRF) Protection Middleware
 * Enforces valid anti-CSRF token on all state-changing HTTP requests.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * Explicit path exemptions from CSRF verification for future API/webhook routes.
     * Note: GET/HEAD/OPTIONS requests are already exempted by HTTP standard;
     * do not add GET routes here.
     */
    protected array $except = [];

    public function handle(Request $request, Closure $next): Response
    {
        // Safe read-only HTTP methods do not require CSRF token
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Check if path is in exempt list
        $path = $request->getPath();
        foreach ($this->except as $exceptPath) {
            if ($path === $exceptPath || str_ends_with($path, $exceptPath)) {
                return $next($request);
            }
        }

        $tokenName = Config::get('security.csrf.token_name', '_csrf_token');
        $headerName = Config::get('security.csrf.header_name', 'X-CSRF-TOKEN');

        // Look for token in request input or headers
        $token = $request->input($tokenName) ?? $request->header($headerName);

        if (!is_string($token) || !Security::validateCsrfToken($token)) {
            Logger::warning('CSRF validation failed for request', [
                'path' => $path,
                'ip' => $request->ip(),
                'method' => $request->getMethod(),
            ]);

            if ($request->expectsJson()) {
                return Response::json([
                    'error' => 'CSRF token mismatch or expired.',
                    'status' => 403,
                ], 403);
            }

            return Response::html(
                '<!DOCTYPE html><html><head><title>403 Forbidden - CSRF Validation Failed</title>' .
                '<style>body{font-family:system-ui,sans-serif;padding:3rem;line-height:1.6;color:#1e293b;max-width:600px;margin:auto}' .
                'h1{color:#dc2626}a{color:#2563eb;text-decoration:none}</style></head>' .
                '<body><h1>403 Forbidden</h1>' .
                '<p>The form submission could not be verified due to an expired or missing security token.</p>' .
                '<p><a href="javascript:history.back()">&larr; Return back and refresh the page</a></p>' .
                '</body></html>',
                403
            );
        }

        return $next($request);
    }
}
