<?php

namespace Tests\Feature;

use App\Models\CarbootEvent;
use App\Models\Space;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunityVendorBookingAccessTest extends TestCase
{
    private array $createdUserIds = [];
    private array $createdEventIds = [];

    protected function tearDown(): void
    {
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
        Space::query()->firstOrCreate(
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
        ]);
        $this->createdEventIds[] = $event->id;

        $user = $this->trackUser(User::create([
            'name' => 'New Applicant',
            'email' => 'new-applicant-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'none',
        ]));

        Sanctum::actingAs($user);

        $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'tapak_quantity' => 1,
            'total_price' => 20,
            'product_category' => 'Food & Beverages',
            'product_details' => 'Ayam Gunting',
        ])
            ->assertCreated()
            ->assertJsonPath('booking.approval_status', 'Pending_Staff');
    }
}
