<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CampaignRepository;
use App\Repositories\UserRepository;
use App\Services\EventService;
use App\Services\RoleService;
use InvalidArgumentException;
use Throwable;

/**
 * Event Administrative Controller
 * Handles event catalog listing, creation, view, edit, status transition, soft-deletion, and restoration.
 */
class EventController extends Controller
{
    private EventService $eventService;
    private CampaignRepository $campaignRepo;
    private UserRepository $userRepo;

    public function __construct(
        ?EventService $eventService = null,
        ?CampaignRepository $campaignRepo = null,
        ?UserRepository $userRepo = null
    ) {
        $this->eventService = $eventService ?? new EventService();
        $this->campaignRepo = $campaignRepo ?? new CampaignRepository();
        $this->userRepo = $userRepo ?? new UserRepository();
    }

    /**
     * Display event catalog with filtering by campaign, status, category, format, and search.
     * Minimum Role: viewer
     */
    public function index(Request $request): Response
    {
        $campaignId = $request->query('campaign_id');
        $status = $request->query('status');
        $category = $request->query('category');
        $format = $request->query('format');
        $search = $request->query('search');
        $trash = (bool) $request->query('trash', false);

        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_VIEWER);
        $isSuperAdmin = RoleService::hasExactRole($userRole, RoleService::ROLE_SUPER_ADMIN);

        // Only super_admin can inspect trash
        $includeDeleted = $isSuperAdmin && $trash;

        $filters = [
            'campaign_id' => !empty($campaignId) ? (int) $campaignId : null,
            'status'      => $status,
            'category'    => $category,
            'format'      => $format,
            'search'      => $search,
        ];

        $events = $this->eventService->getRepository()->all($includeDeleted, $filters);
        $campaigns = $this->campaignRepo->all(false);
        $counts = $this->eventService->getRepository()->countByStatus($filters['campaign_id']);

        return $this->render('admin/events/index', [
            'title'          => 'Event Management',
            'breadcrumb'     => 'Events & Circles',
            'events'         => $events,
            'campaigns'      => $campaigns,
            'counts'         => $counts,
            'filters'        => $filters,
            'isTrash'        => $includeDeleted,
            'userRole'       => $userRole,
            'canCreate'      => RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR),
            'canEdit'        => RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR),
            'canDelete'      => $isSuperAdmin,
            'categories'     => EventService::ALLOWED_CATEGORIES,
            'formats'        => EventService::ALLOWED_FORMATS,
            'statuses'       => EventService::ALLOWED_STATUSES,
        ], 'layouts/admin');
    }

    /**
     * Display event creation form.
     * Minimum Role: coordinator
     */
    public function create(Request $request): Response
    {
        $campaigns = $this->campaignRepo->all(false);
        $coordinators = $this->userRepo->getEligibleCoordinators();

        $old = Session::getFlash('old', []);
        $errors = Session::getFlash('errors', []);

        // Pre-select campaign if passed in query string
        $preselectedCampaignId = $request->query('campaign_id');
        if (!empty($preselectedCampaignId) && empty($old['campaign_id'])) {
            $old['campaign_id'] = (int) $preselectedCampaignId;
        }

        return $this->render('admin/events/create', [
            'title'        => 'Create New Event',
            'breadcrumb'   => 'Create Event',
            'campaigns'    => $campaigns,
            'coordinators' => $coordinators,
            'old'          => $old,
            'errors'       => $errors,
            'categories'   => EventService::ALLOWED_CATEGORIES,
            'formats'      => EventService::ALLOWED_FORMATS,
            'statuses'     => EventService::ALLOWED_STATUSES,
        ], 'layouts/admin');
    }

    /**
     * Store a new event in the database.
     * Minimum Role: coordinator
     */
    public function store(Request $request): Response
    {
        $postData = [
            'campaign_id'           => $request->post('campaign_id'),
            'coordinator_id'        => $request->post('coordinator_id'),
            'title'                 => (string) $request->post('title', ''),
            'slug'                  => (string) $request->post('slug', ''),
            'category'              => (string) $request->post('category', ''),
            'description'           => (string) $request->post('description', ''),
            'format'                => (string) $request->post('format', 'in_person'),
            'venue_name'            => (string) $request->post('venue_name', ''),
            'venue_address'         => (string) $request->post('venue_address', ''),
            'online_meeting_url'    => (string) $request->post('online_meeting_url', ''),
            'start_time'            => (string) $request->post('start_time', ''),
            'end_time'              => (string) $request->post('end_time', ''),
            'capacity'              => $request->post('capacity', ''),
            'registration_deadline' => (string) $request->post('registration_deadline', ''),
            'requires_approval'     => (bool) $request->post('requires_approval', false),
            'status'                => (string) $request->post('status', 'draft'),
        ];

        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $event = $this->eventService->createEvent($postData, $actorId);
            Session::flash('success', "Event '{$event['title']}' created successfully.");
            return $this->redirect('/admin/events');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('errors', $e->errors ?? ['general' => $e->getMessage()]);
            Session::flash('old', $postData);
            return $this->redirect('/admin/events/create');
        } catch (Throwable $e) {
            Session::flash('error', 'Unable to create event: ' . $e->getMessage());
            Session::flash('old', $postData);
            return $this->redirect('/admin/events/create');
        }
    }

    /**
     * Display detailed event overview.
     * Minimum Role: viewer
     */
    public function show(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $event = $this->eventService->getRepository()->findById($id, true);

        if (!$event) {
            Session::flash('error', 'The requested event does not exist.');
            return $this->redirect('/admin/events');
        }

        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_VIEWER);
        $isSuperAdmin = RoleService::hasExactRole($userRole, RoleService::ROLE_SUPER_ADMIN);

        return $this->render('admin/events/show', [
            'title'      => $event['title'] . ' &mdash; Details',
            'breadcrumb' => $event['title'],
            'event'      => $event,
            'userRole'   => $userRole,
            'canEdit'    => RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR),
            'canDelete'  => $isSuperAdmin,
        ], 'layouts/admin');
    }

    /**
     * Display event edit form.
     * Minimum Role: coordinator
     */
    public function edit(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $event = $this->eventService->getRepository()->findById($id);

        if (!$event) {
            Session::flash('error', 'Event not found or has been soft-deleted.');
            return $this->redirect('/admin/events');
        }

        $campaigns = $this->campaignRepo->all(false);
        $coordinators = $this->userRepo->getEligibleCoordinators();

        // Convert database DATETIME to HTML5 datetime-local format (YYYY-MM-DDTHH:MM) for form pre-population
        $eventForForm = $event;
        if (!empty($eventForForm['start_time'])) {
            $eventForForm['start_time'] = date('Y-m-d\TH:i', strtotime($eventForForm['start_time']));
        }
        if (!empty($eventForForm['end_time'])) {
            $eventForForm['end_time'] = date('Y-m-d\TH:i', strtotime($eventForForm['end_time']));
        }
        if (!empty($eventForForm['registration_deadline'])) {
            $eventForForm['registration_deadline'] = date('Y-m-d\TH:i', strtotime($eventForForm['registration_deadline']));
        }

        $old = Session::getFlash('old', $eventForForm);
        $errors = Session::getFlash('errors', []);

        return $this->render('admin/events/edit', [
            'title'        => 'Edit: ' . $event['title'],
            'breadcrumb'   => 'Edit Event',
            'event'        => $event,
            'campaigns'    => $campaigns,
            'coordinators' => $coordinators,
            'old'          => $old,
            'errors'       => $errors,
            'categories'   => EventService::ALLOWED_CATEGORIES,
            'formats'      => EventService::ALLOWED_FORMATS,
            'statuses'     => EventService::ALLOWED_STATUSES,
        ], 'layouts/admin');
    }

    /**
     * Process event update.
     * Minimum Role: coordinator
     */
    public function update(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $postData = [
            'campaign_id'           => $request->post('campaign_id'),
            'coordinator_id'        => $request->post('coordinator_id'),
            'title'                 => (string) $request->post('title', ''),
            'slug'                  => (string) $request->post('slug', ''),
            'category'              => (string) $request->post('category', ''),
            'description'           => (string) $request->post('description', ''),
            'format'                => (string) $request->post('format', 'in_person'),
            'venue_name'            => (string) $request->post('venue_name', ''),
            'venue_address'         => (string) $request->post('venue_address', ''),
            'online_meeting_url'    => (string) $request->post('online_meeting_url', ''),
            'start_time'            => (string) $request->post('start_time', ''),
            'end_time'              => (string) $request->post('end_time', ''),
            'capacity'              => $request->post('capacity', ''),
            'registration_deadline' => (string) $request->post('registration_deadline', ''),
            'requires_approval'     => (bool) $request->post('requires_approval', false),
            'status'                => (string) $request->post('status', 'draft'),
        ];

        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $updated = $this->eventService->updateEvent($id, $postData, $actorId);
            Session::flash('success', "Event '{$updated['title']}' updated successfully.");
            return $this->redirect("/admin/events/{$id}");
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('errors', $e->errors ?? ['general' => $e->getMessage()]);
            Session::flash('old', $postData);
            return $this->redirect("/admin/events/{$id}/edit");
        } catch (Throwable $e) {
            Session::flash('error', 'Unable to update event: ' . $e->getMessage());
            Session::flash('old', $postData);
            return $this->redirect("/admin/events/{$id}/edit");
        }
    }

    /**
     * Quick status transition endpoint.
     * Minimum Role: coordinator
     */
    public function updateStatus(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $status = (string) $request->post('status', '');
        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $this->eventService->updateStatus($id, $status, $actorId);
            Session::flash('success', "Event status changed to '{$status}'.");
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if (!empty($referer) && str_contains($referer, '/admin/events')) {
            return Response::redirect($referer);
        }

        return $this->redirect('/admin/events');
    }

    /**
     * Soft-delete an event.
     * Minimum Role: super_admin
     */
    public function destroy(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $this->eventService->softDeleteEvent($id, $actorId);
            Session::flash('success', 'Event was soft-deleted successfully.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirect('/admin/events');
    }

    /**
     * Restore a soft-deleted event.
     * Minimum Role: super_admin
     */
    public function restore(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $this->eventService->restoreEvent($id, $actorId);
            Session::flash('success', 'Event was restored successfully.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirect('/admin/events');
    }
}
