<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorCategory;
use App\Models\VendorItem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase41VendorItemFoundationTest extends TestCase
{
    /** @var list<int> */
    private array $createdUserIds = [];

    /** @var list<int> */
    private array $createdItemIds = [];

    /** @var list<int> */
    private array $createdCategoryIds = [];

    /** @var list<int> */
    private array $createdBookingIds = [];

    /** @var list<int> */
    private array $createdEventIds = [];

    protected function tearDown(): void
    {
        VendorItem::query()->whereIn('id', $this->createdItemIds)->get()->each->delete();
        Booking::query()->whereIn('id', $this->createdBookingIds)->delete();
        CarbootEvent::query()->whereIn('id', $this->createdEventIds)->get()->each->delete();
        User::query()->whereIn('id', $this->createdUserIds)->delete();
        VendorCategory::query()->whereIn('id', $this->createdCategoryIds)->delete();

        parent::tearDown();
    }

    public function test_create_and_update_store_canonical_category_id_and_derived_label(): void
    {
        $vendor = $this->createCommunityUser();
        $first = $this->createCategory('First');
        $second = $this->createCategory('Second');
        Sanctum::actingAs($vendor);

        $create = $this->postJson('/api/vendor/items', $this->itemPayload([
            'vendor_category_id' => $first->id,
        ]));

        $create->assertCreated()
            ->assertJsonPath('item.vendor_category_id', $first->id)
            ->assertJsonPath('item.category', $first->label)
            ->assertJsonPath('item.is_reservable', false)
            ->assertJsonPath('item.has_active_reservation', false);

        $itemId = (int) $create->json('item.id');
        $this->createdItemIds[] = $itemId;

        $this->assertDatabaseHas('vendor_items', [
            'id' => $itemId,
            'vendor_category_id' => $first->id,
            'category' => $first->label,
        ]);

        $this->putJson("/api/vendor/items/{$itemId}", [
            'vendor_category_id' => $second->id,
        ])->assertOk()
            ->assertJsonPath('item.vendor_category_id', $second->id)
            ->assertJsonPath('item.category', $second->label);

        $this->assertDatabaseHas('vendor_items', [
            'id' => $itemId,
            'vendor_category_id' => $second->id,
            'category' => $second->label,
        ]);
    }

    public function test_conflicting_or_invalid_categories_are_rejected_without_fallback(): void
    {
        $vendor = $this->createCommunityUser();
        $category = $this->createCategory('Selectable');
        $other = $this->createCategory('Other');
        $inactive = $this->createCategory('Inactive', ['is_active' => false]);
        $private = $this->createCategory('Private', ['is_public' => false]);
        Sanctum::actingAs($vendor);

        $this->postJson('/api/vendor/items', $this->itemPayload([
            'vendor_category_id' => $category->id,
            'category' => $other->label,
        ]))->assertUnprocessable()
            ->assertJsonPath('error', 'CATEGORY_FIELDS_MISMATCH');

        $this->postJson('/api/vendor/items', $this->itemPayload([
            'vendor_category_id' => 999999999,
        ]))->assertUnprocessable()
            ->assertJsonPath('error', 'CATEGORY_NOT_FOUND');

        $this->postJson('/api/vendor/items', $this->itemPayload([
            'vendor_category_id' => $inactive->id,
        ]))->assertUnprocessable()
            ->assertJsonPath('error', 'CATEGORY_INACTIVE');

        $this->postJson('/api/vendor/items', $this->itemPayload([
            'vendor_category_id' => $private->id,
        ]))->assertUnprocessable()
            ->assertJsonPath('error', 'CATEGORY_NOT_PUBLIC');

        $this->postJson('/api/vendor/items', $this->itemPayload([
            'category' => 'Unknown category that must not become Others',
        ]))->assertUnprocessable()
            ->assertJsonPath('error', 'UNKNOWN_LEGACY_CATEGORY');

        $this->assertSame(0, VendorItem::query()->where('user_id', $vendor->id)->count());
    }

    public function test_known_legacy_label_is_only_a_strict_compatibility_adapter(): void
    {
        $vendor = $this->createCommunityUser();
        $category = $this->createCategory('Compatibility');
        Sanctum::actingAs($vendor);

        $response = $this->postJson('/api/vendor/items', $this->itemPayload([
            'category' => $category->label,
        ]));

        $response->assertCreated()
            ->assertJsonPath('item.vendor_category_id', $category->id)
            ->assertJsonPath('item.category', $category->label);

        $this->createdItemIds[] = (int) $response->json('item.id');
    }

    public function test_existing_legacy_item_remains_readable(): void
    {
        $vendor = $this->createCommunityUser();
        $item = $this->createItem($vendor, null, 'Historical Legacy Category');
        Sanctum::actingAs($vendor);

        $this->getJson("/api/vendor/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('item.vendor_category_id', null)
            ->assertJsonPath('item.category', 'Historical Legacy Category');
    }

    public function test_public_presenter_adds_disabled_boolean_readiness_without_private_data(): void
    {
        $vendor = $this->createCommunityUser();
        $category = $this->createCategory('Public');
        $item = $this->createItem($vendor, $category);
        $this->createApprovedBooking($vendor);

        $response = $this->getJson("/api/marketplace/items/{$item->id}");

        $response->assertOk()
            ->assertJsonPath('item.is_reservable', false)
            ->assertJsonPath('item.has_active_reservation', false)
            ->assertJsonMissingPath('item.reservation_id')
            ->assertJsonMissingPath('item.reserving_user')
            ->assertJsonMissingPath('item.charge_note');

        $this->assertIsBool($response->json('item.is_reservable'));
        $this->assertIsBool($response->json('item.has_active_reservation'));
    }

    public function test_owner_delete_remains_centralized_and_cleans_gallery_files(): void
    {
        Storage::fake('public');

        $owner = $this->createCommunityUser();
        $other = $this->createCommunityUser();
        $category = $this->createCategory('Delete');
        $item = $this->createItem($owner, $category);

        Storage::disk('public')->put('reuse-items/phase41-primary.jpg', 'primary');
        Storage::disk('public')->put('reuse-items/phase41-secondary.jpg', 'secondary');

        $primary = $item->images()->create([
            'image_path' => 'reuse-items/phase41-primary.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $secondary = $item->images()->create([
            'image_path' => 'reuse-items/phase41-secondary.jpg',
            'sort_order' => 1,
            'is_primary' => false,
        ]);
        $item->updateQuietly(['image_path' => $primary->image_path]);

        Sanctum::actingAs($other);
        $this->deleteJson("/api/vendor/items/{$item->id}")->assertForbidden();
        $this->assertDatabaseHas('vendor_items', ['id' => $item->id]);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/vendor/items/{$item->id}")->assertOk();

        Storage::disk('public')->assertMissing('reuse-items/phase41-primary.jpg');
        Storage::disk('public')->assertMissing('reuse-items/phase41-secondary.jpg');
        $this->assertDatabaseMissing('vendor_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('reuse_item_images', ['id' => $primary->id]);
        $this->assertDatabaseMissing('reuse_item_images', ['id' => $secondary->id]);
        $this->assertTrue(Schema::hasTable('item_reservations'));
        $this->assertDatabaseMissing('item_reservations', ['vendor_item_id' => $item->id]);

        $this->createdItemIds = array_values(array_diff($this->createdItemIds, [$item->id]));
    }

    private function createCommunityUser(): User
    {
        $user = User::factory()->community()->create();
        $this->createdUserIds[] = $user->id;

        return $user;
    }

    private function createCategory(string $suffix, array $overrides = []): VendorCategory
    {
        $key = strtolower($suffix).'-'.uniqid();
        $category = VendorCategory::factory()->create(array_merge([
            'slug' => 'phase41-'.$key,
            'label' => 'Phase 4.1 '.$suffix.' '.uniqid(),
        ], $overrides));
        $this->createdCategoryIds[] = $category->id;

        return $category;
    }

    private function createItem(
        User $vendor,
        ?VendorCategory $category,
        ?string $legacyLabel = null,
    ): VendorItem {
        $item = VendorItem::query()->create([
            'user_id' => $vendor->id,
            'name' => 'Phase 4.1 Item '.uniqid(),
            'category' => $legacyLabel ?? $category?->label ?? 'Legacy',
            'vendor_category_id' => $category?->id,
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => '15.00',
            'description' => 'Phase 4.1 fixture',
            'status' => 'active',
        ]);
        $this->createdItemIds[] = $item->id;

        return $item;
    }

    private function createApprovedBooking(User $vendor): Booking
    {
        $event = CarbootEvent::query()->create([
            'title' => 'Phase 4.1 Marketplace Event '.uniqid(),
            'starts_at' => now()->addDays(15),
            'ends_at' => now()->addDays(15)->addHours(8),
            'status' => 'Available',
            'max_slots' => 20,
        ]);
        $this->createdEventIds[] = $event->id;

        $space = Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 20, 'status' => 'Available'],
        );

        $booking = Booking::query()->create([
            'user_id' => $vendor->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Phase 4.1',
            'product_details' => 'Phase 4.1 fixture',
            'approval_status' => 'Approved',
        ]);
        $this->createdBookingIds[] = $booking->id;

        return $booking;
    }

    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Phase 4.1 API Item '.uniqid(),
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => '10.00',
            'description' => 'Phase 4.1 API fixture',
            'status' => 'active',
        ], $overrides);
    }
}
