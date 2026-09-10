<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ParticipantService;
use App\Services\RoleService;
use Throwable;

/**
 * Participant Administrative Controller
 * Handles paginated roster display, search, filtering, profile overview, creation with duplicate warning, editing, and status moderation.
 */
class ParticipantController extends Controller
{
    private ParticipantService $participantService;

    public function __construct(?ParticipantService $participantService = null)
    {
        $this->participantService = $participantService ?? new ParticipantService();
    }

    /**
     * Display paginated participant roster with server-side filtering, search, and role-based contact masking.
     * Minimum Role: viewer
     */
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;

        $filters = [
            'status'   => $request->query('status'),
            'category' => $request->query('category'),
            'search'   => $request->query('search'),
        ];

        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_VIEWER);
        $canManage = RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR);

        $pagination = $this->participantService->getPaginatedParticipants($filters, $page, $perPage, $userRole);
        $counts = $this->participantService->getRepository()->countByStatus();

        return $this->render('admin/participants/index', [
            'title'      => 'Participant Directory',
            'breadcrumb' => 'Participants',
            'pagination' => $pagination,
            'counts'     => $counts,
            'filters'    => $filters,
            'userRole'   => $userRole,
            'canCreate'  => $canManage,
            'canEdit'    => $canManage,
            'categories' => ParticipantService::ALLOWED_CATEGORIES,
            'statuses'   => ParticipantService::ALLOWED_STATUSES,
        ], 'layouts/admin');
    }

    /**
     * Display participant creation form.
     * Minimum Role: coordinator
     */
    public function create(Request $request): Response
    {
        $old = Session::getFlash('old', []);
        $errors = Session::getFlash('errors', []);
        $duplicate = Session::getFlash('duplicate', null);

        return $this->render('admin/participants/create', [
            'title'      => 'Register Participant',
            'breadcrumb' => 'New Participant',
            'old'        => $old,
            'errors'     => $errors,
            'duplicate'  => $duplicate,
            'categories' => ParticipantService::ALLOWED_CATEGORIES,
            'statuses'   => ParticipantService::ALLOWED_STATUSES,
        ], 'layouts/admin');
    }

    /**
     * Store new participant record with duplicate warning check.
     * Minimum Role: coordinator
     */
    public function store(Request $request): Response
    {
        $postData = [
            'full_name'         => (string) $request->post('full_name', ''),
            'email'             => (string) $request->post('email', ''),
            'phone'             => (string) $request->post('phone', ''),
            'category'          => (string) $request->post('category', 'community'),
            'organization_name' => (string) $request->post('organization_name', ''),
            'agreed_guidelines' => (bool) $request->post('agreed_guidelines', false),
            'privacy_consent'   => (bool) $request->post('privacy_consent', false),
            'status'            => (string) $request->post('status', 'active'),
        ];

        $confirmDistinct = (bool) $request->post('confirm_distinct', false);
        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $participant = $this->participantService->createParticipant($postData, $actorId, $confirmDistinct);
            Session::flash('success', "Participant '{$participant['full_name']}' created successfully.");
            return $this->redirect('/admin/participants');
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('errors', $e->errors);
            Session::flash('old', $postData);

            if (!empty($e->errors['duplicate_detected'])) {
                Session::flash('duplicate', [
                    'message' => $e->errors['duplicate_detected'],
                    'id'      => $e->errors['duplicate_id'] ?? null,
                    'name'    => $e->errors['duplicate_name'] ?? null,
                ]);
            }

            return $this->redirect('/admin/participants/create');
        } catch (Throwable $e) {
            Session::flash('error', 'Unable to create participant: ' . $e->getMessage());
            Session::flash('old', $postData);
            return $this->redirect('/admin/participants/create');
        }
    }

    /**
     * Display participant detail profile with role-governed contact masking.
     * Minimum Role: viewer
     */
    public function show(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_VIEWER);
        $canManage = RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR);

        $participant = $this->participantService->getParticipantById($id, $userRole);

        if (!$participant) {
            Session::flash('error', 'The requested participant does not exist.');
            return $this->redirect('/admin/participants');
        }

        return $this->render('admin/participants/show', [
            'title'       => $participant['full_name'] . ' &mdash; Profile',
            'breadcrumb'  => $participant['full_name'],
            'participant' => $participant,
            'userRole'    => $userRole,
            'canEdit'     => $canManage,
            'statuses'    => ParticipantService::ALLOWED_STATUSES,
        ], 'layouts/admin');
    }

    /**
     * Display participant edit form (unmasked data for authorized coordinators).
     * Minimum Role: coordinator
     */
    public function edit(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $participant = $this->participantService->getRepository()->findById($id);

        if (!$participant) {
            Session::flash('error', 'Participant not found.');
            return $this->redirect('/admin/participants');
        }

        $old = Session::getFlash('old', $participant);
        $errors = Session::getFlash('errors', []);

        return $this->render('admin/participants/edit', [
            'title'       => 'Edit: ' . $participant['full_name'],
            'breadcrumb'  => 'Edit Participant',
            'participant' => $participant,
            'old'         => $old,
            'errors'      => $errors,
            'categories'  => ParticipantService::ALLOWED_CATEGORIES,
            'statuses'    => ParticipantService::ALLOWED_STATUSES,
        ], 'layouts/admin');
    }

    /**
     * Process participant updates (consent timestamps remain strictly immutable).
     * Minimum Role: coordinator
     */
    public function update(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $postData = [
            'full_name'         => (string) $request->post('full_name', ''),
            'email'             => (string) $request->post('email', ''),
            'phone'             => (string) $request->post('phone', ''),
            'category'          => (string) $request->post('category', 'community'),
            'organization_name' => (string) $request->post('organization_name', ''),
            'status'            => (string) $request->post('status', 'active'),
        ];

        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $updated = $this->participantService->updateParticipant($id, $postData, $actorId);
            Session::flash('success', "Participant '{$updated['full_name']}' updated successfully.");
            return $this->redirect("/admin/participants/{$id}");
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('errors', $e->errors);
            Session::flash('old', $postData);
            return $this->redirect("/admin/participants/{$id}/edit");
        } catch (Throwable $e) {
            Session::flash('error', 'Unable to update participant: ' . $e->getMessage());
            Session::flash('old', $postData);
            return $this->redirect("/admin/participants/{$id}/edit");
        }
    }

    /**
     * Transition participant lifecycle status with optional audit reason.
     * Minimum Role: coordinator
     */
    public function updateStatus(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $status = (string) $request->post('status', '');
        $reason = (string) $request->post('reason', '');
        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $this->participantService->updateStatus($id, $status, $reason, $actorId);
            Session::flash('success', "Participant status updated to '" . ucfirst($status) . "'.");
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if (!empty($referer) && str_contains($referer, '/admin/participants')) {
            return Response::redirect($referer);
        }

        return $this->redirect('/admin/participants');
    }
}
