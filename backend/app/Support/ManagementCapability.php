<?php

namespace App\Support;

/**
 * Governance boundaries for Carboot@CMart vs CMart venue/activity management.
 *
 * Canonical (Phase 1.3C):
 * - Carboot operations + raw analytics: organizer, super_admin
 * - CMart activities: organizer, cmart_management, super_admin
 * - Generated reports: organizer, cmart_management, super_admin
 *
 * TEMPORARY compatibility (removed in PR2): the `staff` role keeps Carboot
 * queue/operations capabilities because the two-stage booking pipeline
 * (Pending_Staff -> Pending_Boss) still exists. This is NOT final governance.
 * Legacy manager/uum identities normalize to organizer in ManagementRole and
 * therefore inherit organizer capabilities without being listed here.
 */
class ManagementCapability
{
    public const CARBOOT_OPERATIONS = 'carboot_operations';
    public const CARBOOT_OPERATIONAL_ANALYTICS = 'carboot_operational_analytics';
    public const CMART_ACTIVITY_MANAGEMENT = 'cmart_activity_management';
    public const GENERATED_REPORTS = 'generated_reports';
    public const STAFF_QUEUE_ASSIST = 'staff_queue_assist';

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
        if (!ManagementRole::isManagementUser($role)) {
            return [];
        }

        $capabilities = [];

        if (self::canAssistCarbootOperations($role)) {
            $capabilities[] = self::STAFF_QUEUE_ASSIST;
        }

        if (self::canPerformCarbootOperations($role)) {
            $capabilities[] = self::CARBOOT_OPERATIONS;
        }

        if (self::canManageCmartActivities($role)) {
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
     * Carboot booking queues, vendor coordination, and carboot event management.
     * Canonical: organizer + super_admin. `staff` is a TEMPORARY entry until
     * PR2 removes the staff pipeline stage — cmart_management must never appear here.
     */
    public static function canPerformCarbootOperations(?string $role): bool
    {
        return in_array(ManagementRole::normalize($role), [
            ManagementRole::STAFF,
            ManagementRole::ORGANIZER,
            ManagementRole::SUPER_ADMIN,
        ], true);
    }

    /**
     * Raw Carboot analytics (revenue, word cloud, audit trail).
     * Organizer-owned; CMart Management and staff are explicitly excluded.
     */
    public static function canAccessCarbootOperationalAnalytics(?string $role): bool
    {
        return in_array(ManagementRole::normalize($role), [
            ManagementRole::ORGANIZER,
            ManagementRole::SUPER_ADMIN,
        ], true);
    }

    /**
     * CMart venue/activity CRUD (news, promotions, non-carboot venue events).
     */
    public static function canManageCmartActivities(?string $role): bool
    {
        return in_array(ManagementRole::normalize($role), [
            ManagementRole::STAFF,
            ManagementRole::ORGANIZER,
            ManagementRole::CMART_MANAGEMENT,
            ManagementRole::SUPER_ADMIN,
        ], true);
    }

    /**
     * Consumable operational reports without raw analytics dashboards.
     */
    public static function canAccessGeneratedReports(?string $role): bool
    {
        return in_array(ManagementRole::normalize($role), [
            ManagementRole::ORGANIZER,
            ManagementRole::CMART_MANAGEMENT,
            ManagementRole::SUPER_ADMIN,
        ], true);
    }

    /**
     * Tier-1 queue assist and operational tooling.
     */
    public static function canAssistCarbootOperations(?string $role): bool
    {
        return self::canPerformCarbootOperations($role);
    }

    /**
     * Whether this role maps to Organizer operational ownership.
     * Legacy manager/uum identities normalize to organizer, so they map too.
     */
    public static function mapsToFutureOrganizer(?string $role): bool
    {
        return ManagementRole::isOrganizerEquivalent($role);
    }
}
