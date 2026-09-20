<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\RoleService;
use Throwable;

/**
 * Super Administrator Admin Management Controller
 * Handles administrative accounts, lifecycle states, password resets, and module permissions.
 * Strictly restricted to Super Administrators.
 */
class AdminManagementController
{
    private UserRepository $userRepo;
    private PermissionService $permService;
    private AuditService $auditService;

    public function __construct(
        ?UserRepository $userRepo = null,
        ?PermissionService $permService = null,
        ?AuditService $auditService = null
    ) {
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->permService = $permService ?? new PermissionService();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Defense-in-depth authorization guard.
     * Enforces that only Super Administrators can execute any Admin Management action.
     */
    private function ensureSuperAdmin(Request $request, string $action): ?Response
    {
        Session::start();
        $userRole = (string) Session::get('_auth_user_role', '');

        if (!RoleService::isSuperAdmin($userRole)) {
            try {
                $this->auditService->log(
                    'auth.access_denied',
                    'route',
                    null,
                    [
                        'path'          => $request->getPath(),
                        'required_role' => RoleService::ROLE_SUPER_ADMIN,
                        'user_role'     => $userRole ?: 'anonymous',
                        'action'        => $action,
                    ]
                );
            } catch (Throwable) {
                // Ignore audit logging failures during access denied
            }

            return Response::html(View::render('errors/403', [
                'title'        => '403 Forbidden',
                'requiredRole' => 'Super Administrator',
                'userRole'     => RoleService::getRoleLabel($userRole ?: 'anonymous'),
            ], 'layouts/admin'), 403);
        }

        return null;
    }

    /**
     * List all administrative users.
     * GET /admin/admins
     */
    public function index(Request $request): Response
    {
        if ($denied = $this->ensureSuperAdmin($request, 'index')) {
            return $denied;
        }

        $admins = $this->userRepo->getAllAdmins();

        return Response::html(View::render('admin/admins/index', [
            'title'     => 'Admin Management',
            'admins'    => $admins,
            'activeNav' => 'admins',
        ], 'layouts/admin'));
    }

    /**
     * Create Admin Form.
     * GET /admin/admins/create
     */
    public function create(Request $request): Response
    {
        if ($denied = $this->ensureSuperAdmin($request, 'create')) {
            return $denied;
        }

        // Module permissions available for assignment (excluding admin-management)
        $groupedPerms = $this->permService->getAllPermissionsGrouped(true);

        return Response::html(View::render('admin/admins/create', [
            'title'        => 'Create Administrator',
            'groupedPerms' => $groupedPerms,
            'activeNav'    => 'admins',
        ], 'layouts/admin'));
    }

    /**
     * Store New Administrator.
     * POST /admin/admins
     */
    public function store(Request $request): Response
    {
        if ($denied = $this->ensureSuperAdmin($request, 'store')) {
            return $denied;
        }

        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $name = trim((string) $request->input('name', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $phone = trim((string) $request->input('phone', ''));
        $password = (string) $request->input('password', '');
        $confirmPassword = (string) $request->input('password_confirmation', '');

        if (empty($name) || mb_strlen($name) < 2) {
            Session::flash('error', 'Administrator name must be at least 2 characters.');
            return Response::redirect(url('/admin/admins/create'));
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please provide a valid email address.');
            return Response::redirect(url('/admin/admins/create'));
        }

        if ($this->userRepo->emailExists($email)) {
            Session::flash('error', "An account with email [{$email}] already exists.");
            return Response::redirect(url('/admin/admins/create'));
        }

        if (strlen($password) < 8) {
            Session::flash('error', 'Password must be at least 8 characters in length.');
            return Response::redirect(url('/admin/admins/create'));
        }

        if ($password !== $confirmPassword) {
            Session::flash('error', 'Password confirmation does not match.');
            return Response::redirect(url('/admin/admins/create'));
        }

        // Internal role resolved strictly server-side: Super Admin creating an Administrator
        $role = RoleService::ROLE_DEFAULT_ADMINISTRATOR;

        try {
            $hash = Security::hashPassword($password);
            $newAdminId = $this->userRepo->create([
                'name'          => $name,
                'email'         => $email,
                'phone'         => $phone ?: null,
                'password_hash' => $hash,
                'role'          => $role,
                'status'        => 'active',
            ]);

            // Assign initial module permissions if selected
            $permIds = (array) $request->input('permissions', []);
            $cleanIds = array_map('intval', array_filter($permIds, 'is_numeric'));
            if (!empty($cleanIds)) {
                $this->permService->assignPermissions($newAdminId, $cleanIds);
            }

            $this->auditService->log(
                'administrator_created',
                'users',
                $newAdminId,
                [
                    'name'  => $name,
                    'email' => $email,
                    'role'  => 'Administrator',
                ],
                $currentUserId,
                'admin'
            );

            Session::flash('success', "Administrator [{$name}] successfully created.");
            return Response::redirect(url('/admin/admins'));
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to create administrator. Please try again.');
            return Response::redirect(url('/admin/admins/create'));
        }
    }

    /**
     * Edit Administrator Form.
     * GET /admin/admins/{id}/edit
     */
    public function edit(Request $request, array $vars): Response
    {
        if ($denied = $this->ensureSuperAdmin($request, 'edit')) {
            return $denied;
        }

        $id = (int) ($vars['id'] ?? 0);
        $admin = $this->userRepo->findById($id);

        if (!$admin) {
            Session::flash('error', 'Administrator not found.');
            return Response::redirect(url('/admin/admins'));
        }

        $isSuperAdmin = RoleService::isSuperAdmin($admin['role'] ?? '');

        return Response::html(View::render('admin/admins/edit', [
            'title'        => 'Edit Administrator Profile',
            'admin'        => $admin,
            'isSuperAdmin' => $isSuperAdmin,
            'activeNav'    => 'admins',
        ], 'layouts/admin'));
    }

    /**
     * Update Administrator Profile.
     * POST /admin/admins/{id}
     */
    public function update(Request $request, array $vars): Response
    {
        if ($denied = $this->ensureSuperAdmin($request, 'update')) {
            return $denied;
        }

        $id = (int) ($vars['id'] ?? 0);
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $admin = $this->userRepo->findById($id);
        if (!$admin) {
            Session::flash('error', 'Administrator not found.');
            return Response::redirect(url('/admin/admins'));
        }

        $name = trim((string) $request->input('name', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $phone = trim((string) $request->input('phone', ''));
        $status = trim((string) $request->input('status', $admin['status']));

        // Preserve target user's existing internal role (do not accept client-provided role)
        $role = $admin['role'];

        if (empty($name) || mb_strlen($name) < 2) {
            Session::flash('error', 'Name must be at least 2 characters.');
            return Response::redirect(url("/admin/admins/{$id}/edit"));
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please provide a valid email address.');
            return Response::redirect(url("/admin/admins/{$id}/edit"));
        }

        // Email uniqueness check if changed
        if ($email !== strtolower($admin['email']) && $this->userRepo->emailExists($email)) {
            Session::flash('error', "The email [{$email}] is already in use by another user.");
            return Response::redirect(url("/admin/admins/{$id}/edit"));
        }

        // Prevent self-deactivation
        if ($id === $currentUserId && $status !== 'active') {
            Session::flash('error', 'You cannot deactivate your own administrative account.');
            return Response::redirect(url("/admin/admins/{$id}/edit"));
        }

        // Prevent deactivating the only active Super Administrator
        if (RoleService::isSuperAdmin($role) && $status !== 'active' && $this->userRepo->countSuperAdmins() <= 1) {
            Session::flash('error', 'Cannot deactivate the only active Super Administrator.');
            return Response::redirect(url("/admin/admins/{$id}/edit"));
        }

        if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
            $status = 'active';
        }

        try {
            $this->userRepo->updateAdmin($id, [
                'name'   => $name,
                'email'  => $email,
                'phone'  => $phone ?: null,
                'role'   => $role,
                'status' => $status,
            ]);

            $this->auditService->log(
                'admin.update',
                'users',
                $id,
                [
                    'old_status' => $admin['status'],
                    'new_status' => $status,
                ],
                $currentUserId,
                'admin'
            );

            Session::flash('success', "Administrator [{$name}] updated successfully.");
            return Response::redirect(url('/admin/admins'));
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to update administrator.');
            return Response::redirect(url("/admin/admins/{$id}/edit"));
        }
    }

    /**
     * Password Reset Form.
     * GET /admin/admins/{id}/password
     */
    public function password(Request $request, array $vars): Response
    {
        if ($denied = $this->ensureSuperAdmin($request, 'password')) {
            return $denied;
        }

        $id = (int) ($vars['id'] ?? 0);
        $admin = $this->userRepo->findById($id);

        if (!$admin) {
            Session::flash('error', 'Administrator not found.');
            return Response::redirect(url('/admin/admins'));
        }

        return Response::html(View::render('admin/admins/password', [
            'title'     => "Reset Password: {$admin['name']}",
            'admin'     => $admin,
            'activeNav' => 'admins',
        ], 'layouts/admin'));
    }

    /**
     * Process Password Reset.
     * POST /admin/admins/{id}/password
     */
    public function updatePassword(Request $request, array $vars): Response
    {
        if ($denied = $this->ensureSuperAdmin($request, 'updatePassword')) {
            return $denied;
        }

        $id = (int) ($vars['id'] ?? 0);
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $admin = $this->userRepo->findById($id);
        if (!$admin) {
            Session::flash('error', 'Administrator not found.');
            return Response::redirect(url('/admin/admins'));
        }

        $password = (string) $request->input('password', '');
        $confirmPassword = (string) $request->input('password_confirmation', '');

        if (strlen($password) < 8) {
            Session::flash('error', 'Password must be at least 8 characters in length.');
            return Response::redirect(url("/admin/admins/{$id}/password"));
        }

        if ($password !== $confirmPassword) {
            Session::flash('error', 'Password confirmation does not match.');
            return Response::redirect(url("/admin/admins/{$id}/password"));
        }

        try {
            $hash = Security::hashPassword($password);
            $this->userRepo->resetPassword($id, $hash);

            $this->auditService->log(
                'admin.password_reset',
                'users',
                $id,
                ['target_user' => $admin['email']],
                $currentUserId,
                'admin'
            );

            Session::flash('success', "Password successfully updated for [{$admin['name']}].");
            return Response::redirect(url('/admin/admins'));
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to update password.');
            return Response::redirect(url("/admin/admins/{$id}/password"));
        }
    }

    /**
     * Activate Administrator Account.
     * POST /admin/admins/{id}/activate
     */
    public function activate(Request $request, array $vars): Response
    {
        if ($denied = $this->ensureSuperAdmin($request, 'activate')) {
            return $denied;
        }

        $id = (int) ($vars['id'] ?? 0);
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $admin = $this->userRepo->findById($id);
        if (!$admin) {
            Session::flash('error', 'Administrator not found.');
            return Response::redirect(url('/admin/admins'));
        }

        $this->userRepo->updateAdmin($id, ['status' => 'active']);

        $this->auditService->log(
            'admin.activate',
            'users',
            $id,
            ['target_user' => $admin['email']],
            $currentUserId,
            'admin'
        );

        Session::flash('success', "Administrator [{$admin['name']}] activated.");
        return Response::redirect(url('/admin/admins'));
    }

    /**
     * Deactivate Administrator Account.
     * POST /admin/admins/{id}/deactivate
     */
    public function deactivate(Request $request, array $vars): Response
    {
        if ($denied = $this->ensureSuperAdmin($request, 'deactivate')) {
            return $denied;
        }

        $id = (int) ($vars['id'] ?? 0);
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $admin = $this->userRepo->findById($id);
        if (!$admin) {
            Session::flash('error', 'Administrator not found.');
            return Response::redirect(url('/admin/admins'));
        }

        if ($id === $currentUserId) {
            Session::flash('error', 'You cannot deactivate your own administrative account.');
            return Response::redirect(url('/admin/admins'));
        }

        if (RoleService::isSuperAdmin($admin['role'] ?? '') && $this->userRepo->countSuperAdmins() <= 1) {
            Session::flash('error', 'Cannot deactivate the only active Super Administrator.');
            return Response::redirect(url('/admin/admins'));
        }

        $this->userRepo->updateAdmin($id, ['status' => 'inactive']);

        $this->auditService->log(
            'admin.deactivate',
            'users',
            $id,
            ['target_user' => $admin['email']],
            $currentUserId,
            'admin'
        );

        Session::flash('success', "Administrator [{$admin['name']}] deactivated.");
        return Response::redirect(url('/admin/admins'));
    }

    /**
     * Permission Assignment Matrix Form.
     * GET /admin/admins/{id}/permissions
     */
    public function permissions(Request $request, array $vars): Response
    {
        if ($denied = $this->ensureSuperAdmin($request, 'permissions')) {
            return $denied;
        }

        $id = (int) ($vars['id'] ?? 0);
        $admin = $this->userRepo->findById($id);

        if (!$admin) {
            Session::flash('error', 'Administrator not found.');
            return Response::redirect(url('/admin/admins'));
        }

        $isTargetSuper = RoleService::isSuperAdmin($admin['role'] ?? '');
        // Exclude admin-management module for regular administrators
        $groupedPerms = $this->permService->getAllPermissionsGrouped(true);
        $assignedIds = $this->permService->getUserPermissionIds($id);

        return Response::html(View::render('admin/admins/permissions', [
            'title'         => "Permission Assignment: {$admin['name']}",
            'admin'         => $admin,
            'isTargetSuper' => $isTargetSuper,
            'groupedPerms'  => $groupedPerms,
            'assignedIds'   => $assignedIds,
            'activeNav'     => 'admins',
        ], 'layouts/admin'));
    }

    /**
     * Save Permission Assignment.
     * POST /admin/admins/{id}/permissions
     */
    public function updatePermissions(Request $request, array $vars): Response
    {
        if ($denied = $this->ensureSuperAdmin($request, 'updatePermissions')) {
            return $denied;
        }

        $id = (int) ($vars['id'] ?? 0);
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $admin = $this->userRepo->findById($id);
        if (!$admin) {
            Session::flash('error', 'Administrator not found.');
            return Response::redirect(url('/admin/admins'));
        }

        $permIds = (array) $request->input('permissions', []);
        $cleanIds = array_map('intval', array_filter($permIds, 'is_numeric'));

        try {
            $this->permService->assignPermissions($id, $cleanIds);

            $this->auditService->log(
                'admin.permissions_update',
                'users',
                $id,
                [
                    'target_user'    => $admin['email'],
                    'assigned_count' => count($cleanIds),
                ],
                $currentUserId,
                'admin'
            );

            Session::flash('success', "Permissions updated for [{$admin['name']}].");
            return Response::redirect(url('/admin/admins'));
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to update permissions.');
            return Response::redirect(url("/admin/admins/{$id}/permissions"));
        }
    }
}
