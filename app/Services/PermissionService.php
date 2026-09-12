<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;

/**
 * Granular Permission Management Service
 * Enforces module/action authorization with full Super Admin override.
 */
class PermissionService
{
    private PermissionRepository $permRepo;
    private UserRepository $userRepo;

    public function __construct(
        ?PermissionRepository $permRepo = null,
        ?UserRepository $userRepo = null
    ) {
        $this->permRepo = $permRepo ?? new PermissionRepository();
        $this->userRepo = $userRepo ?? new UserRepository();
    }

    /**
     * Check whether a user has a required permission.
     * Super Admin always has full access.
     */
    public function hasPermission(int $userId, string $permission, ?string $role = null): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if ($role === null) {
            $user = $this->userRepo->findById($userId);
            $role = $user['role'] ?? null;
        }

        // Super Admin possesses unconditional permission across all modules
        if ($role === RoleService::ROLE_SUPER_ADMIN) {
            return true;
        }

        $userPerms = $this->permRepo->getUserPermissionNames($userId);
        return in_array($permission, $userPerms, true);
    }

    /**
     * Convenient check whether a user can perform an action or possesses a permission name.
     * Supports both can($userId, 'module.action') and can($userId, 'module', 'action').
     */
    public function can(int $userId, string $moduleOrPerm, ?string $action = null): bool
    {
        $permission = $action !== null ? "{$moduleOrPerm}.{$action}" : $moduleOrPerm;
        return $this->hasPermission($userId, $permission);
    }

    /**
     * Get permission IDs assigned to a user.
     */
    public function getUserPermissionIds(int $userId): array
    {
        return $this->permRepo->getUserPermissionIds($userId);
    }

    /**
     * Assign / synchronize permission IDs for a user.
     */
    public function assignPermissions(int $userId, array $permissionIds): void
    {
        $this->permRepo->syncUserPermissions($userId, $permissionIds);
    }

    /**
     * Get all registered permissions grouped by module.
     */
    public function getAllPermissionsGrouped(): array
    {
        $all = $this->permRepo->getAll();
        $grouped = [];

        foreach ($all as $p) {
            $module = $p['module'];
            if (!isset($grouped[$module])) {
                $grouped[$module] = [
                    'label'       => $this->getModuleLabel($module),
                    'permissions' => [],
                ];
            }
            $grouped[$module]['permissions'][] = $p;
        }

        return $grouped;
    }

    /**
     * Human-friendly label for a system module.
     */
    public function getModuleLabel(string $module): string
    {
        return match ($module) {
            'dashboard'     => 'Dashboard Overview',
            'admins'        => 'Admin Management',
            'events'        => 'Events Module',
            'forms'         => 'Event Registration Forms',
            'registrations' => 'Registrations Module',
            'checkin'       => 'Check-in System',
            'analytics'     => 'Registration Analytics',
            'certificates'  => 'Certificates Module',
            'reports'       => 'Executive Reports',
            'form_settings' => 'Form Settings (Global)',
            'settings'      => 'System Settings',
            default         => ucfirst(str_replace('_', ' ', $module)),
        };
    }
}
