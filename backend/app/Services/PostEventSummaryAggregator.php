<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\ItemReservation;
use App\Support\CmartVenue;
use App\Support\ReportType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Builds a safe, non-PII Post-Event Summary snapshot for a carboot event.
 *
 * Core schema failures abort generation. Optional metrics are marked unavailable.
 */
class PostEventSummaryAggregator
{
    public const SCHEMA_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function build(CarbootEvent $event): array
    {
        $this->assertCoreSchema();

        $dataAvailability = [];
        $provisional = $this->isProvisional($event);
        $venue = CmartVenue::resolve($event);

        try {
            $bookingCounts = $this->bookingApprovalCounts($event->id);
            $invoiceSummary = $this->invoiceSummaryForApprovedBookings($event->id);
            $siteSummary = $this->eventSiteSummary($event->id);
            $categoryDistribution = $this->vendorCategoryDistribution($event->id);
        } catch (Throwable $e) {
            Log::error('Post-event summary core aggregation failed.', [
                'carboot_event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Unable to generate the Post-Event Summary because required event data could not be aggregated.',
                0,
                $e,
            );
        }

        $reservationCounts = $this->itemReservationCounts($event->id, $dataAvailability);
        $feedbackSummary = $this->feedbackSummary($event->id, $dataAvailability);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'report_type' => ReportType::POST_EVENT_SUMMARY,
            'generated_at' => now()->toIso8601String(),
            'provisional' => $provisional,
            'venue' => $venue,
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'status' => $event->status,
                'starts_at' => optional($event->starts_at)?->toIso8601String(),
                'ends_at' => optional($event->ends_at)?->toIso8601String(),
                'max_slots' => $event->max_slots,
                'venue' => $venue,
            ],
            'sections' => [
                'booking_pipeline' => [
                    'by_approval_status' => $bookingCounts,
                    'total_bookings' => array_sum($bookingCounts),
                    'approved_count' => (int) ($bookingCounts['Approved'] ?? 0),
                ],
                'payments' => $invoiceSummary,
                'event_sites' => $siteSummary,
                'item_reservations' => $reservationCounts,
                'vendor_categories' => [
                    'distribution' => $categoryDistribution,
                    'note' => 'Counts use category labels only; no vendor identity is included.',
                ],
                'feedback' => $feedbackSummary,
            ],
            'data_availability' => $dataAvailability,
        ];
    }

    private function assertCoreSchema(): void
    {
        $requiredTables = [
            'carboot_events',
            'bookings',
            'invoices',
            'event_sites',
            'vendor_categories',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException(
                    "Unable to generate the Post-Event Summary because required table \"{$table}\" is missing."
                );
            }
        }

        foreach (['carboot_event_id', 'approval_status', 'product_category', 'vendor_category_id'] as $column) {
            if (! Schema::hasColumn('bookings', $column)) {
                throw new RuntimeException(
                    "Unable to generate the Post-Event Summary because required column bookings.{$column} is missing."
                );
            }
        }

        foreach (['amount', 'payment_status'] as $column) {
            if (! Schema::hasColumn('invoices', $column)) {
                throw new RuntimeException(
                    "Unable to generate the Post-Event Summary because required column invoices.{$column} is missing."
                );
            }
        }
    }

    private function isProvisional(CarbootEvent $event): bool
    {
        if ($event->status === 'Closed') {
            return false;
        }

        if ($event->ends_at !== null && $event->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, int>
     */
    private function bookingApprovalCounts(int $eventId): array
    {
        return Booking::query()
            ->where('carboot_event_id', $eventId)
            ->select('approval_status', DB::raw('count(*) as aggregate'))
            ->groupBy('approval_status')
            ->pluck('aggregate', 'approval_status')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return array{expected: float, collected: float, outstanding: float, invoice_count: int, scope: string}
     */
    private function invoiceSummaryForApprovedBookings(int $eventId): array
    {
        $invoices = Invoice::query()
            ->whereHas('booking', function ($query) use ($eventId) {
                $query->where('carboot_event_id', $eventId)
                    ->where('approval_status', 'Approved');
            })
            ->get(['amount', 'payment_status']);

        $expected = (float) $invoices->sum('amount');
        $collected = (float) $invoices->where('payment_status', 'Paid')->sum('amount');
        $outstanding = (float) $invoices->where('payment_status', 'Unpaid')->sum('amount');

        return [
            'expected' => round($expected, 2),
            'collected' => round($collected, 2),
            'outstanding' => round($outstanding, 2),
            'invoice_count' => $invoices->count(),
            'scope' => 'Approved bookings for this event only',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventSiteSummary(int $eventId): array
    {
        $counts = EventSite::query()
            ->where('carboot_event_id', $eventId)
            ->select('operational_status', DB::raw('count(*) as aggregate'))
            ->groupBy('operational_status')
            ->pluck('aggregate', 'operational_status')
            ->map(fn ($count) => (int) $count)
            ->all();

        return [
            'available' => true,
            'total' => array_sum($counts),
            'by_operational_status' => $counts,
        ];
    }

    /**
     * @param  array<string, mixed>  $dataAvailability
     * @return array<string, mixed>
     */
    private function itemReservationCounts(int $eventId, array &$dataAvailability): array
    {
        if (! Schema::hasTable('item_reservations')) {
            $dataAvailability['item_reservations'] = 'omitted';
            $dataAvailability['item_reservations_note'] = 'Item reservation table is not available in this environment.';

            return [
                'available' => false,
            ];
        }

        $counts = ItemReservation::query()
            ->where('carboot_event_id', $eventId)
            ->select('reservation_status', DB::raw('count(*) as aggregate'))
            ->groupBy('reservation_status')
            ->pluck('aggregate', 'reservation_status')
            ->map(fn ($count) => (int) $count)
            ->all();

        return [
            'available' => true,
            'total' => array_sum($counts),
            'by_reservation_status' => $counts,
        ];
    }

    /**
     * Category label distribution for approved bookings — no vendor PII.
     *
     * Uses a derived subquery so MySQL ONLY_FULL_GROUP_BY accepts the resolved alias.
     * Fallback order: category_label_snapshot → vendor_categories.label → product_category → Uncategorised.
     *
     * @return list<array{label: string, count: int}>
     */
    private function vendorCategoryDistribution(int $eventId): array
    {
        $hasSnapshot = Schema::hasColumn('bookings', 'category_label_snapshot');

        $resolvedExpression = $hasSnapshot
            ? "COALESCE(bookings.category_label_snapshot, vendor_categories.label, bookings.product_category, 'Uncategorised')"
            : "COALESCE(vendor_categories.label, bookings.product_category, 'Uncategorised')";

        $inner = Booking::query()
            ->where('bookings.carboot_event_id', $eventId)
            ->where('bookings.approval_status', 'Approved')
            ->leftJoin('vendor_categories', 'bookings.vendor_category_id', '=', 'vendor_categories.id')
            ->selectRaw("{$resolvedExpression} as resolved_category");

        $rows = DB::query()
            ->fromSub($inner, 'category_resolution')
            ->select('resolved_category', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('resolved_category')
            ->orderByDesc('aggregate')
            ->get();

        return $rows->map(fn ($row) => [
            'label' => (string) ($row->resolved_category ?: 'Uncategorised'),
            'count' => (int) $row->aggregate,
        ])->values()->all();
    }

    /**
     * Feedback has no carboot_event_id — omit averages with an availability note.
     *
     * @param  array<string, mixed>  $dataAvailability
     * @return array<string, mixed>
     */
    private function feedbackSummary(int $eventId, array &$dataAvailability): array
    {
        if (! Schema::hasTable('feedbacks') || ! Schema::hasColumn('feedbacks', 'carboot_event_id')) {
            $dataAvailability['feedback'] = 'omitted';
            $dataAvailability['feedback_note'] = 'Feedback rows are not linked to carboot events; averages cannot be scoped safely.';

            return [
                'available' => false,
                'average_rating' => null,
                'response_count' => null,
            ];
        }

        $stats = DB::table('feedbacks')
            ->where('carboot_event_id', $eventId)
            ->where(function ($query) {
                $query->whereNull('is_hidden')->orWhere('is_hidden', false);
            })
            ->selectRaw('count(*) as response_count, avg(rating) as average_rating')
            ->first();

        return [
            'available' => true,
            'response_count' => (int) ($stats->response_count ?? 0),
            'average_rating' => $stats->average_rating !== null
                ? round((float) $stats->average_rating, 2)
                : null,
        ];
    }
}
