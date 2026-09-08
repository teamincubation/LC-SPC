<?php

declare(strict_types=1);

/**
 * Listening Community SPC (LC-SPC) - Front Controller Entry Point
 * All web traffic routes through this file.
 */

define('APP_ROOT', dirname(__DIR__));

// Autoloader: Prefer Composer autoloader; provide robust PSR-4 fallback
if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
} else {
    spl_autoload_register(function (string $class): void {
        $prefix = 'App\\';
        $baseDir = APP_ROOT . '/app/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });

    // Manually load helpers if vendor autoloader not present
    require_once APP_ROOT . '/app/Core/helpers.php';
}

use App\Core\App;

// Bootstrap and dispatch the application HTTP lifecycle
App::run(APP_ROOT);
