<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CampaignRepository;
use App\Repositories\EventRepository;

/**
 * Home Controller
 * Presents the application public landing portal with active campaigns and upcoming events.
 */
class HomeController extends Controller
{
    private CampaignRepository $campaignRepo;
    private EventRepository $eventRepo;

    public function __construct(
        ?CampaignRepository $campaignRepo = null,
        ?EventRepository $eventRepo = null
    ) {
        $this->campaignRepo = $campaignRepo ?? new CampaignRepository();
        $this->eventRepo = $eventRepo ?? new EventRepository();
    }

    public function index(Request $request): Response
    {
        $dbStatus = Database::checkHealth();

        $activeCampaigns = [];
        $upcomingEvents = [];

        if ($dbStatus['status'] === 'connected') {
            try {
                $activeCampaigns = $this->campaignRepo->getActivePublicCampaigns();
                $upcomingEvents = $this->eventRepo->getPublicUpcomingEvents([], 6);
            } catch (\Throwable) {
                // Graceful fallback if database tables are unmigrated in isolated environments
            }
        }

        $data = [
            'appName'         => Config::get('app.name'),
            'fullTitle'       => Config::get('app.full_title'),
            'version'         => Config::get('app.version'),
            'env'             => Config::get('app.env'),
            'debug'           => Config::get('app.debug'),
            'basePath'        => Config::get('app.base_path'),
            'dbConnected'     => $dbStatus['status'] === 'connected',
            'dbStatus'        => $dbStatus,
            'phpVersion'      => PHP_VERSION,
            'activeCampaigns' => $activeCampaigns,
            'upcomingEvents'  => $upcomingEvents,
        ];

        return $this->render('home/index', $data);
    }
}

