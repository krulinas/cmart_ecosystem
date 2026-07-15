<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;

class VendorBookingPresenter
{
    public const WITHDRAWABLE_STATUSES = ['Pending_Organizer', 'Needs_Revision', 'Approved'];

    public const TERMINAL_STATUSES = ['Rejected', 'Cancelled', 'Withdrawn'];

    public const PAYMENT_STATE_UNPAID = 'unpaid';

    public const PAYMENT_STATE_PAYMENT_SUBMITTED = 'payment_submitted';

    public const PAYMENT_STATE_PAID = 'paid';

    public const NO_REFUND_WARNING_MS =
        'Anda boleh menarik diri selepas bayaran dibuat, tetapi bayaran tidak akan dipulangkan. Tapak yang telah ditempah akan dibuka semula kepada vendor lain.';

    public static function eventLabel(Booking $booking): string
    {
        if ($booking->relationLoaded('carbootEvent') && $booking->carbootEvent) {
            return $booking->carbootEvent->title;
        }

        $event = CarbootEvent::query()
            ->whereDate('starts_at', $booking->booking_date)
            ->orderBy('starts_at')
            ->first();

        if ($event) {
            return $event->title;
        }

        return $booking->booking_date->format('j M Y');
    }

    public static function boothNumber(Booking $booking): ?string
    {
        if ($booking->approval_status !== 'Approved') {
            return null;
        }

        $booking->loadMissing([
            'bookingDayAllocations.eventSite',
        ]);

        $sites = $booking->bookingDayAllocations
            ->pluck('eventSite')
            ->filter()
            ->unique('id')
            ->sortBy('label')
            ->values();

        if ($sites->isNotEmpty()) {
            return $sites->pluck('label')->implode(', ');
        }

        $prefix = chr(65 + ($booking->id % 3));

        return sprintf('%s-%02d', $prefix, $booking->id);
    }

    /**
     * Derive withdrawal payment state from authoritative Invoice fields.
     */
    public static function withdrawalPaymentState(Booking $booking): string
    {
        $booking->loadMissing('invoice');
        $status = $booking->invoice?->payment_status;

        if ($status === 'Paid') {
            return self::PAYMENT_STATE_PAID;
        }

        if ($status === 'Pending Verification') {
            return self::PAYMENT_STATE_PAYMENT_SUBMITTED;
        }

        return self::PAYMENT_STATE_UNPAID;
    }

    public static function canVendorWithdraw(Booking $booking, ?int $viewerUserId = null): bool
    {
        if ($viewerUserId === null || $booking->user_id !== $viewerUserId) {
            return false;
        }

        if (in_array($booking->approval_status, self::TERMINAL_STATUSES, true)) {
            return false;
        }

        return in_array($booking->approval_status, self::WITHDRAWABLE_STATUSES, true);
    }

    public static function withdrawalPolicy(Booking $booking, ?int $viewerUserId = null): array
    {
        $paymentState = self::withdrawalPaymentState($booking);
        $canWithdraw = self::canVendorWithdraw($booking, $viewerUserId);
        $requiresAcknowledgement = $canWithdraw
            && in_array($paymentState, [self::PAYMENT_STATE_PAID, self::PAYMENT_STATE_PAYMENT_SUBMITTED], true);

        $warningMessage = match ($paymentState) {
            self::PAYMENT_STATE_PAID, self::PAYMENT_STATE_PAYMENT_SUBMITTED => self::NO_REFUND_WARNING_MS,
            default => 'Penarikan diri akan menamatkan tempahan ini dan tapak yang dipilih akan dibuka semula kepada vendor lain. Tindakan ini tidak boleh dibatalkan melalui papan pemuka.',
        };

        return [
            'can_withdraw' => $canWithdraw,
            'payment_state' => $paymentState,
            'refund_allowed' => false,
            'requires_no_refund_acknowledgement' => $requiresAcknowledgement,
            'warning_message' => $warningMessage,
        ];
    }

    public static function siteSelection(Booking $booking): ?array
    {
        $booking->loadMissing([
            'bookingDayAllocations.eventSite.space',
            'bookingDayAllocations.eventDay',
        ]);

        $allocations = $booking->bookingDayAllocations;

        if ($allocations->isEmpty()) {
            return null;
        }

        $sites = $allocations
            ->pluck('eventSite')
            ->filter()
            ->unique('id')
            ->sortBy('label')
            ->values()
            ->map(fn ($site) => [
                'id' => $site->id,
                'label' => $site->label,
                'row_label' => $site->row_label,
                'position_number' => $site->position_number,
                'space_id' => $site->space_id,
                'space_name' => $site->space?->space_size,
                'price' => number_format((float) ($site->space?->price ?? 0), 2, '.', ''),
            ])
            ->values()
            ->all();

        $days = $allocations
            ->pluck('eventDay')
            ->filter()
            ->unique('id')
            ->sortBy('operational_date')
            ->values()
            ->map(fn ($day) => [
                'id' => $day->id,
                'operational_date' => $day->operational_date->format('Y-m-d'),
                'starts_at' => $day->starts_at?->toIso8601String(),
                'ends_at' => $day->ends_at?->toIso8601String(),
                'operational_status' => $day->operational_status,
            ])
            ->values()
            ->all();

        $activeAllocations = $allocations->filter(fn ($row) => $row->occupiesSite());
        $allocationStatus = null;

        if ($activeAllocations->isNotEmpty()) {
            $statuses = $activeAllocations->pluck('allocation_status')->unique()->values();
            $allocationStatus = $statuses->count() === 1 ? $statuses->first() : 'mixed';
        } elseif ($allocations->isNotEmpty()) {
            $released = $allocations->where('allocation_status', BookingDayAllocation::STATUS_RELEASED);
            $allocationStatus = $released->count() === $allocations->count()
                ? BookingDayAllocation::STATUS_RELEASED
                : $allocations->first()->allocation_status;
        }

        return [
            'site_count' => count($sites),
            'active_day_count' => count($days),
            'allocation_count' => $allocations->count(),
            'allocation_status' => $allocationStatus,
            'sites' => $sites,
            'days' => $days,
        ];
    }

    public static function tapakQuantity(Booking $booking): ?int
    {
        $selection = self::siteSelection($booking);

        return $selection['site_count'] ?? null;
    }

    /**
     * Organizer-only, read-only interpretation of an operational withdrawal.
     *
     * @return array<string, mixed>|null
     */
    public static function withdrawalReconciliation(Booking $booking): ?array
    {
        if ($booking->approval_status !== 'Withdrawn') {
            return null;
        }

        $booking->loadMissing(['invoice', 'withdrawnBy']);
        $paymentState = self::withdrawalPaymentState($booking);
        $siteSelection = self::siteSelection($booking);
        $verificationAudit = $booking->relationLoaded('auditLogs')
            ? $booking->auditLogs
                ->where('action', 'organizer_verified_payment')
                ->sortByDesc('id')
                ->first()
            : null;

        return [
            'is_withdrawn' => true,
            'withdrawn_at' => $booking->withdrawn_at?->toIso8601String(),
            'withdrawn_by' => $booking->withdrawnBy ? [
                'id' => $booking->withdrawnBy->id,
                'name' => $booking->withdrawnBy->name,
            ] : null,
            'payment_state' => $paymentState,
            'invoice_payment_status' => $booking->invoice?->payment_status,
            'invoice_amount' => $booking->invoice
                ? number_format((float) $booking->invoice->amount, 2, '.', '')
                : null,
            'payment_proof_present' => filled($booking->invoice?->payment_proof_path),
            'payment_verified' => $paymentState === self::PAYMENT_STATE_PAID,
            'payment_verified_at' => $verificationAudit?->created_at?->toIso8601String(),
            'payment_verified_by' => $verificationAudit?->actor ? [
                'id' => $verificationAudit->actor->id,
                'name' => $verificationAudit->actor->name,
            ] : null,
            'no_refund_applied' => in_array(
                $paymentState,
                [self::PAYMENT_STATE_PAID, self::PAYMENT_STATE_PAYMENT_SUBMITTED],
                true,
            ),
            'financial_history_preserved' => $booking->invoice !== null,
            'allocation_status' => $siteSelection['allocation_status'] ?? null,
            'sites_released' => ($siteSelection['allocation_status'] ?? null)
                === BookingDayAllocation::STATUS_RELEASED,
            'released_site_labels' => collect($siteSelection['sites'] ?? [])
                ->pluck('label')
                ->values()
                ->all(),
            'active_day_count' => $siteSelection['active_day_count'] ?? 0,
            'event_days' => $siteSelection['days'] ?? [],
        ];
    }

    public static function presentForVendor(Booking $booking, ?int $viewerUserId = null): array
    {
        $booking->loadMissing(['space', 'invoice', 'carbootEvent']);

        $payload = array_merge($booking->toArray(), [
            'can_withdraw' => self::canVendorWithdraw($booking, $viewerUserId),
            'withdrawal_policy' => self::withdrawalPolicy($booking, $viewerUserId),
            'event_label' => self::eventLabel($booking),
        ]);

        unset($payload['audit_logs'], $payload['booking_day_allocations'], $payload['withdrawn_by']);
        $payload['invoice'] = self::safeInvoice($booking);

        $siteSelection = self::siteSelection($booking);
        if ($siteSelection !== null) {
            $payload['site_selection'] = $siteSelection;
            $payload['tapak_quantity'] = $siteSelection['site_count'];
        }

        return $payload;
    }

    public static function presentForOrganizer(Booking $booking, bool $includeAuditTimeline = false): array
    {
        $booking->loadMissing(['user', 'space', 'invoice', 'carbootEvent', 'withdrawnBy']);
        if ($includeAuditTimeline) {
            $booking->loadMissing('auditLogs.actor');
        }

        $payload = $booking->toArray();
        unset($payload['audit_logs'], $payload['booking_day_allocations'], $payload['withdrawn_by']);
        $payload['invoice'] = self::safeInvoice($booking);
        $payload['withdrawal_policy'] = self::withdrawalPolicy($booking);
        $payload['withdrawal_reconciliation'] = self::withdrawalReconciliation($booking);
        if ($includeAuditTimeline) {
            $payload['audit_timeline'] = BookingAuditPresenter::timeline($booking);
        }

        $siteSelection = self::siteSelection($booking);
        if ($siteSelection !== null) {
            $payload['site_selection'] = $siteSelection;
            $payload['tapak_quantity'] = $siteSelection['site_count'];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function safeInvoice(Booking $booking): ?array
    {
        if (!$booking->invoice) {
            return null;
        }

        return [
            'id' => $booking->invoice->id,
            'amount' => number_format((float) $booking->invoice->amount, 2, '.', ''),
            'payment_status' => $booking->invoice->payment_status,
            'payment_proof_present' => filled($booking->invoice->payment_proof_path),
            'payment_submitted_at' => $booking->invoice->payment_submitted_at?->toIso8601String(),
        ];
    }
}
