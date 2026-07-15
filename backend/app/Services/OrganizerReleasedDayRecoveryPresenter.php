<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Phase 2B.4 — safe Organizer recovery queue response shaping.
 */
class OrganizerReleasedDayRecoveryPresenter
{
    /**
     * @param  Collection<int, array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    public static function presentMany(Collection $groups, bool $includeAuditTimeline = false): array
    {
        return $groups
            ->map(fn (array $group) => self::presentGroup($group, $includeAuditTimeline))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $group
     */
    public static function presentGroup(array $group, bool $includeAuditTimeline = false): array
    {
        /** @var Booking|null $booking */
        $booking = $group['source_booking'] ?? null;
        $event = $group['event'] ?? null;
        $eventDay = $group['event_day'] ?? null;
        $release = $group['release'] ?? [];
        /** @var User|null $releasedBy */
        $releasedBy = $release['released_by'] ?? null;

        $payload = [
            'id' => $group['id'],
            'source_booking' => $booking ? [
                'id' => $booking->id,
                'reference' => self::bookingReference($booking),
                'status' => $booking->approval_status,
                'vendor_name' => $booking->user?->name,
                'business_name' => $booking->user?->businessProfile?->business_name,
            ] : null,
            'event' => $event ? [
                'id' => $event->id,
                'title' => $event->title,
            ] : null,
            'event_day' => $eventDay ? [
                'id' => $eventDay->id,
                'operational_date' => $eventDay->operational_date->format('Y-m-d'),
                'starts_at' => $eventDay->starts_at?->toIso8601String(),
                'ends_at' => $eventDay->ends_at?->toIso8601String(),
                'operational_status' => $eventDay->operational_status,
            ] : null,
            'released_sites' => collect($group['released_sites'] ?? [])
                ->map(fn (array $site) => [
                    'id' => $site['id'],
                    'label' => $site['label'],
                    'space_name' => $site['space_name'],
                    'recovery_state' => $site['recovery_state'],
                    'blocker' => $site['blocker'],
                ])
                ->values()
                ->all(),
            'release' => [
                'reason' => $release['reason'] ?? null,
                'released_at' => $release['released_at']?->toIso8601String(),
                'released_by' => $releasedBy ? [
                    'id' => $releasedBy->id,
                    'name' => $releasedBy->name,
                ] : ($booking?->attendanceExceptions?->last()?->applied_by_name
                    ? ['id' => null, 'name' => $booking->attendanceExceptions->last()->applied_by_name]
                    : null),
            ],
            'attendance_exception_reason' => $group['attendance_exception_reason'] ?? null,
            'source_payment_state' => $group['source_payment_state'] ?? null,
            'source_invoice_amount' => $group['source_invoice_amount'] ?? null,
            'recovery_state' => $group['recovery_state'] ?? null,
            'recoverable_site_count' => $group['recoverable_site_count'] ?? 0,
            'blocked_site_count' => $group['blocked_site_count'] ?? 0,
            'standard_full_event_available' => (bool) ($group['standard_full_event_available'] ?? false),
            'recovery_channel' => $group['recovery_channel'] ?? 'released_day_queue',
        ];

        if ($includeAuditTimeline && $booking) {
            $booking->loadMissing('auditLogs.actor');
            $payload['audit_timeline'] = BookingAuditPresenter::timeline($booking);
        }

        return $payload;
    }

    public static function bookingReference(Booking $booking): string
    {
        return sprintf('BKG-%04d', $booking->id);
    }
}
