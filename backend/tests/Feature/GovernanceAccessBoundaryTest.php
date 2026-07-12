<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ManagementCapability;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GovernanceAccessBoundaryTest extends TestCase
{
    private function requireUser(string $email, string $role, string $name): User
    {
        $user = User::where('email', $email)->first();
        if ($user) {
            if ($user->role !== $role) {
                $user->role = $role;
                $user->save();
            }

            return $user;
        }

        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password123'),
            'phone_number' => '0199999999',
            'role' => $role,
            'vendor_status' => 'none',
        ]);
    }

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

    public function test_legacy_admin_account_migrated_to_organizer_can_access_analytics_endpoints(): void
    {
        // admin@cmart.com was the legacy manager demo; PR1 remaps it to organizer.
        $legacyAdmin = $this->requireUser('admin@cmart.com', 'organizer', 'Carboot Organizer (Ops)');

        Sanctum::actingAs($legacyAdmin);

        $this->getJson('/api/boss/analytics/revenue')->assertOk();
        $this->getJson('/api/boss/audit-logs')->assertOk();
    }

    public function test_no_users_hold_legacy_manager_or_uum_roles_after_migration(): void
    {
        $this->assertSame(
            0,
            User::whereIn('role', ['manager', 'uum'])->count(),
            'Legacy manager/uum roles must be remapped to organizer by the PR1 role migration.',
        );
    }

    public function test_organizer_can_access_carboot_operational_analytics_endpoints(): void
    {
        $organizer = $this->requireUser('organizer@cmart.com', 'organizer', 'Carboot Organizer');

        Sanctum::actingAs($organizer);

        $this->getJson('/api/boss/analytics/revenue')->assertOk();
        $this->getJson('/api/boss/audit-logs')->assertOk();
    }

    public function test_cmart_management_is_denied_raw_analytics_but_can_access_generated_reports(): void
    {
        $venueManager = $this->requireUser('venue@cmart.com', 'cmart_management', 'CMart Venue Manager');

        Sanctum::actingAs($venueManager);

        $this->getJson('/api/boss/analytics/revenue')->assertForbidden();
        $this->getJson('/api/boss/analytics/wordcloud/feedback')->assertForbidden();
        $this->getJson('/api/boss/audit-logs')->assertForbidden();
        $this->getJson('/api/management/reports/operational-overview')->assertOk();
        $this->getJson('/api/bookings')->assertForbidden();
        $this->getJson('/api/news-posts')->assertOk();
    }

    public function test_management_me_payload_includes_governance_capabilities(): void
    {
        $organizer = $this->requireUser('admin@cmart.com', 'organizer', 'Carboot Organizer (Ops)');

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
