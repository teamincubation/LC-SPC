<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\CapacityExceededException;
use App\Core\Exceptions\InvalidStateTransitionException;
use App\Core\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\CampaignRepository;
use App\Repositories\EventRepository;
use App\Repositories\ParticipantRepository;
use App\Repositories\RegistrationRepository;
use App\Services\RegistrationService;
use App\Services\RoleService;
use RuntimeException;

/**
 * Admin Registration Controller
 * Manages event registration listings, enrollment creation, lifecycle actions,
 * and attendance pass displays in the administrative portal.
 */
class RegistrationController
{
    private RegistrationRepository $registrationRepo;
    private RegistrationService $registrationService;
    private EventRepository $eventRepo;
    private CampaignRepository $campaignRepo;
    private ParticipantRepository $participantRepo;

    public function __construct(
        ?RegistrationRepository $registrationRepo = null,
        ?RegistrationService $registrationService = null,
        ?EventRepository $eventRepo = null,
        ?CampaignRepository $campaignRepo = null,
        ?ParticipantRepository $participantRepo = null
    ) {
        $this->registrationRepo = $registrationRepo ?? new RegistrationRepository();
        $this->registrationService = $registrationService ?? new RegistrationService();
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->campaignRepo = $campaignRepo ?? new CampaignRepository();
        $this->participantRepo = $participantRepo ?? new ParticipantRepository();
    }

    /**
     * List all registrations with filters, search, KPI counters, and server-side pagination.
     * GET /admin/registrations
     * Role: viewer+
     */
    public function index(Request $request): Response
    {
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_VIEWER);

        $filters = [
            'campaign_id' => $request->query('campaign_id') ? (int) $request->query('campaign_id') : null,
            'event_id'    => $request->query('event_id') ? (int) $request->query('event_id') : null,
            'status'      => $request->query('status') ? trim((string) $request->query('status')) : null,
            'search'      => $request->query('search') ? trim((string) $request->query('search')) : null,
        ];

        $page = max(1, (int) $request->query('page', 1));
        $paginated = $this->registrationRepo->paginate($filters, $page, 20);

        // Apply Privacy Shield masking for staff and viewer roles
        $paginated['items'] = $this->registrationService->maskRegistrationList($paginated['items'], $userRole);

        // Summary KPI counts (scoped to selected event if filtered)
        $kpis = $this->registrationRepo->countByStatus($filters['event_id']);

        // Active campaigns and events for filter dropdowns
        $campaigns = $this->campaignRepo->all(false);
        $events = $this->eventRepo->all(false);

        return Response::html(
            View::render('admin/registrations/index', [
                'title'       => 'Registration Directory',
                'items'       => $paginated['items'],
                'pagination'  => $paginated,
                'filters'     => $filters,
                'kpis'        => $kpis,
                'campaigns'   => $campaigns,
                'events'      => $events,
                'userRole'    => $userRole,
            ], 'layouts/admin')
        );
    }

    /**
     * Show enrollment creation form.
     * GET /admin/registrations/create
     * Role: coordinator+
     */
    public function create(Request $request): Response
    {
        $selectedEventId = (int) $request->query('event_id', 0);
        $selectedParticipantId = (int) $request->query('participant_id', 0);

        // Fetch published, active events for enrollment
        $events = $this->eventRepo->all(false, ['status' => 'published']);
        $recentParticipants = $this->participantRepo->all(['status' => 'active']);

        return Response::html(
            View::render('admin/registrations/create', [
                'title'                 => 'New Event Registration',
                'events'                => $events,
                'recentParticipants'    => $recentParticipants,
                'selectedEventId'       => $selectedEventId,
                'selectedParticipantId' => $selectedParticipantId,
                'errors'                => Session::getFlash('errors', []),
                'old'                   => Session::getFlash('old', []),
            ], 'layouts/admin')
        );
    }

    /**
     * Process new registration submission.
     * POST /admin/registrations
     * Role: coordinator+, Csrf
     */
    public function store(Request $request): Response
    {
        $actorId = (int) Session::get('_auth_user_id', 0);
        $eventId = (int) $request->post('event_id', 0);
        $participantMode = (string) $request->post('participant_mode', 'existing');
        $adminNotes = $request->post('admin_notes');

        if ($eventId <= 0) {
            Session::flash('errors', ['event_id' => 'Please select an event.']);
            Session::flash('old', $request->post());
            return Response::redirect(url('/admin/registrations/create'));
        }

        try {
            if ($participantMode === 'new') {
                $participantData = [
                    'full_name'         => $request->post('full_name'),
                    'email'             => $request->post('email'),
                    'phone'             => $request->post('phone'),
                    'category'          => $request->post('category', 'community'),
                    'organization_name' => $request->post('organization_name'),
                    'agreed_guidelines' => $request->post('agreed_guidelines'),
                    'privacy_consent'   => $request->post('privacy_consent'),
                ];

                $result = $this->registrationService->createRegistrationWithParticipant(
                    $eventId,
                    $participantData,
                    $adminNotes ? (string) $adminNotes : null,
                    $actorId
                );
            } else {
                $participantId = (int) $request->post('participant_id', 0);
                if ($participantId <= 0) {
                    Session::flash('errors', ['participant_id' => 'Please select an existing participant.']);
                    Session::flash('old', $request->post());
                    return Response::redirect(url('/admin/registrations/create'));
                }

                $result = $this->registrationService->registerParticipant(
                    $eventId,
                    $participantId,
                    $adminNotes ? (string) $adminNotes : null,
                    $actorId
                );
            }

            $regId = (int) ($result['registration']['id'] ?? 0);
            $regCode = (string) ($result['registration']['registration_code'] ?? '');
            $status = $result['status'] ?? '';

            if ($status === 'already_confirmed') {
                Session::flash('warning', "Participant is already registered for this event. Pass code: {$regCode}.");
            } elseif ($status === 'already_pending') {
                Session::flash('warning', "Participant already has a pending registration awaiting coordinator approval.");
            } elseif ($status === 'already_waitlisted') {
                Session::flash('warning', "Participant is currently on the waitlist for this event.");
            } elseif ($status === 'reactivated') {
                Session::flash('success', "Cancelled registration reactivated with a new pass code: {$regCode}.");
            } elseif ($status === 'created') {
                $newStatus = $result['new_status'] ?? 'confirmed';
                if ($newStatus === 'waitlisted') {
                    Session::flash('warning', "Event capacity reached. Registration has been placed on the Waitlist.");
                } elseif ($newStatus === 'pending') {
                    Session::flash('info', "Registration submitted and placed in 'pending' status awaiting approval.");
                } else {
                    Session::flash('success', "Registration successfully confirmed. Pass generated: {$regCode}.");
                }
            }

            return Response::redirect(url('/admin/registrations/' . $regId));
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());
            Session::flash('old', $request->post());
            return Response::redirect(url('/admin/registrations/create'));
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('old', $request->post());
            return Response::redirect(url('/admin/registrations/create'));
        }
    }

    /**
     * Show registration detail view with event info, participant card, and lifecycle actions.
     * GET /admin/registrations/{id}
     * Role: viewer+
     */
    public function show(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $registration = $this->registrationRepo->findById($id);

        if (!$registration) {
            Session::flash('error', "Registration record #{$id} not found.");
            return Response::redirect(url('/admin/registrations'));
        }

        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_VIEWER);
        $registration = $this->registrationService->maskRegistration($registration, $userRole);

        // Fetch current confirmed count for the event
        $confirmedCount = $this->registrationRepo->countConfirmedByEvent((int) $registration['event_id']);
        $isEventFull = ((int) $registration['event_capacity'] > 0) && ($confirmedCount >= (int) $registration['event_capacity']);

        return Response::html(
            View::render('admin/registrations/show', [
                'title'          => 'Registration #' . $registration['id'] . ' — ' . $registration['registration_code'],
                'registration'   => $registration,
                'confirmedCount' => $confirmedCount,
                'isEventFull'    => $isEventFull,
                'userRole'       => $userRole,
            ], 'layouts/admin')
        );
    }

    /**
     * Display administrative attendance pass view.
     * GET /admin/registrations/{id}/pass
     * Role: viewer+
     */
    public function pass(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $registration = $this->registrationRepo->findById($id);

        if (!$registration) {
            Session::flash('error', "Registration record #{$id} not found.");
            return Response::redirect(url('/admin/registrations'));
        }

        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_VIEWER);
        $registration = $this->registrationService->maskRegistration($registration, $userRole);

        return Response::html(
            View::render('admin/registrations/pass', [
                'title'        => 'Attendance Pass — ' . $registration['registration_code'],
                'registration' => $registration,
                'userRole'     => $userRole,
            ], 'layouts/admin')
        );
    }

    /**
     * Display printable attendance pass layout.
     * GET /admin/registrations/{id}/print
     * Role: viewer+
     */
    public function printPass(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $registration = $this->registrationRepo->findById($id);

        if (!$registration) {
            return Response::html('Attendance pass not found.', 404);
        }

        return Response::html(
            View::render('admin/registrations/print_pass', [
                'title'        => 'Print Pass — ' . $registration['registration_code'],
                'registration' => $registration,
            ])
        );
    }

    /**
     * Coordinator approves pending registration to confirmed.
     * POST /admin/registrations/{id}/approve
     * Role: coordinator+, Csrf
     */
    public function approve(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $this->registrationService->approveRegistration($id, $actorId);
            Session::flash('success', "Registration #{$id} has been approved and confirmed.");
        } catch (CapacityExceededException $e) {
            Session::flash('error', $e->getMessage());
        } catch (InvalidStateTransitionException | RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('/admin/registrations/' . $id));
    }

    /**
     * Coordinator moves pending registration to waitlisted when event capacity is full.
     * POST /admin/registrations/{id}/waitlist
     * Role: coordinator+, Csrf
     */
    public function moveToWaitlist(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $this->registrationService->movePendingToWaitlist($id, $actorId);
            Session::flash('info', "Registration #{$id} has been placed on the waitlist.");
        } catch (InvalidStateTransitionException | RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('/admin/registrations/' . $id));
    }

    /**
     * Coordinator promotes waitlisted registration to confirmed when seats open.
     * POST /admin/registrations/{id}/promote
     * Role: coordinator+, Csrf
     */
    public function promote(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $this->registrationService->promoteWaitlist($id, $actorId);
            Session::flash('success', "Waitlisted registration #{$id} successfully promoted to confirmed.");
        } catch (CapacityExceededException $e) {
            Session::flash('error', $e->getMessage());
        } catch (InvalidStateTransitionException | RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('/admin/registrations/' . $id));
    }

    /**
     * Cancel an active, pending, or waitlisted registration.
     * POST /admin/registrations/{id}/cancel
     * Role: coordinator+, Csrf
     */
    public function cancel(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $actorId = (int) Session::get('_auth_user_id', 0);
        $reason = $request->post('reason');

        try {
            $this->registrationService->cancelRegistration($id, $reason ? (string) $reason : null, $actorId);
            Session::flash('info', "Registration #{$id} has been cancelled.");
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
        } catch (InvalidStateTransitionException | RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('/admin/registrations/' . $id));
    }

    /**
     * Reactivate a cancelled registration.
     * POST /admin/registrations/{id}/reactivate
     * Role: coordinator+, Csrf
     */
    public function reactivate(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $actorId = (int) Session::get('_auth_user_id', 0);

        $reg = $this->registrationRepo->findById($id);
        if (!$reg) {
            Session::flash('error', "Registration #{$id} not found.");
            return Response::redirect(url('/admin/registrations'));
        }

        try {
            $result = $this->registrationService->registerParticipant((int) $reg['event_id'], (int) $reg['participant_id'], null, $actorId);
            $newCode = (string) ($result['registration']['registration_code'] ?? '');
            Session::flash('success', "Registration #{$id} successfully reactivated with a new pass code: {$newCode}.");
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('/admin/registrations/' . $id));
    }
}
