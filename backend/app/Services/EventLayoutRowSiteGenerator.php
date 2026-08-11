<?php

namespace App\Services;

use App\Exceptions\DomainConflictException;
use App\Models\CarbootEvent;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Space;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase 3.5 — row-aware site generation (does not replace EventSiteLayoutGenerator).
 *
 * Boundary: Phase 2 generator remains full-event bulk generation without EventLayoutRow.
 * This service only creates sites under one existing active layout row.
 */
class EventLayoutRowSiteGenerator
{
    public const MAX_SITES_PER_REQUEST = 100;

    /**
     * @param  array{
     *   space_id: int,
     *   count: int,
     *   label_prefix: string,
     *   start_number?: int,
     *   number_padding?: int,
     *   start_grid_column?: int,
     *   grid_row?: int,
     *   display_order_start?: int
     * }  $payload
     * @return array{created: list<EventSite>}
     */
    public function generate(EventLayoutRow $row, array $payload): array
    {
        if (! $row->is_active || $row->archived_at !== null) {
            throw new InvalidArgumentException('Sites can only be generated for an active, non-archived layout row.');
        }

        $space = Space::query()->findOrFail(
            Space::resolveId(isset($payload['space_id']) ? (int) $payload['space_id'] : null)
        );

        $count = (int) $payload['count'];
        if ($count < 1 || $count > self::MAX_SITES_PER_REQUEST) {
            throw new InvalidArgumentException(
                'count must be between 1 and ' . self::MAX_SITES_PER_REQUEST . '.'
            );
        }

        $prefix = strtoupper(trim((string) $payload['label_prefix']));
        if ($prefix === '' || ! preg_match('/^[A-Z0-9][A-Z0-9\-]*$/', $prefix)) {
            throw new InvalidArgumentException('label_prefix must be a non-empty alphanumeric label prefix.');
        }

        $startNumber = (int) ($payload['start_number'] ?? 1);
        $padding = (int) ($payload['number_padding'] ?? 2);
        $startGridColumn = (int) ($payload['start_grid_column'] ?? $startNumber);
        $gridRow = (int) ($payload['grid_row'] ?? 1);
        $displayOrderStart = (int) ($payload['display_order_start'] ?? $startNumber);

        if ($startNumber < 1 || $padding < 1 || $padding > 6) {
            throw new InvalidArgumentException('start_number and number_padding are invalid.');
        }

        $plans = [];
        for ($offset = 0; $offset < $count; $offset++) {
            $number = $startNumber + $offset;
            $label = $prefix . str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
            $plans[] = [
                'label' => $label,
                'position_number' => $number,
                'grid_row' => $gridRow,
                'grid_column' => $startGridColumn + $offset,
                'display_order' => $displayOrderStart + $offset,
            ];
        }

        return DB::transaction(function () use ($row, $space, $plans) {
            $event = CarbootEvent::query()
                ->whereKey($row->carboot_event_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRow = EventLayoutRow::query()
                ->whereKey($row->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingLabels = EventSite::query()
                ->forEvent($event->id)
                ->pluck('label')
                ->map(fn ($label) => strtoupper((string) $label))
                ->all();
            $existingLabelSet = array_fill_keys($existingLabels, true);

            $existingPositions = EventSite::query()
                ->forEvent($event->id)
                ->where('row_label', $lockedRow->label)
                ->pluck('position_number')
                ->map(fn ($n) => (int) $n)
                ->all();
            $existingPositionSet = array_fill_keys($existingPositions, true);

            foreach ($plans as $plan) {
                if (isset($existingLabelSet[$plan['label']])) {
                    throw new DomainConflictException(
                        "Site label {$plan['label']} already exists for this event.",
                        'SITE_LABEL_CONFLICT',
                    );
                }
                if (isset($existingPositionSet[$plan['position_number']])) {
                    throw new DomainConflictException(
                        "Site position {$plan['position_number']} already exists for row {$lockedRow->label}.",
                        'SITE_POSITION_CONFLICT',
                    );
                }
            }

            $created = [];
            foreach ($plans as $plan) {
                $created[] = EventSite::create([
                    'carboot_event_id' => $event->id,
                    'event_layout_row_id' => $lockedRow->id,
                    'space_id' => $space->id,
                    'label' => $plan['label'],
                    'row_label' => $lockedRow->label,
                    'position_number' => $plan['position_number'],
                    'grid_row' => $plan['grid_row'],
                    'grid_column' => $plan['grid_column'],
                    'display_order' => $plan['display_order'],
                    'operational_status' => EventSite::STATUS_ACTIVE,
                    'metadata' => null,
                ]);
            }

            return ['created' => $created];
        });
    }
}
