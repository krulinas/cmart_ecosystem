<?php

namespace App\Services;

use App\Exceptions\DomainConflictException;
use App\Models\CarbootEvent;
use App\Models\EventLayoutAuditLog;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Space;
use App\Models\User;
use App\Support\CmartCarbootPhysicalLayout;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Atomic standard Carboot parking layout: rows A–D × 16 sites (64 total).
 *
 * Empty-layout only. Creates all physical sites as disabled/not open.
 * Organizer must then confirm exactly vendor_site_open_limit open sites.
 */
class StandardEventLayoutGenerator
{
    public function __construct(
        private readonly EventLayoutService $layout,
        private readonly EventLayoutLockService $locks,
        private readonly EventLayoutAuditLogger $audit,
        private readonly EventLayoutReadinessService $readiness,
    ) {}

    /**
     * @param  array{
     *   space_id: int,
     *   row_categories: array{A: int, B: int, C: int, D: int}
     * }  $payload
     * @return array{
     *   rows_created: int,
     *   sites_created: int,
     *   row_labels: list<string>,
     *   site_labels: list<string>,
     *   vendor_site_open_limit: int,
     *   needs_open_site_selection: bool,
     *   readiness: array<string, mixed>
     * }
     */
    public function generate(CarbootEvent $event, User $actor, array $payload): array
    {
        $spaceId = (int) ($payload['space_id'] ?? 0);
        $rowCategories = $payload['row_categories'] ?? [];

        if ($spaceId < 1) {
            throw new InvalidArgumentException('A valid space_id is required.');
        }

        foreach (CmartCarbootPhysicalLayout::ROW_LABELS as $label) {
            if (! isset($rowCategories[$label]) || (int) $rowCategories[$label] < 1) {
                throw new InvalidArgumentException(
                    "A vendor category is required for row {$label}."
                );
            }
        }

        return DB::transaction(function () use ($event, $actor, $spaceId, $rowCategories) {
            $lockedEvent = CarbootEvent::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->layout->assertVendorSiteOpenLimitConfigured($lockedEvent);

            if ($lockedEvent->public_layout_published_at !== null) {
                throw new DomainConflictException(
                    'Unpublish the public layout before generating the standard parking template.',
                    'PUBLIC_LAYOUT_PUBLISHED',
                );
            }

            $existingRowCount = EventLayoutRow::query()
                ->forEvent($lockedEvent->id)
                ->count();
            $existingSiteCount = EventSite::query()
                ->forEvent($lockedEvent->id)
                ->count();

            if ($existingRowCount > 0 || $existingSiteCount > 0) {
                throw new DomainConflictException(
                    'A layout already exists for this event. The standard parking template can only be generated on an empty layout.',
                    'LAYOUT_ALREADY_EXISTS',
                );
            }

            $locks = $this->locks->eventLockSummary((int) $lockedEvent->id);
            if ($locks['has_allocation_history'] || $locks['structural_replacement_locked']) {
                throw new DomainConflictException(
                    'This event has booking allocation history and cannot receive a destructive layout template.',
                    'ALLOCATION_HISTORY_PRESENT',
                );
            }

            $space = Space::query()->find($spaceId);
            if (! $space) {
                throw new InvalidArgumentException('Invalid space_id: space type not found.');
            }

            $categoriesByLabel = [];
            foreach (CmartCarbootPhysicalLayout::ROW_LABELS as $label) {
                $categoriesByLabel[$label] = $this->layout->requireAssignableCategory(
                    (int) $rowCategories[$label],
                );
            }

            $createdRows = [];
            $createdSites = [];
            $siteLabels = [];

            foreach (CmartCarbootPhysicalLayout::ROW_LABELS as $index => $label) {
                $gridRow = $index + 1;
                $displayOrder = $index + 1;
                $category = $categoriesByLabel[$label];

                $row = EventLayoutRow::create([
                    'carboot_event_id' => $lockedEvent->id,
                    'vendor_category_id' => $category->id,
                    'label' => $label,
                    'slug' => $this->layout->makeUniqueSlug($lockedEvent, $label),
                    'description' => "Standard parking row {$label}",
                    'display_order' => $displayOrder,
                    'is_active' => true,
                    'is_public' => true,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'archived_at' => null,
                ]);
                $createdRows[] = $row;

                for ($position = 1; $position <= CmartCarbootPhysicalLayout::SITES_PER_ROW; $position++) {
                    $siteLabel = $label.str_pad((string) $position, 2, '0', STR_PAD_LEFT);
                    $site = EventSite::create([
                        'carboot_event_id' => $lockedEvent->id,
                        'event_layout_row_id' => $row->id,
                        'space_id' => $space->id,
                        'label' => $siteLabel,
                        'row_label' => $label,
                        'position_number' => $position,
                        'grid_row' => $gridRow,
                        'grid_column' => $position,
                        'display_order' => $position,
                        'operational_status' => EventSite::STATUS_DISABLED,
                        'metadata' => [
                            'template' => CmartCarbootPhysicalLayout::TEMPLATE_KEY,
                            'aisle_after_row' => $label === 'B' ? 'vehicle_aisle' : null,
                            'orientation' => [
                                'exit' => 'above_row_a',
                                'entrance' => 'below_row_d',
                            ],
                        ],
                    ]);
                    $createdSites[] = $site;
                    $siteLabels[] = $siteLabel;
                }
            }

            $this->audit->record(
                (int) $lockedEvent->id,
                $actor,
                EventLayoutAuditLog::ACTION_STANDARD_TEMPLATE_GENERATED,
                null,
                [
                    'rows_created' => count($createdRows),
                    'sites_created' => count($createdSites),
                    'row_labels' => CmartCarbootPhysicalLayout::ROW_LABELS,
                    'site_labels' => $siteLabels,
                    'initial_status' => EventSite::STATUS_DISABLED,
                    'vendor_site_open_limit' => (int) $lockedEvent->vendor_site_open_limit,
                ],
                null,
                null,
                [
                    'space_id' => $space->id,
                    'row_categories' => array_map(
                        fn ($category) => $category->id,
                        $categoriesByLabel,
                    ),
                    'template' => CmartCarbootPhysicalLayout::TEMPLATE_KEY,
                    'needs_open_site_selection' => true,
                ],
            );

            $readiness = $this->readiness->assess($lockedEvent->fresh());

            return [
                'rows_created' => count($createdRows),
                'sites_created' => count($createdSites),
                'row_labels' => CmartCarbootPhysicalLayout::ROW_LABELS,
                'site_labels' => $siteLabels,
                'vendor_site_open_limit' => (int) $lockedEvent->vendor_site_open_limit,
                'needs_open_site_selection' => true,
                'readiness' => [
                    'operational_ready' => $readiness['operational_ready'],
                    'public_ready' => $readiness['public_ready'],
                    'blocking_reasons' => $readiness['blocking_reasons'],
                ],
            ];
        });
    }
}
