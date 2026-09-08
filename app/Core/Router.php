<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

/**
 * Lightweight, Subdirectory-Aware HTTP Router
 * Resolves routes uniformly across local development (/) and production subdirectories (/LC/).
 */
class Router
{
    private array $routes = [];
    private array $groupStack = [];
    private array $globalMiddleware = [];

    /**
     * Register global middleware executed on every request.
     */
    public function use(string|callable $middleware): self
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    /**
     * Register a GET route.
     */
    public function get(string $path, array|Closure $action, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $action, $middleware);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, array|Closure $action, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $action, $middleware);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, array|Closure $action, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $action, $middleware);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, array|Closure $action, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $action, $middleware);
    }

    /**
     * Group routes with common prefix and middleware.
     */
    public function group(array $attributes, Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Internal route registration with group prefix and middleware merging.
     */
    private function addRoute(string $method, string $path, array|Closure $action, array $middleware = []): self
    {
        $prefix = '';
        $groupMiddleware = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $groupMiddleware = array_merge($groupMiddleware, (array) $group['middleware']);
            }
        }

        $fullPath = '/' . trim($prefix . '/' . trim($path, '/'), '/');
        if ($fullPath !== '/') {
            $fullPath = rtrim($fullPath, '/');
        }

        $combinedMiddleware = array_merge($groupMiddleware, $middleware);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'action' => $action,
            'middleware' => $combinedMiddleware,
        ];

        return $this;
    }

    /**
     * Normalize incoming request path by stripping configured base path.
     * E.g. in production: /LC/health -> /health
     * In local: /health -> /health
     */
    public function normalizePath(string $rawPath): string
    {
        $path = parse_url($rawPath, PHP_URL_PATH) ?: '/';
        $basePath = trim((string) Config::get('app.base_path', ''), '/');

        if ($basePath !== '') {
            $prefix = '/' . $basePath;

            if ($path === $prefix || $path === $prefix . '/') {
                return '/';
            }

            if (str_starts_with($path, $prefix . '/')) {
                $path = substr($path, strlen($prefix));
            }
        }

        // Auto-detect /LC subdirectory fallback if present
        if ($path === '/LC' || $path === '/LC/') {
            return '/';
        }
        if (str_starts_with($path, '/LC/')) {
            $path = substr($path, 3);
        }

        $clean = '/' . trim($path, '/');
        return $clean !== '/' ? rtrim($clean, '/') : '/';
    }

    /**
     * Dispatch the current request through middleware and match to route handler.
     */
    public function dispatch(Request $request): Response
    {
        $normalizedPath = $this->normalizePath($request->getPath());
        $method = $request->getMethod();

        $matchedRoute = null;
        $routeParams = [];

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->compilePattern($route['path']);
            if (preg_match($pattern, $normalizedPath, $matches)) {
                $matchedRoute = $route;

                // Extract named capture groups
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $routeParams[$key] = urldecode($value);
                    }
                }
                break;
            }
        }

        if (!$matchedRoute) {
            // Check for method not allowed (405)
            $allowedMethods = [];
            foreach ($this->routes as $route) {
                $pattern = $this->compilePattern($route['path']);
                if (preg_match($pattern, $normalizedPath)) {
                    $allowedMethods[] = $route['method'];
                }
            }

            if (!empty($allowedMethods)) {
                return Response::json([
                    'error' => 'Method Not Allowed',
                    'allowed' => array_unique($allowedMethods),
                ], 405, ['Allow' => implode(', ', array_unique($allowedMethods))]);
            }

            return $this->handleNotFound($request);
        }

        // Build middleware pipeline
        $pipeline = array_merge($this->globalMiddleware, $matchedRoute['middleware']);

        $handler = function (Request $req) use ($matchedRoute, $routeParams): Response {
            $action = $matchedRoute['action'];

            if ($action instanceof Closure) {
                $result = $action($req, $routeParams);
            } elseif (is_array($action) && count($action) === 2) {
                [$controllerClass, $methodName] = $action;

                if (!class_exists($controllerClass)) {
                    throw new RuntimeException("Controller [{$controllerClass}] not found.");
                }

                $controller = new $controllerClass();
                if (!method_exists($controller, $methodName)) {
                    throw new RuntimeException("Method [{$methodName}] not found on controller [{$controllerClass}].");
                }

                $result = $controller->$methodName($req, $routeParams);
            } else {
                throw new RuntimeException("Invalid route action specified.");
            }

            if ($result instanceof Response) {
                return $result;
            }

            if (is_string($result)) {
                return Response::html($result);
            }

            if (is_array($result)) {
                return Response::json($result);
            }

            return new Response('');
        };

        return $this->runMiddlewarePipeline($pipeline, $request, $handler);
    }

    /**
     * Execute middleware pipeline.
     */
    private function runMiddlewarePipeline(array $middlewares, Request $request, Closure $destination): Response
    {
        $runner = array_reduce(
            array_reverse($middlewares),
            function (Closure $next, $middleware) {
                return function (Request $req) use ($next, $middleware): Response {
                    if (is_string($middleware) && class_exists($middleware)) {
                        $instance = new $middleware();
                        return $instance->handle($req, $next);
                    }
                    if (is_callable($middleware)) {
                        return $middleware($req, $next);
                    }
                    return $next($req);
                };
            },
            $destination
        );

        return $runner($request);
    }

    /**
     * Compile a route pattern to regex. E.g. /events/{id} -> #^/events/(?P<id>[^/]+)$#
     */
    private function compilePattern(string $path): string
    {
        if ($path === '/') {
            return '#^/$#';
        }

        $pattern = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Render 404 response.
     */
    private function handleNotFound(Request $request): Response
    {
        if ($request->expectsJson() || str_starts_with($request->getPath(), '/api')) {
            return Response::json(['error' => 'Resource Not Found', 'status' => 404], 404);
        }

        $viewPath = dirname(__DIR__) . '/Views/errors/404.php';
        if (file_exists($viewPath)) {
            ob_start();
            include $viewPath;
            $content = ob_get_clean();
            return Response::html($content ?: '<h1>404 Not Found</h1>', 404);
        }

        return Response::html('<h1>404 Not Found</h1><p>The requested page was not found.</p>', 404);
    }

    /**
     * Get list of registered routes.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
