<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Role & RBAC Service
 * Implements the approved hierarchical single-role model:
 * super_admin (40) > coordinator (30) > staff (20) > viewer (10)
 */
class RoleService
{
    public const ROLE_VIEWER = 'viewer';
    public const ROLE_STAFF = 'staff';
    public const ROLE_COORDINATOR = 'coordinator';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_DEFAULT_ADMINISTRATOR = self::ROLE_STAFF;

    /**
     * Canonical Role Hierarchy Ranks
     */
    public const ROLE_HIERARCHY = [
        self::ROLE_VIEWER      => 10,
        self::ROLE_STAFF       => 20,
        self::ROLE_COORDINATOR => 30,
        self::ROLE_ADMIN       => 35,
        self::ROLE_SUPER_ADMIN => 40,
    ];

    /**
     * Check if a given role slug is a recognized valid role.
     */
    public static function isValidRole(string $role): bool
    {
        return array_key_exists($role, self::ROLE_HIERARCHY);
    }

    /**
     * Determine if a role represents a Super Administrator.
     */
    public static function isSuperAdmin(?string $role): bool
    {
        return $role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Determine if a role represents a standard non-super-admin Administrator.
     */
    public static function isAdministrator(?string $role): bool
    {
        return !empty($role) && $role !== self::ROLE_SUPER_ADMIN;
    }

    /**
     * Get numerical rank for a role (higher rank = higher permissions).
     */
    public static function getRoleRank(string $role): int
    {
        return self::ROLE_HIERARCHY[$role] ?? 0;
    }

    /**
     * Determine if a user's role satisfies the required role rank.
     * E.g. hasRole('coordinator', 'staff') === true
     */
    public static function hasRole(string $userRole, string $requiredRole): bool
    {
        $userRank = self::getRoleRank($userRole);
        $requiredRank = self::getRoleRank($requiredRole);

        if ($userRank === 0 || $requiredRank === 0) {
            return false;
        }

        return $userRank >= $requiredRank;
    }

    /**
     * Determine if a user's role exactly matches a specific role.
     */
    public static function hasExactRole(string $userRole, string $expectedRole): bool
    {
        return $userRole === $expectedRole;
    }

    /**
     * Return list of all valid roles.
     */
    public static function getAllRoles(): array
    {
        return array_keys(self::ROLE_HIERARCHY);
    }

    /**
     * User-facing role presentation label:
     * - super_admin -> 'Super Administrator'
     * - all other roles -> 'Administrator'
     */
    public static function getRoleLabel(string $role): string
    {
        if (self::isSuperAdmin($role)) {
            return 'Super Administrator';
        }

        return 'Administrator';
    }

    /**
     * CSS badge class for visual status rendering.
     */
    public static function getBadgeClass(string $role): string
    {
        return self::isSuperAdmin($role) ? 'badge-danger' : 'badge-primary';
    }
}
