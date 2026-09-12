<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\ValidationException;
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
     * List all administrative users.
     * GET /admin/admins
     */
    public function index(Request $request): Response
    {
        $admins = $this->userRepo->getAllAdmins();

        return Response::html(View::render('admin/admins/index', [
            'title'      => 'Admin Management',
            'admins'     => $admins,
            'activeNav'  => 'admins',
        ], 'layouts/admin'));
    }

    /**
     * Create Admin Form.
     * GET /admin/admins/create
     */
    public function create(Request $request): Response
    {
        return Response::html(View::render('admin/admins/create', [
            'title'     => 'Create Administrator',
            'roles'     => RoleService::getAllRoles(),
            'activeNav' => 'admins',
        ], 'layouts/admin'));
    }

    /**
     * Store New Administrator.
     * POST /admin/admins
     */
    public function store(Request $request): Response
    {
        $currentUserId = (int) Session::get('_auth_user_id', 0);
        $currentUserRole = (string) Session::get('_auth_user_role', RoleService::ROLE_SUPER_ADMIN);

        $name = trim((string) $request->input('name', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $phone = trim((string) $request->input('phone', ''));
        $password = (string) $request->input('password', '');
        $role = trim((string) $request->input('role', 'staff'));

        if (empty($name) || strlen($name) < 2) {
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

        if (!RoleService::isValidRole($role)) {
            Session::flash('error', 'Invalid role selected.');
            return Response::redirect(url('/admin/admins/create'));
        }

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

            $this->auditService->log(
                'admin.create',
                'users',
                $newAdminId,
                [
                    'name'  => $name,
                    'email' => $email,
                    'role'  => $role,
                ],
                $currentUserId,
                $currentUserRole
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
        $id = (int) ($vars['id'] ?? 0);
        $admin = $this->userRepo->findById($id);

        if (!$admin) {
            Session::flash('error', 'Administrator not found.');
            return Response::redirect(url('/admin/admins'));
        }

        return Response::html(View::render('admin/admins/edit', [
            'title'     => 'Edit Administrator',
            'admin'     => $admin,
            'roles'     => RoleService::getAllRoles(),
            'activeNav' => 'admins',
        ], 'layouts/admin'));
    }

    /**
     * Update Administrator Profile.
     * POST /admin/admins/{id}
     */
    public function update(Request $request, array $vars): Response
    {
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
        $role = trim((string) $request->input('role', $admin['role']));
        $status = trim((string) $request->input('status', $admin['status']));

        if (empty($name) || strlen($name) < 2) {
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

        // Prevent self-demotion or self-deactivation if sole super_admin
        if ($id === $currentUserId) {
            if ($status !== 'active') {
                Session::flash('error', 'You cannot deactivate your own administrative account.');
                return Response::redirect(url("/admin/admins/{$id}/edit"));
            }
            if ($role !== RoleService::ROLE_SUPER_ADMIN && $this->userRepo->countSuperAdmins() <= 1) {
                Session::flash('error', 'Cannot remove super_admin role from the only active Super Administrator.');
                return Response::redirect(url("/admin/admins/{$id}/edit"));
            }
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
                    'old_role'   => $admin['role'],
                    'new_role'   => $role,
                    'old_status' => $admin['status'],
                    'new_status' => $status,
                ],
                $currentUserId,
                $currentUserRole
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
        $id = (int) ($vars['id'] ?? 0);
        $admin = $this->userRepo->findById($id);

        if (!$admin) {
            Session::flash('error', 'Administrator not found.');
            return Response::redirect(url('/admin/admins'));
        }

        return Response::html(View::render('admin/admins/password', [
            'title'     => "Reset Password — {$admin['name']}",
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
                $currentUserRole
            );

            Session::flash('success', "Password successfully updated for [{$admin['name']}].");
            return Response::redirect(url('/admin/admins'));
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to update password.');
            return Response::redirect(url("/admin/admins/{$id}/password"));
        }
    }

    /**
     * Permission Assignment Matrix Form.
     * GET /admin/admins/{id}/permissions
     */
    public function permissions(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $admin = $this->userRepo->findById($id);

        if (!$admin) {
            Session::flash('error', 'Administrator not found.');
            return Response::redirect(url('/admin/admins'));
        }

        $groupedPerms = $this->permService->getAllPermissionsGrouped();
        $assignedIds = $this->permService->getUserPermissionIds($id);

        return Response::html(View::render('admin/admins/permissions', [
            'title'         => "Permission Assignment — {$admin['name']}",
            'admin'         => $admin,
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
                    'target_user'      => $admin['email'],
                    'assigned_count'   => count($cleanIds),
                ],
                $currentUserId,
                $currentUserRole
            );

            Session::flash('success', "Permissions updated for [{$admin['name']}].");
            return Response::redirect(url('/admin/admins'));
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to update permissions.');
            return Response::redirect(url("/admin/admins/{$id}/permissions"));
        }
    }
}
