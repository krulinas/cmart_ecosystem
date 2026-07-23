<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ManagementCapability;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\TracksProvisionedUsers;
use Tests\TestCase;

class GovernanceAccessBoundaryTest extends TestCase
{
    use TracksProvisionedUsers;

    protected function tearDown(): void
    {
        $this->cleanupProvisionedUsers();
        parent::tearDown();
    }

    public function test_cmart_management_cannot_access_carboot_operational_analytics_endpoints(): void
    {
        $venue = $this->provisionUser(
            'governance-management@example.test',
            'cmart_management',
            'Governance CMart Management',
        );

        Sanctum::actingAs($venue);

        $this->getJson('/api/boss/analytics/revenue')->assertForbidden();
        $this->getJson('/api/boss/analytics/wordcloud/feedback')->assertForbidden();
        $this->getJson('/api/boss/audit-logs')->assertForbidden();
    }

    public function test_legacy_admin_account_migrated_to_organizer_can_access_analytics_endpoints(): void
    {
        // admin@cmart.com was the legacy manager demo; PR1 remaps it to organizer.
        $legacyAdmin = $this->provisionUser('admin@cmart.com', 'organizer', 'Carboot Organizer (Ops)');

        Sanctum::actingAs($legacyAdmin);

        $this->getJson('/api/boss/analytics/revenue')->assertOk();
        $this->getJson('/api/boss/audit-logs')->assertOk();
    }

    public function test_no_users_hold_legacy_staff_manager_or_uum_roles_after_pr2(): void
    {
        $this->assertSame(
            0,
            User::whereIn('role', ['staff', 'manager', 'uum'])->count(),
            'Legacy staff/manager/uum roles must be remapped by PR1/PR2 role migrations.',
        );
    }

    public function test_organizer_can_access_carboot_operational_analytics_endpoints(): void
    {
        $organizer = $this->provisionUser('organizer@cmart.com', 'organizer', 'Carboot Organizer');

        Sanctum::actingAs($organizer);

        $this->getJson('/api/boss/analytics/revenue')->assertOk();
        $this->getJson('/api/boss/audit-logs')->assertOk();
    }

    public function test_cmart_management_is_denied_raw_analytics_but_can_access_generated_reports(): void
    {
        $venueManager = $this->provisionUser('venue@cmart.com', 'cmart_management', 'CMart Venue Manager');

        Sanctum::actingAs($venueManager);

        $this->getJson('/api/boss/analytics/revenue')->assertForbidden();
        $this->getJson('/api/boss/analytics/wordcloud/feedback')->assertForbidden();
        $this->getJson('/api/boss/audit-logs')->assertForbidden();
        $this->getJson('/api/management/reports/operational-overview')->assertForbidden();
        $this->getJson('/api/bookings')->assertForbidden();
        $this->getJson('/api/news-posts')->assertOk();
    }

    public function test_management_me_payload_includes_governance_capabilities(): void
    {
        $organizer = $this->provisionUser('admin@cmart.com', 'organizer', 'Carboot Organizer (Ops)');

        Sanctum::actingAs($organizer);

        $response = $this->getJson('/api/auth/me');
        $response->assertOk();
        $response->assertJsonPath('user.maps_to_future_organizer', true);
        $response->assertJsonFragment([
            ManagementCapability::CARBOOT_OPERATIONAL_ANALYTICS,
        ]);
    }

    public function test_community_vendor_can_still_access_dashboard_apis_while_pending(): void
    {
        $vendor = $this->provisionUser(
            'governance-pending-vendor@example.test',
            'community',
            'Governance Pending Vendor',
        );
        $vendor->update(['vendor_status' => 'pending']);

        Sanctum::actingAs($vendor);

        $this->getJson('/api/vendor/bookings')->assertOk();
        $this->getJson('/api/vendor/profile')->assertOk();
        $this->getJson('/api/staff/operations-summary')->assertForbidden();
    }
}
