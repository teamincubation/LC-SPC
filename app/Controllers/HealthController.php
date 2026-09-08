<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;

/**
 * Production Health Check Controller
 * Verifies system vitality while keeping public output minimal and non-revealing.
 */
class HealthController extends Controller
{
    /**
     * Public Health Endpoint: GET /health
     * Returns minimal status JSON and proper HTTP status code (200 OK / 503 Service Unavailable).
     */
    public function index(Request $request): Response
    {
        $healthy = true;
        $issues = [];

        // 1. Verify PHP Version
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            $healthy = false;
            $issues[] = 'Runtime PHP version requirement not met';
        }

        // 2. Verify Storage Writable
        $logDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_writable($logDir) && !is_writable(dirname($logDir))) {
            $healthy = false;
            $issues[] = 'Storage filesystem is not writable';
        }

        // 3. Verify Database Connectivity (Internally checked without throwing)
        $dbConnected = Database::isConnected();
        if (!$dbConnected) {
            // Note: If DB is down, flag as unhealthy
            $healthy = false;
            $issues[] = 'Database connection failed';
        }

        if (!$healthy) {
            Logger::error('Health check failed', ['issues' => $issues]);

            return Response::json([
                'status' => 'unhealthy',
                'application' => 'LC-SPC',
            ], 503);
        }

        // Minimal, secure public response
        return Response::json([
            'status' => 'ok',
            'application' => 'LC-SPC',
        ], 200);
    }
}
