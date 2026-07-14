<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunityVendorBookingAccessTest extends TestCase
{
    private array $createdUserIds = [];
    private array $createdEventIds = [];

    private array $createdSiteIds = [];
    private array $createdDayIds = [];
    private array $createdBookingIds = [];
    private array $createdInvoiceIds = [];
    private array $createdAllocationIds = [];

    protected function tearDown(): void
    {
        if ($this->createdAllocationIds !== []) {
            BookingDayAllocation::whereIn('id', $this->createdAllocationIds)->delete();
            $this->createdAllocationIds = [];
        }

        if ($this->createdInvoiceIds !== []) {
            Invoice::whereIn('id', $this->createdInvoiceIds)->delete();
            $this->createdInvoiceIds = [];
        }

        if ($this->createdBookingIds !== []) {
            Booking::whereIn('id', $this->createdBookingIds)->delete();
            $this->createdBookingIds = [];
        }

        if ($this->createdDayIds !== []) {
            EventDay::whereIn('id', $this->createdDayIds)->delete();
            $this->createdDayIds = [];
        }

        if ($this->createdSiteIds !== []) {
            EventSite::whereIn('id', $this->createdSiteIds)->delete();
            $this->createdSiteIds = [];
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

    private function trackUser(User $user): User
    {
        $this->createdUserIds[] = $user->id;

        return $user;
    }

    public function test_community_visitor_can_list_own_bookings_without_vendor_approval(): void
    {
        $user = $this->trackUser(User::create([
            'name' => 'Applicant Vendor',
            'email' => 'applicant-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'none',
        ]));

        Sanctum::actingAs($user);

        $this->getJson('/api/vendor/bookings')
            ->assertOk()
            ->assertJson([]);
    }

    public function test_community_visitor_can_submit_booking_without_vendor_approval(): void
    {
        $space = Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            [
                'location' => 'CMart Kompleks Changlun',
                'price' => 20,
                'status' => 'Available',
            ],
        );

        $event = CarbootEvent::query()->create([
            'title' => 'Test Bookable Event',
            'description' => 'Onboarding flow test event',
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(7)->addHours(6),
            'status' => 'Open',
            'location' => 'CMart Kompleks Changlun',
            'day_generation_mode' => 'calendar_days',
        ]);
        $this->createdEventIds[] = $event->id;

        $site = EventSite::create([
            'carboot_event_id' => $event->id,
            'space_id' => $space->id,
            'label' => 'A01',
            'row_label' => 'A',
            'position_number' => 1,
            'grid_row' => 1,
            'grid_column' => 1,
            'display_order' => 1,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ]);
        $this->createdSiteIds[] = $site->id;

        $day = EventDay::create([
            'carboot_event_id' => $event->id,
            'operational_date' => $event->starts_at->toDateString(),
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
            'operational_status' => EventDay::STATUS_ACTIVE,
            'display_order' => 1,
        ]);
        $this->createdDayIds[] = $day->id;

        $user = $this->trackUser(User::create([
            'name' => 'New Applicant',
            'email' => 'new-applicant-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'none',
        ]));

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => [$site->id],
            'product_category' => 'Food & Beverages',
            'product_details' => 'Ayam Gunting',
        ])
            ->assertCreated()
            ->assertJsonPath('booking.approval_status', 'Pending_Organizer');

        $bookingId = $response->json('booking.id');
        if ($bookingId) {
            $this->createdBookingIds[] = $bookingId;
            $this->createdAllocationIds = array_merge(
                $this->createdAllocationIds,
                BookingDayAllocation::where('booking_id', $bookingId)->pluck('id')->all(),
            );
        }

        $invoiceId = $response->json('invoice.id');
        if ($invoiceId) {
            $this->createdInvoiceIds[] = $invoiceId;
        }
    }
}
