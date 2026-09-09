<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hardened Session Manager
 * Configures secure session cookies (HttpOnly, SameSite, Secure) and handles flash data.
 */
class Session
{
    private static bool $started = false;

    /**
     * Start and configure session with strict security settings.
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        // Fail safely if headers have already been sent to prevent starting
        // an insecure session without the required cookie and security attributes.
        if (headers_sent()) {
            Logger::error('Session::start() aborted: HTTP headers already sent; cannot apply secure session parameters.');
            return;
        }

        $sessionConfig = Config::get('security.session', []);
        $name = $sessionConfig['name'] ?? 'LCSPC_SESSION';
        $lifetime = (int) ($sessionConfig['lifetime'] ?? 7200);
        $path = $sessionConfig['path'] ?? '/';
        $domain = $sessionConfig['domain'] ?? '';
        $secure = (bool) ($sessionConfig['secure'] ?? false);
        $httponly = (bool) ($sessionConfig['httponly'] ?? true);
        $samesite = $sessionConfig['samesite'] ?? 'Lax';

        // Auto-detect HTTPS or production environment if secure not explicitly forced
        if (!$secure && (
            (Config::get('app.env') === 'production') ||
            (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        )) {
            $secure = true;
        }

        session_name($name);

        // Store sessions in dedicated storage directory if writable
        $sessionStoragePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
        if (is_dir($sessionStoragePath) && is_writable($sessionStoragePath)) {
            session_save_path($sessionStoragePath);
        }

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');

        session_start();
        self::$started = true;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::start();
        return array_key_exists($key, $_SESSION);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Regenerate the session ID securely.
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        self::start();
        if (session_status() === PHP_SESSION_ACTIVE) {
            return session_regenerate_id($deleteOldSession);
        }
        return false;
    }

    /**
     * Store a one-time flash notification message.
     */
    public static function flash(string $key, mixed $message): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $message;
    }

    /**
     * Retrieve and clear a one-time flash message.
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::start();
        if (isset($_SESSION['_flash'][$key])) {
            $msg = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $msg;
        }
        return $default;
    }

    /**
     * Destroy the current session completely.
     * Deletes the session cookie with matching security parameters including SameSite.
     */
    public static function destroy(): void
    {
        $_SESSION = [];

        $name = session_name();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            // Delete cookie using matching security attributes including SameSite where supported
            if (!headers_sent()) {
                setcookie($name, '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => (bool) ($params['secure'] ?? false),
                    'httponly' => (bool) ($params['httponly'] ?? true),
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]);
            }

            if (isset($_COOKIE[$name])) {
                unset($_COOKIE[$name]);
            }
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$started = false;
    }

    /**
     * Check if session has been started and is active.
     */
    public static function isStarted(): bool
    {
        return self::$started && session_status() === PHP_SESSION_ACTIVE;
    }
}
