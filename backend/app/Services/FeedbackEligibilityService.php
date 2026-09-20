<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Event-scoped feedback eligibility and event option lists.
 *
 * Event statuses in this codebase are only: Available, Almost Full, Closed.
 * There is no separate "Cancelled" event status. Closed is the normal
 * post-event / no-longer-bookable lifecycle status (and is also used when
 * staff close an event early). Deleted events are hard-removed from the DB.
 *
 * Feedback therefore allows Available, Almost Full, and Closed.
 * Vendor eligibility uses Approved bookings only — no start/end date checks.
 */
class FeedbackEligibilityService
{
    public const VENDOR_ELIGIBLE_BOOKING_STATUS = 'Approved';

    /** Carboot event statuses that may receive feedback (all defined statuses). */
    public const FEEDBACK_ALLOWED_EVENT_STATUSES = ['Available', 'Almost Full', 'Closed'];

    public const VENDOR_INELIGIBLE_MESSAGE =
        'You can review an event as a vendor after receiving an approved booking for that event.';

    public function userHasEligibleVendorBooking(User $user, int $eventId): bool
    {
        if (! Schema::hasTable('bookings')) {
            return false;
        }

        return Booking::query()
            ->where('user_id', $user->id)
            ->where('carboot_event_id', $eventId)
            ->where('approval_status', self::VENDOR_ELIGIBLE_BOOKING_STATUS)
            ->exists();
    }

    public function userHasAnyEligibleVendorBooking(User $user): bool
    {
        if (! Schema::hasTable('bookings')) {
            return false;
        }

        return Booking::query()
            ->where('user_id', $user->id)
            ->where('approval_status', self::VENDOR_ELIGIBLE_BOOKING_STATUS)
            ->whereNotNull('carboot_event_id')
            ->exists();
    }

    /**
     * Distinct events linked to the user's Approved bookings
     * (upcoming / ongoing / completed / closed). Closed is allowed.
     *
     * @return list<array{id: int, title: string, starts_at: ?string, ends_at: ?string, date_label: string, status: string}>
     */
    public function eligibleVendorEventsForUser(User $user): array
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasTable('carboot_events')) {
            return [];
        }

        $eventIds = Booking::query()
            ->where('user_id', $user->id)
            ->where('approval_status', self::VENDOR_ELIGIBLE_BOOKING_STATUS)
            ->whereNotNull('carboot_event_id')
            ->distinct()
            ->pluck('carboot_event_id');

        if ($eventIds->isEmpty()) {
            return [];
        }

        return $this->presentEvents(
            CarbootEvent::query()
                ->whereIn('id', $eventIds)
                ->orderByDesc('starts_at')
                ->get(['id', 'title', 'starts_at', 'ends_at', 'status']),
        );
    }

    /**
     * Carboot events for non-vendor feedback selection.
     * Includes Available, Almost Full, and Closed. Deleted rows are absent.
     *
     * @return list<array{id: int, title: string, starts_at: ?string, ends_at: ?string, date_label: string, status: string}>
     */
    public function visibleEventsForFeedback(): array
    {
        if (! Schema::hasTable('carboot_events')) {
            return [];
        }

        return $this->presentEvents(
            CarbootEvent::query()
                ->orderByDesc('starts_at')
                ->get(['id', 'title', 'starts_at', 'ends_at', 'status']),
        );
    }

    public function findExistingFeedback(User $user, int $eventId, string $participationType): ?Feedback
    {
        if (! Schema::hasTable('feedbacks')) {
            return null;
        }

        return Feedback::query()
            ->where('user_id', $user->id)
            ->where('carboot_event_id', $eventId)
            ->where('participation_type', $participationType)
            ->first();
    }

    public function assertVendorMaySubmit(User $user, int $eventId): void
    {
        $this->assertEventAcceptsFeedback($eventId);

        if (! $this->userHasEligibleVendorBooking($user, $eventId)) {
            throw ValidationException::withMessages([
                'carboot_event_id' => [self::VENDOR_INELIGIBLE_MESSAGE],
            ]);
        }
    }

    /**
     * Event must exist (not deleted). Closed is allowed.
     * There is no Cancelled event status to block separately.
     */
    public function assertEventAcceptsFeedback(int $eventId): void
    {
        $event = CarbootEvent::query()->whereKey($eventId)->first();
        if (! $event) {
            throw ValidationException::withMessages([
                'carboot_event_id' => ['Please select a valid Carboot event.'],
            ]);
        }
    }

    public function isVendorParticipation(string $participationType): bool
    {
        return $participationType === 'vendor';
    }

    /**
     * @param  Collection<int, CarbootEvent>  $events
     * @return list<array{id: int, title: string, starts_at: ?string, ends_at: ?string, date_label: string, status: string}>
     */
    private function presentEvents(Collection $events): array
    {
        return $events->map(function (CarbootEvent $event) {
            $starts = optional($event->starts_at)?->toIso8601String();
            $ends = optional($event->ends_at)?->toIso8601String();
            $dateLabel = $event->starts_at
                ? $event->starts_at->timezone(config('app.timezone', 'Asia/Kuala_Lumpur'))->format('d M Y')
                : 'Date TBA';

            return [
                'id' => (int) $event->id,
                'title' => (string) $event->title,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'date_label' => $dateLabel,
                'status' => (string) $event->status,
            ];
        })->values()->all();
    }
}
