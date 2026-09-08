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

        if (headers_sent()) {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            self::$started = true;
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

        // Auto-detect HTTPS if secure not explicitly forced
        if (!$secure && (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')) {
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
        return session_regenerate_id($deleteOldSession);
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
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
            self::$started = false;
        }
    }
}
