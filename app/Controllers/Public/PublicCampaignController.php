<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\CampaignRepository;
use App\Repositories\EventRepository;

/**
 * Public Campaign Controller
 * Handles public browsing of active awareness initiatives and campaign transparent archives.
 */
class PublicCampaignController
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

    /**
     * Display public campaign directory.
     * GET /campaigns
     */
    public function index(Request $request): Response
    {
        $activeCampaigns = $this->campaignRepo->getActivePublicCampaigns();
        $archivedCampaigns = $this->campaignRepo->getArchivedPublicCampaigns();

        return Response::html(
            View::render('public/campaigns/index', [
                'title'             => 'Initiatives & Campaigns — Listening Community',
                'activeCampaigns'   => $activeCampaigns,
                'archivedCampaigns' => $archivedCampaigns,
            ])
        );
    }

    /**
     * Display public campaign detail and its published events.
     * GET /campaigns/{slug}
     */
    public function show(Request $request, array $vars): Response
    {
        $slug = trim((string) ($vars['slug'] ?? ''));
        if ($slug === '') {
            return $this->notFound();
        }

        $campaign = $this->campaignRepo->findBySlug($slug);

        // Visibility Gate: only active or completed campaigns are publicly viewable (drafts return 404)
        if (!$campaign || in_array($campaign['status'], ['draft'], true)) {
            return $this->notFound();
        }

        $events = $this->eventRepo->getPublicUpcomingEvents([
            'campaign_slug' => $slug,
        ]);

        return Response::html(
            View::render('public/campaigns/show', [
                'title'    => $campaign['title'] . ' — Listening Community',
                'campaign' => $campaign,
                'events'   => $events,
            ])
        );
    }

    private function notFound(): Response
    {
        return Response::html(
            View::render('errors/404', [
                'title'   => 'Campaign Not Found',
                'message' => 'The requested campaign was not found or is currently not available for public view.',
            ]),
            404
        );
    }
}
