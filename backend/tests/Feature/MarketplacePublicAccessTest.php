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

class MarketplacePublicAccessTest extends TestCase
{
    private array $createdUserIds = [];
    private array $createdBookingIds = [];
    private array $createdEventIds = [];
    private array $createdItemIds = [];

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

    private function createVendor(): User
    {
        $vendor = User::create([
            'name' => 'Marketplace Vendor ' . uniqid(),
            'email' => 'marketplace-vendor-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'approved',
        ]);

        $this->createdUserIds[] = $vendor->id;

        return $vendor;
    }

    private function createUpcomingEvent(): CarbootEvent
    {
        $event = CarbootEvent::query()->create([
            'title' => 'Public Preview Event ' . uniqid(),
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(6),
            'status' => 'Available',
            'description' => 'Marketplace preview test event',
            'max_slots' => 50,
        ]);

        $this->createdEventIds[] = $event->id;

        return $event;
    }

    private function createBooking(User $vendor, CarbootEvent $event, string $approvalStatus): Booking
    {
        $space = Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 20.00, 'status' => 'Available'],
        );

        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Pre-loved / Thrift',
            'product_details' => 'Marketplace preview test booking',
            'approval_status' => $approvalStatus,
        ]);
        $this->createdBookingIds[] = $booking->id;

        Invoice::create([
            'booking_id' => $booking->id,
            'amount' => 20.00,
            'payment_status' => 'Unpaid',
        ]);

        return $booking;
    }

    private function createItem(User $vendor, string $name, string $status = 'active'): VendorItem
    {
        $item = VendorItem::create([
            'user_id' => $vendor->id,
            'name' => $name,
            'category' => 'Pre-loved / Thrift',
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => 25.00,
            'description' => 'Public preview test item',
            'status' => $status,
        ]);
        $this->createdItemIds[] = $item->id;

        return $item;
    }

    public function test_approved_booking_with_active_item_appears_on_public_marketplace(): void
    {
        $vendor = $this->createVendor();
        $event = $this->createUpcomingEvent();
        $this->createBooking($vendor, $event, 'Approved');
        $item = $this->createItem($vendor, 'liverpool home 25/26');

        $response = $this->getJson('/api/marketplace/items');

        $response->assertOk()
            ->assertJsonPath('public_listing_enabled', true);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('liverpool home 25/26'));

        $this->getJson("/api/marketplace/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('item.name', 'liverpool home 25/26')
            ->assertJsonPath('item.purchase_mode', 'in-person only');
    }

    public function test_pending_booking_with_active_item_does_not_appear(): void
    {
        $vendor = $this->createVendor();
        $event = $this->createUpcomingEvent();
        $this->createBooking($vendor, $event, 'Pending_Organizer');
        $item = $this->createItem($vendor, 'Pending Vendor Item');

        $this->getJson('/api/marketplace/items')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Pending Vendor Item']);

        $this->getJson("/api/marketplace/items/{$item->id}")
            ->assertNotFound();
    }

    public function test_rejected_booking_with_active_item_does_not_appear(): void
    {
        $vendor = $this->createVendor();
        $event = $this->createUpcomingEvent();
        $this->createBooking($vendor, $event, 'Rejected');
        $item = $this->createItem($vendor, 'Rejected Vendor Item');

        $this->getJson('/api/marketplace/items')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Rejected Vendor Item']);

        $this->getJson("/api/marketplace/items/{$item->id}")
            ->assertNotFound();
    }

    public function test_withdrawn_booking_with_active_item_does_not_appear(): void
    {
        $vendor = $this->createVendor();
        $event = $this->createUpcomingEvent();
        $this->createBooking($vendor, $event, 'Withdrawn');
        $item = $this->createItem($vendor, 'Withdrawn Vendor Item');

        $this->getJson('/api/marketplace/items')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Withdrawn Vendor Item']);

        $this->getJson("/api/marketplace/items/{$item->id}")
            ->assertNotFound();
    }

    public function test_inactive_item_does_not_appear_even_when_booking_is_approved(): void
    {
        $vendor = $this->createVendor();
        $event = $this->createUpcomingEvent();
        $this->createBooking($vendor, $event, 'Approved');
        $item = $this->createItem($vendor, 'Inactive Draft Item', 'inactive');

        $this->getJson('/api/marketplace/items')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Inactive Draft Item']);

        $this->getJson("/api/marketplace/items/{$item->id}")
            ->assertNotFound();
    }

    public function test_paid_booking_is_not_required_for_public_preview(): void
    {
        $vendor = $this->createVendor();
        $event = $this->createUpcomingEvent();
        $booking = $this->createBooking($vendor, $event, 'Approved');
        $booking->invoice->update(['payment_status' => 'Unpaid']);
        $this->createItem($vendor, 'Unpaid Approved Preview Item');

        $names = collect($this->getJson('/api/marketplace/items')->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Unpaid Approved Preview Item'));
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
        $this->createdItemIds[] = $item->id;

        Sanctum::actingAs($vendor);

        $response = $this->getJson('/api/vendor/items');

        $response->assertOk();

        $names = collect($response->json('items'))->pluck('name');
        $this->assertTrue($names->contains('Vendor Private Prep Item'));
    }
}
