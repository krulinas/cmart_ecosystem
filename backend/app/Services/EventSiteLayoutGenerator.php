<?php

namespace App\Services;

use App\Exceptions\DomainConflictException;
use App\Models\CarbootEvent;
use App\Models\EventSite;
use App\Models\Space;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase 2A.5 — controlled bulk generation of physical parking sites.
 */
class EventSiteLayoutGenerator
{
    public const MAX_SITES_PER_REQUEST = 500;

    /**
     * @param  list<array{
     *   row_label: string,
     *   start_position?: int,
     *   count: int,
     *   grid_row?: int,
     *   grid_column_start?: int,
     *   label_prefix?: string|null
     * }>  $rows
     * @return array{created: list<EventSite>, replaced: int, skipped_duplicates: int}
     */
    public function generate(
        CarbootEvent $event,
        ?int $spaceId,
        array $rows,
        bool $replaceExisting = false,
    ): array {
        $space = Space::query()->findOrFail(Space::resolveId($spaceId));

        if ($rows === []) {
            throw new InvalidArgumentException('At least one row definition is required.');
        }

        $plans = [];
        $seenLabels = [];
        $seenRowPositions = [];

        foreach ($rows as $index => $row) {
            $rowLabel = strtoupper(trim((string) ($row['row_label'] ?? '')));
            if ($rowLabel === '') {
                throw new InvalidArgumentException("Row #{$index}: row_label is required.");
            }

            $count = (int) ($row['count'] ?? 0);
            if ($count < 1) {
                throw new InvalidArgumentException("Row {$rowLabel}: count must be at least 1.");
            }

            $startPosition = (int) ($row['start_position'] ?? 1);
            if ($startPosition < 1) {
                throw new InvalidArgumentException("Row {$rowLabel}: start_position must be at least 1.");
            }

            $gridRow = (int) ($row['grid_row'] ?? ($index + 1));
            $gridColumnStart = (int) ($row['grid_column_start'] ?? $startPosition);
            $labelPrefix = isset($row['label_prefix']) && $row['label_prefix'] !== null && $row['label_prefix'] !== ''
                ? strtoupper(trim((string) $row['label_prefix']))
                : $rowLabel;

            for ($offset = 0; $offset < $count; $offset++) {
                $position = $startPosition + $offset;
                $label = sprintf('%s%02d', $labelPrefix, $position);
                $rowPosKey = $rowLabel . ':' . $position;

                if (isset($seenLabels[$label])) {
                    throw new InvalidArgumentException("Duplicate label in request: {$label}.");
                }
                if (isset($seenRowPositions[$rowPosKey])) {
                    throw new InvalidArgumentException(
                        "Duplicate row/position in request: {$rowLabel} #{$position}."
                    );
                }

                $seenLabels[$label] = true;
                $seenRowPositions[$rowPosKey] = true;

                $plans[] = [
                    'carboot_event_id' => $event->id,
                    'space_id' => $space->id,
                    'label' => $label,
                    'row_label' => $rowLabel,
                    'position_number' => $position,
                    'grid_row' => $gridRow,
                    'grid_column' => $gridColumnStart + $offset,
                    'display_order' => count($plans) + 1,
                    'operational_status' => EventSite::STATUS_ACTIVE,
                    'metadata' => null,
                ];
            }
        }

        if (count($plans) > self::MAX_SITES_PER_REQUEST) {
            throw new InvalidArgumentException(
                'Cannot generate more than ' . self::MAX_SITES_PER_REQUEST . ' sites in one request.'
            );
        }

        $existingCount = EventSite::query()->forEvent($event->id)->count();
        if ($existingCount > 0 && ! $replaceExisting) {
            // Allow additive generation only when no label/position collision.
            $conflicts = EventSite::query()
                ->forEvent($event->id)
                ->where(function ($query) use ($seenLabels, $seenRowPositions) {
                    $query->whereIn('label', array_keys($seenLabels));
                    foreach (array_keys($seenRowPositions) as $key) {
                        [$rowLabel, $position] = explode(':', $key, 2);
                        $query->orWhere(function ($inner) use ($rowLabel, $position) {
                            $inner->where('row_label', $rowLabel)
                                ->where('position_number', (int) $position);
                        });
                    }
                })
                ->exists();

            if ($conflicts) {
                throw new InvalidArgumentException(
                    'Generated sites would collide with existing event sites. '
                    . 'Use replace_existing=true or adjust the row definitions.'
                );
            }
        }

        return DB::transaction(function () use ($event, $plans, $existingCount, $replaceExisting) {
            $replaced = 0;

            if ($replaceExisting && $existingCount > 0) {
                $existingSites = EventSite::query()->forEvent($event->id)->get();
                $hasHistory = $existingSites->contains(
                    fn (EventSite $site) => $site->hasAllocationHistory()
                );

                if ($hasHistory) {
                    throw new DomainConflictException(
                        'Cannot replace event sites while allocation history exists. Existing layout was preserved.',
                        'event_site_replace_blocked_by_history',
                    );
                }

                $replaced = EventSite::query()->forEvent($event->id)->delete();
            }

            $created = [];
            foreach ($plans as $plan) {
                $created[] = EventSite::create($plan);
            }

            return [
                'created' => $created,
                'replaced' => $replaced,
                'skipped_duplicates' => 0,
            ];
        });
    }
}
