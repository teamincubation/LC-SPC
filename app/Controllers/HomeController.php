<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Home Controller
 * Presents the application landing and foundation readiness status.
 */
class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $dbStatus = Database::checkHealth();

        $data = [
            'appName' => Config::get('app.name'),
            'fullTitle' => Config::get('app.full_title'),
            'version' => Config::get('app.version'),
            'env' => Config::get('app.env'),
            'debug' => Config::get('app.debug'),
            'basePath' => Config::get('app.base_path'),
            'dbConnected' => $dbStatus['status'] === 'connected',
            'dbStatus' => $dbStatus,
            'phpVersion' => PHP_VERSION,
        ];

        return $this->render('home/index', $data);
    }
}
