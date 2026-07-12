<?php

namespace App\Support;

/**
 * Governance boundaries for Carboot@CMart vs CMart venue/activity management.
 *
 * Canonical (Phase 1.3C PR2):
 * - Carboot operations, analytics, payment verify, pass check-in: organizer, super_admin
 * - CMart activities: organizer, cmart_management, super_admin
 * - Generated reports: organizer, cmart_management, super_admin
 */
class ManagementCapability
{
    public const CARBOOT_OPERATIONS = 'carboot_operations';
    public const CARBOOT_OPERATIONAL_ANALYTICS = 'carboot_operational_analytics';
    public const CMART_ACTIVITY_MANAGEMENT = 'cmart_activity_management';
    public const GENERATED_REPORTS = 'generated_reports';
    public const ORGANIZER_QUEUE = 'organizer_queue';

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
            self::ORGANIZER_QUEUE,
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

        if (self::canPerformCarbootOperations($role)) {
            $capabilities[] = self::CARBOOT_OPERATIONS;
            $capabilities[] = self::ORGANIZER_QUEUE;
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
     * Carboot booking queues, vendor coordination, carboot events, payment verify, pass check-in.
     */
    public static function canPerformCarbootOperations(?string $role): bool
    {
        return in_array(ManagementRole::normalize($role), [
            ManagementRole::ORGANIZER,
            ManagementRole::SUPER_ADMIN,
        ], true);
    }

    /**
     * Raw Carboot analytics (revenue, word cloud, audit trail).
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
     * @deprecated Use canPerformCarbootOperations() — alias kept for PR3 frontend sync.
     */
    public static function canAssistCarbootOperations(?string $role): bool
    {
        return self::canPerformCarbootOperations($role);
    }

    public static function mapsToFutureOrganizer(?string $role): bool
    {
        return ManagementRole::isOrganizerEquivalent($role);
    }
}
