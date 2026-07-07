<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorItem;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplacePublicAccessTest extends TestCase
{
    private ?int $createdItemId = null;

    protected function tearDown(): void
    {
        if ($this->createdItemId !== null) {
            VendorItem::whereKey($this->createdItemId)->delete();
            $this->createdItemId = null;
        }

        parent::tearDown();
    }

    public function test_public_marketplace_index_does_not_expose_vendor_items(): void
    {
        $vendor = User::where('email', 'vendor@cmart.com')->first();
        if (!$vendor) {
            $this->markTestSkipped('Seeded vendor user (vendor@cmart.com) not found.');
        }

        $item = VendorItem::create([
            'user_id' => $vendor->id,
            'name' => 'Audit Test Vintage Lamp',
            'category' => 'Pre-loved / Thrift',
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => 25.00,
            'description' => 'Private vendor prep item — must not appear publicly.',
            'status' => 'active',
        ]);
        $this->createdItemId = $item->id;

        $response = $this->getJson('/api/marketplace/items');

        $response->assertOk()
            ->assertJsonPath('public_listing_enabled', false)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('data', []);

        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('Audit Test Vintage Lamp', $payload);
    }

    public function test_public_marketplace_show_is_not_available(): void
    {
        $vendor = User::where('email', 'vendor@cmart.com')->first();
        if (!$vendor) {
            $this->markTestSkipped('Seeded vendor user (vendor@cmart.com) not found.');
        }

        $item = VendorItem::create([
            'user_id' => $vendor->id,
            'name' => 'Audit Test Show Block',
            'category' => 'Others',
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => 10.00,
            'status' => 'active',
        ]);
        $this->createdItemId = $item->id;

        $this->getJson("/api/marketplace/items/{$item->id}")
            ->assertNotFound();
    }

    public function test_vendor_private_items_endpoint_still_returns_own_items(): void
    {
        $vendor = User::where('email', 'vendor@cmart.com')->first();
        if (!$vendor) {
            $this->markTestSkipped('Seeded vendor user (vendor@cmart.com) not found.');
        }

        $item = VendorItem::create([
            'user_id' => $vendor->id,
            'name' => 'Vendor Private Prep Item',
            'category' => 'Handicrafts & Art',
            'condition' => 'Like New',
            'pricing_type' => 'fixed',
            'price' => 40.00,
            'status' => 'active',
        ]);
        $this->createdItemId = $item->id;

        Sanctum::actingAs($vendor);

        $response = $this->getJson('/api/vendor/items');

        $response->assertOk();

        $names = collect($response->json('items'))->pluck('name');
        $this->assertTrue($names->contains('Vendor Private Prep Item'));
    }
}
