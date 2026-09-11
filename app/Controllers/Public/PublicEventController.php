<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\CampaignRepository;
use App\Repositories\EventRepository;

/**
 * Public Event Controller
 * Handles public event catalog browsing, filtering, and event detail visibility.
 */
class PublicEventController
{
    private EventRepository $eventRepo;
    private CampaignRepository $campaignRepo;

    public function __construct(
        ?EventRepository $eventRepo = null,
        ?CampaignRepository $campaignRepo = null
    ) {
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->campaignRepo = $campaignRepo ?? new CampaignRepository();
    }

    /**
     * Display public upcoming events directory.
     * GET /events
     */
    public function index(Request $request): Response
    {
        $filters = [
            'category'      => $request->query('category'),
            'format'        => $request->query('format'),
            'campaign_slug' => $request->query('campaign'),
            'search'        => $request->query('search'),
        ];

        $events = $this->eventRepo->getPublicUpcomingEvents($filters, 50);
        $campaigns = $this->campaignRepo->getActivePublicCampaigns();

        return Response::html(
            View::render('public/events/index', [
                'title'     => 'Upcoming Events — Listening Community',
                'events'    => $events,
                'campaigns' => $campaigns,
                'filters'   => $filters,
            ])
        );
    }

    /**
     * Display public event detail page with registration readiness.
     * GET /events/{campaign_slug}/{event_slug}
     */
    public function show(Request $request, array $vars): Response
    {
        $campaignSlug = trim((string) ($vars['campaign_slug'] ?? ''));
        $eventSlug = trim((string) ($vars['event_slug'] ?? ''));

        if ($campaignSlug === '' || $eventSlug === '') {
            return $this->notFound();
        }

        $event = $this->eventRepo->findPublicByCampaignAndSlug($campaignSlug, $eventSlug);

        // Visibility Gate: strictly non-draft, non-deleted, active campaign events
        if (!$event) {
            return $this->notFound();
        }

        // Evaluate user-friendly availability status (Guardrail 4: without exposing raw seat counts)
        $availability = $this->calculateAvailabilityState($event);

        return Response::html(
            View::render('public/events/show', [
                'title'        => $event['title'] . ' — ' . $event['campaign_title'],
                'event'        => $event,
                'availability' => $availability,
            ])
        );
    }

    /**
     * Calculate user-friendly availability state without leaking internal registration metrics.
     */
    private function calculateAvailabilityState(array $event): array
    {
        $now = time();
        $startTime = strtotime((string) $event['start_time']);
        $deadlineTime = !empty($event['registration_deadline']) ? strtotime((string) $event['registration_deadline']) : null;
        $capacity = (int) $event['capacity'];
        $confirmedCount = (int) ($event['confirmed_count'] ?? 0);
        $requiresApproval = (int) ($event['requires_approval'] ?? 0);

        $isPastDeadline = $deadlineTime !== null && $deadlineTime < $now;
        $isPastStart = $startTime <= $now;
        $isRegistrationOpen = ($event['status'] === 'published') && !$isPastStart && !$isPastDeadline;

        if (!$isRegistrationOpen) {
            return [
                'isOpen'        => false,
                'label'         => 'Registration Closed',
                'badgeClass'    => 'badge-secondary',
                'notice'        => $isPastStart ? 'This event has already started.' : 'Registration for this event is currently closed.',
                'canRegister'   => false,
                'isWaitlist'    => false,
            ];
        }

        if ($capacity > 0 && $confirmedCount >= $capacity) {
            return [
                'isOpen'        => true,
                'label'         => 'Waitlist',
                'badgeClass'    => 'badge-warning',
                'notice'        => 'Regular seats are filled. New registrations will be placed on the official waitlist.',
                'canRegister'   => true,
                'isWaitlist'    => true,
            ];
        }

        if ($capacity > 0 && ($capacity - $confirmedCount) <= max(5, (int) round($capacity * 0.15))) {
            return [
                'isOpen'        => true,
                'label'         => 'Limited Availability',
                'badgeClass'    => 'badge-info',
                'notice'        => 'Limited seats remaining. Register early to secure your spot.',
                'canRegister'   => true,
                'isWaitlist'    => false,
            ];
        }

        return [
            'isOpen'        => true,
            'label'         => 'Registration Open',
            'badgeClass'    => 'badge-success',
            'notice'        => $requiresApproval === 1 ? 'Free registration. Coordinator approval required before pass issuance.' : 'Free registration. Instant attendance pass confirmed upon submission.',
            'canRegister'   => true,
            'isWaitlist'    => false,
        ];
    }

    private function notFound(): Response
    {
        return Response::html(
            View::render('errors/404', [
                'title'   => 'Event Not Found',
                'message' => 'The requested event was not found or is not available for public view.',
            ]),
            404
        );
    }
}
