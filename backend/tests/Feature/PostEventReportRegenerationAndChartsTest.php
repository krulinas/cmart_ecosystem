<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\GeneratedReport;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use App\Services\ReportDraftService;
use App\Support\PostEventReportChartSvg;
use App\Support\PostEventReportSnapshotCompare;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostEventReportRegenerationAndChartsTest extends TestCase
{
    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $eventIds = [];

    /** @var list<int> */
    private array $bookingIds = [];

    /** @var list<int> */
    private array $reportIds = [];

    protected function tearDown(): void
    {
        GeneratedReport::query()->whereIn('id', $this->reportIds)->delete();
        Invoice::query()->whereIn('booking_id', $this->bookingIds)->delete();
        Booking::query()->whereIn('id', $this->bookingIds)->delete();
        CarbootEvent::query()->whereIn('id', $this->eventIds)->get()->each->delete();
        User::query()->whereIn('id', $this->userIds)->delete();
        parent::tearDown();
    }

    public function test_regenerate_preserves_id_version_and_created_at_and_advances_snapshot_time(): void
    {
        $organizer = $this->organizer();
        $event = $this->event();
        $this->approvedBooking($event, $organizer);

        $drafts = app(ReportDraftService::class);
        $report = $drafts->generate($event, $organizer);
        $this->reportIds[] = $report->id;

        $createdAt = $report->created_at?->toIso8601String();
        $version = $report->version;
        $beforeGeneratedAt = $report->snapshot['generated_at'] ?? null;

        sleep(1);

        Sanctum::actingAs($organizer);
        $response = $this->postJson("/api/organizer/generated-reports/{$report->id}/regenerate")
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'metrics_changed',
                'snapshot_generated_at',
                'generated_report' => ['id', 'version', 'created_at', 'snapshot_generated_at', 'snapshot'],
            ]);

        $payload = $response->json('generated_report');
        $this->assertSame($report->id, $payload['id']);
        $this->assertSame($version, $payload['version']);
        $this->assertSame($createdAt, $payload['created_at']);
        $this->assertNotSame($beforeGeneratedAt, $payload['snapshot_generated_at']);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // Immediate second regen with unchanged source data should report no metric changes.
        $second = $this->postJson("/api/organizer/generated-reports/{$report->id}/regenerate")->assertOk();
        $this->assertFalse($second->json('metrics_changed'));
        $this->assertStringContainsString('No metric changes', $second->json('message'));
    }

    public function test_regenerate_reports_metrics_changed_when_source_data_changes(): void
    {
        $organizer = $this->organizer();
        $event = $this->event();
        $booking = $this->approvedBooking($event, $organizer);

        $drafts = app(ReportDraftService::class);
        $report = $drafts->generate($event, $organizer);
        $this->reportIds[] = $report->id;
        $beforeApproved = $report->snapshot['sections']['booking_pipeline']['approved_count'] ?? null;

        // Change source data: add another approved booking.
        $second = Booking::query()->create([
            'user_id' => $this->community()->id,
            'space_id' => Space::defaultPhysical()->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Food & Beverages',
            'product_details' => 'Second approved booking',
            'approval_status' => 'Approved',
        ]);
        $this->bookingIds[] = $second->id;

        $result = $drafts->regenerate($report->fresh(), $organizer);
        $this->assertTrue($result['metrics_changed']);
        $afterApproved = $result['generated_report']->snapshot['sections']['booking_pipeline']['approved_count'] ?? null;
        $this->assertNotSame($beforeApproved, $afterApproved);
        $this->assertSame($report->created_at?->toIso8601String(), $result['generated_report']->created_at?->toIso8601String());
        unset($booking);
    }

    public function test_snapshot_compare_ignores_timestamps(): void
    {
        $a = ['generated_at' => '2026-01-01T00:00:00+08:00', 'sections' => ['payments' => ['collected_revenue' => 0]]];
        $b = ['generated_at' => '2026-01-02T00:00:00+08:00', 'sections' => ['payments' => ['collected_revenue' => 0]]];
        $c = ['generated_at' => '2026-01-02T00:00:00+08:00', 'sections' => ['payments' => ['collected_revenue' => 10]]];

        $this->assertFalse(PostEventReportSnapshotCompare::metricsChanged($a, $b));
        $this->assertTrue(PostEventReportSnapshotCompare::metricsChanged($a, $c));
    }

    public function test_doughnut_svg_uses_visible_polygons_not_arc_paths(): void
    {
        $html = PostEventReportChartSvg::doughnut([
            ['label' => 'Sites sold', 'count' => 9, 'color' => '#3970E4'],
            ['label' => 'Remaining', 'count' => 55, 'color' => '#E8EEF6'],
        ]);

        $this->assertStringContainsString('data-chart-kind="doughnut"', $html);
        $this->assertStringContainsString('data-chart-visual="1"', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString(' A ', $html);
    }


    public function test_show_and_list_send_no_store_cache_headers(): void
    {
        $organizer = $this->organizer();
        $event = $this->event();
        $this->approvedBooking($event, $organizer);
        $report = app(ReportDraftService::class)->generate($event, $organizer);
        $this->reportIds[] = $report->id;

        Sanctum::actingAs($organizer);
        $this->getJson('/api/organizer/generated-reports?status=draft')
            ->assertOk()
            ->assertHeader('Cache-Control');
        $show = $this->getJson("/api/organizer/generated-reports/{$report->id}")->assertOk();
        $this->assertStringContainsString('no-store', (string) $show->headers->get('Cache-Control'));
        $this->assertArrayHasKey('snapshot_generated_at', $show->json('data') ?? $show->json());
    }

    private function organizer(): User
    {
        $user = User::query()->create([
            'name' => 'Report Organizer',
            'email' => 'report-org-'.uniqid().'@example.test',
            'password' => bcrypt('password123'),
            'role' => 'organizer',
            'vendor_status' => 'pending',
        ]);
        $this->userIds[] = $user->id;

        return $user;
    }

    private function community(): User
    {
        $user = User::query()->create([
            'name' => 'Report Vendor',
            'email' => 'report-vendor-'.uniqid().'@example.test',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'approved',
        ]);
        $this->userIds[] = $user->id;

        return $user;
    }

    private function event(): CarbootEvent
    {
        $event = CarbootEvent::query()->create([
            'title' => 'Regen Event '.uniqid(),
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => 'Closed',
            'description' => 'Programme introduction from event description.',
            'max_slots' => 20,
            'site_price' => 20,
            'item_reservation_service_fee' => null,
        ]);
        $this->eventIds[] = $event->id;

        return $event;
    }

    private function approvedBooking(CarbootEvent $event, User $organizer): Booking
    {
        unset($organizer);
        $vendor = $this->community();
        $booking = Booking::query()->create([
            'user_id' => $vendor->id,
            'space_id' => Space::defaultPhysical()->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Pre-loved / Thrift',
            'product_details' => 'Regen booking',
            'approval_status' => 'Approved',
        ]);
        $this->bookingIds[] = $booking->id;

        return $booking;
    }
}
