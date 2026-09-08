<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Middleware Interface
 */
interface MiddlewareInterface
{
    /**
     * Process an incoming request and return an HTTP Response.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response;
}
