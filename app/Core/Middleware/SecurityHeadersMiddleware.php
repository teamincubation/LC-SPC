<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Security Headers Middleware
 * Attaches standard HTTP response headers to harden defense against XSS, clickjacking, and MIME sniffing.
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = Config::get('security.headers', []);
        foreach ($headers as $name => $value) {
            $response->setHeader($name, (string) $value);
        }

        // Apply HSTS when served over HTTPS
        if ($request->isSecure() || Config::get('app.env') === 'production') {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
