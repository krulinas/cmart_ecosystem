<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\ItemReservation;
use App\Models\SurveyResponse;
use App\Support\CmartVenue;
use App\Support\ReportDateTimeFormatter;
use App\Support\ReportType;
use App\Support\SurveySchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Builds a privacy-safe, event-scoped Post-Event Summary snapshot.
 *
 * Core schema failures abort generation. Optional metrics are marked unavailable.
 * Missing data is never invented as zero when the metric was not calculated.
 */
class PostEventSummaryAggregator
{
    public const SCHEMA_VERSION = 2;

    public const TIMEZONE = ReportDateTimeFormatter::TIMEZONE;

    /**
     * @return array<string, mixed>
     */
    public function build(CarbootEvent $event): array
    {
        $this->assertCoreSchema();

        $dataAvailability = [];
        $warnings = [];
        $provisional = $this->isProvisional($event);
        $venue = CmartVenue::resolve($event);
        $generatedAt = now()->timezone(self::TIMEZONE);

        try {
            $bookingPipeline = $this->bookingPipeline($event->id);
            $attendance = $this->attendanceSummary($event->id, $dataAvailability);
            $invoiceSummary = $this->financialSummary($event->id, $warnings);
            $siteSummary = $this->eventSiteSummary($event->id);
            $siteDayUtilisation = $this->siteDayUtilisation($event->id, $dataAvailability);
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

        $mode = $this->analyticsSourceMode($event);
        $includeSystem = in_array($mode, ['combined', 'system_only'], true);

        // Survey is always evaluated; response membership is mode-filtered in forAnalytics().
        $vendorSurveySummary = $this->vendorSurveySummary($event->id, $dataAvailability, $mode);
        $environmental = $this->environmentalProxies($vendorSurveySummary);

        $startsIso = optional($event->starts_at)?->toIso8601String();
        $endsIso = optional($event->ends_at)?->toIso8601String();

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'report_type' => ReportType::POST_EVENT_SUMMARY,
            'generated_at' => $generatedAt->toIso8601String(),
            'generated_at_display' => ReportDateTimeFormatter::datetime($generatedAt->toIso8601String()),
            'timezone' => self::TIMEZONE,
            'provisional' => $provisional,
            'report_lifecycle_label' => $provisional ? 'Provisional' : 'Final',
            'analytics_source_mode' => $mode,
            'venue' => $venue,
            'language' => 'en',
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $startsIso,
                'ends_at' => $endsIso,
                'starts_at_display' => ReportDateTimeFormatter::datetime($startsIso),
                'ends_at_display' => ReportDateTimeFormatter::datetime($endsIso),
                'date_range_display' => ReportDateTimeFormatter::range($startsIso, $endsIso),
                'venue' => $venue,
            ],
            'sections' => [
                'booking_pipeline' => $includeSystem ? $bookingPipeline : ['excluded' => true],
                'attendance' => $includeSystem ? $attendance : ['excluded' => true],
                'payments' => $includeSystem ? $invoiceSummary : ['excluded' => true],
                'event_sites' => $includeSystem ? $siteSummary : ['excluded' => true],
                'site_day_utilisation' => $includeSystem ? $siteDayUtilisation : ['excluded' => true],
                'item_reservations' => $includeSystem ? $reservationCounts : ['excluded' => true],
                'vendor_categories' => $includeSystem ? [
                    'available' => true,
                    'distribution' => $categoryDistribution,
                    'note' => 'Counts use booking category labels only; no vendor identity is included.',
                ] : ['excluded' => true],
                'feedback' => $includeSystem ? $feedbackSummary : ['excluded' => true],
                'vendor_survey' => $vendorSurveySummary,
                'environmental_social' => $environmental,
            ],
            'methodology' => $this->methodologyNotes($provisional, $generatedAt, $warnings, $dataAvailability, $attendance, $siteDayUtilisation, $vendorSurveySummary, $invoiceSummary),
            'data_availability' => $dataAvailability,
            'data_quality_warnings' => $warnings,
        ];
    }

    private function analyticsSourceMode(CarbootEvent $event): string
    {
        if (! Schema::hasColumn('carboot_events', 'analytics_source_mode')) {
            return 'system_only';
        }

        $mode = (string) ($event->analytics_source_mode ?: 'system_only');

        return in_array($mode, ['combined', 'system_only', 'csv_only'], true)
            ? $mode
            : 'system_only';
    }

    private function assertCoreSchema(): void
    {
        foreach (['carboot_events', 'bookings', 'invoices', 'event_sites', 'vendor_categories'] as $table) {
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
     * @return array<string, mixed>
     */
    private function bookingPipeline(int $eventId): array
    {
        $byStatus = Booking::query()
            ->where('carboot_event_id', $eventId)
            ->select('approval_status', DB::raw('count(*) as aggregate'))
            ->groupBy('approval_status')
            ->pluck('aggregate', 'approval_status')
            ->map(fn ($count) => (int) $count)
            ->all();

        $total = array_sum($byStatus);
        $approved = (int) ($byStatus['Approved'] ?? 0);

        $pendingStatuses = ['Pending_Organizer', 'Pending_Staff', 'Pending_Boss'];
        $pendingTotal = 0;
        foreach ($pendingStatuses as $status) {
            $pendingTotal += (int) ($byStatus[$status] ?? 0);
        }

        $uniqueApplicants = (int) Booking::query()
            ->where('carboot_event_id', $eventId)
            ->whereNotNull('user_id')
            ->selectRaw('COUNT(DISTINCT user_id) as aggregate')
            ->value('aggregate');

        $approvedUniqueVendors = (int) Booking::query()
            ->where('carboot_event_id', $eventId)
            ->where('approval_status', 'Approved')
            ->whereNotNull('user_id')
            ->selectRaw('COUNT(DISTINCT user_id) as aggregate')
            ->value('aggregate');

        return [
            'available' => true,
            'by_approval_status' => $byStatus,
            'total_bookings' => $total,
            'pending_count' => $pendingTotal,
            'pending_organizer_count' => (int) ($byStatus['Pending_Organizer'] ?? 0),
            'needs_revision_count' => (int) ($byStatus['Needs_Revision'] ?? 0),
            'approved_count' => $approved,
            'rejected_count' => (int) ($byStatus['Rejected'] ?? 0),
            'cancelled_count' => (int) ($byStatus['Cancelled'] ?? 0),
            'withdrawn_count' => (int) ($byStatus['Withdrawn'] ?? 0),
            'unique_applicants' => $uniqueApplicants,
            'approved_unique_vendors' => $approvedUniqueVendors,
            'labels' => [
                'total_bookings' => 'Total booking applications',
                'unique_applicants' => 'Unique applicants',
                'approved_count' => 'Approved bookings',
                'approved_unique_vendors' => 'Approved unique vendors',
            ],
            'note' => 'Booking counts and unique-vendor counts are separate. Approved bookings are not verified attendance.',
        ];
    }

    /**
     * @param  array<string, mixed>  $dataAvailability
     * @return array<string, mixed>
     */
    private function attendanceSummary(int $eventId, array &$dataAvailability): array
    {
        if (! Schema::hasColumn('bookings', 'checked_in_at')) {
            $dataAvailability['attendance'] = 'omitted';
            $dataAvailability['attendance_note'] = 'Check-in column is not available.';

            return [
                'available' => false,
                'recorded' => false,
                'message' => 'Attendance verification was not recorded for this event.',
            ];
        }

        $checkedIn = (int) Booking::query()
            ->where('carboot_event_id', $eventId)
            ->whereNotNull('checked_in_at')
            ->count();

        if ($checkedIn === 0) {
            $dataAvailability['attendance'] = 'not_recorded';

            return [
                'available' => true,
                'recorded' => false,
                'verified_check_in_count' => null,
                'label' => 'Verified vendor check-ins',
                'message' => 'Attendance verification was not recorded for this event.',
                'note' => 'A single check-in timestamp does not prove complete multi-day attendance.',
            ];
        }

        return [
            'available' => true,
            'recorded' => true,
            'verified_check_in_count' => $checkedIn,
            'label' => 'Verified vendor check-ins',
            'message' => null,
            'note' => 'Approved bookings are not labelled as attendance. A single check-in timestamp does not prove complete multi-day attendance.',
        ];
    }

    /**
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function financialSummary(int $eventId, array &$warnings): array
    {
        $approvedBookingIds = Booking::query()
            ->where('carboot_event_id', $eventId)
            ->where('approval_status', 'Approved')
            ->pluck('id');

        $approvedInvoices = Invoice::query()
            ->whereIn('booking_id', $approvedBookingIds)
            ->get(['id', 'booking_id', 'amount', 'payment_status']);

        $approvedWithoutInvoice = max(0, $approvedBookingIds->count() - $approvedInvoices->pluck('booking_id')->unique()->count());
        if ($approvedWithoutInvoice > 0) {
            $warnings[] = sprintf(
                '%d approved booking(s) have no invoice; expected booth fees may be incomplete.',
                $approvedWithoutInvoice,
            );
        }

        $paidWithdrawnInvoices = Invoice::query()
            ->where('payment_status', 'Paid')
            ->whereHas('booking', function ($query) use ($eventId) {
                $query->where('carboot_event_id', $eventId)
                    ->where('approval_status', 'Withdrawn');
            })
            ->get(['amount', 'booking_id']);

        $paidWithdrawalAmount = round((float) $paidWithdrawnInvoices->sum('amount'), 2);
        $paidWithdrawalCount = $paidWithdrawnInvoices->count();

        $expectedApproved = round((float) $approvedInvoices->sum('amount'), 2);
        $collectedApprovedPaid = round((float) $approvedInvoices->where('payment_status', 'Paid')->sum('amount'), 2);
        $unpaid = round((float) $approvedInvoices->where('payment_status', 'Unpaid')->sum('amount'), 2);
        $pendingVerification = round((float) $approvedInvoices->where('payment_status', 'Pending Verification')->sum('amount'), 2);
        $refunded = round((float) $approvedInvoices->where('payment_status', 'Refunded')->sum('amount'), 2);

        $collectedTotal = round($collectedApprovedPaid + $paidWithdrawalAmount, 2);

        return [
            'available' => true,
            'currency' => 'MYR',
            'expected_booth_fees' => $expectedApproved,
            'collected_booth_fees' => $collectedTotal,
            'collected_from_approved_paid' => $collectedApprovedPaid,
            'unpaid_approved' => $unpaid,
            'pending_verification_approved' => $pendingVerification,
            'refunded_approved' => $refunded,
            // Legacy aliases for EventAnalyticsService / older consumers.
            'expected' => $expectedApproved,
            'collected' => $collectedTotal,
            'outstanding' => $unpaid,
            'approved_bookings_without_invoice' => $approvedWithoutInvoice,
            'invoice_count_approved' => $approvedInvoices->count(),
            'by_payment_status_approved' => [
                'Paid' => $approvedInvoices->where('payment_status', 'Paid')->count(),
                'Unpaid' => $approvedInvoices->where('payment_status', 'Unpaid')->count(),
                'Pending Verification' => $approvedInvoices->where('payment_status', 'Pending Verification')->count(),
                'Refunded' => $approvedInvoices->where('payment_status', 'Refunded')->count(),
            ],
            'paid_withdrawals' => [
                'count' => $paidWithdrawalCount,
                'amount' => $paidWithdrawalAmount,
                'included_in_collected' => true,
                'disclosure' => $paidWithdrawalCount > 0
                    ? sprintf(
                        'Includes RM %s from %d paid withdrawn booking(s) under the non-refundable withdrawal policy.',
                        number_format($paidWithdrawalAmount, 2, '.', ''),
                        $paidWithdrawalCount,
                    )
                    : null,
            ],
            'potentially_incomplete' => $approvedWithoutInvoice > 0,
            'scope' => 'Booth-fee invoices for this event. Vendor survey sales are not organizer revenue.',
            'note' => 'Paid withdrawn bookings are included in collected booth-fee revenue but are not approved participation or attendance.',
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
            'active_count' => (int) ($counts[EventSite::STATUS_ACTIVE] ?? 0),
            'by_operational_status' => $counts,
            'note' => 'Operational site status is not the same as site-day occupancy.',
        ];
    }

    /**
     * Occupied active site-days ÷ available active site-days × 100.
     *
     * @param  array<string, mixed>  $dataAvailability
     * @return array<string, mixed>
     */
    private function siteDayUtilisation(int $eventId, array &$dataAvailability): array
    {
        if (! Schema::hasTable('event_days') || ! Schema::hasTable('booking_day_allocations')) {
            $dataAvailability['site_day_utilisation'] = 'omitted';
            $dataAvailability['site_day_utilisation_note'] = 'Event days or allocations are not available.';

            return [
                'available' => false,
                'message' => 'Not available for this event',
            ];
        }

        $activeSiteIds = EventSite::query()
            ->where('carboot_event_id', $eventId)
            ->where('operational_status', EventSite::STATUS_ACTIVE)
            ->pluck('id');

        $dayIds = EventDay::query()
            ->where('carboot_event_id', $eventId)
            ->pluck('id');

        $availableSiteDays = $activeSiteIds->count() * $dayIds->count();

        if ($availableSiteDays === 0) {
            $dataAvailability['site_day_utilisation'] = 'unavailable_denominator';

            return [
                'available' => false,
                'available_active_site_days' => null,
                'occupied_site_days' => null,
                'utilisation_percent' => null,
                'message' => 'Not available for this event',
                'formula' => 'occupied active site-days ÷ available active site-days × 100',
                'note' => 'Site-day utilisation requires at least one active site and one event day. max_slots is not used as booth capacity.',
            ];
        }

        $occupiedPairs = BookingDayAllocation::query()
            ->whereIn('event_day_id', $dayIds)
            ->whereIn('event_site_id', $activeSiteIds)
            ->whereIn('allocation_status', BookingDayAllocation::OCCUPYING_STATUSES)
            ->where('active_lock', 1)
            ->select('event_site_id', 'event_day_id')
            ->distinct()
            ->get();

        $occupied = $occupiedPairs->count();
        $percent = round(($occupied / $availableSiteDays) * 100, 1);

        return [
            'available' => true,
            'label' => 'Site-day utilisation',
            'available_active_site_days' => $availableSiteDays,
            'occupied_site_days' => $occupied,
            'utilisation_percent' => $percent,
            'formula' => 'occupied active site-days ÷ available active site-days × 100',
            'note' => 'Unavailable and disabled sites are excluded from the denominator. Duplicate site/day allocations are counted once. This is site-day utilisation, not unique physical-booth occupancy. max_slots is not used.',
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

            return ['available' => false];
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
            'note' => 'Marketplace holds for this event only; not vendor lifetime listings.',
        ];
    }

    /**
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
     * @param  array<string, mixed>  $dataAvailability
     * @return array<string, mixed>
     */
    private function feedbackSummary(int $eventId, array &$dataAvailability): array
    {
        if (! Schema::hasTable('feedbacks') || ! Schema::hasColumn('feedbacks', 'carboot_event_id')) {
            $dataAvailability['feedback'] = 'omitted';

            return [
                'available' => false,
                'average_rating' => null,
                'response_count' => null,
                'message' => 'Not available for this event',
            ];
        }

        $stats = DB::table('feedbacks')
            ->where('carboot_event_id', $eventId)
            ->where(function ($query) {
                $query->whereNull('is_hidden')->orWhere('is_hidden', false);
            })
            ->selectRaw('count(*) as response_count, avg(rating) as average_rating')
            ->first();

        $count = (int) ($stats->response_count ?? 0);

        return [
            'available' => true,
            'response_count' => $count,
            'average_rating' => $count > 0 && $stats->average_rating !== null
                ? round((float) $stats->average_rating, 2)
                : null,
            'message' => $count === 0 ? 'No community feedback submissions were recorded for this event.' : null,
        ];
    }

    /**
     * Categorical survey aggregates only — no free-text.
     *
     * @param  array<string, mixed>  $dataAvailability
     * @return array<string, mixed>
     */
    private function vendorSurveySummary(int $eventId, array &$dataAvailability, string $mode): array
    {
        if (! Schema::hasTable('survey_responses')) {
            $dataAvailability['vendor_survey'] = 'omitted';

            return [
                'available' => false,
                'schema_name' => SurveySchema::NAME,
                'analytics_source_mode' => $mode,
                'message' => 'Not available for this event',
            ];
        }

        $responses = SurveyResponse::query()
            ->forAnalytics($eventId, $mode)
            ->get([
                'gross_sales_band',
                'experience_rating',
                'sales_purpose',
                'product_categories',
                'item_conditions',
                'has_difficulty',
                'event_info_sources',
                'items_sold_band',
                'unsold_item_actions',
                'improvement_areas',
                'supporting_activity_attracted_visitors',
                'supporting_activity_impacts',
            ]);

        if ($responses->isEmpty()) {
            $dataAvailability['vendor_survey'] = 'empty';

            return [
                'available' => false,
                'respondent_count' => null,
                'schema_name' => SurveySchema::NAME,
                'analytics_source_mode' => $mode,
                'state' => 'missing_source',
                'message' => 'No survey responses were collected for this event.',
                'note' => 'Survey respondents do not represent all vendors unless response rate is known.',
            ];
        }

        $n = $responses->count();
        $usedEligible = $responses->filter(function (SurveyResponse $row) {
            $conditions = $row->item_conditions ?? [];

            return is_array($conditions) && in_array('terpakai', $conditions, true);
        });
        $usedN = $usedEligible->count();

        return [
            'available' => true,
            'state' => 'available',
            'schema_name' => SurveySchema::NAME,
            'schema_version' => SurveySchema::VERSION,
            'analytics_source_mode' => $mode,
            'respondent_count' => $n,
            'base_display' => sprintf('n = %d responses', $n),
            'distributions' => [
                'gross_sales_band' => $this->singleSelectDistribution($responses, 'gross_sales_band', $n),
                'experience_rating' => $this->singleSelectDistribution($responses, 'experience_rating', $n),
                'sales_purpose' => $this->singleSelectDistribution($responses, 'sales_purpose', $n),
                'product_categories' => $this->multiSelectDistribution($responses, 'product_categories', $n),
                'item_conditions' => $this->multiSelectDistribution($responses, 'item_conditions', $n),
                'event_info_sources' => $this->multiSelectDistribution($responses, 'event_info_sources', $n),
                'improvement_areas' => $this->multiSelectDistribution($responses, 'improvement_areas', $n),
                'supporting_activity_attracted_visitors' => $this->singleSelectDistribution($responses, 'supporting_activity_attracted_visitors', $n),
                'supporting_activity_impacts' => $this->multiSelectDistribution($responses, 'supporting_activity_impacts', $n),
                'registration_difficulty' => $this->difficultyDistribution($responses, $n),
                'items_sold_band' => $this->singleSelectDistribution(
                    $usedEligible->values(),
                    'items_sold_band',
                    $usedN > 0 ? $usedN : $n,
                    $usedN > 0
                        ? 'Denominator is respondents who reported reused/preloved goods.'
                        : 'No reused-goods respondents; distribution uses overall respondent base only when eligible set is empty.',
                ),
                'unsold_item_actions' => $this->multiSelectDistribution(
                    $usedEligible->values(),
                    'unsold_item_actions',
                    $usedN > 0 ? $usedN : $n,
                    $usedN > 0
                        ? 'Denominator is respondents who reported reused/preloved goods.'
                        : null,
                ),
            ],
            'used_goods_respondent_count' => $usedN,
            'note' => 'Categorical survey aggregates only. Exact RM sales are not computed. Free-text answers are excluded. Multi-select percentages may exceed 100%.',
            'limitations' => [
                'Describes responding vendors only.',
                'Gross sales remain categorical bands.',
                'Unanswered values are not treated as zero.',
            ],
        ];
    }

    /**
     * @param  Collection<int, SurveyResponse>  $responses
     * @return array<string, mixed>
     */
    private function singleSelectDistribution(Collection $responses, string $field, int $denominator, ?string $denominatorNote = null): array
    {
        $counts = [];
        $answered = 0;
        foreach ($responses as $row) {
            $value = $row->{$field} ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $key = (string) $value;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $answered++;
        }

        $rows = [];
        foreach ($counts as $key => $count) {
            $rows[] = [
                'key' => $key,
                'label' => $key,
                'count' => $count,
                'denominator' => $denominator,
                'percent' => $denominator > 0 ? round(($count / $denominator) * 100, 1) : null,
            ];
        }

        usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);

        return [
            'answered' => $answered,
            'unanswered' => max(0, $denominator - $answered),
            'denominator' => $denominator,
            'base_display' => sprintf('n = %d responses', $denominator),
            'denominator_note' => $denominatorNote,
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, SurveyResponse>  $responses
     * @return array<string, mixed>
     */
    private function multiSelectDistribution(Collection $responses, string $field, int $denominator, ?string $denominatorNote = null): array
    {
        $counts = [];
        $respondentsWithAny = 0;
        foreach ($responses as $row) {
            $values = $row->{$field} ?? [];
            if (! is_array($values) || $values === []) {
                continue;
            }
            $respondentsWithAny++;
            foreach ($values as $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $key = (string) $value;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        $rows = [];
        foreach ($counts as $key => $count) {
            $rows[] = [
                'key' => $key,
                'label' => $key,
                'count' => $count,
                'denominator' => $denominator,
                'percent' => $denominator > 0 ? round(($count / $denominator) * 100, 1) : null,
            ];
        }
        usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);

        return [
            'answered_respondents' => $respondentsWithAny,
            'denominator' => $denominator,
            'base_display' => sprintf('n = %d responses', $denominator),
            'denominator_note' => $denominatorNote,
            'multi_select' => true,
            'multi_select_note' => 'Multi-select percentages may total more than 100%.',
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, SurveyResponse>  $responses
     * @return array<string, mixed>
     */
    private function difficultyDistribution(Collection $responses, int $denominator): array
    {
        $yes = 0;
        $no = 0;
        foreach ($responses as $row) {
            if ($row->has_difficulty === true) {
                $yes++;
            } elseif ($row->has_difficulty === false) {
                $no++;
            }
        }
        $answered = $yes + $no;

        if ($answered === 0) {
            return [
                'answered' => 0,
                'unanswered' => $denominator,
                'denominator' => $denominator,
                'base_display' => sprintf('n = %d responses', $denominator),
                'rows' => [],
                'message' => 'No registration difficulty answers were recorded.',
            ];
        }

        return [
            'answered' => $answered,
            'unanswered' => max(0, $denominator - $answered),
            'denominator' => $denominator,
            'base_display' => sprintf('n = %d responses', $denominator),
            'rows' => [
                [
                    'key' => 'yes',
                    'label' => 'Reported difficulty',
                    'count' => $yes,
                    'denominator' => $denominator,
                    'percent' => $denominator > 0 ? round(($yes / $denominator) * 100, 1) : null,
                ],
                [
                    'key' => 'no',
                    'label' => 'No difficulty reported',
                    'count' => $no,
                    'denominator' => $denominator,
                    'percent' => $denominator > 0 ? round(($no / $denominator) * 100, 1) : null,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $vendorSurveySummary
     * @return array<string, mixed>
     */
    private function environmentalProxies(array $vendorSurveySummary): array
    {
        if (! ($vendorSurveySummary['available'] ?? false)) {
            return [
                'available' => false,
                'message' => 'No survey insight is available for environmental and social indicators.',
            ];
        }

        $n = (int) ($vendorSurveySummary['respondent_count'] ?? 0);
        $used = (int) ($vendorSurveySummary['used_goods_respondent_count'] ?? 0);
        $unsold = $vendorSurveySummary['distributions']['unsold_item_actions']['rows'] ?? [];
        $itemsSold = $vendorSurveySummary['distributions']['items_sold_band'] ?? null;
        $supporting = $vendorSurveySummary['distributions']['supporting_activity_attracted_visitors'] ?? null;

        $actionCounts = [];
        foreach ($unsold as $row) {
            $actionCounts[$row['key']] = $row['count'];
        }

        return [
            'available' => true,
            'classification' => 'Vendor-reported survey-based proxy indicators',
            'base_display' => sprintf('n = %d responses', $n),
            'vendors_reporting_reused_goods' => $used,
            'used_stock_sales_bands' => $itemsSold,
            'plans_to_donate' => (int) ($actionCounts['sumbangkan'] ?? 0),
            'plans_to_recycle' => (int) ($actionCounts['kitar_semula'] ?? 0),
            'plans_to_relist_or_store' => (int) (($actionCounts['simpan_acara_lain'] ?? 0) + ($actionCounts['jual_dalam_talian'] ?? 0)),
            'plans_to_dispose' => (int) ($actionCounts['buang'] ?? 0),
            'supporting_activity_effect' => $supporting,
            'forbidden_metrics_excluded' => [
                'kilograms_diverted',
                'co2_avoided',
                'carbon_reduction',
                'exact_reused_units_sold',
                'monetary_loss_avoided',
            ],
            'note' => 'These are proxy indicators from self-reported survey answers. They are not verified diversion tonnes, CO₂ figures, or exact sales counts.',
        ];
    }

    /**
     * @param  array<string, mixed>  $dataAvailability
     * @param  array<string, mixed>  $attendance
     * @param  array<string, mixed>  $siteDayUtilisation
     * @param  array<string, mixed>  $vendorSurveySummary
     * @param  array<string, mixed>  $invoiceSummary
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function methodologyNotes(
        bool $provisional,
        $generatedAt,
        array $warnings,
        array $dataAvailability,
        array $attendance,
        array $siteDayUtilisation,
        array $vendorSurveySummary,
        array $invoiceSummary,
    ): array {
        return [
            'single_event_scope' => 'This report covers one carboot event only.',
            'data_cut_off' => ReportDateTimeFormatter::datetime($generatedAt->toIso8601String()),
            'timezone' => self::TIMEZONE,
            'language' => 'English',
            'provisional_or_final' => $provisional ? 'Provisional' : 'Final',
            'booking_versus_unique_vendors' => 'Booking application counts and unique applicant/vendor counts are reported separately.',
            'approved_not_attendance' => 'Approved bookings are not labelled as attendance.',
            'attendance_source' => ($attendance['recorded'] ?? false)
                ? 'Verified vendor check-ins use bookings.checked_in_at.'
                : ($attendance['message'] ?? 'Attendance verification was not recorded for this event.'),
            'site_day_utilisation_formula' => $siteDayUtilisation['formula'] ?? null,
            'survey_respondent_base' => ($vendorSurveySummary['available'] ?? false)
                ? ($vendorSurveySummary['base_display'] ?? null)
                : ($vendorSurveySummary['message'] ?? 'No survey responses were collected for this event.'),
            'multi_select_note' => 'Multi-select survey percentages may total more than 100%.',
            'financial_inclusion_rules' => 'Collected booth fees include Paid invoices for approved bookings plus Paid invoices for withdrawn bookings under the non-refundable withdrawal policy. Pending Verification and Refunded statuses are reported separately. Approved bookings without invoices are not treated as RM0 due.',
            'missing_data_rule' => 'Missing or unavailable metrics are omitted or shown as Not recorded / Not available — never invented as zero.',
            'data_quality_warnings' => $warnings,
            'data_availability' => $dataAvailability,
            'potentially_incomplete_finances' => (bool) ($invoiceSummary['potentially_incomplete'] ?? false),
        ];
    }
}
