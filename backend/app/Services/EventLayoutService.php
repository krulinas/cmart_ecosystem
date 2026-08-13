<?php

namespace App\Services;

use App\Exceptions\DomainConflictException;
use App\Models\CarbootEvent;
use App\Models\EventLayoutAuditLog;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorCategory;
use App\Support\CmartCarbootPhysicalLayout;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Phase 3.5 — Organizer layout row and site mutation orchestration.
 */
class EventLayoutService
{
    public function __construct(
        private readonly EventLayoutLockService $locks,
        private readonly EventLayoutAuditLogger $audit,
        private readonly EventLayoutRowSiteGenerator $rowSiteGenerator,
        private readonly EventLayoutReadinessService $readiness,
    ) {
    }

    public function normalizeRowLabel(string $label): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($label)) ?? '';
        if (CmartCarbootPhysicalLayout::isAllowedRowLabel($normalized)) {
            return CmartCarbootPhysicalLayout::normalizeRowLabel($normalized);
        }

        return $normalized;
    }

    public function makeUniqueSlug(CarbootEvent $event, string $label, ?int $ignoreRowId = null): string
    {
        $base = Str::slug($label);
        if ($base === '') {
            $base = 'row';
        }

        $slug = $base;
        $suffix = 2;
        while (
            EventLayoutRow::query()
                ->forEvent($event->id)
                ->when($ignoreRowId, fn ($q) => $q->where('id', '!=', $ignoreRowId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function assertVendorSiteOpenLimitConfigured(CarbootEvent $event): void
    {
        if ($event->vendor_site_open_limit === null) {
            throw new DomainConflictException(
                'Configure Vendor sites to open before generating or confirming the layout.',
                'VENDOR_SITE_OPEN_LIMIT_NOT_SET',
            );
        }

        $this->assertVendorSiteOpenLimitAssignable($event, (int) $event->vendor_site_open_limit);
    }

    public function assertVendorSiteOpenLimitAssignable(CarbootEvent $event, ?int $limit): void
    {
        if ($limit === null) {
            return;
        }

        $capacity = CmartCarbootPhysicalLayout::physicalSiteCapacity();
        if ($limit < 1 || $limit > $capacity) {
            throw new InvalidArgumentException(
                "Vendor sites to open must be between 1 and {$capacity}."
            );
        }

        $protected = $this->countProtectedOccupiedSites((int) $event->id);
        if ($limit < $protected) {
            throw new DomainConflictException(
                "Cannot set Vendor sites to open below {$protected} because reserved or booked sites are already protected.",
                'VENDOR_SITE_OPEN_LIMIT_BELOW_PROTECTED',
            );
        }
    }

    /**
     * Sites with active reserved/confirmed occupancy that must remain open.
     */
    public function countProtectedOccupiedSites(int $eventId): int
    {
        return EventSite::query()
            ->forEvent($eventId)
            ->whereHas('bookingDayAllocations', function ($query) {
                $query->activeOccupancy();
            })
            ->count();
    }

    /**
     * @param  array{
     *   label: string,
     *   vendor_category_id: int,
     *   space_id: int,
     *   description?: string|null,
     *   display_order?: int|null,
     *   is_active?: bool,
     *   is_public?: bool
     * }  $data
     */
    public function createRow(CarbootEvent $event, User $actor, array $data): EventLayoutRow
    {
        return DB::transaction(function () use ($event, $actor, $data) {
            $lockedEvent = CarbootEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            $label = $this->normalizeRowLabel((string) $data['label']);
            if ($label === '') {
                throw new InvalidArgumentException('Row label cannot be empty.');
            }

            if (! CmartCarbootPhysicalLayout::isAllowedRowLabel($label)) {
                throw new DomainConflictException(
                    'Physical row identity must be one of A, B, C, or D for this venue.',
                    'ROW_OUTSIDE_VENUE_TEMPLATE',
                );
            }

            $existingLabels = EventLayoutRow::query()
                ->forEvent($lockedEvent->id)
                ->pluck('label')
                ->all();

            if (CmartCarbootPhysicalLayout::allTemplateRowsPresent($existingLabels)) {
                throw new DomainConflictException(
                    'All physical rows for this venue are already in use.',
                    'VENUE_TEMPLATE_ROWS_EXHAUSTED',
                );
            }

            if (! in_array($label, CmartCarbootPhysicalLayout::unusedRowLabels($existingLabels), true)) {
                throw new DomainConflictException(
                    "Layout row label [{$label}] already exists for this event.",
                    'ROW_LABEL_CONFLICT',
                );
            }

            $category = $this->requireAssignableCategory((int) $data['vendor_category_id']);

            $space = Space::query()->findOrFail(
                Space::resolveId(isset($data['space_id']) ? (int) $data['space_id'] : null)
            );

            $displayOrder = $data['display_order'] ?? null;
            if ($displayOrder === null) {
                $max = (int) EventLayoutRow::query()->forEvent($lockedEvent->id)->max('display_order');
                $displayOrder = $max + 1;
            }

            $isActive = (bool) ($data['is_active'] ?? true);

            try {
                $row = EventLayoutRow::create([
                    'carboot_event_id' => $lockedEvent->id,
                    'vendor_category_id' => $category->id,
                    'label' => $label,
                    'slug' => $this->makeUniqueSlug($lockedEvent, $label),
                    'description' => $data['description'] ?? null,
                    'display_order' => (int) $displayOrder,
                    'is_active' => $isActive,
                    'is_public' => (bool) ($data['is_public'] ?? true),
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'archived_at' => null,
                ]);
            } catch (QueryException $exception) {
                $this->translateRowUnique($exception);
                throw $exception;
            }

            $gridRow = CmartCarbootPhysicalLayout::gridRowForLabel($label);
            for ($position = 1; $position <= CmartCarbootPhysicalLayout::SITES_PER_ROW; $position++) {
                $siteLabel = $label.str_pad((string) $position, 2, '0', STR_PAD_LEFT);
                try {
                    EventSite::create([
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
                            'created_via' => 'add_row',
                        ],
                    ]);
                } catch (QueryException $exception) {
                    $this->translateSiteUnique($exception);
                    throw $exception;
                }
            }

            if ($isActive && $row->eventSites()->count() === 0) {
                throw new DomainConflictException(
                    'An active row cannot be saved without physical sites.',
                    'ACTIVE_ROW_HAS_NO_ACTIVE_SITES',
                );
            }

            $this->audit->record(
                $lockedEvent->id,
                $actor,
                EventLayoutAuditLog::ACTION_ROW_CREATED,
                null,
                array_merge($this->rowSnapshot($row), [
                    'sites_created' => CmartCarbootPhysicalLayout::SITES_PER_ROW,
                    'initial_site_status' => EventSite::STATUS_DISABLED,
                ]),
                $row->id,
            );

            return $row->fresh(['vendorCategory', 'eventSites']);
        });
    }

    /**
     * Confirm the organizer's vendor-booking site selection.
     *
     * Derives and stores vendor_site_open_limit from the confirmed unique site IDs.
     * Protected open sites (active allocations / disable_locked) cannot be deselected.
     *
     * @param  list<int>  $siteIds
     * @return array{
     *   opened: int,
     *   closed: int,
     *   open_site_count: int,
     *   vendor_site_open_limit: int,
     *   readiness: array<string, mixed>
     * }
     */
    public function setOpenSites(CarbootEvent $event, User $actor, array $siteIds): array
    {
        return DB::transaction(function () use ($event, $actor, $siteIds) {
            $lockedEvent = CarbootEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            $uniqueIds = array_values(array_unique(array_map('intval', $siteIds)));
            if ($uniqueIds === []) {
                throw new InvalidArgumentException('Select at least one site for vendor booking.');
            }

            $sites = EventSite::query()
                ->forEvent($lockedEvent->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($sites->isEmpty()) {
                throw new DomainConflictException(
                    'No physical sites exist for this event.',
                    'NO_PHYSICAL_SITES',
                );
            }

            foreach ($uniqueIds as $siteId) {
                $site = $sites->get($siteId);
                if (! $site) {
                    throw new DomainConflictException(
                        'One or more selected sites do not belong to this event.',
                        'INVALID_SITE',
                    );
                }
                if ($site->event_layout_row_id === null) {
                    throw new DomainConflictException(
                        'One or more selected sites are invalid legacy sites outside a layout row.',
                        'INVALID_SITE',
                    );
                }
            }

            $selected = array_fill_keys($uniqueIds, true);
            $opened = 0;
            $closed = 0;

            foreach ($sites as $site) {
                $shouldOpen = isset($selected[$site->id]);
                $locks = $this->locks->siteLocks($site);
                $protected = $locks['disable_locked'] || $locks['has_active_allocations'];
                $protectedOpen = $site->operational_status === EventSite::STATUS_ACTIVE && $protected;

                if (! $shouldOpen && $protectedOpen) {
                    throw new DomainConflictException(
                        "Site {$site->label} cannot be closed because it has an active reservation or booking.",
                        'ACTIVE_ALLOCATIONS_PRESENT',
                    );
                }

                if ($shouldOpen) {
                    if ($site->operational_status !== EventSite::STATUS_ACTIVE) {
                        $site->operational_status = EventSite::STATUS_ACTIVE;
                        $site->save();
                        $opened++;
                    }
                    continue;
                }

                // Never overwrite protected occupancy statuses; only close unprotected sites.
                if ($protected) {
                    continue;
                }

                if ($site->operational_status === EventSite::STATUS_ACTIVE
                    || $site->operational_status === EventSite::STATUS_UNAVAILABLE
                ) {
                    $site->operational_status = EventSite::STATUS_DISABLED;
                    $site->save();
                    $closed++;
                }
            }

            $limit = count($uniqueIds);
            $this->assertVendorSiteOpenLimitAssignable($lockedEvent, $limit);

            $lockedEvent->vendor_site_open_limit = $limit;
            $lockedEvent->save();

            $activeCount = EventSite::query()
                ->forEvent($lockedEvent->id)
                ->active()
                ->count();

            if ($activeCount !== $limit) {
                throw new DomainConflictException(
                    'The layout changed while confirming open sites. Refresh and try again.',
                    'OPEN_SITE_SELECTION_COUNT_MISMATCH',
                );
            }

            $this->audit->record(
                (int) $lockedEvent->id,
                $actor,
                EventLayoutAuditLog::ACTION_OPEN_SITES_SET,
                null,
                [
                    'opened_site_ids' => $uniqueIds,
                    'opened' => $opened,
                    'closed' => $closed,
                    'vendor_site_open_limit' => $limit,
                    'open_site_count' => $activeCount,
                ],
            );

            $fresh = $lockedEvent->fresh();
            $readiness = $this->readiness->assess($fresh);

            return [
                'opened' => $opened,
                'closed' => $closed,
                'open_site_count' => $activeCount,
                'vendor_site_open_limit' => (int) $fresh->vendor_site_open_limit,
                'readiness' => [
                    'operational_ready' => $readiness['operational_ready'],
                    'public_ready' => $readiness['public_ready'],
                    'blocking_reasons' => $readiness['blocking_reasons'],
                ],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRow(EventLayoutRow $row, User $actor, array $data): EventLayoutRow
    {
        return DB::transaction(function () use ($row, $actor, $data) {
            CarbootEvent::query()->whereKey($row->carboot_event_id)->lockForUpdate()->firstOrFail();
            $locked = EventLayoutRow::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $before = $this->rowSnapshot($locked);
            $locks = $this->locks->rowLocks($locked);

            if (array_key_exists('label', $data)) {
                $label = $this->normalizeRowLabel((string) $data['label']);
                if ($label === '') {
                    throw new InvalidArgumentException('Row label cannot be empty.');
                }
                if ($label !== $locked->label) {
                    if ($locks['rename_locked']) {
                        throw new DomainConflictException(
                            'Row label cannot change after allocation history exists.',
                            'ROW_LABEL_LOCKED',
                        );
                    }
                    if (! CmartCarbootPhysicalLayout::isAllowedRowLabel($label)) {
                        throw new DomainConflictException(
                            'Physical row identity must be one of A, B, C, or D for this venue.',
                            'ROW_OUTSIDE_VENUE_TEMPLATE',
                        );
                    }
                    if (
                        EventLayoutRow::query()
                            ->forEvent($locked->carboot_event_id)
                            ->where('label', $label)
                            ->where('id', '!=', $locked->id)
                            ->exists()
                    ) {
                        throw new DomainConflictException(
                            "Layout row label [{$label}] already exists for this event.",
                            'ROW_LABEL_CONFLICT',
                        );
                    }
                    $locked->label = $label;
                    $locked->slug = $this->makeUniqueSlug(
                        CarbootEvent::query()->findOrFail($locked->carboot_event_id),
                        $label,
                        $locked->id,
                    );
                    EventSite::query()
                        ->where('event_layout_row_id', $locked->id)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->each(function (EventSite $site) use ($label) {
                            $site->row_label = $label;
                            $site->save();
                        });
                }
            }

            if (array_key_exists('vendor_category_id', $data)) {
                $categoryId = (int) $data['vendor_category_id'];
                if ($categoryId !== (int) $locked->vendor_category_id) {
                    if ($locks['category_change_locked']) {
                        throw new DomainConflictException(
                            'Row category cannot change after allocation history exists.',
                            'ROW_CATEGORY_LOCKED',
                        );
                    }
                    $category = $this->requireAssignableCategory($categoryId);
                    $locked->vendor_category_id = $category->id;
                }
            }

            if (array_key_exists('description', $data)) {
                $locked->description = $data['description'];
            }
            if (array_key_exists('display_order', $data)) {
                $locked->display_order = (int) $data['display_order'];
            }
            if (array_key_exists('is_public', $data)) {
                $locked->is_public = (bool) $data['is_public'];
            }
            if (array_key_exists('is_active', $data) && (bool) $data['is_active'] !== (bool) $locked->is_active) {
                // Activation/deactivation of archived rows must use archive/unarchive endpoints.
                if ($locked->archived_at !== null) {
                    throw new InvalidArgumentException(
                        'Archived rows must be unarchived before changing is_active.'
                    );
                }
                $nextActive = (bool) $data['is_active'];
                if ($nextActive) {
                    $siteCount = EventSite::query()
                        ->where('event_layout_row_id', $locked->id)
                        ->count();
                    if ($siteCount === 0) {
                        throw new DomainConflictException(
                            'An active row cannot be saved without physical sites.',
                            'ACTIVE_ROW_HAS_NO_ACTIVE_SITES',
                        );
                    }
                }
                $locked->is_active = $nextActive;
            }

            $locked->updated_by = $actor->id;

            try {
                $locked->save();
            } catch (QueryException $exception) {
                $this->translateRowUnique($exception);
                throw $exception;
            }

            $this->audit->record(
                (int) $locked->carboot_event_id,
                $actor,
                EventLayoutAuditLog::ACTION_ROW_UPDATED,
                $before,
                $this->rowSnapshot($locked->fresh()),
                $locked->id,
            );

            return $locked->fresh(['vendorCategory']);
        });
    }

    /**
     * @param  list<array{id: int, display_order: int}>  $rows
     */
    public function reorderRows(CarbootEvent $event, User $actor, array $rows): void
    {
        DB::transaction(function () use ($event, $actor, $rows) {
            CarbootEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            $ids = array_map(fn ($row) => (int) $row['id'], $rows);
            if (count($ids) !== count(array_unique($ids))) {
                throw new InvalidArgumentException('Duplicate row IDs are not allowed in reorder payload.');
            }

            $orders = array_map(fn ($row) => (int) $row['display_order'], $rows);
            if (count($orders) !== count(array_unique($orders))) {
                throw new InvalidArgumentException('Duplicate display_order values are not allowed.');
            }

            $existing = EventLayoutRow::query()
                ->forEvent($event->id)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($existing->count() !== count($ids)) {
                throw new DomainConflictException(
                    'One or more rows do not belong to this event.',
                    'INVALID_LAYOUT_ROW',
                );
            }

            $before = $existing->map(fn (EventLayoutRow $row) => [
                'id' => $row->id,
                'display_order' => $row->display_order,
            ])->values()->all();

            foreach ($rows as $item) {
                $row = $existing[(int) $item['id']];
                $row->display_order = (int) $item['display_order'];
                $row->updated_by = $actor->id;
                $row->save();
            }

            $this->audit->record(
                $event->id,
                $actor,
                EventLayoutAuditLog::ACTION_ROW_REORDERED,
                ['rows' => $before],
                ['rows' => $rows],
            );
        });
    }

    public function deleteEmptyRow(EventLayoutRow $row, User $actor): void
    {
        DB::transaction(function () use ($row, $actor) {
            CarbootEvent::query()->whereKey($row->carboot_event_id)->lockForUpdate()->firstOrFail();
            $locked = EventLayoutRow::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $locks = $this->locks->rowLocks($locked);

            if ($locks['delete_locked']) {
                throw new DomainConflictException(
                    'Layout row cannot be deleted while it contains sites or allocation history.',
                    'ROW_NOT_EMPTY',
                );
            }

            $before = $this->rowSnapshot($locked);
            $eventId = (int) $locked->carboot_event_id;
            $rowId = (int) $locked->id;
            $locked->delete();

            $this->audit->record(
                $eventId,
                $actor,
                EventLayoutAuditLog::ACTION_ROW_DELETED,
                $before,
                null,
                $rowId,
            );
        });
    }

    public function archiveRow(EventLayoutRow $row, User $actor): EventLayoutRow
    {
        return DB::transaction(function () use ($row, $actor) {
            CarbootEvent::query()->whereKey($row->carboot_event_id)->lockForUpdate()->firstOrFail();
            $locked = EventLayoutRow::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();

            if ($this->locks->rowHasActiveAllocations($locked)) {
                throw new DomainConflictException(
                    'Layout row cannot be archived while active reserved or confirmed allocations exist.',
                    'ACTIVE_ALLOCATIONS_PRESENT',
                );
            }

            $before = $this->rowSnapshot($locked);
            $sites = EventSite::query()
                ->where('event_layout_row_id', $locked->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $disabledLabels = [];
            foreach ($sites as $site) {
                if ($site->operational_status === EventSite::STATUS_ACTIVE) {
                    $site->operational_status = EventSite::STATUS_DISABLED;
                    $site->save();
                    $disabledLabels[] = $site->label;
                }
            }

            $locked->is_active = false;
            $locked->is_public = false;
            $locked->archived_at = now();
            $locked->updated_by = $actor->id;
            $locked->save();

            $this->audit->record(
                (int) $locked->carboot_event_id,
                $actor,
                EventLayoutAuditLog::ACTION_ROW_ARCHIVED,
                $before,
                $this->rowSnapshot($locked->fresh()),
                $locked->id,
                null,
                ['disabled_site_labels' => $disabledLabels],
            );

            return $locked->fresh(['vendorCategory']);
        });
    }

    /**
     * @return array{row: EventLayoutRow, readiness: array<string, mixed>}
     */
    public function unarchiveRow(EventLayoutRow $row, User $actor): array
    {
        return DB::transaction(function () use ($row, $actor) {
            CarbootEvent::query()->whereKey($row->carboot_event_id)->lockForUpdate()->firstOrFail();
            $locked = EventLayoutRow::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $before = $this->rowSnapshot($locked);

            if ($locked->vendor_category_id === null) {
                throw new InvalidArgumentException('Cannot unarchive a row without a category.');
            }
            $this->requireAssignableCategory((int) $locked->vendor_category_id);

            $locked->is_active = true;
            $locked->is_public = false;
            $locked->archived_at = null;
            $locked->updated_by = $actor->id;
            $locked->save();

            $fresh = $locked->fresh(['vendorCategory']);
            $this->audit->record(
                (int) $fresh->carboot_event_id,
                $actor,
                EventLayoutAuditLog::ACTION_ROW_UNARCHIVED,
                $before,
                $this->rowSnapshot($fresh),
                $fresh->id,
            );

            $event = CarbootEvent::query()->findOrFail($fresh->carboot_event_id);

            return [
                'row' => $fresh,
                'readiness' => $this->readiness->assess($event),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSite(EventLayoutRow $row, User $actor, array $data): EventSite
    {
        return DB::transaction(function () use ($row, $actor, $data) {
            CarbootEvent::query()->whereKey($row->carboot_event_id)->lockForUpdate()->firstOrFail();
            $lockedRow = EventLayoutRow::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();

            if (! $lockedRow->is_active || $lockedRow->archived_at !== null) {
                throw new InvalidArgumentException('Sites can only be created under an active, non-archived row.');
            }
            if ($lockedRow->vendor_category_id === null) {
                throw new InvalidArgumentException('Row must have a category before sites can be created.');
            }
            $this->requireAssignableCategory((int) $lockedRow->vendor_category_id);

            $space = Space::query()->findOrFail(
                Space::resolveId(isset($data['space_id']) ? (int) $data['space_id'] : null)
            );

            $label = strtoupper(trim((string) $data['label']));
            $status = (string) ($data['operational_status'] ?? $data['status'] ?? EventSite::STATUS_DISABLED);
            if (! in_array($status, EventSite::OPERATIONAL_STATUSES, true)) {
                throw new InvalidArgumentException('Invalid site status.');
            }

            if ($status === EventSite::STATUS_ACTIVE) {
                $event = CarbootEvent::query()->findOrFail($lockedRow->carboot_event_id);
                if ($event->vendor_site_open_limit !== null) {
                    $activeCount = EventSite::query()
                        ->forEvent((int) $lockedRow->carboot_event_id)
                        ->active()
                        ->count();
                    if ($activeCount + 1 > (int) $event->vendor_site_open_limit) {
                        throw new DomainConflictException(
                            'Creating this site as active would exceed Vendor sites to open for the event.',
                            'ACTIVE_SITE_COUNT_EXCEEDS_VENDOR_LIMIT',
                        );
                    }
                }
            }

            try {
                $site = EventSite::create([
                    'carboot_event_id' => $lockedRow->carboot_event_id,
                    'event_layout_row_id' => $lockedRow->id,
                    'space_id' => $space->id,
                    'label' => $label,
                    'row_label' => $lockedRow->label,
                    'position_number' => (int) $data['position_number'],
                    'grid_row' => (int) $data['grid_row'],
                    'grid_column' => (int) $data['grid_column'],
                    'display_order' => (int) ($data['display_order'] ?? $data['position_number']),
                    'operational_status' => $status,
                    'metadata' => $data['metadata'] ?? null,
                ]);
            } catch (QueryException $exception) {
                $this->translateSiteUnique($exception);
                throw $exception;
            }

            $this->audit->record(
                (int) $lockedRow->carboot_event_id,
                $actor,
                EventLayoutAuditLog::ACTION_SITE_CREATED,
                null,
                $this->siteSnapshot($site),
                $lockedRow->id,
                $site->id,
            );

            return $site->fresh(['space', 'eventLayoutRow']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{created: list<EventSite>}
     */
    public function generateSites(EventLayoutRow $row, User $actor, array $payload): array
    {
        if ($row->vendor_category_id === null) {
            throw new InvalidArgumentException('Row must have a category before sites can be generated.');
        }
        $this->requireAssignableCategory((int) $row->vendor_category_id);

        $result = $this->rowSiteGenerator->generate($row, $payload);
        $labels = array_map(fn (EventSite $site) => $site->label, $result['created']);

        $this->audit->record(
            (int) $row->carboot_event_id,
            $actor,
            EventLayoutAuditLog::ACTION_SITES_GENERATED,
            null,
            [
                'row_id' => $row->id,
                'row_label' => $row->label,
                'created_count' => count($labels),
                'labels' => $labels,
            ],
            $row->id,
            null,
            [
                'space_id' => Space::resolveId(
                    isset($payload['space_id']) ? (int) $payload['space_id'] : null
                ),
                'count' => (int) $payload['count'],
                'label_prefix' => $payload['label_prefix'],
            ],
        );

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSite(EventSite $site, User $actor, array $data): EventSite
    {
        return DB::transaction(function () use ($site, $actor, $data) {
            CarbootEvent::query()->whereKey($site->carboot_event_id)->lockForUpdate()->firstOrFail();
            $locked = EventSite::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();
            $before = $this->siteSnapshot($locked);
            $locks = $this->locks->siteLocks($locked);

            $structuralKeys = [
                'label',
                'event_layout_row_id',
                'space_id',
                'position_number',
                'grid_row',
                'grid_column',
            ];
            foreach ($structuralKeys as $key) {
                if (! array_key_exists($key, $data)) {
                    continue;
                }
                $incoming = $data[$key];
                if ($key === 'label') {
                    $incoming = strtoupper(trim((string) $incoming));
                }
                if ((string) $incoming !== (string) $locked->{$key}) {
                    if ($locks['structure_locked']) {
                        throw new DomainConflictException(
                            'Site structural identity cannot change after allocation history exists.',
                            'SITE_STRUCTURE_LOCKED',
                        );
                    }
                }
            }

            if (array_key_exists('label', $data)) {
                $locked->label = strtoupper(trim((string) $data['label']));
            }
            if (array_key_exists('space_id', $data)) {
                Space::query()->findOrFail((int) $data['space_id']);
                $locked->space_id = (int) $data['space_id'];
            }
            if (array_key_exists('position_number', $data)) {
                $locked->position_number = (int) $data['position_number'];
            }
            if (array_key_exists('grid_row', $data)) {
                $locked->grid_row = (int) $data['grid_row'];
            }
            if (array_key_exists('grid_column', $data)) {
                $locked->grid_column = (int) $data['grid_column'];
            }
            if (array_key_exists('display_order', $data)) {
                $locked->display_order = (int) $data['display_order'];
            }

            if (array_key_exists('event_layout_row_id', $data)) {
                $targetRowId = (int) $data['event_layout_row_id'];
                if ($targetRowId !== (int) $locked->event_layout_row_id) {
                    $target = EventLayoutRow::query()
                        ->whereKey($targetRowId)
                        ->lockForUpdate()
                        ->first();
                    if (! $target || (int) $target->carboot_event_id !== (int) $locked->carboot_event_id) {
                        throw new DomainConflictException(
                            'Target layout row does not belong to this event.',
                            'INVALID_LAYOUT_ROW',
                        );
                    }
                    if (! $target->is_active || $target->archived_at !== null) {
                        throw new InvalidArgumentException('Target layout row must be active and not archived.');
                    }
                    if ($target->vendor_category_id === null) {
                        throw new InvalidArgumentException('Target layout row must have a category.');
                    }
                    $this->requireAssignableCategory((int) $target->vendor_category_id);
                    $locked->event_layout_row_id = $target->id;
                    $locked->row_label = $target->label;
                }
            }

            $statusKey = array_key_exists('operational_status', $data)
                ? 'operational_status'
                : (array_key_exists('status', $data) ? 'status' : null);
            if ($statusKey !== null) {
                $status = (string) $data[$statusKey];
                if (! in_array($status, EventSite::OPERATIONAL_STATUSES, true)) {
                    throw new InvalidArgumentException('Invalid site status.');
                }
                if (
                    in_array($status, [EventSite::STATUS_UNAVAILABLE, EventSite::STATUS_DISABLED], true)
                    && $status !== $locked->operational_status
                    && $locks['disable_locked']
                ) {
                    throw new DomainConflictException(
                        'Site cannot be disabled while active reserved or confirmed allocations exist.',
                        'ACTIVE_ALLOCATIONS_PRESENT',
                    );
                }
                if (
                    $status === EventSite::STATUS_ACTIVE
                    && $locked->operational_status !== EventSite::STATUS_ACTIVE
                ) {
                    $event = CarbootEvent::query()->findOrFail($locked->carboot_event_id);
                    if ($event->vendor_site_open_limit !== null) {
                        $activeCount = EventSite::query()
                            ->forEvent((int) $locked->carboot_event_id)
                            ->active()
                            ->where('id', '!=', $locked->id)
                            ->count();
                        if ($activeCount + 1 > (int) $event->vendor_site_open_limit) {
                            throw new DomainConflictException(
                                'Opening this site would exceed Vendor sites to open for the event.',
                                'ACTIVE_SITE_COUNT_EXCEEDS_VENDOR_LIMIT',
                            );
                        }
                    }
                }
                $locked->operational_status = $status;
            }

            try {
                $locked->save();
            } catch (QueryException $exception) {
                $this->translateSiteUnique($exception);
                throw $exception;
            }

            $fresh = $locked->fresh(['space', 'eventLayoutRow']);
            $this->audit->record(
                (int) $fresh->carboot_event_id,
                $actor,
                EventLayoutAuditLog::ACTION_SITE_UPDATED,
                $before,
                $this->siteSnapshot($fresh),
                $fresh->event_layout_row_id,
                $fresh->id,
            );

            return $fresh;
        });
    }

    /**
     * @param  list<array{id: int, display_order: int}>  $sites
     */
    public function reorderSites(EventLayoutRow $row, User $actor, array $sites): void
    {
        DB::transaction(function () use ($row, $actor, $sites) {
            CarbootEvent::query()->whereKey($row->carboot_event_id)->lockForUpdate()->firstOrFail();
            EventLayoutRow::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();

            $ids = array_map(fn ($site) => (int) $site['id'], $sites);
            if (count($ids) !== count(array_unique($ids))) {
                throw new InvalidArgumentException('Duplicate site IDs are not allowed in reorder payload.');
            }

            $existing = EventSite::query()
                ->where('event_layout_row_id', $row->id)
                ->where('carboot_event_id', $row->carboot_event_id)
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($existing->count() !== count($ids)) {
                throw new DomainConflictException(
                    'One or more sites do not belong to this layout row.',
                    'INVALID_SITE',
                );
            }

            $before = $existing->map(fn (EventSite $site) => [
                'id' => $site->id,
                'display_order' => $site->display_order,
            ])->values()->all();

            foreach ($sites as $item) {
                $site = $existing[(int) $item['id']];
                $site->display_order = (int) $item['display_order'];
                $site->save();
            }

            $this->audit->record(
                (int) $row->carboot_event_id,
                $actor,
                EventLayoutAuditLog::ACTION_SITE_REORDERED,
                ['sites' => $before],
                ['sites' => $sites],
                $row->id,
            );
        });
    }

    public function deleteSite(EventSite $site, User $actor): void
    {
        DB::transaction(function () use ($site, $actor) {
            CarbootEvent::query()->whereKey($site->carboot_event_id)->lockForUpdate()->firstOrFail();
            $locked = EventSite::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();

            if (CmartCarbootPhysicalLayout::isCanonicalSiteLabel((string) $locked->label)) {
                throw new DomainConflictException(
                    'Physical parking sites cannot be deleted. Set the site to NOT OPEN or Unavailable instead.',
                    'CANONICAL_SITE_DELETE_FORBIDDEN',
                );
            }

            if ($locked->hasAllocationHistory()) {
                throw new DomainConflictException(
                    'Event site has allocation history and cannot be deleted.',
                    'SITE_HAS_ALLOCATION_HISTORY',
                );
            }

            $before = $this->siteSnapshot($locked);
            $eventId = (int) $locked->carboot_event_id;
            $rowId = $locked->event_layout_row_id ? (int) $locked->event_layout_row_id : null;
            $siteId = (int) $locked->id;
            $locked->delete();

            $this->audit->record(
                $eventId,
                $actor,
                EventLayoutAuditLog::ACTION_SITE_DELETED,
                $before,
                null,
                $rowId,
                $siteId,
            );
        });
    }

    /**
     * Restore one missing canonical CMart physical site as NOT OPEN.
     *
     * Sites are hard-deleted in this schema, so restoration creates the
     * missing canonical identity (label/position) without touching bookings.
     *
     * @return array{
     *   site: EventSite,
     *   restored_labels: list<string>,
     *   readiness: array<string, mixed>
     * }
     */
    public function restoreCanonicalSite(EventLayoutRow $row, User $actor, string $label): array
    {
        return DB::transaction(function () use ($row, $actor, $label) {
            $event = CarbootEvent::query()->whereKey($row->carboot_event_id)->lockForUpdate()->firstOrFail();
            $lockedRow = EventLayoutRow::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedRow->carboot_event_id !== (int) $event->id) {
                throw new DomainConflictException(
                    'Layout row does not belong to this event.',
                    'INVALID_SITE',
                );
            }

            $rowLabel = CmartCarbootPhysicalLayout::normalizeRowLabel((string) $lockedRow->label);
            if (! CmartCarbootPhysicalLayout::isAllowedRowLabel($rowLabel)) {
                throw new DomainConflictException(
                    'Canonical site restoration is only available for physical rows A–D.',
                    'ROW_OUTSIDE_VENUE_TEMPLATE',
                );
            }

            $parsed = CmartCarbootPhysicalLayout::parseCanonicalSiteLabel($label);
            if ($parsed === null || $parsed['row'] !== $rowLabel) {
                throw new InvalidArgumentException(
                    "Label must be a missing canonical site for row {$rowLabel} (e.g. {$rowLabel}01–{$rowLabel}16)."
                );
            }

            $canonicalLabel = CmartCarbootPhysicalLayout::siteLabelFor($rowLabel, $parsed['position']);
            $position = $parsed['position'];

            $existingOnRow = EventSite::query()
                ->forEvent((int) $event->id)
                ->where('event_layout_row_id', $lockedRow->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $existingLabels = $existingOnRow->pluck('label')->all();
            $missing = CmartCarbootPhysicalLayout::missingSiteLabels($rowLabel, $existingLabels);
            if (! in_array($canonicalLabel, $missing, true)) {
                throw new DomainConflictException(
                    "Site {$canonicalLabel} already exists on this row.",
                    'CANONICAL_SITE_ALREADY_EXISTS',
                );
            }

            if ($existingOnRow->contains(fn (EventSite $site) => (int) $site->position_number === $position)) {
                throw new DomainConflictException(
                    "Position {$position} is already occupied on row {$rowLabel}.",
                    'CANONICAL_SITE_POSITION_CONFLICT',
                );
            }

            // Event-wide uniqueness: label must not exist under another row/unresolved site.
            $eventWide = EventSite::query()
                ->forEvent((int) $event->id)
                ->whereRaw('UPPER(TRIM(label)) = ?', [$canonicalLabel])
                ->lockForUpdate()
                ->first();
            if ($eventWide) {
                throw new DomainConflictException(
                    "Site {$canonicalLabel} already exists for this event.",
                    'CANONICAL_SITE_ALREADY_EXISTS',
                );
            }

            if (! $lockedRow->is_active || $lockedRow->archived_at !== null) {
                throw new InvalidArgumentException('Sites can only be restored under an active, non-archived row.');
            }
            if ($lockedRow->vendor_category_id === null) {
                throw new InvalidArgumentException('Row must have a category before sites can be restored.');
            }
            $this->requireAssignableCategory((int) $lockedRow->vendor_category_id);

            $space = Space::query()->findOrFail(Space::resolveId(null));
            $gridRow = CmartCarbootPhysicalLayout::gridRowForLabel($rowLabel);

            try {
                $site = EventSite::create([
                    'carboot_event_id' => $event->id,
                    'event_layout_row_id' => $lockedRow->id,
                    'space_id' => $space->id,
                    'label' => $canonicalLabel,
                    'row_label' => $rowLabel,
                    'position_number' => $position,
                    'grid_row' => $gridRow,
                    'grid_column' => $position,
                    'display_order' => $position,
                    'operational_status' => EventSite::STATUS_DISABLED,
                    'metadata' => [
                        'template' => CmartCarbootPhysicalLayout::TEMPLATE_KEY,
                        'restored_canonical' => true,
                    ],
                ]);
            } catch (QueryException $exception) {
                $this->translateSiteUnique($exception);
                throw $exception;
            }

            $this->normalizeCanonicalRowDisplayOrder($lockedRow, $rowLabel);

            $this->audit->record(
                (int) $event->id,
                $actor,
                EventLayoutAuditLog::ACTION_CANONICAL_SITE_RESTORED,
                null,
                $this->siteSnapshot($site->fresh()),
                $lockedRow->id,
                $site->id,
                [
                    'restored_labels' => [$canonicalLabel],
                    'initial_status' => EventSite::STATUS_DISABLED,
                ],
            );

            $readiness = $this->readiness->assess($event->fresh());

            return [
                'site' => $site->fresh(['space', 'eventLayoutRow']),
                'restored_labels' => [$canonicalLabel],
                'readiness' => [
                    'operational_ready' => $readiness['operational_ready'],
                    'public_ready' => $readiness['public_ready'],
                    'blocking_reasons' => $readiness['blocking_reasons'],
                ],
            ];
        });
    }

    /**
     * Restore every currently missing canonical site for a physical row as NOT OPEN.
     *
     * @return array{
     *   sites: list<EventSite>,
     *   restored_labels: list<string>,
     *   readiness: array<string, mixed>
     * }
     */
    public function restoreAllMissingCanonicalSites(EventLayoutRow $row, User $actor): array
    {
        return DB::transaction(function () use ($row, $actor) {
            $event = CarbootEvent::query()->whereKey($row->carboot_event_id)->lockForUpdate()->firstOrFail();
            $lockedRow = EventLayoutRow::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();

            $rowLabel = CmartCarbootPhysicalLayout::normalizeRowLabel((string) $lockedRow->label);
            if (! CmartCarbootPhysicalLayout::isAllowedRowLabel($rowLabel)) {
                throw new DomainConflictException(
                    'Canonical site restoration is only available for physical rows A–D.',
                    'ROW_OUTSIDE_VENUE_TEMPLATE',
                );
            }

            $existingOnRow = EventSite::query()
                ->forEvent((int) $event->id)
                ->where('event_layout_row_id', $lockedRow->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $missing = CmartCarbootPhysicalLayout::missingSiteLabels(
                $rowLabel,
                $existingOnRow->pluck('label')->all(),
            );
            if ($missing === []) {
                throw new DomainConflictException(
                    'This row already has all 16 physical sites.',
                    'CANONICAL_ROW_COMPLETE',
                );
            }

            if (! $lockedRow->is_active || $lockedRow->archived_at !== null) {
                throw new InvalidArgumentException('Sites can only be restored under an active, non-archived row.');
            }
            if ($lockedRow->vendor_category_id === null) {
                throw new InvalidArgumentException('Row must have a category before sites can be restored.');
            }
            $this->requireAssignableCategory((int) $lockedRow->vendor_category_id);

            $space = Space::query()->findOrFail(Space::resolveId(null));
            $gridRow = CmartCarbootPhysicalLayout::gridRowForLabel($rowLabel);
            $created = [];
            $labels = [];

            foreach ($missing as $canonicalLabel) {
                $parsed = CmartCarbootPhysicalLayout::parseCanonicalSiteLabel($canonicalLabel);
                $position = (int) $parsed['position'];

                if ($existingOnRow->contains(fn (EventSite $site) => (int) $site->position_number === $position)) {
                    throw new DomainConflictException(
                        "Position {$position} is already occupied on row {$rowLabel}.",
                        'CANONICAL_SITE_POSITION_CONFLICT',
                    );
                }

                $eventWide = EventSite::query()
                    ->forEvent((int) $event->id)
                    ->whereRaw('UPPER(TRIM(label)) = ?', [$canonicalLabel])
                    ->lockForUpdate()
                    ->first();
                if ($eventWide) {
                    throw new DomainConflictException(
                        "Site {$canonicalLabel} already exists for this event.",
                        'CANONICAL_SITE_ALREADY_EXISTS',
                    );
                }

                try {
                    $site = EventSite::create([
                        'carboot_event_id' => $event->id,
                        'event_layout_row_id' => $lockedRow->id,
                        'space_id' => $space->id,
                        'label' => $canonicalLabel,
                        'row_label' => $rowLabel,
                        'position_number' => $position,
                        'grid_row' => $gridRow,
                        'grid_column' => $position,
                        'display_order' => $position,
                        'operational_status' => EventSite::STATUS_DISABLED,
                        'metadata' => [
                            'template' => CmartCarbootPhysicalLayout::TEMPLATE_KEY,
                            'restored_canonical' => true,
                        ],
                    ]);
                } catch (QueryException $exception) {
                    $this->translateSiteUnique($exception);
                    throw $exception;
                }

                $this->audit->record(
                    (int) $event->id,
                    $actor,
                    EventLayoutAuditLog::ACTION_CANONICAL_SITE_RESTORED,
                    null,
                    $this->siteSnapshot($site),
                    $lockedRow->id,
                    $site->id,
                    [
                        'restored_labels' => [$canonicalLabel],
                        'initial_status' => EventSite::STATUS_DISABLED,
                        'restore_batch' => true,
                    ],
                );

                $created[] = $site->fresh(['space', 'eventLayoutRow']);
                $labels[] = $canonicalLabel;
                $existingOnRow->push($site);
            }

            $this->normalizeCanonicalRowDisplayOrder($lockedRow, $rowLabel);

            $readiness = $this->readiness->assess($event->fresh());

            return [
                'sites' => $created,
                'restored_labels' => $labels,
                'readiness' => [
                    'operational_ready' => $readiness['operational_ready'],
                    'public_ready' => $readiness['public_ready'],
                    'blocking_reasons' => $readiness['blocking_reasons'],
                ],
            ];
        });
    }

    /**
     * Keep restored canonical sites in numeric parking order (A01…A16).
     * Updates display_order only — never renumbers existing site labels.
     */
    private function normalizeCanonicalRowDisplayOrder(EventLayoutRow $row, string $rowLabel): void
    {
        $sites = EventSite::query()
            ->where('event_layout_row_id', $row->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($sites as $site) {
            $parsed = CmartCarbootPhysicalLayout::parseCanonicalSiteLabel((string) $site->label);
            if ($parsed === null || $parsed['row'] !== $rowLabel) {
                continue;
            }
            $position = $parsed['position'];
            if ((int) $site->display_order !== $position) {
                $site->display_order = $position;
                $site->save();
            }
        }
    }

    public function requireAssignableCategory(int $categoryId): VendorCategory
    {
        $category = VendorCategory::query()->find($categoryId);
        if (! $category) {
            throw new InvalidArgumentException('Vendor category not found.');
        }
        if (! $category->is_active || $category->archived_at !== null) {
            throw new InvalidArgumentException('Vendor category is inactive or archived and cannot be assigned.');
        }

        return $category;
    }

    /**
     * @return array<string, mixed>
     */
    public function rowSnapshot(EventLayoutRow $row): array
    {
        return [
            'id' => $row->id,
            'carboot_event_id' => $row->carboot_event_id,
            'vendor_category_id' => $row->vendor_category_id,
            'label' => $row->label,
            'slug' => $row->slug,
            'description' => $row->description,
            'display_order' => $row->display_order,
            'is_active' => $row->is_active,
            'is_public' => $row->is_public,
            'archived_at' => optional($row->archived_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function siteSnapshot(EventSite $site): array
    {
        return [
            'id' => $site->id,
            'carboot_event_id' => $site->carboot_event_id,
            'event_layout_row_id' => $site->event_layout_row_id,
            'space_id' => $site->space_id,
            'label' => $site->label,
            'row_label' => $site->row_label,
            'position_number' => $site->position_number,
            'grid_row' => $site->grid_row,
            'grid_column' => $site->grid_column,
            'display_order' => $site->display_order,
            'operational_status' => $site->operational_status,
        ];
    }

    private function translateRowUnique(QueryException $exception): void
    {
        $message = $exception->getMessage();
        if (str_contains($message, 'event_layout_rows_event_label_unique') || str_contains($message, 'label')) {
            throw new DomainConflictException('Layout row label already exists for this event.', 'ROW_LABEL_CONFLICT');
        }
        if (str_contains($message, 'event_layout_rows_event_slug_unique') || str_contains($message, 'slug')) {
            throw new DomainConflictException('Layout row slug already exists for this event.', 'ROW_LABEL_CONFLICT');
        }
    }

    private function translateSiteUnique(QueryException $exception): void
    {
        $message = $exception->getMessage();
        if (str_contains($message, 'event_sites_event_label_unique')) {
            throw new DomainConflictException('Site label already exists for this event.', 'SITE_LABEL_CONFLICT');
        }
        if (str_contains($message, 'event_sites_event_row_position_unique')) {
            throw new DomainConflictException('Site position already exists for this row.', 'SITE_POSITION_CONFLICT');
        }
    }
}
