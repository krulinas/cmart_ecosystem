<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorItem;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorPrivateItemsAccessTest extends TestCase
{
    private array $createdUserIds = [];
    private array $createdItemIds = [];
    private array $createdBookingIds = [];
    private array $createdEventIds = [];

    protected function tearDown(): void
    {
        if ($this->createdItemIds !== []) {
            VendorItem::whereIn('id', $this->createdItemIds)->delete();
            $this->createdItemIds = [];
        }

        if ($this->createdBookingIds !== []) {
            Booking::whereIn('id', $this->createdBookingIds)->delete();
            $this->createdBookingIds = [];
        }

        if ($this->createdEventIds !== []) {
            CarbootEvent::whereIn('id', $this->createdEventIds)->delete();
            $this->createdEventIds = [];
        }

        if ($this->createdUserIds !== []) {
            User::whereIn('id', $this->createdUserIds)->delete();
            $this->createdUserIds = [];
        }

        parent::tearDown();
    }

    private function createCommunityVendor(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Test Vendor ' . uniqid(),
            'email' => 'vendor-private-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'none',
        ], $overrides));

        $this->createdUserIds[] = $user->id;

        return $user;
    }

    private function createBookingFor(User $user, string $approvalStatus, string $paymentStatus = 'Unpaid'): Booking
    {
        $space = Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 30.00, 'status' => 'Available'],
        );

        $event = CarbootEvent::query()->create([
            'title' => 'Private Items Test Event ' . uniqid(),
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(6),
            'status' => 'Available',
            'description' => 'Vendor private items access test',
            'max_slots' => 50,
        ]);
        $this->createdEventIds[] = $event->id;

        $booking = Booking::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Food & Beverages',
            'product_details' => 'Test product',
            'approval_status' => $approvalStatus,
        ]);
        $this->createdBookingIds[] = $booking->id;

        Invoice::create([
            'booking_id' => $booking->id,
            'amount' => 20.00,
            'payment_status' => $paymentStatus,
        ]);

        return $booking;
    }

    private function createPrivateItemPayload(string $suffix): array
    {
        return [
            'name' => "Private Prep Item {$suffix}",
            'category' => 'Pre-loved / Thrift',
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => 15.00,
            'description' => 'Private vendor preparation record.',
            'status' => 'active',
        ];
    }

    private function assertVendorCanCreateAndListItem(User $user, string $suffix): void
    {
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/vendor/items', $this->createPrivateItemPayload($suffix));
        $create->assertCreated();
        $itemId = $create->json('item.id');
        $this->assertNotNull($itemId);
        $this->createdItemIds[] = $itemId;

        $list = $this->getJson('/api/vendor/items');
        $list->assertOk();
        $names = collect($list->json('items'))->pluck('name');
        $this->assertTrue($names->contains("Private Prep Item {$suffix}"));
    }

    public function test_vendor_without_approved_booking_can_create_and_list_private_item(): void
    {
        $vendor = $this->createCommunityVendor();
        $this->assertVendorCanCreateAndListItem($vendor, 'no-approved-booking');
    }

    public function test_vendor_with_pending_booking_can_create_and_list_private_item(): void
    {
        $vendor = $this->createCommunityVendor();
        $this->createBookingFor($vendor, 'Pending_Staff');
        $this->assertVendorCanCreateAndListItem($vendor, 'pending-booking');
    }

    public function test_vendor_with_approved_unpaid_booking_can_create_and_list_private_item(): void
    {
        $vendor = $this->createCommunityVendor(['vendor_status' => 'approved']);
        $this->createBookingFor($vendor, 'Approved', 'Unpaid');
        $this->assertVendorCanCreateAndListItem($vendor, 'approved-unpaid');
    }

    public function test_vendor_with_approved_paid_booking_can_create_and_list_private_item(): void
    {
        $vendor = $this->createCommunityVendor(['vendor_status' => 'approved']);
        $this->createBookingFor($vendor, 'Approved', 'Paid');
        $this->assertVendorCanCreateAndListItem($vendor, 'approved-paid');
    }

    public function test_approved_booking_publishes_active_items_publicly(): void
    {
        $vendor = $this->createCommunityVendor(['vendor_status' => 'approved']);
        $this->createBookingFor($vendor, 'Approved', 'Paid');

        Sanctum::actingAs($vendor);

        $create = $this->postJson('/api/vendor/items', $this->createPrivateItemPayload('should-appear-publicly'));
        $create->assertCreated();
        $this->createdItemIds[] = $create->json('item.id');

        $public = $this->getJson('/api/marketplace/items');
        $public->assertOk()
            ->assertJsonPath('public_listing_enabled', true);

        $names = collect($public->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Private Prep Item should-appear-publicly'));
    }

    public function test_vendor_cannot_access_another_vendors_private_item(): void
    {
        $vendorA = $this->createCommunityVendor();
        $vendorB = $this->createCommunityVendor();

        Sanctum::actingAs($vendorA);
        $create = $this->postJson('/api/vendor/items', $this->createPrivateItemPayload('vendor-a-only'));
        $create->assertCreated();
        $itemId = $create->json('item.id');
        $this->createdItemIds[] = $itemId;

        Sanctum::actingAs($vendorB);

        $this->getJson("/api/vendor/items/{$itemId}")
            ->assertForbidden();

        $this->putJson("/api/vendor/items/{$itemId}", [
            'name' => 'Hijacked Item',
            'category' => 'Others',
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => 1,
            'status' => 'active',
        ])->assertForbidden();

        $this->deleteJson("/api/vendor/items/{$itemId}")
            ->assertForbidden();
    }
}
