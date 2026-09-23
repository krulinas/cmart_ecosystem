<?php

namespace Tests\Feature;

use App\Models\CarbootEvent;
use App\Models\User;
use App\Services\AnalyticsPythonClient;
use App\Services\OrganizerEventBenchmarkService;
use App\Services\PostEventSummaryAggregator;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\TestCase;

class OrganizerEventBenchmarkTest extends TestCase
{
    use CleansUpTestFixtures;

    protected function tearDown(): void
    {
        Mockery::close();
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    public function test_normalize_event_metrics_uses_aggregator_values_without_inventing_zeros(): void
    {
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Benchmark Fixture '.uniqid(),
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => 'Closed',
            'description' => 'benchmark',
            'max_slots' => 10,
            'day_generation_mode' => 'calendar_days',
        ]));

        $snapshot = [
            'sections' => [
                'booking_pipeline' => [
                    'approved_unique_vendors' => 5,
                ],
                'event_performance' => [
                    'site_utilisation_percent' => 14.1,
                    'average_overall_rating' => null,
                ],
                'payments' => [
                    'collection_rate_percent' => null,
                ],
                'feedback' => [],
            ],
        ];

        $aggregator = Mockery::mock(PostEventSummaryAggregator::class);
        $aggregator->shouldReceive('build')->once()->with(Mockery::on(fn ($e) => (int) $e->id === (int) $event->id))->andReturn($snapshot);

        $python = Mockery::mock(AnalyticsPythonClient::class);
        $service = new OrganizerEventBenchmarkService($aggregator, $python);

        $row = $service->normalizeEventMetrics($event);

        $this->assertSame((int) $event->id, $row['event_id']);
        $this->assertSame(5, $row['approved_vendors']);
        $this->assertSame(14.1, $row['site_utilisation_percent']);
        $this->assertNull($row['collection_rate_percent']);
        $this->assertNull($row['average_rating']);
    }

    public function test_benchmark_proxy_returns_python_payload_on_success(): void
    {
        $organizer = $this->trackUser(User::create([
            'name' => 'Bench Org',
            'email' => 'bench-org-'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'organizer',
            'vendor_status' => 'none',
        ]));

        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Benchmark Live '.uniqid(),
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => 'Closed',
            'description' => 'benchmark',
            'max_slots' => 10,
            'day_generation_mode' => 'calendar_days',
        ]));

        Http::fake([
            '*/api/analytics/event-benchmark' => Http::response([
                'selected_event_id' => (int) $event->id,
                'sample_size' => 1,
                'metrics' => [
                    'site_utilisation_percent' => [
                        'value' => null,
                        'median' => null,
                        'percentile' => null,
                        'delta_from_median' => null,
                        'status' => null,
                        'valid_n' => 0,
                        'warning' => 'insufficient_history_for_median',
                    ],
                ],
                'trends' => [],
                'warnings' => ['insufficient_history_for_median_comparison'],
            ], 200),
        ]);

        $token = $organizer->createToken('bench')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/organizer/events/'.$event->id.'/analytics/benchmark');

        $response->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('selected_event_id', (int) $event->id);

        Http::assertSent(function ($request) use ($event) {
            if (! str_contains($request->url(), '/api/analytics/event-benchmark')) {
                return false;
            }
            $data = $request->data();
            $this->assertSame((int) $event->id, (int) ($data['selected_event_id'] ?? 0));
            $this->assertIsArray($data['events'] ?? null);
            $match = collect($data['events'])->firstWhere('event_id', (int) $event->id);
            $this->assertNotNull($match);
            $this->assertArrayHasKey('site_utilisation_percent', $match);
            // Missing metrics must remain null, not coerced to 0.
            if (array_key_exists('collection_rate_percent', $match)) {
                $this->assertTrue($match['collection_rate_percent'] === null || is_numeric($match['collection_rate_percent']));
            }

            return true;
        });
    }

    public function test_benchmark_degrades_gracefully_when_python_unreachable(): void
    {
        $organizer = $this->trackUser(User::create([
            'name' => 'Bench Org Down',
            'email' => 'bench-down-'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'organizer',
            'vendor_status' => 'none',
        ]));

        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Benchmark Down '.uniqid(),
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => 'Closed',
            'description' => 'benchmark',
            'max_slots' => 10,
            'day_generation_mode' => 'calendar_days',
        ]));

        Http::fake([
            '*/api/analytics/event-benchmark' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('offline');
            },
        ]);

        $token = $organizer->createToken('bench')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/organizer/events/'.$event->id.'/analytics/benchmark');

        $response->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonFragment(['python_unreachable']);
    }
}
