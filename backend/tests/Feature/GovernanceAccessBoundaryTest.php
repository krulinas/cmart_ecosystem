<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ManagementCapability;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GovernanceAccessBoundaryTest extends TestCase
{
    public function test_staff_cannot_access_carboot_operational_analytics_endpoints(): void
    {
        $staff = User::where('email', 'staff@cmart.com')->first();
        if (!$staff) {
            $this->markTestSkipped('Seeded staff user (staff@cmart.com) not found. Run database seeders.');
        }

        Sanctum::actingAs($staff);

        $this->getJson('/api/boss/analytics/revenue')->assertForbidden();
        $this->getJson('/api/boss/analytics/wordcloud/feedback')->assertForbidden();
        $this->getJson('/api/boss/audit-logs')->assertForbidden();
    }

    public function test_manager_can_access_carboot_operational_analytics_endpoints(): void
    {
        $manager = User::where('email', 'admin@cmart.com')->first();
        if (!$manager) {
            $this->markTestSkipped('Seeded manager user (admin@cmart.com) not found. Run database seeders.');
        }

        Sanctum::actingAs($manager);

        $this->getJson('/api/boss/analytics/revenue')->assertOk();
        $this->getJson('/api/boss/audit-logs')->assertOk();
    }

    public function test_management_me_payload_includes_governance_capabilities(): void
    {
        $manager = User::where('email', 'admin@cmart.com')->first();
        if (!$manager) {
            $this->markTestSkipped('Seeded manager user (admin@cmart.com) not found. Run database seeders.');
        }

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/me');
        $response->assertOk();
        $response->assertJsonPath('user.maps_to_future_organizer', true);
        $response->assertJsonFragment([
            ManagementCapability::CARBOOT_OPERATIONAL_ANALYTICS,
        ]);
    }

    public function test_community_vendor_can_still_access_dashboard_apis_while_pending(): void
    {
        $vendor = User::where('role', 'community')
            ->where('vendor_status', 'pending')
            ->first();

        if (!$vendor) {
            $vendor = User::where('role', 'community')->first();
            if (!$vendor) {
                $this->markTestSkipped('No community vendor user found in database.');
            }

            $vendor->vendor_status = 'pending';
            $vendor->save();
        }

        Sanctum::actingAs($vendor);

        $this->getJson('/api/vendor/bookings')->assertOk();
        $this->getJson('/api/vendor/profile')->assertOk();
        $this->getJson('/api/staff/operations-summary')->assertForbidden();
    }
}
