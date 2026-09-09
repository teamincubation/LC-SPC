<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Middleware\SecurityHeadersMiddleware;
use Throwable;

/**
 * Core Application Bootstrap
 * Initializes environment, configuration, logging, error handlers, and dispatches the HTTP cycle.
 */
class App
{
    private static bool $bootstrapped = false;
    private static Router $router;
    private static string $rootPath = '';

    /**
     * Bootstrap the application runtime.
     */
    public static function bootstrap(string $rootPath): Router
    {
        if (self::$bootstrapped) {
            return self::$router;
        }

        self::$rootPath = $rootPath;

        if (!defined('APP_ROOT')) {
            define('APP_ROOT', $rootPath);
        }

        // 1. Load environment variables
        Env::load($rootPath . DIRECTORY_SEPARATOR . '.env');

        // 2. Load configurations
        Config::load($rootPath . DIRECTORY_SEPARATOR . 'config');

        // 3. Set timezone
        $timezone = Config::get('app.timezone', 'Asia/Kolkata');
        date_default_timezone_set($timezone);

        // 4. Configure error & exception handling
        self::registerErrorHandlers();

        // 5. Initialize secure session
        Session::start();

        // 6. Initialize Router with global middleware
        self::$router = new Router();
        self::$router->use(SecurityHeadersMiddleware::class);

        self::$bootstrapped = true;

        return self::$router;
    }

    /**
     * Run the application and send the HTTP response.
     */
    public static function run(string $rootPath): void
    {
        try {
            $router = self::bootstrap($rootPath);

            // Load route definitions
            require_once $rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'routes.php';

            $request = Request::capture();
            $response = $router->dispatch($request);
            $response->send();
        } catch (Throwable $e) {
            self::handleException($e);
        }
    }

    /**
     * Register global PHP error and exception handlers.
     */
    private static function registerErrorHandlers(): void
    {
        $debug = (bool) Config::get('app.debug', false);

        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }

        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $level)) {
                return false;
            }

            Logger::error("PHP Notice/Warning: {$message}", [
                'file' => $file,
                'line' => $line,
                'level' => $level,
            ]);

            if (Config::get('app.debug', false)) {
                return false; // let standard handler show in debug mode
            }

            return true;
        });

        set_exception_handler([self::class, 'handleException']);
    }

    /**
     * Global uncaught exception handler.
     */
    public static function handleException(Throwable $e): void
    {
        // Log full exception details securely
        Logger::error("Uncaught Exception: " . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $debug = (bool) Config::get('app.debug', false);

        if ($debug) {
            // Development: Display detailed formatted diagnostic view
            echo '<!DOCTYPE html><html><head><title>500 Internal Server Error (Debug)</title>' .
                '<style>body{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;background:#0f172a;color:#e2e8f0;padding:2rem;line-height:1.6}' .
                'h1{color:#f87171;font-size:1.5rem;margin-bottom:0.5rem}' .
                '.msg{background:#1e293b;padding:1rem;border-radius:0.5rem;border-left:4px solid #ef4444;font-size:1.1rem;margin:1rem 0;word-break:break-all}' .
                '.trace{background:#1e293b;padding:1rem;border-radius:0.5rem;white-space:pre-wrap;font-size:0.85rem;overflow-x:auto}' .
                '</style></head><body>' .
                '<h1>Application Exception</h1>' .
                '<div class="msg">' . htmlspecialchars($e->getMessage()) . '</div>' .
                '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ' : ' . $e->getLine() . '</p>' .
                '<div class="trace">' . htmlspecialchars($e->getTraceAsString()) . '</div>' .
                '</body></html>';
            return;
        }

        // Production: Display safe, professional 500 error page without leaking credentials or paths
        $root = !empty(self::$rootPath) ? self::$rootPath : (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2));
        $errorView = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . '500.php';
        if (file_exists($errorView)) {
            include $errorView;
        } else {
            echo '<!DOCTYPE html><html><head><title>500 Server Error</title>' .
                '<style>body{font-family:system-ui,sans-serif;padding:3rem;text-align:center;color:#334155}h1{color:#dc2626}</style></head>' .
                '<body><h1>500 - System Error</h1><p>An unexpected error occurred. Our team has been notified.</p></body></html>';
        }
    }

    public static function getRouter(): Router
    {
        return self::$router;
    }
}
