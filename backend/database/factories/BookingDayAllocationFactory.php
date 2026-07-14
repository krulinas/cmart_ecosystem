<?php

namespace Database\Factories;

use App\Models\BookingDayAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingDayAllocation>
 *
 * Callers must supply booking_id, event_day_id, and event_site_id
 * (Booking/EventDay/EventSite factories are not defined in this repo).
 */
class BookingDayAllocationFactory extends Factory
{
    protected $model = BookingDayAllocation::class;

    public function definition(): array
    {
        $status = BookingDayAllocation::STATUS_RESERVED;

        return [
            'allocation_status' => $status,
            'reserved_at' => now(),
            'confirmed_at' => null,
            'released_at' => null,
            'released_by' => null,
            'release_reason' => null,
            'active_lock' => BookingDayAllocation::activeLockForStatus($status),
        ];
    }

    public function reserved(): static
    {
        return $this->state(fn () => [
            'allocation_status' => BookingDayAllocation::STATUS_RESERVED,
            'reserved_at' => now(),
            'confirmed_at' => null,
            'released_at' => null,
            'released_by' => null,
            'release_reason' => null,
            'active_lock' => 1,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'allocation_status' => BookingDayAllocation::STATUS_CONFIRMED,
            'reserved_at' => now()->subHour(),
            'confirmed_at' => now(),
            'released_at' => null,
            'released_by' => null,
            'release_reason' => null,
            'active_lock' => 1,
        ]);
    }

    public function released(?User $actor = null, ?string $reason = 'released'): static
    {
        return $this->state(fn () => [
            'allocation_status' => BookingDayAllocation::STATUS_RELEASED,
            'reserved_at' => now()->subHours(2),
            'confirmed_at' => null,
            'released_at' => now(),
            'released_by' => $actor?->id,
            'release_reason' => $reason,
            'active_lock' => null,
        ]);
    }

    public function cancelled(?User $actor = null, ?string $reason = 'cancelled'): static
    {
        return $this->state(fn () => [
            'allocation_status' => BookingDayAllocation::STATUS_CANCELLED,
            'reserved_at' => now()->subHours(2),
            'confirmed_at' => null,
            'released_at' => now(),
            'released_by' => $actor?->id,
            'release_reason' => $reason,
            'active_lock' => null,
        ]);
    }
}
