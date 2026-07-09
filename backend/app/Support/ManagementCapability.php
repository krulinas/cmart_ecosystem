<?php

namespace App\Support;

/**
 * Phase 1 governance boundaries for Carboot@CMart vs CMart venue/activity management.
 *
 * The organizer role is prepared here but not yet stored in the users.role ENUM.
 * Until Phase 2, manager operational access maps to future organizer boundaries.
 */
class ManagementCapability
{
    public const CARBOOT_OPERATIONS = 'carboot_operations';
    public const CARBOOT_OPERATIONAL_ANALYTICS = 'carboot_operational_analytics';
    public const CMART_ACTIVITY_MANAGEMENT = 'cmart_activity_management';
    public const GENERATED_REPORTS = 'generated_reports';
    public const STAFF_QUEUE_ASSIST = 'staff_queue_assist';

    /** Prepared for Phase 2 — not yet persisted in users.role. */
    public const ROLE_ORGANIZER = 'organizer';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CARBOOT_OPERATIONS,
            self::CARBOOT_OPERATIONAL_ANALYTICS,
            self::CMART_ACTIVITY_MANAGEMENT,
            self::GENERATED_REPORTS,
            self::STAFF_QUEUE_ASSIST,
        ];
    }

    public static function can(?string $role, string $capability): bool
    {
        return in_array($capability, self::resolveForRole($role), true);
    }

    /**
     * @return list<string>
     */
    public static function resolveForRole(?string $role): array
    {
        if (!ManagementRole::isCmartWorker($role)) {
            return [];
        }

        $normalized = ManagementRole::normalize($role);
        $capabilities = [self::STAFF_QUEUE_ASSIST];

        if (in_array($normalized, [ManagementRole::STAFF, ManagementRole::MANAGER, ManagementRole::SUPER_ADMIN], true)) {
            $capabilities[] = self::CARBOOT_OPERATIONS;
            $capabilities[] = self::CMART_ACTIVITY_MANAGEMENT;
        }

        if (self::canAccessCarbootOperationalAnalytics($role)) {
            $capabilities[] = self::CARBOOT_OPERATIONAL_ANALYTICS;
        }

        if (self::canAccessGeneratedReports($role)) {
            $capabilities[] = self::GENERATED_REPORTS;
        }

        return array_values(array_unique($capabilities));
    }

    /**
     * Raw Carboot analytics (revenue, word cloud, audit trail).
     * Phase 1: manager + super_admin. Phase 2: organizer + super_admin only.
     */
    public static function canAccessCarbootOperationalAnalytics(?string $role): bool
    {
        $normalized = ManagementRole::normalize($role);

        if ($normalized === self::ROLE_ORGANIZER) {
            return true;
        }

        return in_array($normalized, [ManagementRole::MANAGER, ManagementRole::SUPER_ADMIN], true);
    }

    /**
     * CMart venue/activity CRUD (events, news, promotions).
     */
    public static function canManageCmartActivities(?string $role): bool
    {
        return ManagementRole::isCmartWorker($role);
    }

    /**
     * Consumable reports without full raw analytics dashboards.
     * Phase 1: manager + super_admin retain access while organizer role is introduced.
     */
    public static function canAccessGeneratedReports(?string $role): bool
    {
        $normalized = ManagementRole::normalize($role);

        if ($normalized === self::ROLE_ORGANIZER) {
            return true;
        }

        return in_array($normalized, [ManagementRole::MANAGER, ManagementRole::SUPER_ADMIN], true);
    }

    /**
     * Tier-1 queue assist and operational tooling.
     */
    public static function canAssistCarbootOperations(?string $role): bool
    {
        return ManagementRole::isCmartWorker($role);
    }

    /**
     * Whether this role maps to future Organizer operational ownership.
     * Phase 1 bridge: manager carries organizer duties until organizer is migrated.
     */
    public static function mapsToFutureOrganizer(?string $role): bool
    {
        $normalized = ManagementRole::normalize($role);

        return in_array($normalized, [ManagementRole::MANAGER, ManagementRole::SUPER_ADMIN, self::ROLE_ORGANIZER], true);
    }
}
