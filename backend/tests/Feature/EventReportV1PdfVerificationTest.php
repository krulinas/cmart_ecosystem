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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\Concerns\EnsuresCanonicalLayoutForSites;
use Tests\TestCase;

/**
 * Schema-v2 DomPDF generation + privacy/immutability checkpoint fixtures (cmart_test only).
 */
class EventReportV1PdfVerificationTest extends TestCase
{
    use CleansUpTestFixtures;
    use EnsuresCanonicalLayoutForSites;

    /** @var list<int> */
    private array $createdReportIds = [];

    /** @var list<int> */
    private array $createdSurveyResponseIds = [];

    /** @var list<int> */
    private array $createdSurveyUploadIds = [];

    private string $artifactDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artifactDir = storage_path('app/tmp/event-report-v1-verification');
        File::ensureDirectoryExists($this->artifactDir);
    }

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

    private function user(string $role, string $prefix = 'pdfv'): User
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
        return Space::defaultPhysical();
    }

    public function test_schema_v2_pdf_privacy_immutability_and_legacy_v1_render(): void
    {
        if (! class_exists(Pdf::class)) {
            $this->markTestSkipped('DomPDF is not available.');
        }

        $organizer = $this->user('organizer');
        $starts = now()->subDays(8)->setTime(10, 0, 0);
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Verification Carboot ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addDay()->setTime(22, 0, 0),
            'status' => 'Closed',
            'description' => 'Phase 1 verification fixture',
            'max_slots' => 40,
            'day_generation_mode' => 'calendar_days',
            'analytics_source_mode' => 'combined',
        ]));

        $layout = $this->seedLayout($event, 2, 2);
        $vendorA = $this->user('community', 'va');
        $vendorB = $this->user('community', 'vb');

        $approvedPaid = $this->createBooking($event, $vendorA, 'Approved', 50, 'Paid', true);
        $this->createBooking($event, $vendorA, 'Approved', 20, 'Unpaid', false);
        $this->createBooking($event, $vendorB, 'Approved', 15, 'Pending Verification', false);
        $this->createBooking($event, $vendorB, 'Pending_Organizer', 30, 'Unpaid', false);
        $this->createBooking($event, $vendorB, 'Needs_Revision', 30, 'Unpaid', false);
        $this->createBooking($event, $vendorB, 'Rejected', 30, 'Unpaid', false);
        $this->createBooking($event, $vendorB, 'Cancelled', 30, 'Unpaid', false);
        $this->createBooking($event, $vendorB, 'Withdrawn', 40, 'Paid', false);
        $this->createBooking($event, $this->user('community', 'noinv'), 'Approved', null, 'Unpaid', false);

        $this->occupy($approvedPaid, $layout['days'][0], $layout['sites'][0]);
        $this->occupy($approvedPaid, $layout['days'][1], $layout['sites'][0]);

        $this->seedMixedSurvey($event);

        $drafts = app(ReportDraftService::class);
        $publication = app(ReportPublicationService::class);

        $v1 = $this->trackReport($drafts->generate($event, $organizer));
        $v1->update([
            'organizer_observations' => 'Verification observations: turnout felt steady across both days.',
            'organizer_recommendations' => 'Verification recommendations: keep multi-day site allocation guidance clear.',
        ]);
        $v1 = $v1->fresh();

        $this->assertSame(2, $v1->snapshot['schema_version']);
        $this->assertTrue($v1->snapshot['sections']['payments']['potentially_incomplete']);
        $this->assertNotNull($v1->snapshot['sections']['payments']['paid_withdrawals']['disclosure']);
        $this->assertArrayNotHasKey('qualitative_comments', $v1->snapshot['sections']['vendor_survey']);

        $published = $this->trackReport($publication->publish($v1, $organizer));
        $frozenSnapshot = $published->snapshot;
        $frozenJson = json_encode($frozenSnapshot);

        $viewData = PostEventReportPdfViewData::forAudience($published, 'cmart');
        $pdf = Pdf::loadView('reports.post_event_summary', $viewData)->setPaper('a4');
        $pdfBinary = $pdf->output();
        $pdfPath = $this->artifactDir . DIRECTORY_SEPARATOR . 'schema-v2-post-event-summary.pdf';
        File::put($pdfPath, $pdfBinary);
        $this->assertGreaterThan(1000, strlen($pdfBinary));
        File::put($this->artifactDir . DIRECTORY_SEPARATOR . 'schema-v2-manifest.json', json_encode([
            'pdf_path' => $pdfPath,
            'bytes' => strlen($pdfBinary),
            'report_id' => $published->id,
            'version' => $published->version,
            'schema_version' => $published->snapshot['schema_version'] ?? null,
            'generated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));

        $text = $this->extractPdfTextApprox($viewData);
        File::put($this->artifactDir . DIRECTORY_SEPARATOR . 'schema-v2-extracted-text.txt', $text);

        foreach ([
            'VERIFICATION_RAW_COMMENT_MARKER',
            'leak@example.com',
            '0123456789',
            'payment_proof_path',
            'demo-gateway/',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $text, "PDF text leaked: {$forbidden}");
        }
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:/', $text);
        $this->assertStringNotContainsString('Report status: Available', $text);
        $this->assertStringNotContainsString('<td>Available</td>', view('reports.post_event_summary', $viewData)->render());
        $this->assertStringContainsString('RM', $text);
        $this->assertStringContainsString('Final', $text);
        $this->assertStringContainsString('Site-day utilisation', $text);
        $this->assertStringContainsString('Verification observations', $text);
        $this->assertStringContainsString('Includes RM', $text);

        // Mutate live data; published V1 must stay frozen.
        $this->createBooking($event, $this->user('community', 'late'), 'Approved', 99, 'Paid', true);
        $reloaded = GeneratedReport::findOrFail($published->id);
        $this->assertSame($frozenJson, json_encode($reloaded->snapshot));

        $rerender = Pdf::loadView(
            'reports.post_event_summary',
            PostEventReportPdfViewData::forAudience($reloaded, 'cmart'),
        )->setPaper('a4')->output();
        $this->assertSame(
            $frozenSnapshot['sections']['booking_pipeline']['approved_count'],
            $reloaded->snapshot['sections']['booking_pipeline']['approved_count'],
        );
        $this->assertGreaterThan(1000, strlen($rerender));

        $revision = $this->trackReport($drafts->createRevision($reloaded, $organizer, 'Capture late booking'));
        $this->assertSame(2, $revision->version);
        $this->assertGreaterThan(
            $frozenSnapshot['sections']['booking_pipeline']['approved_count'],
            $revision->snapshot['sections']['booking_pipeline']['approved_count'],
        );

        $publishedV2 = $this->trackReport($publication->publish($revision, $organizer));
        $this->assertSame(GeneratedReportStatus::SUPERSEDED, GeneratedReport::findOrFail($published->id)->status);
        $this->assertSame(GeneratedReportStatus::PUBLISHED, $publishedV2->status);
        $this->assertSame(
            $frozenJson,
            json_encode(GeneratedReport::findOrFail($published->id)->snapshot),
        );

        // Historical schema-v1 privacy + presentation path.
        $legacy = $this->trackReport(GeneratedReport::create([
            'carboot_event_id' => $event->id,
            'report_type' => ReportType::POST_EVENT_SUMMARY,
            'version' => 9,
            'status' => GeneratedReportStatus::SUPERSEDED,
            'snapshot' => [
                'schema_version' => 1,
                'provisional' => false,
                'event' => [
                    'title' => $event->title,
                    'status' => 'Available',
                    'starts_at' => $event->starts_at->toIso8601String(),
                    'ends_at' => $event->ends_at->toIso8601String(),
                ],
                'sections' => [
                    'booking_pipeline' => ['total_bookings' => 4, 'approved_count' => 2],
                    'payments' => ['expected' => 70.5, 'collected' => 50.0, 'outstanding' => 20.5],
                    'vendor_survey' => [
                        'respondent_count' => 1,
                        'qualitative_comments' => ['LEGACY_FREE_TEXT_MARKER'],
                        'email' => 'legacy@example.com',
                        'booking_ids' => [11, 22],
                    ],
                ],
            ],
            'organizer_observations' => 'Legacy organizer note',
            'prepared_by' => $organizer->id,
            'published_by' => $organizer->id,
            'published_at' => now()->subDay(),
            'event_title_snapshot' => $event->title,
            'event_starts_at_snapshot' => $event->starts_at,
            'event_ends_at_snapshot' => $event->ends_at,
        ]));

        $originalLegacy = json_encode($legacy->snapshot);
        $cmartPayload = (new CmartGeneratedReportResource($legacy))->toArray(Request::create('/'));
        $this->assertArrayNotHasKey('qualitative_comments', $cmartPayload['snapshot']['sections']['vendor_survey']);
        $this->assertArrayNotHasKey('email', $cmartPayload['snapshot']['sections']['vendor_survey']);
        $this->assertArrayNotHasKey('status', $cmartPayload['snapshot']['event']);
        $this->assertSame('Final', $cmartPayload['cover_status']);

        $legacyView = PostEventReportPdfViewData::forAudience($legacy->fresh(), 'cmart');
        $legacyPdf = Pdf::loadView('reports.post_event_summary', $legacyView)->setPaper('a4')->output();
        File::put($this->artifactDir . DIRECTORY_SEPARATOR . 'schema-v1-legacy.pdf', $legacyPdf);
        $legacyText = $this->extractPdfTextApprox($legacyView);
        $this->assertStringNotContainsString('LEGACY_FREE_TEXT_MARKER', $legacyText);
        $this->assertStringNotContainsString('legacy@example.com', $legacyText);
        $this->assertStringContainsString('RM 70.50', $legacyText);
        $this->assertSame($originalLegacy, json_encode(GeneratedReport::findOrFail($legacy->id)->snapshot));
    }

    /**
     * Rendered Blade text used for privacy scans (DomPDF input).
     *
     * @param  array<string, mixed>  $viewData
     */
    private function extractPdfTextApprox(array $viewData): string
    {
        $html = view('reports.post_event_summary', $viewData)->render();

        return trim(html_entity_decode(strip_tags($html)));
    }

    /**
     * @return array{days: \Illuminate\Support\Collection, sites: \Illuminate\Support\Collection}
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
                'label' => sprintf('V%02d', $position),
                'row_label' => 'V',
                'position_number' => $position,
                'grid_row' => 1,
                'grid_column' => $position,
                'display_order' => $position,
                'operational_status' => EventSite::STATUS_ACTIVE,
            ]);
            $this->createdSiteIds[] = $site->id;

            return $this->attachSiteToFoodLayout($event, $site, 'V');
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
        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $this->space()->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'vendor_category_id' => $this->foodVendorCategory()->id,
            'product_category' => 'Food & Beverages',
            'product_details' => 'PDF verification booking fixture with enough detail.',
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

    private function occupy(Booking $booking, EventDay $day, EventSite $site): void
    {
        $allocation = BookingDayAllocation::create([
            'booking_id' => $booking->id,
            'event_day_id' => $day->id,
            'event_site_id' => $site->id,
            'allocation_status' => BookingDayAllocation::STATUS_CONFIRMED,
            'reserved_at' => now()->subDays(2),
            'confirmed_at' => now()->subDay(),
            'active_lock' => BookingDayAllocation::activeLockForStatus(BookingDayAllocation::STATUS_CONFIRMED),
        ]);
        $this->createdAllocationIds[] = $allocation->id;
    }

    private function seedMixedSurvey(CarbootEvent $event): void
    {
        if (! Schema::hasTable('survey_responses')) {
            $this->markTestSkipped('Survey tables unavailable');
        }

        $batch = RawSurveyUpload::create([
            'carboot_event_id' => $event->id,
            'uploaded_by' => $this->user('organizer', 'sv')->id,
            'schema_name' => SurveySchema::NAME,
            'schema_version' => SurveySchema::VERSION,
            'original_filename' => 'verification.csv',
            'storage_disk' => 'local',
            'storage_path' => 'surveys/verification-' . uniqid() . '.csv',
            'mime_type' => 'text/csv',
            'file_size' => 20,
            'sha256' => hash('sha256', uniqid('', true)),
            'status' => RawSurveyUpload::STATUS_COMPLETED,
            'is_active' => true,
            'submission_source' => RawSurveyUpload::SOURCE_CSV_IMPORT,
            'total_row_count' => 2,
            'valid_row_count' => 2,
            'invalid_row_count' => 0,
        ]);
        $this->createdSurveyUploadIds[] = $batch->id;

        foreach ([
            [
                'submission_source' => SurveyResponse::SOURCE_CSV_IMPORT,
                'import_batch_id' => $batch->id,
                'gross_sales_band' => 'rm_100_299',
                'experience_rating' => 'puas_hati',
                'item_conditions' => ['terpakai'],
                'items_sold_band' => 'separuh',
                'unsold_item_actions' => ['sumbangkan', 'kitar_semula'],
                'product_categories' => ['pakaian', 'buku'],
                'comments_and_suggestions' => 'VERIFICATION_RAW_COMMENT_MARKER',
            ],
            [
                'submission_source' => SurveyResponse::SOURCE_SYSTEM_SUBMISSION,
                'import_batch_id' => null,
                'gross_sales_band' => 'rm_300_499',
                'experience_rating' => 'neutral',
                'item_conditions' => ['baru'],
                'product_categories' => ['pakaian'],
                'supporting_activity_attracted_visitors' => 'ya',
            ],
        ] as $index => $row) {
            $response = SurveyResponse::create(array_merge([
                'carboot_event_id' => $event->id,
                'schema_name' => SurveySchema::NAME,
                'schema_version' => SurveySchema::VERSION,
                'source_row_number' => $index + 1,
                'respondent_id' => 'pdf-respondent-' . uniqid() . '-' . $index,
                'validation_status' => 'valid',
                'is_active' => true,
            ], $row));
            $this->createdSurveyResponseIds[] = $response->id;
        }
    }
}
