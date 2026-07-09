<?php

namespace Tests\Unit;

use App\Support\ManagementCapability;
use App\Support\ManagementRole;
use PHPUnit\Framework\TestCase;

class ManagementCapabilityTest extends TestCase
{
    public function test_staff_can_assist_carboot_operations_without_analytics(): void
    {
        $capabilities = ManagementCapability::resolveForRole(ManagementRole::STAFF);

        $this->assertContains(ManagementCapability::CARBOOT_OPERATIONS, $capabilities);
        $this->assertContains(ManagementCapability::CMART_ACTIVITY_MANAGEMENT, $capabilities);
        $this->assertContains(ManagementCapability::STAFF_QUEUE_ASSIST, $capabilities);
        $this->assertNotContains(ManagementCapability::CARBOOT_OPERATIONAL_ANALYTICS, $capabilities);
        $this->assertFalse(ManagementCapability::canAccessCarbootOperationalAnalytics(ManagementRole::STAFF));
    }

    public function test_manager_maps_to_future_organizer_and_retains_analytics_in_phase_one(): void
    {
        $capabilities = ManagementCapability::resolveForRole(ManagementRole::MANAGER);

        $this->assertTrue(ManagementCapability::mapsToFutureOrganizer(ManagementRole::MANAGER));
        $this->assertContains(ManagementCapability::CARBOOT_OPERATIONAL_ANALYTICS, $capabilities);
        $this->assertContains(ManagementCapability::GENERATED_REPORTS, $capabilities);
        $this->assertTrue(ManagementCapability::canAccessCarbootOperationalAnalytics(ManagementRole::MANAGER));
    }

    public function test_super_admin_is_reserved_but_shares_organizer_analytics_boundary(): void
    {
        $this->assertTrue(ManagementCapability::mapsToFutureOrganizer(ManagementRole::SUPER_ADMIN));
        $this->assertTrue(ManagementCapability::canAccessCarbootOperationalAnalytics(ManagementRole::SUPER_ADMIN));
        $this->assertFalse(ManagementCapability::mapsToFutureOrganizer(ManagementRole::STAFF));
    }

    public function test_prepared_organizer_role_has_carboot_analytics_without_db_enum_yet(): void
    {
        $this->assertTrue(ManagementCapability::canAccessCarbootOperationalAnalytics(ManagementCapability::ROLE_ORGANIZER));
        $this->assertTrue(ManagementCapability::mapsToFutureOrganizer(ManagementCapability::ROLE_ORGANIZER));
    }

    public function test_community_vendor_has_no_management_capabilities(): void
    {
        $this->assertSame([], ManagementCapability::resolveForRole('community'));
        $this->assertFalse(ManagementCapability::canManageCmartActivities('community'));
    }
}
