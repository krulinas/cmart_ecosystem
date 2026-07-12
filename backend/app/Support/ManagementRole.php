<?php

namespace App\Support;

/**
 * Phase 1.3C PR1 — canonical role identity.
 *
 * Canonical roles: organizer, cmart_management, super_admin (+ community on the public side).
 * Legacy identities (manager, uum, cmart_admin, boss) normalize to organizer.
 * `staff` is a TEMPORARY transitional role: it keeps its Carboot queue duty only
 * until PR2 replaces the two-stage booking pipeline with direct Organizer review,
 * after which staff accounts are remapped to cmart_management and the role retires.
 */
class ManagementRole
{
    // Canonical management roles.
    public const ORGANIZER = 'organizer';
    public const CMART_MANAGEMENT = 'cmart_management';
    public const SUPER_ADMIN = 'super_admin';

    /**
     * TEMPORARY (PR2 removes): staff-stage actor for the legacy two-stage
     * booking pipeline. Do not grant new authority to this role.
     */
    public const STAFF = 'staff';

    /**
     * LEGACY (normalized to organizer): retained only so pre-migration data,
     * route strings, and the single legacy bridge test keep working until the
     * PR2 ENUM shrink.
     */
    public const MANAGER = 'manager';
    public const LEGACY_UUM = 'uum';
    public const LEGACY_STAFF = 'cmart_staff';
    public const LEGACY_MANAGER = 'cmart_admin';
    public const LEGACY_BOSS = 'boss';

    public static function normalize(?string $role): ?string
    {
        return match ($role) {
            self::LEGACY_STAFF => self::STAFF,
            // UUM = Organizer; manager was the legacy Organizer bridge.
            self::MANAGER,
            self::LEGACY_UUM,
            self::LEGACY_MANAGER,
            self::LEGACY_BOSS => self::ORGANIZER,
            self::STAFF,
            self::ORGANIZER,
            self::CMART_MANAGEMENT,
            self::SUPER_ADMIN => $role,
            default => $role,
        };
    }

    public static function isStaffRole(?string $role): bool
    {
        return self::normalize($role) === self::STAFF;
    }

    /**
     * @deprecated Legacy manager identities now normalize to organizer, so this
     * always returns false for real roles. Kept only until PR3 removes callers.
     */
    public static function isManagerRole(?string $role): bool
    {
        return self::normalize($role) === self::MANAGER;
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
            self::STAFF,
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
     * Carboot Organizer authority (legacy manager/uum identities included via normalize).
     */
    public static function isOrganizerEquivalent(?string $role): bool
    {
        return in_array(self::normalize($role), [
            self::ORGANIZER,
            self::SUPER_ADMIN,
        ], true);
    }

    public static function canAccessManagerRoutes(?string $role): bool
    {
        return ManagementCapability::canAccessCarbootOperationalAnalytics($role);
    }

    /**
     * Role key used for booking workflow state transitions.
     *
     * NOTE (PR2): the state machine still uses the legacy 'staff' / 'manager'
     * keys. Organizer and super admin map onto the 'manager' key until PR2
     * replaces the two-stage pipeline with direct Organizer review.
     */
    public static function workflowRoleKey(?string $role): ?string
    {
        $normalized = self::normalize($role);

        if (in_array($normalized, [self::ORGANIZER, self::SUPER_ADMIN], true)) {
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

        // Organizer-authority bridge: routes that require organizer (or the
        // legacy manager identity, which normalizes to organizer) also accept
        // the reserved super_admin role.
        if (
            in_array($normalizedRequired, [self::ORGANIZER, self::MANAGER], true)
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
     * Raw role strings accepted on management workspace routes.
     * Legacy strings remain listed during the PR1→PR2 dual-accept window.
     *
     * @return list<string>
     */
    public static function managementWorkspaceRoles(): array
    {
        return [
            self::STAFF,
            self::ORGANIZER,
            self::CMART_MANAGEMENT,
            self::SUPER_ADMIN,
            self::MANAGER,
            self::LEGACY_STAFF,
            self::LEGACY_MANAGER,
            self::LEGACY_BOSS,
        ];
    }

    /**
     * Roles allowed on Carboot operational routes (bookings, queues, events).
     * `staff` is TEMPORARY here until PR2 removes the staff pipeline stage.
     *
     * @return list<string>
     */
    public static function carbootOperationalRoles(): array
    {
        return [
            self::STAFF,
            self::ORGANIZER,
            self::SUPER_ADMIN,
            self::MANAGER,
            self::LEGACY_STAFF,
            self::LEGACY_MANAGER,
            self::LEGACY_BOSS,
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
            self::MANAGER,
            self::LEGACY_MANAGER,
            self::LEGACY_BOSS,
        ];
    }

    /**
     * Roles allowed to manage CMart venue activities/news.
     * cmart_management is the canonical CMart-side role; staff entry is
     * TEMPORARY until PR2 remaps staff accounts to cmart_management.
     *
     * @return list<string>
     */
    public static function cmartActivityRoles(): array
    {
        return [
            self::STAFF,
            self::ORGANIZER,
            self::CMART_MANAGEMENT,
            self::SUPER_ADMIN,
            self::MANAGER,
            self::LEGACY_STAFF,
            self::LEGACY_MANAGER,
            self::LEGACY_BOSS,
        ];
    }

    public static function routeRoleList(array $roles): string
    {
        return implode(',', $roles);
    }
}
