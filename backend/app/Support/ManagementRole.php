<?php

namespace App\Support;

class ManagementRole
{
    public const STAFF = 'staff';
    public const MANAGER = 'manager';
    public const SUPER_ADMIN = 'super_admin';

    public const LEGACY_STAFF = 'cmart_staff';
    public const LEGACY_MANAGER = 'cmart_admin';

    public const LEGACY_BOSS = 'boss';

    public static function normalize(?string $role): ?string
    {
        return match ($role) {
            self::LEGACY_STAFF => self::STAFF,
            self::LEGACY_MANAGER, self::LEGACY_BOSS => self::MANAGER,
            self::STAFF, self::MANAGER, self::SUPER_ADMIN => $role,
            default => $role,
        };
    }

    public static function isStaffRole(?string $role): bool
    {
        return self::normalize($role) === self::STAFF;
    }

    public static function isManagerRole(?string $role): bool
    {
        return self::normalize($role) === self::MANAGER;
    }

    public static function isSuperAdminRole(?string $role): bool
    {
        return self::normalize($role) === self::SUPER_ADMIN;
    }

    public static function isCmartWorker(?string $role): bool
    {
        return in_array(self::normalize($role), [self::STAFF, self::MANAGER, self::SUPER_ADMIN], true);
    }

    public static function canAccessManagerRoutes(?string $role): bool
    {
        $normalized = self::normalize($role);

        return in_array($normalized, [self::MANAGER, self::SUPER_ADMIN], true);
    }

    /**
     * Role key used for booking workflow state transitions.
     * Super admins follow the same approval matrix as managers.
     */
    public static function workflowRoleKey(?string $role): ?string
    {
        $normalized = self::normalize($role);

        if ($normalized === self::SUPER_ADMIN) {
            return self::MANAGER;
        }

        return $normalized;
    }

    public static function matches(?string $userRole, string $requiredRole): bool
    {
        if ($userRole === null) {
            return false;
        }

        if ($userRole === $requiredRole) {
            return true;
        }

        $normalizedUser = self::normalize($userRole);
        $normalizedRequired = self::normalize($requiredRole) ?? $requiredRole;

        if ($normalizedUser === $normalizedRequired) {
            return true;
        }

        if ($normalizedRequired === self::MANAGER && $normalizedUser === self::SUPER_ADMIN) {
            return true;
        }

        return false;
    }

    public static function userHasAnyRole(?string $userRole, array $requiredRoles): bool
    {
        foreach ($requiredRoles as $requiredRole) {
            if (self::matches($userRole, $requiredRole)) {
                return true;
            }
        }

        return false;
    }
}
