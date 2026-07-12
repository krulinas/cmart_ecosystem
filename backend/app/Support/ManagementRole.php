<?php

namespace App\Support;

/**
 * Phase 1.3C PR2 — canonical role identity (final).
 *
 * Active DB roles: community, organizer, cmart_management, super_admin.
 * Legacy strings (staff, manager, uum, cmart_admin, boss, cmart_staff) are
 * normalized at read time for backward-compatible API payloads only.
 */
class ManagementRole
{
    public const COMMUNITY = 'community';
    public const ORGANIZER = 'organizer';
    public const CMART_MANAGEMENT = 'cmart_management';
    public const SUPER_ADMIN = 'super_admin';

    /** @deprecated Legacy string — normalizes to cmart_management */
    public const STAFF = 'staff';
    /** @deprecated Legacy string — normalizes to organizer */
    public const MANAGER = 'manager';
    /** @deprecated Legacy string — normalizes to organizer */
    public const LEGACY_UUM = 'uum';
    /** @deprecated Legacy string — normalizes to cmart_management */
    public const LEGACY_STAFF = 'cmart_staff';
    /** @deprecated Legacy string — normalizes to organizer */
    public const LEGACY_MANAGER = 'cmart_admin';
    /** @deprecated Legacy string — normalizes to organizer */
    public const LEGACY_BOSS = 'boss';

    public static function normalize(?string $role): ?string
    {
        return match ($role) {
            self::LEGACY_STAFF, self::STAFF => self::CMART_MANAGEMENT,
            self::MANAGER,
            self::LEGACY_UUM,
            self::LEGACY_MANAGER,
            self::LEGACY_BOSS => self::ORGANIZER,
            self::COMMUNITY,
            self::ORGANIZER,
            self::CMART_MANAGEMENT,
            self::SUPER_ADMIN => $role,
            default => $role,
        };
    }

    /**
     * @deprecated Staff role removed in PR2. Always returns false.
     */
    public static function isStaffRole(?string $role): bool
    {
        return false;
    }

    /**
     * @deprecated Manager role removed in PR2. Always returns false.
     */
    public static function isManagerRole(?string $role): bool
    {
        return false;
    }

    public static function isOrganizerRole(?string $role): bool
    {
        return self::normalize($role) === self::ORGANIZER;
    }

    public static function isCmartManagementRole(?string $role): bool
    {
        return self::normalize($role) === self::CMART_MANAGEMENT;
    }

    public static function isSuperAdminRole(?string $role): bool
    {
        return self::normalize($role) === self::SUPER_ADMIN;
    }

    /**
     * Any role that may access the management workspace (/admin).
     */
    public static function isManagementUser(?string $role): bool
    {
        return in_array(self::normalize($role), [
            self::ORGANIZER,
            self::CMART_MANAGEMENT,
            self::SUPER_ADMIN,
        ], true);
    }

    /**
     * @deprecated Use isManagementUser() — kept for backward compatibility.
     */
    public static function isCmartWorker(?string $role): bool
    {
        return self::isManagementUser($role);
    }

    /**
     * Carboot Organizer authority (includes reserved super_admin technical override).
     */
    public static function isOrganizerEquivalent(?string $role): bool
    {
        return in_array(self::normalize($role), [
            self::ORGANIZER,
            self::SUPER_ADMIN,
        ], true);
    }

    public static function canAccessOrganizerRoutes(?string $role): bool
    {
        return ManagementCapability::canAccessCarbootOperationalAnalytics($role);
    }

    /**
     * @deprecated Use canAccessOrganizerRoutes() — kept for PR3 frontend compatibility.
     */
    public static function canAccessManagerRoutes(?string $role): bool
    {
        return self::canAccessOrganizerRoutes($role);
    }

    /**
     * @deprecated Removed in PR2 — direct Organizer workflow no longer uses role keys.
     */
    public static function workflowRoleKey(?string $role): ?string
    {
        $normalized = self::normalize($role);

        if (in_array($normalized, [self::ORGANIZER, self::SUPER_ADMIN], true)) {
            return self::ORGANIZER;
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

        if (
            $normalizedRequired === self::ORGANIZER
            && in_array($normalizedUser, [self::ORGANIZER, self::SUPER_ADMIN], true)
        ) {
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

    /**
     * @return list<string>
     */
    public static function managementWorkspaceRoles(): array
    {
        return [
            self::ORGANIZER,
            self::CMART_MANAGEMENT,
            self::SUPER_ADMIN,
        ];
    }

    /**
     * Roles allowed on Carboot operational routes (bookings, events, pass verify).
     *
     * @return list<string>
     */
    public static function carbootOperationalRoles(): array
    {
        return [
            self::ORGANIZER,
            self::SUPER_ADMIN,
        ];
    }

    /**
     * @return list<string>
     */
    public static function organizerEquivalentRoles(): array
    {
        return [
            self::ORGANIZER,
            self::SUPER_ADMIN,
        ];
    }

    /**
     * Roles allowed to manage CMart venue activities/news.
     *
     * @return list<string>
     */
    public static function cmartActivityRoles(): array
    {
        return [
            self::ORGANIZER,
            self::CMART_MANAGEMENT,
            self::SUPER_ADMIN,
        ];
    }

    public static function routeRoleList(array $roles): string
    {
        return implode(',', $roles);
    }
}
