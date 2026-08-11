<?php

namespace Tests\Concerns;

use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorCategory;

/**
 * Shared Phase 3.5 layout fixture builders for feature tests.
 */
trait Phase35EventLayoutFixtures
{
    protected const PRODUCT_DETAILS = 'Phase 3.5 layout feature test fixture with sufficient product detail length for validation.';

    private function createEvent(array $overrides = []): CarbootEvent
    {
        $starts = now()->addDays(10)->setTime(8, 0);

        return $this->trackEvent(CarbootEvent::create(array_merge([
            'title' => 'Phase35 Layout Event ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(17, 0),
            'status' => 'Available',
            'description' => 'Phase 3.5 layout test event',
            'max_slots' => 50,
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
            'site_price' => CarbootEvent::DEFAULT_SITE_PRICE,
            'vendor_site_open_limit' => 64,
        ], $overrides)));
    }

    private function createActiveDay(CarbootEvent $event, array $overrides = []): EventDay
    {
        $day = EventDay::create(array_merge([
            'carboot_event_id' => $event->id,
            'operational_date' => $event->starts_at->toDateString(),
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
            'operational_status' => EventDay::STATUS_ACTIVE,
            'display_order' => 1,
        ], $overrides));
        $this->createdDayIds[] = $day->id;

        return $day;
    }

    private function standardSpace(): Space
    {
        return Space::defaultPhysical();
    }

    private function foodCategory(): VendorCategory
    {
        return VendorCategory::query()->where('slug', 'food-beverages')->firstOrFail();
    }

    private function thriftCategory(): VendorCategory
    {
        return VendorCategory::query()->where('slug', 'pre-loved-thrift')->firstOrFail();
    }

    private function shortLabel(string $prefix = 'R'): string
    {
        return $prefix . substr(uniqid(), -6);
    }

    /**
     * Caller must Sanctum::actingAs() before invoking.
     *
     * @return array{id: int, slug: string}
     */
    private function createLayoutRowViaApi(CarbootEvent $event, array $payload): array
    {
        $response = $this->postJson(
            "/api/organizer/events/{$event->id}/layout/rows",
            array_merge([
                'label' => $this->shortLabel('Row'),
                'vendor_category_id' => $this->foodCategory()->id,
                'is_active' => true,
                'is_public' => true,
            ], $payload),
        );

        $response->assertCreated();

        return [
            'id' => (int) $response->json('row.id'),
            'slug' => (string) $response->json('row.slug'),
        ];
    }

    /**
     * Caller must Sanctum::actingAs() before invoking.
     */
    private function createLayoutSiteViaApi(CarbootEvent $event, int $rowId, array $payload): int
    {
        $response = $this->postJson(
            "/api/organizer/events/{$event->id}/layout/rows/{$rowId}/sites",
            array_merge([
                'label' => 'S' . uniqid(),
                'space_id' => $this->standardSpace()->id,
                'position_number' => 1,
                'grid_row' => 1,
                'grid_column' => 1,
                'operational_status' => EventSite::STATUS_DISABLED,
            ], $payload),
        );

        $response->assertCreated();
        $siteId = (int) $response->json('site.id');
        $this->createdSiteIds[] = $siteId;

        return $siteId;
    }

    private function createBookingForSite(CarbootEvent $event, EventSite $site, EventDay $day, User $vendor): Booking
    {
        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $site->space_id,
            'carboot_event_id' => $event->id,
            'booking_date' => $day->operational_date->toDateString(),
            'product_category' => 'Food & Beverages',
            'product_details' => self::PRODUCT_DETAILS,
            'approval_status' => 'Pending_Organizer',
            'vendor_category_id' => $this->foodCategory()->id,
        ]);
        $this->createdBookingIds[] = $booking->id;

        return $booking;
    }

    private function seedReleasedAllocation(
        CarbootEvent $event,
        EventSite $site,
        EventDay $day,
        ?User $vendor = null,
    ): BookingDayAllocation {
        $vendor ??= $this->trackUser(User::create([
            'name' => 'Phase35 Vendor ' . uniqid(),
            'email' => 'p35-vendor-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'approved',
        ]));

        $booking = $this->createBookingForSite($event, $site, $day, $vendor);

        $allocation = BookingDayAllocation::create([
            'booking_id' => $booking->id,
            'event_day_id' => $day->id,
            'event_site_id' => $site->id,
            'allocation_status' => BookingDayAllocation::STATUS_RELEASED,
            'reserved_at' => now()->subHour(),
            'released_at' => now(),
            'active_lock' => null,
        ]);
        $this->createdAllocationIds[] = $allocation->id;

        return $allocation;
    }

    private function seedReservedAllocation(
        CarbootEvent $event,
        EventSite $site,
        EventDay $day,
        ?User $vendor = null,
    ): BookingDayAllocation {
        $vendor ??= $this->trackUser(User::create([
            'name' => 'Phase35 Vendor ' . uniqid(),
            'email' => 'p35-vendor-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'approved',
        ]));

        $booking = $this->createBookingForSite($event, $site, $day, $vendor);

        $allocation = BookingDayAllocation::create([
            'booking_id' => $booking->id,
            'event_day_id' => $day->id,
            'event_site_id' => $site->id,
            'allocation_status' => BookingDayAllocation::STATUS_RESERVED,
            'reserved_at' => now(),
            'active_lock' => 1,
        ]);
        $this->createdAllocationIds[] = $allocation->id;

        return $allocation;
    }

    private function createRowRecord(CarbootEvent $event, array $overrides = []): EventLayoutRow
    {
        return EventLayoutRow::create(array_merge([
            'carboot_event_id' => $event->id,
            'vendor_category_id' => $this->foodCategory()->id,
            'label' => $this->shortLabel('Dir'),
            'slug' => 'direct-row-' . uniqid(),
            'display_order' => 1,
            'is_active' => true,
            'is_public' => true,
        ], $overrides));
    }

    private function createSiteRecord(CarbootEvent $event, EventLayoutRow $row, array $overrides = []): EventSite
    {
        $site = EventSite::create(array_merge([
            'carboot_event_id' => $event->id,
            'event_layout_row_id' => $row->id,
            'space_id' => $this->standardSpace()->id,
            'label' => 'D' . uniqid(),
            'row_label' => $row->label,
            'position_number' => 1,
            'grid_row' => 1,
            'grid_column' => 1,
            'display_order' => 1,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ], $overrides));
        $this->createdSiteIds[] = $site->id;

        return $site;
    }
}
