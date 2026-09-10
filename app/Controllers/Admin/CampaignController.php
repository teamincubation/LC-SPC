<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\CampaignService;
use App\Services\RoleService;
use InvalidArgumentException;
use Throwable;

/**
 * Campaign Administrative Controller
 * Handles campaign listing, creation, view, edit, status transition, soft-deletion, and restoration.
 */
class CampaignController extends Controller
{
    private CampaignService $campaignService;

    public function __construct(?CampaignService $campaignService = null)
    {
        $this->campaignService = $campaignService ?? new CampaignService();
    }

    /**
     * Display campaign catalog with filtering and search.
     * Minimum Role: viewer
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $search = $request->query('search');
        $trash = (bool) $request->query('trash', false);

        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_VIEWER);
        $isSuperAdmin = RoleService::hasExactRole($userRole, RoleService::ROLE_SUPER_ADMIN);

        // Only super_admin can inspect trash
        $includeDeleted = $isSuperAdmin && $trash;

        $campaigns = $this->campaignService->getRepository()->all($includeDeleted, $status, $search);
        $counts = $this->campaignService->getRepository()->countByStatus();

        return $this->render('admin/campaigns/index', [
            'title'          => 'Campaign Management',
            'breadcrumb'     => 'Campaigns',
            'campaigns'      => $campaigns,
            'counts'         => $counts,
            'currentStatus'  => $status,
            'search'         => $search,
            'isTrash'        => $includeDeleted,
            'userRole'       => $userRole,
            'canCreate'      => RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR),
            'canEdit'        => RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR),
            'canDelete'      => $isSuperAdmin,
        ], 'layouts/admin');
    }

    /**
     * Display campaign creation form.
     * Minimum Role: coordinator
     */
    public function create(Request $request): Response
    {
        $old = Session::getFlash('old', []);
        $errors = Session::getFlash('errors', []);

        return $this->render('admin/campaigns/create', [
            'title'      => 'Create New Campaign',
            'breadcrumb' => 'Create Campaign',
            'old'        => $old,
            'errors'     => $errors,
            'statuses'   => CampaignService::ALLOWED_STATUSES,
        ], 'layouts/admin');
    }

    /**
     * Store new campaign in database.
     * Minimum Role: coordinator
     */
    public function store(Request $request): Response
    {
        $postData = [
            'title'       => (string) $request->post('title', ''),
            'slug'        => (string) $request->post('slug', ''),
            'theme'       => (string) $request->post('theme', ''),
            'description' => (string) $request->post('description', ''),
            'start_date'  => (string) $request->post('start_date', ''),
            'end_date'    => (string) $request->post('end_date', ''),
            'status'      => (string) $request->post('status', 'draft'),
        ];

        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $campaign = $this->campaignService->createCampaign($postData, $actorId);
            Session::flash('success', "Campaign '{$campaign['title']}' created successfully.");
            return $this->redirect('/admin/campaigns');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('errors', $e->errors ?? ['general' => $e->getMessage()]);
            Session::flash('old', $postData);
            return $this->redirect('/admin/campaigns/create');
        } catch (Throwable $e) {
            Session::flash('error', 'Unable to create campaign: ' . $e->getMessage());
            Session::flash('old', $postData);
            return $this->redirect('/admin/campaigns/create');
        }
    }

    /**
     * Display detailed campaign overview.
     * Minimum Role: viewer
     */
    public function show(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $campaign = $this->campaignService->getRepository()->findById($id, true);

        if (!$campaign) {
            Session::flash('error', 'The requested campaign does not exist.');
            return $this->redirect('/admin/campaigns');
        }

        $userRole = (string) Session::get('_auth_user_role', RoleService::ROLE_VIEWER);
        $isSuperAdmin = RoleService::hasExactRole($userRole, RoleService::ROLE_SUPER_ADMIN);

        return $this->render('admin/campaigns/show', [
            'title'      => $campaign['title'] . ' &mdash; Details',
            'breadcrumb' => $campaign['title'],
            'campaign'   => $campaign,
            'userRole'   => $userRole,
            'canEdit'    => RoleService::hasRole($userRole, RoleService::ROLE_COORDINATOR),
            'canDelete'  => $isSuperAdmin,
        ], 'layouts/admin');
    }

    /**
     * Display campaign edit form.
     * Minimum Role: coordinator
     */
    public function edit(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $campaign = $this->campaignService->getRepository()->findById($id);

        if (!$campaign) {
            Session::flash('error', 'Campaign not found or has been soft-deleted.');
            return $this->redirect('/admin/campaigns');
        }

        $old = Session::getFlash('old', $campaign);
        $errors = Session::getFlash('errors', []);

        return $this->render('admin/campaigns/edit', [
            'title'      => 'Edit: ' . $campaign['title'],
            'breadcrumb' => 'Edit Campaign',
            'campaign'   => $campaign,
            'old'        => $old,
            'errors'     => $errors,
            'statuses'   => CampaignService::ALLOWED_STATUSES,
        ], 'layouts/admin');
    }

    /**
     * Process campaign update.
     * Minimum Role: coordinator
     */
    public function update(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $postData = [
            'title'       => (string) $request->post('title', ''),
            'slug'        => (string) $request->post('slug', ''),
            'theme'       => (string) $request->post('theme', ''),
            'description' => (string) $request->post('description', ''),
            'start_date'  => (string) $request->post('start_date', ''),
            'end_date'    => (string) $request->post('end_date', ''),
            'status'      => (string) $request->post('status', 'draft'),
        ];

        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $updated = $this->campaignService->updateCampaign($id, $postData, $actorId);
            Session::flash('success', "Campaign '{$updated['title']}' updated successfully.");
            return $this->redirect("/admin/campaigns/{$id}");
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('errors', $e->errors ?? ['general' => $e->getMessage()]);
            Session::flash('old', $postData);
            return $this->redirect("/admin/campaigns/{$id}/edit");
        } catch (Throwable $e) {
            Session::flash('error', 'Unable to update campaign: ' . $e->getMessage());
            Session::flash('old', $postData);
            return $this->redirect("/admin/campaigns/{$id}/edit");
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
            $this->campaignService->updateStatus($id, $status, $actorId);
            Session::flash('success', "Campaign status changed to '{$status}'.");
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if (!empty($referer) && str_contains($referer, '/admin/campaigns')) {
            return Response::redirect($referer);
        }

        return $this->redirect('/admin/campaigns');
    }

    /**
     * Soft-delete a campaign.
     * Minimum Role: super_admin
     */
    public function destroy(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $this->campaignService->softDeleteCampaign($id, $actorId);
            Session::flash('success', 'Campaign was soft-deleted successfully.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirect('/admin/campaigns');
    }

    /**
     * Restore a soft-deleted campaign.
     * Minimum Role: super_admin
     */
    public function restore(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('_auth_user_id', 0);

        try {
            $this->campaignService->restoreCampaign($id, $actorId);
            Session::flash('success', 'Campaign was restored successfully.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirect('/admin/campaigns');
    }
}
