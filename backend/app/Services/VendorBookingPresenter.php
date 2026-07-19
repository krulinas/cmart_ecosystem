<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAttendanceException;
use App\Models\BookingAttendanceExceptionDay;
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

    public const ATTENDANCE_NO_REFUND_WARNING_MS =
        'Pengecualian hari tidak mengubah jumlah bayaran. Tiada bayaran balik akan diberikan bagi hari yang dilepaskan.';

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

        $activeAllocations = $allocations->filter(fn ($row) => $row->occupiesSite());
        $selectionAllocations = $activeAllocations->isNotEmpty()
            ? $activeAllocations
            : $allocations->reject(
                fn ($row) => $row->release_reason
                    === BookingAllocationLifecycleService::REASON_ORGANIZER_SITE_REASSIGNMENT,
            );

        $sites = $selectionAllocations
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

        $days = $selectionAllocations
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
            'allocation_count' => $selectionAllocations->count(),
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

    /**
     * Safe full-event policy and append-only attendance-exception summary.
     *
     * @return array<string, mixed>
     */
    public static function attendancePolicy(Booking $booking, bool $forOrganizer = false): array
    {
        $booking->loadMissing([
            'invoice',
            'carbootEvent',
            'attendanceExceptions.appliedBy',
            'attendanceExceptions.days.eventDay',
            'bookingDayAllocations.eventSite.space',
            'bookingDayAllocations.eventDay',
        ]);

        $allocations = $booking->bookingDayAllocations;
        $exceptions = $booking->attendanceExceptions->sortBy('id')->values();
        /** @var BookingAttendanceException|null $latest */
        $latest = $exceptions->last();

        $originalDayIds = $allocations->pluck('event_day_id')->map(fn ($id) => (int) $id)->unique();
        $retainedDayIds = $latest
            ? $latest->days
                ->where('disposition', BookingAttendanceExceptionDay::DISPOSITION_RETAINED)
                ->pluck('event_day_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
            : $originalDayIds;
        $releasedDayIds = $exceptions
            ->flatMap(fn (BookingAttendanceException $exception) => $exception->days
                ->where('disposition', BookingAttendanceExceptionDay::DISPOSITION_RELEASED)
                ->pluck('event_day_id'))
            ->map(fn ($id) => (int) $id)
            ->unique();

        $daysById = $allocations
            ->pluck('eventDay')
            ->filter()
            ->unique('id')
            ->keyBy('id');
        $dayPresenter = function (int $dayId) use ($allocations, $daysById) {
            $day = $daysById->get($dayId);
            if (! $day) {
                return null;
            }

            $dayAllocations = $allocations->where('event_day_id', $dayId);
            $statuses = $dayAllocations->pluck('allocation_status')->unique()->values();
            $status = $statuses->count() === 1 ? $statuses->first() : 'mixed';
            $hasStarted = $day->starts_at === null || $day->starts_at->lte(now());

            return [
                'id' => $day->id,
                'operational_date' => $day->operational_date->format('Y-m-d'),
                'starts_at' => $day->starts_at?->toIso8601String(),
                'ends_at' => $day->ends_at?->toIso8601String(),
                'allocation_status' => $status,
                'has_started' => $hasStarted,
                'can_be_released' => ! $hasStarted && $dayAllocations->contains(
                    fn (BookingDayAllocation $row) => $row->occupiesSite(),
                ),
            ];
        };

        $retainedDays = $retainedDayIds
            ->sort()
            ->map($dayPresenter)
            ->filter()
            ->sortBy('starts_at')
            ->values()
            ->all();
        $releasedDays = $releasedDayIds
            ->sort()
            ->map($dayPresenter)
            ->filter()
            ->sortBy('starts_at')
            ->values()
            ->all();
        $siteLabels = $allocations
            ->pluck('eventSite')
            ->filter()
            ->unique('id')
            ->sortBy('label')
            ->pluck('label')
            ->values()
            ->all();
        $paymentState = self::withdrawalPaymentState($booking);
        $canReduce = $forOrganizer
            && in_array($booking->approval_status, self::WITHDRAWABLE_STATUSES, true)
            && $booking->carbootEvent?->day_generation_mode === CarbootEvent::DAY_MODE_CALENDAR
            && count($retainedDays) >= 2
            && collect($retainedDays)->contains('can_be_released', true);

        $payload = [
            'mode' => $latest ? 'organizer_exception' : 'full_event',
            'full_event_required_by_default' => true,
            'has_exception' => $latest !== null,
            'can_organizer_reduce_days' => $canReduce,
            'original_event_day_count' => $originalDayIds->count(),
            'retained_event_day_count' => count($retainedDays),
            'released_event_day_count' => count($releasedDays),
            'retained_days' => $retainedDays,
            'released_days' => $releasedDays,
            'site_labels' => $siteLabels,
            'reason' => $latest?->reason,
            'applied_at' => $latest?->applied_at?->toIso8601String(),
            'applied_by' => $latest ? (
                $forOrganizer
                    ? [
                        'id' => $latest->appliedBy?->id,
                        'name' => $latest->applied_by_name,
                    ]
                    : ['name' => 'Organizer']
            ) : null,
            'payment_state' => $latest?->payment_state ?? $paymentState,
            'no_refund_applied' => $latest !== null
                && in_array($latest->payment_state, [
                    self::PAYMENT_STATE_PAID,
                    self::PAYMENT_STATE_PAYMENT_SUBMITTED,
                ], true),
            'requires_no_refund_acknowledgement' => in_array($paymentState, [
                self::PAYMENT_STATE_PAID,
                self::PAYMENT_STATE_PAYMENT_SUBMITTED,
            ], true),
            'no_refund_warning' => self::ATTENDANCE_NO_REFUND_WARNING_MS,
        ];

        if ($forOrganizer) {
            $payload['exception_history'] = $exceptions
                ->map(fn (BookingAttendanceException $exception) => [
                    'id' => $exception->id,
                    'reason' => $exception->reason,
                    'previous_retained_day_count' => $exception->previous_retained_day_count,
                    'retained_day_count' => $exception->retained_day_count,
                    'released_day_count' => $exception->released_day_count,
                    'payment_state' => $exception->payment_state,
                    'no_refund_acknowledged' => $exception->no_refund_acknowledged,
                    'applied_at' => $exception->applied_at?->toIso8601String(),
                    'applied_by' => [
                        'id' => $exception->appliedBy?->id,
                        'name' => $exception->applied_by_name,
                    ],
                ])
                ->values()
                ->all();
        }

        return $payload;
    }

    public static function presentForVendor(Booking $booking, ?int $viewerUserId = null): array
    {
        $booking->loadMissing(['space', 'invoice', 'carbootEvent', 'vendorCategory', 'activeCategoryOverride']);

        $payload = array_merge($booking->toArray(), [
            'can_withdraw' => self::canVendorWithdraw($booking, $viewerUserId),
            'withdrawal_policy' => self::withdrawalPolicy($booking, $viewerUserId),
            'attendance_policy' => self::attendancePolicy($booking),
            'event_label' => self::eventLabel($booking),
        ]);

        unset($payload['audit_logs'], $payload['booking_day_allocations'], $payload['withdrawn_by']);
        $payload['invoice'] = self::safeInvoice($booking);
        $payload['category'] = self::presentCategory($booking);
        $payload['category_label_snapshot'] = $booking->category_label_snapshot
            ?? $booking->product_category;

        if ($booking->activeCategoryOverride) {
            $payload['placement_exception'] = [
                'applied' => true,
                'message' => 'Pihak penganjur telah menetapkan tapak anda di zon kategori berbeza.',
            ];
        }

        $siteSelection = self::siteSelection($booking);
        if ($siteSelection !== null) {
            $payload['site_selection'] = $siteSelection;
            $payload['tapak_quantity'] = $siteSelection['site_count'];
        }

        return $payload;
    }

    public static function presentForOrganizer(Booking $booking, bool $includeAuditTimeline = false): array
    {
        $booking->loadMissing(['user', 'space', 'invoice', 'carbootEvent', 'withdrawnBy', 'vendorCategory', 'activeCategoryOverride']);
        if ($includeAuditTimeline) {
            $booking->loadMissing('auditLogs.actor');
        }

        $payload = $booking->toArray();
        unset($payload['audit_logs'], $payload['booking_day_allocations'], $payload['withdrawn_by']);
        $payload['invoice'] = self::safeInvoice($booking);
        $payload['category'] = self::presentCategory($booking);
        $payload['category_label_snapshot'] = $booking->category_label_snapshot
            ?? $booking->product_category;
        $payload['category_placement'] = self::categoryPlacement($booking);
        $payload['withdrawal_policy'] = self::withdrawalPolicy($booking);
        $payload['withdrawal_reconciliation'] = self::withdrawalReconciliation($booking);
        $payload['attendance_policy'] = self::attendancePolicy($booking, true);
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
     * @return array{id: int, slug: string, label: string}|null
     */
    private static function presentCategory(Booking $booking): ?array
    {
        $label = $booking->category_label_snapshot ?? $booking->product_category;
        $category = $booking->vendorCategory;

        if ($category) {
            return [
                'id' => (int) $category->id,
                'slug' => $category->slug,
                'label' => $label ?: $category->label,
            ];
        }

        if ($booking->vendor_category_id && $label) {
            return [
                'id' => (int) $booking->vendor_category_id,
                'slug' => '',
                'label' => $label,
            ];
        }

        if ($label) {
            return [
                'id' => 0,
                'slug' => '',
                'label' => $label,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function categoryPlacement(Booking $booking): array
    {
        return app(OrganizerBookingCategoryPlacementService::class)->placementPayload($booking);
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
