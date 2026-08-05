<?php

namespace Tests\Feature;

use App\Http\Resources\CmartGeneratedReportResource;
use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\GeneratedReport;
use App\Models\Invoice;
use App\Models\RawSurveyUpload;
use App\Models\Space;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\PostEventSummaryAggregator;
use App\Services\ReportDraftService;
use App\Services\ReportPublicationService;
use App\Support\GeneratedReportStatus;
use App\Support\PostEventReportPdfViewData;
use App\Support\ReportType;
use App\Support\SurveySchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\Concerns\EnsuresCanonicalLayoutForSites;
use Tests\TestCase;

class EventReportV1DataFoundationTest extends TestCase
{
    use CleansUpTestFixtures;
    use EnsuresCanonicalLayoutForSites;

    /** @var list<int> */
    private array $createdReportIds = [];

    /** @var list<int> */
    private array $createdSurveyResponseIds = [];

    /** @var list<int> */
    private array $createdSurveyUploadIds = [];

    protected function tearDown(): void
    {
        try {
            if ($this->createdSurveyResponseIds !== []) {
                SurveyResponse::whereIn('id', $this->createdSurveyResponseIds)->delete();
            }
            if ($this->createdSurveyUploadIds !== []) {
                RawSurveyUpload::whereIn('id', $this->createdSurveyUploadIds)->delete();
            }
            if ($this->createdReportIds !== []) {
                DB::table('report_workflow_audits')
                    ->whereIn('generated_report_id', $this->createdReportIds)
                    ->delete();
                GeneratedReport::whereIn('id', $this->createdReportIds)->delete();
            }
        } finally {
            $this->createdReportIds = [];
            $this->createdSurveyResponseIds = [];
            $this->createdSurveyUploadIds = [];
            $this->cleanupTrackedFixtures();
            parent::tearDown();
        }
    }

    private function trackReport(GeneratedReport $report): GeneratedReport
    {
        $this->createdReportIds[] = $report->id;

        return $report;
    }

    private function user(string $role, string $prefix = 'report'): User
    {
        return $this->trackUser(User::create([
            'name' => "{$prefix} {$role} " . uniqid(),
            'email' => "{$prefix}-{$role}-" . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'none',
        ]));
    }

    private function space(): Space
    {
        return Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 30.00, 'status' => 'Available'],
        );
    }

    private function closedEvent(string $titlePrefix = 'Report Event', int $days = 2): CarbootEvent
    {
        $starts = now()->subDays(10)->setTime(10, 0, 0);
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => $titlePrefix . ' ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addDays(max(0, $days - 1))->setTime(22, 0, 0),
            'status' => 'Closed',
            'description' => 'Event report V1 foundation fixture',
            'max_slots' => 40,
            'day_generation_mode' => 'calendar_days',
            'analytics_source_mode' => 'combined',
        ]));

        return $event;
    }

    /**
     * @return array{days: \Illuminate\Support\Collection<int, EventDay>, sites: \Illuminate\Support\Collection<int, EventSite>}
     */
    private function seedLayout(CarbootEvent $event, int $siteCount = 2, int $dayCount = 2): array
    {
        $space = $this->space();
        $days = collect(range(0, $dayCount - 1))->map(function (int $offset) use ($event) {
            $dayStart = $event->starts_at->copy()->addDays($offset);
            $day = EventDay::create([
                'carboot_event_id' => $event->id,
                'operational_date' => $dayStart->toDateString(),
                'starts_at' => $dayStart,
                'ends_at' => $dayStart->copy()->setTime(22, 0, 0),
                'operational_status' => EventDay::STATUS_ACTIVE,
                'display_order' => $offset + 1,
            ]);
            $this->createdDayIds[] = $day->id;

            return $day;
        });

        $sites = collect(range(1, $siteCount))->map(function (int $position) use ($event, $space) {
            $site = EventSite::create([
                'carboot_event_id' => $event->id,
                'space_id' => $space->id,
                'label' => sprintf('R%02d', $position),
                'row_label' => 'R',
                'position_number' => $position,
                'grid_row' => 1,
                'grid_column' => $position,
                'display_order' => $position,
                'operational_status' => EventSite::STATUS_ACTIVE,
            ]);
            $this->createdSiteIds[] = $site->id;

            return $this->attachSiteToFoodLayout($event, $site, 'R');
        });

        return ['days' => $days, 'sites' => $sites];
    }

    private function createBooking(
        CarbootEvent $event,
        User $vendor,
        string $approvalStatus,
        ?float $invoiceAmount = 30.0,
        string $paymentStatus = 'Unpaid',
        bool $checkedIn = false,
    ): Booking {
        $category = $this->foodVendorCategory();
        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $this->space()->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'vendor_category_id' => $category->id,
            'product_category' => 'Food & Beverages',
            'product_details' => 'Event report V1 booking fixture with enough detail.',
            'approval_status' => $approvalStatus,
            'checked_in_at' => $checkedIn ? now()->subDay() : null,
        ]);
        $this->createdBookingIds[] = $booking->id;

        if ($invoiceAmount !== null) {
            $invoice = Invoice::create([
                'booking_id' => $booking->id,
                'amount' => $invoiceAmount,
                'payment_status' => $paymentStatus,
            ]);
            $this->createdInvoiceIds[] = $invoice->id;
        }

        return $booking->fresh(['invoice']);
    }

    private function occupy(Booking $booking, EventDay $day, EventSite $site, string $status = BookingDayAllocation::STATUS_CONFIRMED): void
    {
        $allocation = BookingDayAllocation::create([
            'booking_id' => $booking->id,
            'event_day_id' => $day->id,
            'event_site_id' => $site->id,
            'allocation_status' => $status,
            'reserved_at' => now()->subDays(2),
            'confirmed_at' => $status === BookingDayAllocation::STATUS_CONFIRMED ? now()->subDay() : null,
            'active_lock' => BookingDayAllocation::activeLockForStatus($status),
        ]);
        $this->createdAllocationIds[] = $allocation->id;
    }

    public function test_cmart_resource_strips_historical_free_text_and_pii(): void
    {
        $event = $this->closedEvent();
        $organizer = $this->user('organizer');

        $report = $this->trackReport(GeneratedReport::create([
            'carboot_event_id' => $event->id,
            'report_type' => ReportType::POST_EVENT_SUMMARY,
            'version' => 1,
            'status' => GeneratedReportStatus::PUBLISHED,
            'snapshot' => [
                'schema_version' => 1,
                'provisional' => false,
                'event' => ['title' => $event->title, 'status' => 'Available'],
                'sections' => [
                    'vendor_survey' => [
                        'respondent_count' => 1,
                        'qualitative_comments' => ['Please keep this secret'],
                        'comments_and_suggestions' => 'hidden',
                    ],
                    'booking_pipeline' => [
                        'total_bookings' => 1,
                        'booking_ids' => [101, 102],
                        'email' => 'vendor@example.com',
                        'phone' => '0123456789',
                    ],
                ],
            ],
            'organizer_observations' => 'Public organizer note',
            'organizer_recommendations' => 'Public organizer recommendation',
            'prepared_by' => $organizer->id,
            'published_by' => $organizer->id,
            'published_at' => now(),
            'event_title_snapshot' => $event->title,
            'event_starts_at_snapshot' => $event->starts_at,
            'event_ends_at_snapshot' => $event->ends_at,
        ]));

        $payload = (new CmartGeneratedReportResource($report))->toArray(Request::create('/'));
        $json = json_encode($payload);

        $this->assertStringNotContainsString('Please keep this secret', $json);
        $this->assertStringNotContainsString('vendor@example.com', $json);
        $this->assertStringNotContainsString('0123456789', $json);
        $this->assertArrayNotHasKey('qualitative_comments', $payload['snapshot']['sections']['vendor_survey']);
        $this->assertArrayNotHasKey('booking_ids', $payload['snapshot']['sections']['booking_pipeline']);
        $this->assertSame('Final', $payload['cover_status']);
        $this->assertSame('Public organizer note', $payload['organizer_observations']);

        $pdfData = PostEventReportPdfViewData::forAudience($report, 'cmart');
        $pdfJson = json_encode($pdfData['snapshot']);
        $this->assertStringNotContainsString('Please keep this secret', $pdfJson);
        $this->assertArrayNotHasKey('qualitative_comments', $pdfData['snapshot']['sections']['vendor_survey'] ?? []);
    }

    public function test_new_snapshot_excludes_qualitative_comments_and_unsupported_esg(): void
    {
        $event = $this->closedEvent();
        $this->seedLayout($event);
        $vendor = $this->user('community', 'survey');
        $this->createBooking($event, $vendor, 'Approved', 30, 'Paid');
        $this->seedSurvey($event, [
            [
                'gross_sales_band' => 'rm_100_299',
                'experience_rating' => 'puas_hati',
                'item_conditions' => ['terpakai'],
                'items_sold_band' => 'separuh',
                'unsold_item_actions' => ['sumbangkan', 'kitar_semula'],
                'comments_and_suggestions' => 'Raw free text must never enter the snapshot',
            ],
        ]);

        $snapshot = app(PostEventSummaryAggregator::class)->build($event->fresh());
        $json = json_encode($snapshot);

        $this->assertArrayNotHasKey('qualitative_comments', $snapshot['sections']['vendor_survey']);
        $this->assertStringNotContainsString('Raw free text must never enter the snapshot', $json);
        $this->assertArrayNotHasKey('kilograms_diverted', $snapshot['sections']['environmental_social']);
        $this->assertArrayNotHasKey('co2_avoided', $snapshot['sections']['environmental_social']);
        $this->assertContains('kilograms_diverted', $snapshot['sections']['environmental_social']['forbidden_metrics_excluded']);
        $this->assertSame('en', $snapshot['language']);
        $this->assertNotNull($snapshot['event']['date_range_display']);
        $this->assertDoesNotMatchRegularExpression(
            '/\d{4}-\d{2}-\d{2}T/',
            (string) $snapshot['event']['date_range_display'],
        );
    }

    public function test_event_isolation_between_a_and_b(): void
    {
        $vendor = $this->user('community', 'shared');
        $eventA = $this->closedEvent('Event A', 1);
        $eventB = $this->closedEvent('Event B', 1);
        $layoutA = $this->seedLayout($eventA, 1, 1);
        $layoutB = $this->seedLayout($eventB, 1, 1);

        $bookingA = $this->createBooking($eventA, $vendor, 'Approved', 40, 'Paid', true);
        $bookingB = $this->createBooking($eventB, $vendor, 'Approved', 55, 'Unpaid', false);
        $this->occupy($bookingA, $layoutA['days'][0], $layoutA['sites'][0]);
        $this->occupy($bookingB, $layoutB['days'][0], $layoutB['sites'][0]);
        $this->seedSurvey($eventA, [['gross_sales_band' => 'rm_100_299', 'experience_rating' => 'puas_hati']]);
        $this->seedSurvey($eventB, [
            ['gross_sales_band' => 'rm_500_plus', 'experience_rating' => 'sangat_puas_hati'],
            ['gross_sales_band' => 'rm_500_plus', 'experience_rating' => 'puas_hati'],
        ]);

        $snapA = app(PostEventSummaryAggregator::class)->build($eventA->fresh());
        $snapB = app(PostEventSummaryAggregator::class)->build($eventB->fresh());

        $this->assertSame(1, $snapA['sections']['booking_pipeline']['total_bookings']);
        $this->assertSame(1, $snapB['sections']['booking_pipeline']['total_bookings']);
        $this->assertSame(40.0, $snapA['sections']['payments']['expected_booth_fees']);
        $this->assertSame(55.0, $snapB['sections']['payments']['expected_booth_fees']);
        $this->assertSame(1, $snapA['sections']['attendance']['verified_check_in_count']);
        $this->assertFalse($snapB['sections']['attendance']['recorded']);
        $this->assertSame(1, $snapA['sections']['vendor_survey']['respondent_count']);
        $this->assertSame(2, $snapB['sections']['vendor_survey']['respondent_count']);
    }

    public function test_published_snapshot_immutability_and_revision(): void
    {
        $event = $this->closedEvent();
        $this->seedLayout($event);
        $organizer = $this->user('organizer');
        $vendor = $this->user('community');
        $this->createBooking($event, $vendor, 'Approved', 30, 'Paid');

        $drafts = app(ReportDraftService::class);
        $publication = app(ReportPublicationService::class);

        $v1 = $this->trackReport($drafts->generate($event, $organizer));
        $v1Approved = $v1->snapshot['sections']['booking_pipeline']['approved_count'];
        $v1Collected = $v1->snapshot['sections']['payments']['collected_booth_fees'];

        $published = $this->trackReport($publication->publish($v1, $organizer));
        $this->assertSame(GeneratedReportStatus::PUBLISHED, $published->status);

        $this->createBooking($event, $this->user('community', 'late'), 'Approved', 99, 'Paid');

        $frozen = GeneratedReport::findOrFail($published->id);
        $this->assertSame($v1Approved, $frozen->snapshot['sections']['booking_pipeline']['approved_count']);
        $this->assertSame($v1Collected, $frozen->snapshot['sections']['payments']['collected_booth_fees']);

        $revision = $this->trackReport($drafts->createRevision($frozen, $organizer, 'Updated live totals'));
        $this->assertSame(2, $revision->version);
        $this->assertSame(2, $revision->snapshot['sections']['booking_pipeline']['approved_count']);
        $this->assertNotEquals(
            $v1Collected,
            $revision->snapshot['sections']['payments']['collected_booth_fees'],
        );
    }

    public function test_booking_pipeline_unique_vendors_and_statuses(): void
    {
        $event = $this->closedEvent();
        $this->seedLayout($event);
        $vendorA = $this->user('community', 'a');
        $vendorB = $this->user('community', 'b');

        $this->createBooking($event, $vendorA, 'Approved', 30, 'Paid');
        $this->createBooking($event, $vendorA, 'Approved', 30, 'Unpaid');
        $this->createBooking($event, $vendorB, 'Pending_Organizer', 30, 'Unpaid');
        $this->createBooking($event, $vendorB, 'Needs_Revision', 30, 'Unpaid');
        $this->createBooking($event, $vendorB, 'Rejected', 30, 'Unpaid');
        $this->createBooking($event, $vendorB, 'Cancelled', 30, 'Unpaid');
        $this->createBooking($event, $vendorB, 'Withdrawn', 30, 'Paid');

        $snapshot = app(PostEventSummaryAggregator::class)->build($event->fresh());
        $pipeline = $snapshot['sections']['booking_pipeline'];

        $this->assertSame(7, $pipeline['total_bookings']);
        $this->assertSame(2, $pipeline['unique_applicants']);
        $this->assertSame(2, $pipeline['approved_count']);
        $this->assertSame(1, $pipeline['approved_unique_vendors']);
        $this->assertSame(1, $pipeline['pending_count']);
        $this->assertSame(1, $pipeline['needs_revision_count']);
        $this->assertSame(1, $pipeline['rejected_count']);
        $this->assertSame(1, $pipeline['cancelled_count']);
        $this->assertSame(1, $pipeline['withdrawn_count']);
    }

    public function test_attendance_check_in_versus_approved_and_missing(): void
    {
        $event = $this->closedEvent();
        $this->seedLayout($event);
        $vendor = $this->user('community');
        $this->createBooking($event, $vendor, 'Approved', 30, 'Paid', false);

        $missing = app(PostEventSummaryAggregator::class)->build($event->fresh());
        $this->assertFalse($missing['sections']['attendance']['recorded']);
        $this->assertNull($missing['sections']['attendance']['verified_check_in_count']);
        $this->assertStringContainsString(
            'Attendance verification was not recorded',
            $missing['sections']['attendance']['message'],
        );

        $this->createBooking($event, $this->user('community', 'in'), 'Approved', 30, 'Paid', true);
        $withCheckIn = app(PostEventSummaryAggregator::class)->build($event->fresh());
        $this->assertTrue($withCheckIn['sections']['attendance']['recorded']);
        $this->assertSame(1, $withCheckIn['sections']['attendance']['verified_check_in_count']);
        $this->assertSame(2, $withCheckIn['sections']['booking_pipeline']['approved_count']);
    }

    public function test_site_day_utilisation_multi_day_and_zero_denominator(): void
    {
        $event = $this->closedEvent('Util Event', 2);
        $layout = $this->seedLayout($event, 2, 2);
        $booking = $this->createBooking($event, $this->user('community'), 'Approved', 30, 'Paid');
        $this->occupy($booking, $layout['days'][0], $layout['sites'][0]);
        $this->occupy($booking, $layout['days'][1], $layout['sites'][0]);

        $released = BookingDayAllocation::create([
            'booking_id' => $booking->id,
            'event_day_id' => $layout['days'][0]->id,
            'event_site_id' => $layout['sites'][1]->id,
            'allocation_status' => BookingDayAllocation::STATUS_RELEASED,
            'reserved_at' => now()->subDays(3),
            'released_at' => now()->subDay(),
            'active_lock' => BookingDayAllocation::activeLockForStatus(BookingDayAllocation::STATUS_RELEASED),
        ]);
        $this->createdAllocationIds[] = $released->id;

        $snapshot = app(PostEventSummaryAggregator::class)->build($event->fresh());
        $util = $snapshot['sections']['site_day_utilisation'];
        $this->assertTrue($util['available']);
        $this->assertSame(4, $util['available_active_site_days']);
        $this->assertSame(2, $util['occupied_site_days']);
        $this->assertSame(50.0, $util['utilisation_percent']);

        $empty = $this->closedEvent('No Sites', 1);
        // No sites/days seeded.
        $emptySnap = app(PostEventSummaryAggregator::class)->build($empty->fresh());
        $this->assertFalse($emptySnap['sections']['site_day_utilisation']['available']);
        $this->assertNull($emptySnap['sections']['site_day_utilisation']['utilisation_percent']);
    }

    public function test_financial_paid_withdrawals_and_missing_invoice(): void
    {
        $event = $this->closedEvent();
        $this->seedLayout($event);
        $this->createBooking($event, $this->user('community', 'paid'), 'Approved', 50, 'Paid');
        $this->createBooking($event, $this->user('community', 'unpaid'), 'Approved', 20, 'Unpaid');
        $this->createBooking($event, $this->user('community', 'pending'), 'Approved', 10, 'Pending Verification');
        $this->createBooking($event, $this->user('community', 'wd'), 'Withdrawn', 40, 'Paid');
        $this->createBooking($event, $this->user('community', 'noinv'), 'Approved', null);

        $payments = app(PostEventSummaryAggregator::class)->build($event->fresh())['sections']['payments'];

        $this->assertSame(80.0, $payments['expected_booth_fees']);
        $this->assertSame(90.0, $payments['collected_booth_fees']);
        $this->assertSame(20.0, $payments['unpaid_approved']);
        $this->assertSame(10.0, $payments['pending_verification_approved']);
        $this->assertSame(1, $payments['approved_bookings_without_invoice']);
        $this->assertTrue($payments['potentially_incomplete']);
        $this->assertSame(1, $payments['paid_withdrawals']['count']);
        $this->assertSame(40.0, $payments['paid_withdrawals']['amount']);
        $this->assertStringContainsString('Includes RM 40.00 from 1 paid withdrawn', $payments['paid_withdrawals']['disclosure']);
    }

    public function test_survey_categorical_conditional_and_multi_select(): void
    {
        $event = $this->closedEvent();
        $this->seedLayout($event);
        $this->seedSurvey($event, [
            [
                'gross_sales_band' => 'rm_100_299',
                'experience_rating' => 'puas_hati',
                'item_conditions' => ['terpakai'],
                'items_sold_band' => 'kebanyakan',
                'unsold_item_actions' => ['sumbangkan', 'kitar_semula'],
                'product_categories' => ['pakaian', 'buku', 'barangan_elektronik'],
                'has_difficulty' => true,
            ],
            [
                'gross_sales_band' => 'rm_100_299',
                'experience_rating' => 'neutral',
                'item_conditions' => ['baru'],
                'product_categories' => ['pakaian', 'buku'],
                'has_difficulty' => false,
            ],
            [
                'gross_sales_band' => null,
                'experience_rating' => null,
                'item_conditions' => null,
            ],
        ]);

        $survey = app(PostEventSummaryAggregator::class)->build($event->fresh())['sections']['vendor_survey'];
        $this->assertTrue($survey['available']);
        $this->assertSame(3, $survey['respondent_count']);
        $this->assertSame(1, $survey['used_goods_respondent_count']);
        $this->assertSame(1, $survey['distributions']['items_sold_band']['denominator']);
        $this->assertSame(1, $survey['distributions']['unsold_item_actions']['rows'][0]['denominator'] ?? 1);
        $multi = $survey['distributions']['product_categories'];
        $this->assertTrue($multi['multi_select']);
        $this->assertGreaterThan(100, collect($multi['rows'])->sum('percent'));
        $this->assertSame(1, $survey['distributions']['gross_sales_band']['unanswered']);
    }

    public function test_no_survey_responses_are_not_zero(): void
    {
        $event = $this->closedEvent();
        $this->seedLayout($event);
        $survey = app(PostEventSummaryAggregator::class)->build($event->fresh())['sections']['vendor_survey'];
        $this->assertFalse($survey['available']);
        $this->assertNull($survey['respondent_count'] ?? null);
        $this->assertStringContainsString('No survey responses were collected', $survey['message']);
    }

    public function test_legacy_snapshot_compatibility_through_cmart_resource(): void
    {
        $event = $this->closedEvent();
        $report = $this->trackReport(GeneratedReport::create([
            'carboot_event_id' => $event->id,
            'report_type' => ReportType::POST_EVENT_SUMMARY,
            'version' => 1,
            'status' => GeneratedReportStatus::PUBLISHED,
            'snapshot' => [
                'schema_version' => 1,
                'provisional' => true,
                'event' => ['title' => $event->title, 'status' => 'Available', 'starts_at' => $event->starts_at->toIso8601String()],
                'sections' => [
                    'booking_pipeline' => ['total_bookings' => 2, 'approved_count' => 1],
                    'payments' => ['expected' => 10, 'collected' => 5, 'outstanding' => 5],
                ],
                'data_availability' => ['feedback' => 'omitted'],
            ],
            'prepared_by' => $this->user('organizer')->id,
            'published_by' => $this->user('organizer', 'pub')->id,
            'published_at' => now(),
            'event_title_snapshot' => $event->title,
            'event_starts_at_snapshot' => $event->starts_at,
            'event_ends_at_snapshot' => $event->ends_at,
        ]));

        $payload = (new CmartGeneratedReportResource($report->fresh('publishedByUser')))->toArray(Request::create('/'));
        $this->assertSame('Provisional', $payload['cover_status']);
        $this->assertSame(2, $payload['snapshot']['sections']['booking_pipeline']['total_bookings']);
        $this->assertArrayNotHasKey('status', $payload['snapshot']['event']);
        $this->assertSame(5, $payload['snapshot']['sections']['payments']['collected']);
    }

    public function test_cmart_api_returns_filtered_snapshot(): void
    {
        $event = $this->closedEvent();
        $cmart = $this->user('cmart_management');
        $report = $this->trackReport(GeneratedReport::create([
            'carboot_event_id' => $event->id,
            'report_type' => ReportType::POST_EVENT_SUMMARY,
            'version' => 1,
            'status' => GeneratedReportStatus::PUBLISHED,
            'snapshot' => [
                'sections' => [
                    'vendor_survey' => [
                        'qualitative_comments' => ['leak'],
                    ],
                ],
            ],
            'prepared_by' => $this->user('organizer')->id,
            'published_by' => $this->user('organizer', 'pub2')->id,
            'published_at' => now(),
            'event_title_snapshot' => $event->title,
            'event_starts_at_snapshot' => $event->starts_at,
            'event_ends_at_snapshot' => $event->ends_at,
        ]));

        Sanctum::actingAs($cmart);
        $response = $this->getJson("/api/cmart/generated-reports/{$report->id}")->assertOk();
        $payload = $response->json('data') ?? $response->json();
        $json = json_encode($payload);
        $this->assertStringNotContainsString('leak', $json);
        $this->assertArrayNotHasKey(
            'qualitative_comments',
            $payload['snapshot']['sections']['vendor_survey'] ?? [],
        );
    }

    public function test_analytics_source_mode_filters_survey_respondent_bases(): void
    {
        $event = $this->closedEvent('Mode Event', 1);
        $this->seedLayout($event, 1, 1);
        $event->update(['analytics_source_mode' => 'combined']);

        // Active CSV batch: 2 valid CSV responses (+ 1 inactive ignored later).
        $this->seedSurvey($event, [
            ['gross_sales_band' => 'rm_100_299', 'experience_rating' => 'puas_hati'],
            ['gross_sales_band' => 'rm_300_499', 'experience_rating' => 'neutral'],
        ]);

        // Valid system submissions: 3
        for ($i = 0; $i < 3; $i++) {
            $response = SurveyResponse::create([
                'carboot_event_id' => $event->id,
                'import_batch_id' => null,
                'submission_source' => SurveyResponse::SOURCE_SYSTEM_SUBMISSION,
                'schema_name' => SurveySchema::NAME,
                'schema_version' => SurveySchema::VERSION,
                'source_row_number' => 1000 + $i,
                'respondent_id' => 'system-respondent-' . uniqid() . '-' . $i,
                'validation_status' => 'valid',
                'is_active' => true,
                'gross_sales_band' => 'rm_500_plus',
                'experience_rating' => 'sangat_puas_hati',
                'comments_and_suggestions' => 'MODE_FIXTURE_FREE_TEXT_MUST_NOT_APPEAR',
            ]);
            $this->createdSurveyResponseIds[] = $response->id;
        }

        // Invalid system response must never count.
        $invalid = SurveyResponse::create([
            'carboot_event_id' => $event->id,
            'import_batch_id' => null,
            'submission_source' => SurveyResponse::SOURCE_SYSTEM_SUBMISSION,
            'schema_name' => SurveySchema::NAME,
            'schema_version' => SurveySchema::VERSION,
            'source_row_number' => 2000,
            'respondent_id' => 'invalid-system-' . uniqid(),
            'validation_status' => 'invalid',
            'is_active' => true,
            'gross_sales_band' => 'rm_500_plus',
        ]);
        $this->createdSurveyResponseIds[] = $invalid->id;

        $aggregator = app(PostEventSummaryAggregator::class);

        $event->update(['analytics_source_mode' => 'combined']);
        $combined = $aggregator->build($event->fresh());
        $this->assertSame(5, $combined['sections']['vendor_survey']['respondent_count']);
        $this->assertStringNotContainsString(
            'MODE_FIXTURE_FREE_TEXT_MUST_NOT_APPEAR',
            json_encode($combined),
        );

        $event->update(['analytics_source_mode' => 'csv_only']);
        $csvOnly = $aggregator->build($event->fresh());
        $this->assertSame(2, $csvOnly['sections']['vendor_survey']['respondent_count']);

        $event->update(['analytics_source_mode' => 'system_only']);
        $systemOnly = $aggregator->build($event->fresh());
        $this->assertTrue($systemOnly['sections']['vendor_survey']['available']);
        $this->assertSame(3, $systemOnly['sections']['vendor_survey']['respondent_count']);
        $this->assertSame('system_only', $systemOnly['sections']['vendor_survey']['analytics_source_mode']);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function seedSurvey(CarbootEvent $event, array $rows): void
    {
        if (! Schema::hasTable('survey_responses') || ! Schema::hasTable('raw_survey_uploads')) {
            $this->markTestSkipped('Survey tables are not available.');
        }

        $batch = RawSurveyUpload::create([
            'carboot_event_id' => $event->id,
            'uploaded_by' => $this->user('organizer', 'survey-up')->id,
            'schema_name' => SurveySchema::NAME,
            'schema_version' => SurveySchema::VERSION,
            'original_filename' => 'fixture.csv',
            'storage_disk' => 'local',
            'storage_path' => 'surveys/fixture-' . uniqid() . '.csv',
            'mime_type' => 'text/csv',
            'file_size' => 12,
            'sha256' => hash('sha256', uniqid('', true)),
            'status' => RawSurveyUpload::STATUS_COMPLETED,
            'is_active' => true,
            'submission_source' => RawSurveyUpload::SOURCE_CSV_IMPORT,
            'total_row_count' => count($rows),
            'valid_row_count' => count($rows),
            'invalid_row_count' => 0,
        ]);
        $this->createdSurveyUploadIds[] = $batch->id;

        foreach ($rows as $index => $row) {
            $response = SurveyResponse::create(array_merge([
                'carboot_event_id' => $event->id,
                'import_batch_id' => $batch->id,
                'submission_source' => SurveyResponse::SOURCE_CSV_IMPORT,
                'schema_name' => SurveySchema::NAME,
                'schema_version' => SurveySchema::VERSION,
                'source_row_number' => $index + 1,
                'respondent_id' => 'fixture-respondent-' . uniqid() . '-' . ($index + 1),
                'validation_status' => 'valid',
                'is_active' => true,
            ], $row));
            $this->createdSurveyResponseIds[] = $response->id;
        }
    }
}
