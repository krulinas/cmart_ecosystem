<?php

namespace App\Support;

use App\Models\CarbootEvent;
use App\Models\GeneratedReport;
use App\Models\User;

/**
 * Programme narrative freeze + publish-readiness for Post-Event reports.
 */
final class PostEventReportProgramme
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<string>  $objectives
     * @return array<string, mixed>
     */
    public static function mergeIntoSnapshot(
        array $snapshot,
        CarbootEvent $event,
        ?string $introduction,
        array $objectives,
        bool $objectivesNotApplicable,
        ?string $conclusion,
        ?User $preparedBy = null,
    ): array {
        $introduction = self::normalizeText($introduction);
        $conclusion = self::normalizeText($conclusion);
        $objectives = self::normalizeObjectives($objectives);

        $snapshot['programme'] = [
            'introduction' => $introduction,
            'objectives' => $objectives,
            'objectives_not_applicable' => $objectivesNotApplicable,
            'conclusion' => $conclusion,
            'details' => self::details($event, $snapshot, $preparedBy),
            'analysis' => PostEventReportAnalysis::fromSnapshot($snapshot),
            'executive_summary' => PostEventReportAnalysis::executiveSummary($snapshot),
        ];

        return $snapshot;
    }

    public static function defaultIntroduction(CarbootEvent $event): ?string
    {
        $description = trim((string) ($event->description ?? ''));

        return $description !== '' ? $description : null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function seedConclusion(array $snapshot): string
    {
        return PostEventReportAnalysis::conclusionDraft($snapshot);
    }

    /**
     * @return array{
     *   ready: bool,
     *   items: list<array{id: string, label: string, satisfied: bool, required: bool}>
     * }
     */
    public static function publishReadiness(GeneratedReport $report): array
    {
        $snapshot = is_array($report->snapshot) ? $report->snapshot : [];
        $programme = is_array($snapshot['programme'] ?? null) ? $snapshot['programme'] : [];

        $intro = self::normalizeText($report->programme_introduction)
            ?? self::normalizeText($programme['introduction'] ?? null);
        $objectives = self::normalizeObjectives(
            is_array($report->programme_objectives) ? $report->programme_objectives : ($programme['objectives'] ?? [])
        );
        $objectivesNa = (bool) ($report->objectives_not_applicable || ($programme['objectives_not_applicable'] ?? false));
        $conclusion = self::normalizeText($report->conclusion)
            ?? self::normalizeText($programme['conclusion'] ?? null);
        $observationsReviewed = self::normalizeText($report->organizer_observations) !== null
            || array_key_exists('organizer_observations', $report->getAttributes());
        // "Reviewed" means the organizer has explicitly saved (nullable empty string counts as reviewed once column touched).
        // For readiness we require non-null column presence after at least one narratives save OR non-empty text.
        // Practical rule: observations and recommendations must be non-empty OR explicitly saved as empty with a marker.
        // Spec: "Organizer observations reviewed" / "Recommendations reviewed" — treat non-empty OR explicitly empty string after save.
        // Use: column is not null (including empty string) as "reviewed".
        $observationsReviewed = $report->organizer_observations !== null;
        $recommendationsReviewed = $report->organizer_recommendations !== null;
        $conclusionReviewed = $report->conclusion !== null;

        $details = is_array($programme['details'] ?? null) ? $programme['details'] : [];
        $detailsAvailable = $details !== [] && ! empty($details['event_name']);

        $snapshotGeneratedAt = $snapshot['generated_at'] ?? null;
        $updatedAt = optional($report->updated_at)?->toIso8601String();
        // Snapshot considered regenerated after latest narrative edits when snapshot.generated_at
        // is not older than the report's updated_at by more than a second — imperfect.
        // Better: compare narratives_updated marker. Use: programme frozen keys exist AND
        // snapshot generated_at is present; require organizer to regenerate after edits via checklist messaging.
        $programmeFrozen = isset($snapshot['programme']) && is_array($snapshot['programme']);
        $narrativesDirty = self::narrativesDirtyVsSnapshot($report, $programme);
        $snapshotFresh = $programmeFrozen && ! $narrativesDirty;

        $items = [
            [
                'id' => 'introduction',
                'label' => 'Introduction provided',
                'satisfied' => $intro !== null,
                'required' => true,
            ],
            [
                'id' => 'programme_details',
                'label' => 'Programme details available',
                'satisfied' => $detailsAvailable,
                'required' => true,
            ],
            [
                'id' => 'objectives',
                'label' => 'Objectives provided or marked not applicable',
                'satisfied' => $objectivesNa || $objectives !== [],
                'required' => true,
            ],
            [
                'id' => 'observations',
                'label' => 'Organizer observations reviewed',
                'satisfied' => $observationsReviewed,
                'required' => true,
            ],
            [
                'id' => 'recommendations',
                'label' => 'Recommendations reviewed',
                'satisfied' => $recommendationsReviewed,
                'required' => true,
            ],
            [
                'id' => 'conclusion',
                'label' => 'Conclusion reviewed',
                'satisfied' => $conclusionReviewed && $conclusion !== null,
                'required' => true,
            ],
            [
                'id' => 'snapshot_fresh',
                'label' => 'Snapshot regenerated after the latest edits',
                'satisfied' => $snapshotFresh,
                'required' => true,
            ],
        ];

        $ready = true;
        foreach ($items as $item) {
            if ($item['required'] && ! $item['satisfied']) {
                $ready = false;
                break;
            }
        }

        return [
            'ready' => $ready,
            'items' => $items,
            'snapshot_generated_at' => $snapshotGeneratedAt,
            'report_updated_at' => $updatedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $programme
     */
    private static function narrativesDirtyVsSnapshot(GeneratedReport $report, array $programme): bool
    {
        $introCol = self::normalizeText($report->programme_introduction);
        $introSnap = self::normalizeText($programme['introduction'] ?? null);
        if ($introCol !== $introSnap) {
            return true;
        }

        $objCol = self::normalizeObjectives(is_array($report->programme_objectives) ? $report->programme_objectives : []);
        $objSnap = self::normalizeObjectives($programme['objectives'] ?? []);
        if ($objCol !== $objSnap) {
            return true;
        }

        $naCol = (bool) $report->objectives_not_applicable;
        $naSnap = (bool) ($programme['objectives_not_applicable'] ?? false);
        if ($naCol !== $naSnap) {
            return true;
        }

        $concCol = self::normalizeText($report->conclusion);
        $concSnap = self::normalizeText($programme['conclusion'] ?? null);
        if ($concCol !== $concSnap) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function details(CarbootEvent $event, array $snapshot, ?User $preparedBy = null): array
    {
        $sections = is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : [];
        $eventMeta = is_array($snapshot['event'] ?? null) ? $snapshot['event'] : [];
        $pipeline = is_array($sections['booking_pipeline'] ?? null) ? $sections['booking_pipeline'] : [];
        $eventPerf = is_array($sections['event_performance'] ?? null) ? $sections['event_performance'] : [];
        $itemRes = is_array($sections['item_reservations'] ?? null) ? $sections['item_reservations'] : [];

        $fee = $event->item_reservation_service_fee;
        $reservationsEnabled = $fee !== null;
        $feeDisplay = null;
        if ($fee === null) {
            $feeDisplay = null;
        } elseif ((float) $fee <= 0) {
            $feeDisplay = 'Free';
        } else {
            $feeDisplay = PostEventReportPresentation::money((float) $fee);
        }

        $details = [
            'event_name' => $event->title ?: ($eventMeta['title'] ?? null),
            'date_time' => $eventMeta['date_range_display'] ?? null,
            'venue' => $snapshot['venue'] ?? ($eventMeta['venue'] ?? 'CMart Changlun'),
            'organizer' => $preparedBy?->name ?? 'Carboot Organizer',
            'event_status' => $event->status,
            'report_version' => null, // filled by caller if needed
            'data_cut_off' => $snapshot['generated_at_display'] ?? ($snapshot['generated_at'] ?? null),
            'open_booking_sites' => $eventPerf['open_booking_sites'] ?? null,
            'site_price' => $event->site_price !== null
                ? PostEventReportPresentation::money((float) $event->site_price)
                : null,
            'approved_bookings' => $pipeline['approved_count'] ?? null,
            'unique_participating_vendors' => $pipeline['approved_unique_vendors'] ?? null,
            'item_reservations_enabled' => $reservationsEnabled,
            'item_reservation_service_fee' => $feeDisplay,
            'item_reservation_service_fee_configured' => $reservationsEnabled,
        ];

        if (! empty($itemRes['available'])) {
            foreach (['total_count', 'pending_charge_count', 'confirmed_count', 'completed_count'] as $key) {
                if (array_key_exists($key, $itemRes) && $itemRes[$key] !== null) {
                    $details['item_reservations_'.$key] = $itemRes[$key];
                }
            }
        }

        return array_filter(
            $details,
            static fn ($v) => $v !== null && $v !== '',
        );
    }

    public static function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  mixed  $objectives
     * @return list<string>
     */
    public static function normalizeObjectives(mixed $objectives): array
    {
        if (! is_array($objectives)) {
            return [];
        }
        $out = [];
        foreach ($objectives as $row) {
            if (! is_string($row) && ! is_numeric($row)) {
                continue;
            }
            $text = trim((string) $row);
            if ($text === '') {
                continue;
            }
            $out[] = mb_substr($text, 0, 500);
        }

        return array_values(array_unique($out));
    }
}
