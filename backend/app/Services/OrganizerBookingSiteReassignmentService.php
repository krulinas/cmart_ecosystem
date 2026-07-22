<?php

namespace App\Services;

use App\Exceptions\AllocationValidationException;
use App\Exceptions\DomainConflictException;
use App\Models\Booking;
use App\Models\BookingCategoryOverride;
use App\Models\BookingDayAllocation;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3.8 — atomic Organizer site reassignment with optional category override.
 */
class OrganizerBookingSiteReassignmentService
{
    public function __construct(
        private readonly OrganizerBookingReassignmentEligibilityService $eligibility,
        private readonly OrganizerBookingAssignmentFingerprintService $fingerprint,
        private readonly OrganizerBookingCategoryPlacementService $placement,
        private readonly BookingSiteCategoryValidator $layoutReadiness,
    ) {
    }

    /**
     * @param  list<int>  $eventSiteIds
     * @return array{booking: Booking, placement: array}
     */
    public function reassign(
        Booking $booking,
        User $actor,
        array $eventSiteIds,
        string $assignmentFingerprint,
        bool $acknowledgeCategoryOverride = false,
        ?string $overrideReason = null,
    ): array {
        return DB::transaction(function () use (
            $booking,
            $actor,
            $eventSiteIds,
            $assignmentFingerprint,
            $acknowledgeCategoryOverride,
            $overrideReason,
        ) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $booking->load(['invoice', 'vendorCategory', 'activeCategoryOverride']);

            $invoice = Invoice::query()->where('booking_id', $booking->id)->lockForUpdate()->first();
            $booking->setRelation('invoice', $invoice);

            $this->eligibility->assertEligible($booking);
            $this->fingerprint->assertMatches($booking, $assignmentFingerprint);

            $requirements = $this->eligibility->requiredSpaceFromBooking($booking);
            $targetSites = $this->eligibility->validateTargetSites(
                $booking,
                $eventSiteIds,
                $requirements['site_count'],
                $requirements['space_id'],
                $requirements['unit_price'],
            );

            $event = $booking->carbootEvent()->lockForUpdate()->firstOrFail();
            $this->layoutReadiness->assertEventOperationallyLayoutReady($event);

            $row = $targetSites->first()->eventLayoutRow;
            EventLayoutRow::query()->whereKey($row->id)->lockForUpdate()->first();

            $assignedCategoryId = (int) $row->vendor_category_id;
            $bookingCategoryId = (int) $booking->vendor_category_id;
            $compatible = $assignedCategoryId === $bookingCategoryId;

            if (! $compatible) {
                $this->assertOverrideInput($acknowledgeCategoryOverride, $overrideReason);
            }

            $activeAllocations = BookingDayAllocation::query()
                ->forBooking((int) $booking->id)
                ->with(['eventSite.eventLayoutRow'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn (BookingDayAllocation $row) => $row->occupiesSite());

            $retainedDayIds = $activeAllocations
                ->pluck('event_day_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $targetSiteIds = $targetSites->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $targetPairs = $this->buildTargetPairs($retainedDayIds, $targetSiteIds);
            $currentPairs = $this->buildCurrentPairs($activeAllocations);

            $toRemove = array_diff_key($currentPairs, $targetPairs);
            $toAdd = array_diff_key($targetPairs, $currentPairs);

            $releasedAt = now();
            foreach ($toRemove as $key => $pair) {
                $allocation = $activeAllocations->first(
                    fn (BookingDayAllocation $row) => $this->pairKey((int) $row->event_day_id, (int) $row->event_site_id) === $key,
                );
                if (! $allocation) {
                    continue;
                }
                $allocation->update([
                    'allocation_status' => BookingDayAllocation::STATUS_RELEASED,
                    'released_at' => $releasedAt,
                    'released_by' => $actor->id,
                    'release_reason' => BookingAllocationLifecycleService::REASON_ORGANIZER_SITE_REASSIGNMENT,
                    'active_lock' => null,
                ]);
            }

            $reservedAt = now();
            foreach ($toAdd as $pair) {
                $existing = BookingDayAllocation::query()
                    ->where('booking_id', $booking->id)
                    ->where('event_day_id', $pair['event_day_id'])
                    ->where('event_site_id', $pair['event_site_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $existing->update([
                        'allocation_status' => BookingDayAllocation::STATUS_RESERVED,
                        'reserved_at' => $reservedAt,
                        'confirmed_at' => null,
                        'released_at' => null,
                        'released_by' => null,
                        'release_reason' => null,
                        'active_lock' => 1,
                    ]);

                    continue;
                }

                BookingDayAllocation::create([
                    'booking_id' => $booking->id,
                    'event_day_id' => $pair['event_day_id'],
                    'event_site_id' => $pair['event_site_id'],
                    'allocation_status' => BookingDayAllocation::STATUS_RESERVED,
                    'reserved_at' => $reservedAt,
                    'confirmed_at' => null,
                    'released_at' => null,
                    'released_by' => null,
                    'release_reason' => null,
                    'active_lock' => 1,
                ]);
            }

            $firstSite = $targetSites->sortBy('id')->first();
            if ($firstSite && (int) $booking->space_id !== (int) $firstSite->space_id) {
                $booking->update(['space_id' => $firstSite->space_id]);
            }

            $this->applyOverrideLifecycle(
                $booking,
                $actor,
                $compatible,
                $overrideReason,
                $targetSites,
                $row,
            );

            $previousSites = collect($currentPairs)->pluck('site_label')->unique()->sort()->implode(',');
            $newSites = $targetSites->pluck('label')->sort()->implode(',');
            $previousRows = $activeAllocations
                ->pluck('eventSite.eventLayoutRow.label')
                ->filter()
                ->unique()
                ->sort()
                ->implode(',');
            $newRowLabel = $row->label;

            BookingAuditLogger::log(
                $booking->fresh(),
                $actor,
                $booking->approval_status,
                $booking->approval_status,
                sprintf(
                    'organizer_site_reassignment; booking_category_id=%d; previous_sites=%s; new_sites=%s; previous_rows=%s; new_row=%s; compatible=%s',
                    $bookingCategoryId,
                    $previousSites,
                    $newSites,
                    $previousRows,
                    $newRowLabel,
                    $compatible ? 'yes' : 'no',
                ),
                null,
                'organizer_site_reassignment',
            );

            $booking = $booking->fresh([
                'vendorCategory',
                'invoice',
                'activeCategoryOverride.appliedBy',
                'bookingDayAllocations.eventSite.space',
                'bookingDayAllocations.eventSite.eventLayoutRow.vendorCategory',
                'bookingDayAllocations.eventDay',
            ]);

            return [
                'booking' => $booking,
                'placement' => $this->placement->placementPayload($booking),
            ];
        });
    }

    private function assertOverrideInput(bool $acknowledged, ?string $reason): void
    {
        if (! $acknowledged) {
            throw new AllocationValidationException(
                'Please confirm that you understand this category exception.',
                'CATEGORY_OVERRIDE_ACKNOWLEDGEMENT_REQUIRED',
            );
        }

        $trimmed = trim((string) $reason);
        if ($trimmed === '') {
            throw new AllocationValidationException(
                'Please provide a reason for this exception.',
                'CATEGORY_OVERRIDE_REASON_REQUIRED',
            );
        }

        if (mb_strlen($trimmed) < 10) {
            throw new AllocationValidationException(
                'The exception reason is too short.',
                'CATEGORY_OVERRIDE_REASON_TOO_SHORT',
            );
        }

        if (mb_strlen($trimmed) > 1000) {
            throw new AllocationValidationException(
                'The exception reason is too long.',
                'CATEGORY_OVERRIDE_REASON_TOO_LONG',
            );
        }
    }

    /**
     * @param  Collection<int, EventSite>  $targetSites
     */
    private function applyOverrideLifecycle(
        Booking $booking,
        User $actor,
        bool $compatible,
        ?string $overrideReason,
        Collection $targetSites,
        EventLayoutRow $row,
    ): void {
        $active = BookingCategoryOverride::query()
            ->where('booking_id', $booking->id)
            ->active()
            ->lockForUpdate()
            ->first();

        if ($compatible) {
            if ($active) {
                $active->update([
                    'status' => BookingCategoryOverride::STATUS_REVOKED,
                    'active_lock' => null,
                    'revoked_by_user_id' => $actor->id,
                    'revoked_at' => now(),
                    'revocation_reason' => 'Placement reassigned to a category-compatible row.',
                ]);

                BookingAuditLogger::log(
                    $booking,
                    $actor,
                    $booking->approval_status,
                    $booking->approval_status,
                    sprintf('organizer_category_override_revoked; override_id=%d', $active->id),
                    null,
                    'organizer_category_override_revoked',
                );
            }

            return;
        }

        if ($active) {
            $active->update([
                'status' => BookingCategoryOverride::STATUS_SUPERSEDED,
                'active_lock' => null,
            ]);

            BookingAuditLogger::log(
                $booking,
                $actor,
                $booking->approval_status,
                $booking->approval_status,
                sprintf('organizer_category_override_superseded; override_id=%d', $active->id),
                null,
                'organizer_category_override_superseded',
            );
        }

        $assignedCategory = $row->vendorCategory;
        $override = BookingCategoryOverride::create([
            'booking_id' => $booking->id,
            'booking_category_id_snapshot' => (int) $booking->vendor_category_id,
            'booking_category_label_snapshot' => $booking->category_label_snapshot ?? $booking->product_category,
            'assigned_category_id_snapshot' => (int) $assignedCategory?->id,
            'assigned_category_label_snapshot' => $assignedCategory?->label ?? '',
            'assigned_row_ids_snapshot' => [(int) $row->id],
            'assigned_row_labels_snapshot' => [$row->label],
            'assigned_site_ids_snapshot' => $targetSites->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'assigned_site_labels_snapshot' => $targetSites->pluck('label')->values()->all(),
            'reason' => trim((string) $overrideReason),
            'applied_by_user_id' => $actor->id,
            'applied_at' => now(),
            'status' => BookingCategoryOverride::STATUS_ACTIVE,
            'active_lock' => 1,
        ]);

        BookingAuditLogger::log(
            $booking,
            $actor,
            $booking->approval_status,
            $booking->approval_status,
            sprintf(
                'organizer_category_override_applied; override_id=%d; booking_category=%s; assigned_category=%s; reason=%s',
                $override->id,
                $booking->category_label_snapshot ?? $booking->product_category,
                $assignedCategory?->label,
                $override->reason,
            ),
            null,
            'organizer_category_override_applied',
        );
    }

    /**
     * @param  list<int>  $dayIds
     * @param  list<int>  $siteIds
     * @return array<string, array{event_day_id: int, event_site_id: int}>
     */
    private function buildTargetPairs(array $dayIds, array $siteIds): array
    {
        $pairs = [];
        foreach ($dayIds as $dayId) {
            foreach ($siteIds as $siteId) {
                $pairs[$this->pairKey($dayId, $siteId)] = [
                    'event_day_id' => $dayId,
                    'event_site_id' => $siteId,
                ];
            }
        }

        return $pairs;
    }

    /**
     * @param  Collection<int, BookingDayAllocation>  $activeAllocations
     * @return array<string, array{event_day_id: int, event_site_id: int, site_label?: string}>
     */
    private function buildCurrentPairs(Collection $activeAllocations): array
    {
        $pairs = [];
        foreach ($activeAllocations as $allocation) {
            $key = $this->pairKey((int) $allocation->event_day_id, (int) $allocation->event_site_id);
            $pairs[$key] = [
                'event_day_id' => (int) $allocation->event_day_id,
                'event_site_id' => (int) $allocation->event_site_id,
                'site_label' => $allocation->eventSite?->label,
            ];
        }

        return $pairs;
    }

    private function pairKey(int $dayId, int $siteId): string
    {
        return $dayId . ':' . $siteId;
    }
}
