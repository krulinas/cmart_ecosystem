<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\TracksProvisionedUsers;
use Tests\TestCase;

class WebAnalyticsSecurityTest extends TestCase
{
    use TracksProvisionedUsers;

    protected function tearDown(): void
    {
        $this->cleanupProvisionedUsers();
        parent::tearDown();
    }

    public function test_unauthenticated_users_cannot_access_admin_analytics_page(): void
    {
        $this->get('/admin/analytics')->assertUnauthorized();
    }

    public function test_unauthenticated_users_cannot_access_analytics_proxy_endpoints(): void
    {
        $this->getJson('/api/proxy/analytics/summary')->assertUnauthorized();
        $this->getJson('/api/proxy/analytics/feedback')->assertUnauthorized();
        $this->getJson('/api/proxy/analytics/products')->assertUnauthorized();
    }

    public function test_community_users_cannot_access_analytics_proxy_endpoints(): void
    {
        $vendor = User::where('role', 'community')->first();
        if (!$vendor) {
            $this->markTestSkipped('No community vendor user found in database.');
        }

        Sanctum::actingAs($vendor);

        $this->getJson('/api/proxy/analytics/summary')->assertForbidden();
        $this->get('/admin/analytics')->assertForbidden();
    }

    public function test_cmart_management_demo_cannot_access_raw_analytics_proxy_endpoints(): void
    {
        $venue = User::where('email', 'staff@cmart.com')->first();
        if (!$venue) {
            $this->markTestSkipped('Seeded cmart_management demo (staff@cmart.com) not found. Run database seeders.');
        }

        Sanctum::actingAs($venue);

        $this->getJson('/api/proxy/analytics/summary')->assertForbidden();
        $this->get('/admin/analytics')->assertForbidden();
    }

    public function test_cmart_management_users_cannot_access_raw_analytics_proxy_endpoints(): void
    {
        $venueManager = $this->provisionUser('venue@cmart.com', 'cmart_management', 'CMart Venue Manager');

        Sanctum::actingAs($venueManager);

        $this->getJson('/api/proxy/analytics/summary')->assertForbidden();
        $this->get('/admin/analytics')->assertForbidden();
    }

    public function test_legacy_admin_account_migrated_to_organizer_can_access_analytics_page(): void
    {
        // admin@cmart.com was the legacy manager demo; PR1 remaps it to organizer.
        $legacyAdmin = $this->provisionUser('admin@cmart.com', 'organizer', 'Carboot Organizer (Ops)');

        Sanctum::actingAs($legacyAdmin);

        $this->get('/admin/analytics')->assertOk();
    }

    public function test_organizer_can_access_analytics_proxy_endpoints(): void
    {
        $organizer = $this->provisionUser('organizer@cmart.com', 'organizer', 'Carboot Organizer');

        Sanctum::actingAs($organizer);

        $this->get('/admin/analytics')->assertOk();
    }
}
