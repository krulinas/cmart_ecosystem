<?php

namespace Tests\Unit;

use App\Support\ManagementCapability;
use App\Support\ManagementRole;
use PHPUnit\Framework\TestCase;

class ManagementCapabilityTest extends TestCase
{
    public function test_organizer_has_full_carboot_operational_capabilities(): void
    {
        $capabilities = ManagementCapability::resolveForRole(ManagementRole::ORGANIZER);

        $this->assertTrue(ManagementCapability::mapsToFutureOrganizer(ManagementRole::ORGANIZER));
        $this->assertContains(ManagementCapability::CARBOOT_OPERATIONS, $capabilities);
        $this->assertContains(ManagementCapability::CARBOOT_OPERATIONAL_ANALYTICS, $capabilities);
        $this->assertContains(ManagementCapability::GENERATED_REPORTS, $capabilities);
        $this->assertContains(ManagementCapability::ORGANIZER_QUEUE, $capabilities);
        $this->assertTrue(ManagementCapability::canAccessCarbootOperationalAnalytics(ManagementRole::ORGANIZER));
    }

    public function test_legacy_strings_normalize_to_canonical_capabilities(): void
    {
        $this->assertSame(ManagementRole::ORGANIZER, ManagementRole::normalize('manager'));
        $this->assertSame(ManagementRole::ORGANIZER, ManagementRole::normalize('uum'));
        $this->assertSame(ManagementRole::CMART_MANAGEMENT, ManagementRole::normalize('staff'));

        foreach (['manager', 'uum'] as $legacyRole) {
            $capabilities = ManagementCapability::resolveForRole($legacyRole);

            $this->assertTrue(ManagementCapability::mapsToFutureOrganizer($legacyRole));
            $this->assertContains(ManagementCapability::CARBOOT_OPERATIONS, $capabilities);
            $this->assertContains(ManagementCapability::CARBOOT_OPERATIONAL_ANALYTICS, $capabilities);
            $this->assertContains(ManagementCapability::GENERATED_REPORTS, $capabilities);
        }

        $staffCapabilities = ManagementCapability::resolveForRole('staff');
        $this->assertNotContains(ManagementCapability::CARBOOT_OPERATIONS, $staffCapabilities);
        $this->assertContains(ManagementCapability::CMART_ACTIVITY_MANAGEMENT, $staffCapabilities);
        $this->assertContains(ManagementCapability::GENERATED_REPORTS, $staffCapabilities);
    }

    public function test_cmart_management_has_activity_and_reports_without_raw_analytics(): void
    {
        $capabilities = ManagementCapability::resolveForRole(ManagementRole::CMART_MANAGEMENT);

        $this->assertContains(ManagementCapability::CMART_ACTIVITY_MANAGEMENT, $capabilities);
        $this->assertContains(ManagementCapability::GENERATED_REPORTS, $capabilities);
        $this->assertNotContains(ManagementCapability::CARBOOT_OPERATIONS, $capabilities);
        $this->assertNotContains(ManagementCapability::CARBOOT_OPERATIONAL_ANALYTICS, $capabilities);
        $this->assertNotContains(ManagementCapability::ORGANIZER_QUEUE, $capabilities);
        $this->assertFalse(ManagementCapability::canAccessCarbootOperationalAnalytics(ManagementRole::CMART_MANAGEMENT));
        $this->assertFalse(ManagementCapability::mapsToFutureOrganizer(ManagementRole::CMART_MANAGEMENT));
    }

    public function test_super_admin_is_reserved_but_retains_analytics_access(): void
    {
        $this->assertTrue(ManagementCapability::mapsToFutureOrganizer(ManagementRole::SUPER_ADMIN));
        $this->assertTrue(ManagementCapability::canAccessCarbootOperationalAnalytics(ManagementRole::SUPER_ADMIN));
        $this->assertFalse(ManagementRole::isStaffRole(ManagementRole::STAFF));
        $this->assertFalse(ManagementRole::isManagerRole(ManagementRole::MANAGER));
    }

    public function test_community_vendor_has_no_management_capabilities(): void
    {
        $this->assertSame([], ManagementCapability::resolveForRole('community'));
        $this->assertFalse(ManagementCapability::canManageCmartActivities('community'));
    }

    public function test_cmart_management_never_gains_carboot_booking_authority(): void
    {
        $this->assertFalse(ManagementCapability::canPerformCarbootOperations(ManagementRole::CMART_MANAGEMENT));
        $this->assertFalse(ManagementCapability::canAssistCarbootOperations(ManagementRole::CMART_MANAGEMENT));
        $this->assertFalse(ManagementRole::isOrganizerEquivalent(ManagementRole::CMART_MANAGEMENT));
    }
}
