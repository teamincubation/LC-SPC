<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Centralized Configuration Store
 * Provides dot-notation access to configuration files located in config/.
 */
class Config
{
    private static array $items = [];
    private static bool $loaded = false;

    /**
     * Load all configuration files from the specified directory.
     */
    public static function load(string $configPath): void
    {
        if (!is_dir($configPath)) {
            return;
        }

        $files = glob(rtrim($configPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $content = require $file;
            if (is_array($content)) {
                self::$items[$key] = $content;
            }
        }

        self::$loaded = true;
    }

    /**
     * Retrieve a configuration item using dot notation.
     * Example: Config::get('app.url') or Config::get('database.database')
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Check if a configuration key exists.
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Set a configuration value at runtime.
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current = $value;
    }

    /**
     * Return all loaded configuration.
     */
    public static function all(): array
    {
        return self::$items;
    }

    /**
     * Check if configuration has been initialized.
     */
    public static function isLoaded(): bool
    {
        return self::$loaded;
    }
}
