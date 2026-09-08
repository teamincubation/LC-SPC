<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight File Logger
 * Writes timestamped logs securely into storage/logs/.
 */
class Logger
{
    private static ?string $logPath = null;

    /**
     * Set or get the log directory path.
     */
    public static function getLogDirectory(): string
    {
        if (self::$logPath === null) {
            self::$logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        }

        if (!is_dir(self::$logPath)) {
            @mkdir(self::$logPath, 0755, true);
        }

        return self::$logPath;
    }

    /**
     * Write a log record.
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $logDir = self::getLogDirectory();
        $date = date('Y-m-d H:i:s');
        $level = strtoupper($level);

        $contextString = '';
        if (!empty($context)) {
            // Remove sensitive keys like password, token, secret from context
            $sanitized = self::sanitizeContext($context);
            $contextString = ' ' . json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $formatted = "[{$date}] [{$level}] {$message}{$contextString}" . PHP_EOL;

        // General application log
        @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'app.log', $formatted, FILE_APPEND | LOCK_EX);

        // Separate dedicated error log for warnings, errors, and critical items
        if (in_array($level, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY', 'WARNING'], true)) {
            @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'error.log', $formatted, FILE_APPEND | LOCK_EX);
        }
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (Config::get('app.debug', false)) {
            self::log('DEBUG', $message, $context);
        }
    }

    /**
     * Strip credentials or passwords from log context arrays.
     */
    private static function sanitizeContext(array $context): array
    {
        $sensitiveKeys = ['password', 'passwd', 'secret', 'token', 'csrf', 'api_key', 'auth'];
        foreach ($context as $key => $val) {
            if (is_string($key)) {
                foreach ($sensitiveKeys as $sensitive) {
                    if (stripos($key, $sensitive) !== false) {
                        $context[$key] = '********';
                        continue 2;
                    }
                }
            }
            if (is_array($val)) {
                $context[$key] = self::sanitizeContext($val);
            }
        }
        return $context;
    }
}
