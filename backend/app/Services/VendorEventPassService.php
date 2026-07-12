<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CarbootEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class VendorEventPassService
{
    public const STATUS_PENDING = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CHECKIN_OPEN = 'checkin_open';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_EVENT_ACTIVE = 'event_active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public function listForUser(int $userId): array
    {
        $bookings = Booking::query()
            ->where('user_id', $userId)
            ->withValidBookingDate()
            ->with(['space', 'invoice'])
            ->orderByDesc('booking_date')
            ->orderByDesc('id')
            ->get();

        $passes = $bookings
            ->map(fn (Booking $booking) => $this->presentPass($booking))
            ->values();

        $today = now()->startOfDay();

        $upcoming = $passes
            ->filter(function (array $pass) use ($today) {
                if (in_array($pass['pass_status'], [self::STATUS_CANCELLED], true)) {
                    return false;
                }

                if (in_array($pass['pass_status'], [self::STATUS_COMPLETED, self::STATUS_EXPIRED], true)) {
                    return false;
                }

                if ($pass['approval_status'] === 'Rejected') {
                    return false;
                }

                $eventDate = Carbon::parse($pass['event_date'])->startOfDay();

                return $eventDate->gte($today) || in_array($pass['pass_status'], [
                    self::STATUS_CHECKIN_OPEN,
                    self::STATUS_CHECKED_IN,
                    self::STATUS_EVENT_ACTIVE,
                ], true);
            })
            ->sortBy([
                fn (array $pass) => $pass['event_date'],
                fn (array $pass) => $pass['booking_id'],
            ])
            ->values();

        $archived = $passes
            ->filter(fn (array $pass) => ! $upcoming->contains(fn (array $u) => $u['booking_id'] === $pass['booking_id']))
            ->sortByDesc('event_date')
            ->values();

        $defaultPass = $this->resolveDefaultPass($upcoming);

        return [
            'upcoming' => $upcoming->all(),
            'archived' => $archived->all(),
            'default_pass_id' => $defaultPass['booking_id'] ?? null,
        ];
    }

    public function presentPass(Booking $booking): array
    {
        $event = $this->resolveEventForBooking($booking);
        [$startsAt, $endsAt] = $this->resolveEventWindow($booking, $event);

        $windowStart = $startsAt->copy()->subHours(3);
        $windowEnd = $endsAt->copy()->addHours(2);
        $now = now();

        $passStatus = $this->resolvePassStatus($booking, $now, $startsAt, $endsAt, $windowStart, $windowEnd);
        $isApproved = $booking->approval_status === 'Approved';
        $isPaid = $booking->invoice?->payment_status === 'Paid';
        $qrActive = $isApproved
            && $isPaid
            && ! in_array($passStatus, [self::STATUS_CANCELLED, self::STATUS_EXPIRED, self::STATUS_COMPLETED], true)
            && $now->between($windowStart, $windowEnd);

        $boothLabel = $isApproved ? VendorBookingPresenter::boothNumber($booking) : null;

        return [
            'booking_id' => $booking->id,
            'approval_status' => $booking->approval_status,
            'payment_status' => $booking->invoice?->payment_status,
            'pass_status' => $passStatus,
            'pass_status_label' => $this->statusLabel($passStatus),
            'event_id' => $event?->id,
            'event_name' => $event?->title ?? VendorBookingPresenter::eventLabel($booking),
            'event_date' => $booking->booking_date->format('Y-m-d'),
            'event_date_label' => $booking->booking_date->format('l, j F Y'),
            'event_starts_at' => $startsAt->toIso8601String(),
            'event_ends_at' => $endsAt->toIso8601String(),
            'event_time_label' => $this->formatTimeRange($startsAt, $endsAt),
            'booth_label' => $boothLabel,
            'booth_type_label' => $booking->space?->space_size ?? 'Standard (1 Parking Lot)',
            'product_category' => $booking->product_category ?? 'Others',
            'product_details' => $booking->product_details,
            'product_label' => $this->productLabel($booking),
            'show_qr' => $isApproved && $isPaid,
            'show_booth' => $isApproved,
            'qr_active' => $qrActive,
            'qr_expired' => $isApproved && $isPaid && $now->gt($windowEnd),
            'checked_in_at' => $booking->checked_in_at?->toIso8601String(),
            'checkin_window_starts_at' => $windowStart->toIso8601String(),
            'checkin_window_ends_at' => $windowEnd->toIso8601String(),
            'pending_message' => $isApproved
                ? ($isPaid ? null : 'Event pass unlocks after CMart verifies your payment')
                : 'Booth will be assigned after approval',
            'is_archived' => in_array($passStatus, [self::STATUS_COMPLETED, self::STATUS_EXPIRED, self::STATUS_CANCELLED], true),
        ];
    }

    public function verifyForStaff(Booking $booking): array
    {
        $pass = $this->presentPass($booking);
        $valid = $booking->approval_status === 'Approved'
            && $pass['qr_active']
            && ! in_array($pass['pass_status'], [self::STATUS_CANCELLED, self::STATUS_EXPIRED], true);

        $reason = null;
        if ($booking->approval_status !== 'Approved') {
            $reason = 'This booking is not approved.';
        } elseif (in_array($booking->approval_status, ['Cancelled', 'Rejected', 'Withdrawn'], true)) {
            $reason = 'This pass has been cancelled.';
        } elseif ($pass['qr_expired']) {
            $reason = 'This QR code has expired.';
        } elseif (! $pass['qr_active']) {
            $reason = 'Check-in is not open yet for this event.';
        }

        return [
            'valid' => $valid,
            'reason' => $valid ? null : $reason,
            'pass' => $pass,
            'vendor' => [
                'name' => $booking->user?->name,
                'email' => $booking->user?->email,
            ],
        ];
    }

    public function checkIn(Booking $booking): array
    {
        if ($booking->approval_status !== 'Approved') {
            return [
                'success' => false,
                'message' => 'Only approved bookings can be checked in.',
            ];
        }

        $verification = $this->verifyForStaff($booking);
        if (! $verification['valid'] && ! $booking->checked_in_at) {
            return [
                'success' => false,
                'message' => $verification['reason'] ?? 'Pass is not valid for check-in.',
            ];
        }

        if (! $booking->checked_in_at) {
            $booking->update(['checked_in_at' => now()]);
            $booking->refresh();
        }

        return [
            'success' => true,
            'message' => 'Vendor checked in successfully.',
            'pass' => $this->presentPass($booking->load('space')),
        ];
    }

    private function resolveDefaultPass(Collection $upcoming): ?array
    {
        if ($upcoming->isEmpty()) {
            return null;
        }

        $approvedUpcoming = $upcoming->filter(fn (array $pass) => $pass['approval_status'] === 'Approved');

        if ($approvedUpcoming->isNotEmpty()) {
            return $approvedUpcoming->first();
        }

        return $upcoming->first();
    }

    public function resolveEventForBooking(Booking $booking): ?CarbootEvent
    {
        if ($booking->carboot_event_id) {
            return CarbootEvent::query()->find($booking->carboot_event_id);
        }

        return CarbootEvent::query()
            ->whereDate('starts_at', $booking->booking_date)
            ->orderBy('starts_at')
            ->first();
    }

    private function resolveEventWindow(Booking $booking, ?CarbootEvent $event): array
    {
        if ($event) {
            return [$event->starts_at->copy(), $event->ends_at->copy()];
        }

        $date = $booking->booking_date->format('Y-m-d');

        return [
            Carbon::parse("{$date} 09:00:00", 'Asia/Kuala_Lumpur'),
            Carbon::parse("{$date} 17:00:00", 'Asia/Kuala_Lumpur'),
        ];
    }

    private function resolvePassStatus(
        Booking $booking,
        Carbon $now,
        Carbon $startsAt,
        Carbon $endsAt,
        Carbon $windowStart,
        Carbon $windowEnd,
    ): string {
        if (in_array($booking->approval_status, ['Cancelled', 'Rejected', 'Withdrawn'], true)) {
            return self::STATUS_CANCELLED;
        }

        if (in_array($booking->approval_status, ['Pending_Organizer', 'Needs_Revision'], true)) {
            return self::STATUS_PENDING;
        }

        if ($booking->approval_status !== 'Approved') {
            return self::STATUS_PENDING;
        }

        if ($booking->checked_in_at) {
            if ($now->gt($windowEnd)) {
                return self::STATUS_COMPLETED;
            }

            if ($now->between($startsAt, $endsAt)) {
                return self::STATUS_EVENT_ACTIVE;
            }

            return self::STATUS_CHECKED_IN;
        }

        if ($now->gt($windowEnd)) {
            return self::STATUS_EXPIRED;
        }

        if ($now->gt($endsAt)) {
            return self::STATUS_COMPLETED;
        }

        if ($now->between($startsAt, $endsAt)) {
            return self::STATUS_EVENT_ACTIVE;
        }

        if ($now->between($windowStart, $windowEnd)) {
            return self::STATUS_CHECKIN_OPEN;
        }

        return self::STATUS_APPROVED;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_CHECKIN_OPEN => 'Check-in Open',
            self::STATUS_CHECKED_IN => 'Checked In',
            self::STATUS_EVENT_ACTIVE => 'Event Active',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function formatTimeRange(Carbon $startsAt, Carbon $endsAt): string
    {
        $opts = ['timeZone' => 'Asia/Kuala_Lumpur', 'hour' => 'numeric', 'minute' => '2-digit', 'hour12' => true];

        return $startsAt->timezone('Asia/Kuala_Lumpur')->format('g:i A')
            . ' – '
            . $endsAt->timezone('Asia/Kuala_Lumpur')->format('g:i A');
    }

    private function productLabel(Booking $booking): string
    {
        $category = $booking->product_category ?? 'Others';
        $details = trim((string) $booking->product_details);

        return $details !== '' ? "{$category} · {$details}" : $category;
    }
}
