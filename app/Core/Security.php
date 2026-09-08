<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Security Utilities Class
 * Provides CSRF token handling, secure output escaping, and password hashing.
 */
class Security
{
    /**
     * Retrieve or generate the current CSRF token from the session.
     */
    public static function csrfToken(): string
    {
        Session::start();
        $token = Session::get('_csrf_token');

        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf_token', $token);
        }

        return $token;
    }

    /**
     * Render an HTML hidden input field containing the current CSRF token.
     */
    public static function csrfField(): string
    {
        $tokenName = Config::get('security.csrf.token_name', '_csrf_token');
        $token = self::csrfToken();
        return '<input type="hidden" name="' . self::escape($tokenName) . '" value="' . self::escape($token) . '">';
    }

    /**
     * Validate an incoming CSRF token against the stored session token.
     */
    public static function validateCsrfToken(?string $providedToken): bool
    {
        if (empty($providedToken)) {
            return false;
        }

        Session::start();
        $storedToken = Session::get('_csrf_token');

        if (!is_string($storedToken) || empty($storedToken)) {
            return false;
        }

        return hash_equals($storedToken, $providedToken);
    }

    /**
     * Safely escape a value for HTML output to prevent Cross-Site Scripting (XSS).
     */
    public static function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return '';
    }

    /**
     * Hash a password securely using modern defaults (Argon2id/Bcrypt).
     */
    public static function hashPassword(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_DEFAULT);
    }

    /**
     * Verify a password against a stored secure hash.
     */
    public static function verifyPassword(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }
}
