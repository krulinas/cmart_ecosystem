<?php

namespace App\Services;

use App\Models\CarbootEvent;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Builds authoritative per-event metric rows (Laravel formulas) and asks Python
 * for statistical cross-event comparison. Does not reimplement business math.
 */
class OrganizerEventBenchmarkService
{
    public function __construct(
        private readonly PostEventSummaryAggregator $aggregator,
        private readonly AnalyticsPythonClient $python,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forEvent(CarbootEvent $selected, ?int $organizerId = null): array
    {
        $events = $this->candidateEvents($selected, $organizerId);
        $rows = $events->map(fn (CarbootEvent $event) => $this->normalizeEventMetrics($event))->values()->all();

        $payload = [
            'selected_event_id' => (int) $selected->id,
            'events' => $rows,
        ];

        try {
            $benchmark = $this->python->eventBenchmark($payload);
        } catch (Throwable $e) {
            return [
                'available' => false,
                'status' => 'unavailable',
                'message' => 'Cross-event benchmarking is temporarily unavailable.',
                'detail' => $e->getMessage(),
                'selected_event_id' => (int) $selected->id,
                'sample_size' => count($rows),
                'payload_echo' => [
                    'event_count' => count($rows),
                ],
                'metrics' => new \stdClass,
                'trends' => [],
                'warnings' => ['python_unreachable'],
            ];
        }

        return [
            'available' => true,
            'status' => 'ready',
            'selected_event_id' => (int) ($benchmark['selected_event_id'] ?? $selected->id),
            'sample_size' => (int) ($benchmark['sample_size'] ?? count($rows)),
            'metrics' => $benchmark['metrics'] ?? new \stdClass,
            'trends' => $benchmark['trends'] ?? [],
            'warnings' => $benchmark['warnings'] ?? [],
            'events' => $rows,
        ];
    }

    /**
     * @return Collection<int, CarbootEvent>
     */
    private function candidateEvents(CarbootEvent $selected, ?int $organizerId = null): Collection
    {
        // Organizer hub is event-scoped; compare against recent closed/open events
        // without inventing ownership filters (carboot_events has no organizer FK).
        unset($organizerId);

        $events = CarbootEvent::query()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->sortBy([
                ['starts_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if (! $events->contains(fn (CarbootEvent $e) => (int) $e->id === (int) $selected->id)) {
            $events = $events->push($selected)->values();
        }

        return $events;
    }

    /**
     * Extract comparable metrics from PostEventSummaryAggregator only.
     *
     * @return array<string, mixed>
     */
    public function normalizeEventMetrics(CarbootEvent $event): array
    {
        $snapshot = $this->aggregator->build($event);
        $sections = is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : [];
        $pipeline = is_array($sections['booking_pipeline'] ?? null) ? $sections['booking_pipeline'] : [];
        $performance = is_array($sections['event_performance'] ?? null) ? $sections['event_performance'] : [];
        $payments = is_array($sections['payments'] ?? null) ? $sections['payments'] : [];
        $feedback = is_array($sections['feedback'] ?? null) ? $sections['feedback'] : [];

        $approvedVendors = $pipeline['approved_unique_vendors']
            ?? $performance['unique_approved_vendors']
            ?? null;

        $utilisation = array_key_exists('site_utilisation_percent', $performance)
            ? $performance['site_utilisation_percent']
            : null;

        $collectionRate = array_key_exists('collection_rate_percent', $payments)
            ? $payments['collection_rate_percent']
            : null;

        $averageRating = $performance['average_overall_rating']
            ?? $feedback['average_rating']
            ?? null;

        $startsAt = $event->starts_at;
        $eventDate = $startsAt ? $startsAt->toDateString() : null;

        return [
            'event_id' => (int) $event->id,
            'event_name' => (string) ($event->title ?? 'Event'),
            'event_date' => $eventDate,
            'approved_vendors' => $this->nullableNumber($approvedVendors),
            'site_utilisation_percent' => $this->nullableNumber($utilisation),
            'collection_rate_percent' => $this->nullableNumber($collectionRate),
            'average_rating' => $this->nullableNumber($averageRating),
        ];
    }

    private function nullableNumber(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = $value + 0;

        return is_float($number) ? round($number, 4) : $number;
    }
}
