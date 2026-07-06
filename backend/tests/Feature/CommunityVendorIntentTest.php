<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorBusinessProfile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunityVendorIntentTest extends TestCase
{
    private array $createdUserIds = [];

    protected function tearDown(): void
    {
        if ($this->createdUserIds !== []) {
            VendorBusinessProfile::whereIn('user_id', $this->createdUserIds)->delete();
            User::whereIn('id', $this->createdUserIds)->delete();
            $this->createdUserIds = [];
        }

        parent::tearDown();
    }

    private function trackUser(User $user): User
    {
        $this->createdUserIds[] = $user->id;

        return $user;
    }

    public function test_new_community_registration_returns_visitor_mode(): void
    {
        $email = 'visitor-intent-' . uniqid() . '@example.com';

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Community Visitor',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'community')
            ->assertJsonPath('user.vendor_status', 'none')
            ->assertJsonPath('user.is_vendor_user', false)
            ->assertJsonPath('user.community_mode', 'visitor')
            ->assertJsonPath('user.vendor_signals', []);

        $userId = $response->json('user.id');
        if ($userId) {
            $this->createdUserIds[] = $userId;
        }
    }

    public function test_seeded_vendor_user_returns_vendor_mode_with_signals(): void
    {
        $vendor = User::where('email', 'vendor@cmart.com')->first();
        if (!$vendor) {
            $this->markTestSkipped('Seeded vendor user not found.');
        }

        Sanctum::actingAs($vendor);

        $response = $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.is_vendor_user', true)
            ->assertJsonPath('user.community_mode', 'vendor');

        $this->assertNotEmpty($response->json('user.vendor_signals'));
    }

    public function test_community_user_without_vendor_activity_is_visitor(): void
    {
        $user = $this->trackUser(User::create([
            'name' => 'Plain Visitor',
            'email' => 'plain-visitor-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'none',
        ]));

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.is_vendor_user', false)
            ->assertJsonPath('user.community_mode', 'visitor')
            ->assertJsonPath('user.vendor_signals', []);
    }

    public function test_community_user_with_business_profile_is_vendor(): void
    {
        $user = $this->trackUser(User::create([
            'name' => 'Profile Vendor',
            'email' => 'profile-vendor-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'none',
        ]));

        VendorBusinessProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Weekend Trader',
            'business_phone' => '0123456789',
            'business_category' => 'Food',
            'description' => 'Test profile',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.is_vendor_user', true)
            ->assertJsonPath('user.community_mode', 'vendor');

        $this->assertContains('business_profile', $response->json('user.vendor_signals'));
    }

    public function test_suspended_vendor_is_still_vendor_mode(): void
    {
        $user = $this->trackUser(User::create([
            'name' => 'Suspended Vendor',
            'email' => 'suspended-vendor-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'suspended',
        ]));

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.vendor_status', 'suspended')
            ->assertJsonPath('user.is_vendor_user', true)
            ->assertJsonPath('user.community_mode', 'vendor');

        $this->assertContains('vendor_status', $response->json('user.vendor_signals'));
    }

    public function test_become_vendor_cta_does_not_change_visitor_status_on_register(): void
    {
        $email = 'become-vendor-cta-' . uniqid() . '@example.com';

        $response = $this->postJson('/api/auth/register', [
            'name' => 'CTA Visitor',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.vendor_status', 'none')
            ->assertJsonPath('user.is_vendor_user', false)
            ->assertJsonPath('user.community_mode', 'visitor');

        $userId = $response->json('user.id');
        if ($userId) {
            $this->createdUserIds[] = $userId;
        }
    }

    public function test_management_user_payload_has_no_community_mode(): void
    {
        $staff = User::where('email', 'staff@cmart.com')->first();
        if (!$staff) {
            $this->markTestSkipped('Seeded staff user not found.');
        }

        Sanctum::actingAs($staff);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.is_vendor_user', false)
            ->assertJsonPath('user.community_mode', null);
    }
}
