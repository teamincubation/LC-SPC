<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Security;
use App\Core\Session;

if (!function_exists('e')) {
    /**
     * Escape HTML output safely.
     */
    function e(mixed $value): string
    {
        return Security::escape($value);
    }
}

if (!function_exists('url')) {
    /**
     * Generate an application URL with proper base path prefixing.
     * E.g. in production: url('/health') -> '/LC/health'
     * In local: url('/health') -> '/health'
     */
    function url(string $path = ''): string
    {
        $basePath = trim((string) Config::get('app.base_path', ''), '/');
        $cleanPath = '/' . ltrim($path, '/');

        if ($basePath === '') {
            return $cleanPath;
        }

        if ($cleanPath === '/') {
            return '/' . $basePath . '/';
        }

        return '/' . $basePath . $cleanPath;
    }
}

if (!function_exists('asset')) {
    /**
     * Generate a URL for a public static asset.
     */
    function asset(string $path): string
    {
        $basePath = trim((string) Config::get('app.base_path', ''), '/');
        $cleanPath = 'assets/' . ltrim($path, '/');

        if ($basePath === '') {
            return '/' . $cleanPath;
        }

        return '/' . $basePath . '/' . $cleanPath;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the current CSRF token.
     */
    function csrf_token(): string
    {
        return Security::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate HTML hidden input for CSRF protection.
     */
    function csrf_field(): string
    {
        return Security::csrfField();
    }
}

if (!function_exists('config')) {
    /**
     * Retrieve a configuration parameter.
     */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('session')) {
    /**
     * Retrieve session data.
     */
    function session(string $key, mixed $default = null): mixed
    {
        return Session::get($key, $default);
    }
}

if (!function_exists('flash')) {
    /**
     * Retrieve and clear flash message.
     */
    function flash(string $key, mixed $default = null): mixed
    {
        return Session::getFlash($key, $default);
    }
}
