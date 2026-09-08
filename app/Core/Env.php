<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight Environment File Parser
 * Loads .env configuration into $_ENV and getenv() securely without external dependencies.
 */
class Env
{
    private static array $variables = [];
    private static bool $loaded = false;

    /**
     * Load environment file from the given path.
     */
    public static function load(string $filePath): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            // Must contain an assignment
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip enclosing quotes if present
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Cast boolean and null literals
            $normalizedValue = match (strtolower($value)) {
                'true', '(true)' => true,
                'false', '(false)' => false,
                'null', '(null)' => null,
                'empty', '(empty)' => '',
                default => $value,
            };

            self::$variables[$key] = $normalizedValue;

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $normalizedValue;
            }
            if (is_string($normalizedValue) || is_numeric($normalizedValue)) {
                putenv("{$key}={$normalizedValue}");
            }
        }

        self::$loaded = true;
    }

    /**
     * Retrieve an environment variable with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$variables)) {
            return self::$variables[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $envValue = getenv($key);
        if ($envValue !== false) {
            return match (strtolower($envValue)) {
                'true', '(true)' => true,
                'false', '(false)' => false,
                'null', '(null)' => null,
                'empty', '(empty)' => '',
                default => $envValue,
            };
        }

        return $default;
    }

    /**
     * Check if environment has been loaded.
     */
    public static function isLoaded(): bool
    {
        return self::$loaded;
    }
}
