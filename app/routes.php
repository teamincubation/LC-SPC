<?php

declare(strict_types=1);

/**
 * LC-SPC Application Route Registry
 * All registered routes resolve consistently both locally (/) and under Hostinger (/LC/).
 */

use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Core\Response;
use App\Core\Router;

/** @var Router $router */

// Public Core Routes
$router->get('/', [HomeController::class, 'index']);
$router->get('/health', [HealthController::class, 'index']);
