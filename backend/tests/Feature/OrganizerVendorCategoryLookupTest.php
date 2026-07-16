<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorCategory;
use App\Support\Migrations\CategoryLegacyMapper;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\TestCase;

class OrganizerVendorCategoryLookupTest extends TestCase
{
    use CleansUpTestFixtures;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function createUser(string $role): User
    {
        return $this->trackUser(User::create([
            'name' => 'Phase35 ' . $role . ' ' . uniqid(),
            'email' => 'p35-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => 'none',
        ]));
    }

    public function test_vendor_categories_returns_seven_ordered_categories_with_usage_keys(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $response = $this->getJson('/api/organizer/vendor-categories');

        $response->assertOk()
            ->assertJsonCount(7, 'categories');

        $categories = $response->json('categories');
        $expectedSlugs = array_column(CategoryLegacyMapper::canonicalCategories(), 'slug');
        $expectedOrders = array_column(CategoryLegacyMapper::canonicalCategories(), 'display_order');

        $this->assertSame($expectedSlugs, array_column($categories, 'slug'));
        $this->assertSame($expectedOrders, array_column($categories, 'display_order'));

        foreach ($categories as $category) {
            $this->assertArrayHasKey('usage', $category);
            $this->assertArrayHasKey('layout_rows', $category['usage']);
            $this->assertArrayHasKey('active_layout_rows', $category['usage']);
            $this->assertArrayHasKey('bookings', $category['usage']);
            $this->assertArrayHasKey('active_allocations', $category['usage']);
            $this->assertArrayHasKey('selectable_for_new_row', $category);
        }
    }

    public function test_vendor_categories_is_read_only_lookup(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $before = VendorCategory::query()->count();

        $this->getJson('/api/organizer/vendor-categories')->assertOk();

        $this->assertSame($before, VendorCategory::query()->count());
        $this->assertSame(7, $before);
    }

    public function test_community_cannot_access_vendor_categories(): void
    {
        Sanctum::actingAs($this->createUser('community'));

        $this->getJson('/api/organizer/vendor-categories')->assertForbidden();
    }
}
