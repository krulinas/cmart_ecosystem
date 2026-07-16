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
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  array{
     *   label: string,
     *   vendor_category_id: int,
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

            $category = $this->requireAssignableCategory((int) $data['vendor_category_id']);

            if (
                EventLayoutRow::query()
                    ->forEvent($lockedEvent->id)
                    ->where('label', $label)
                    ->exists()
            ) {
                throw new DomainConflictException(
                    "Layout row label [{$label}] already exists for this event.",
                    'ROW_LABEL_CONFLICT',
                );
            }

            $displayOrder = $data['display_order'] ?? null;
            if ($displayOrder === null) {
                $max = (int) EventLayoutRow::query()->forEvent($lockedEvent->id)->max('display_order');
                $displayOrder = $max + 1;
            }

            try {
                $row = EventLayoutRow::create([
                    'carboot_event_id' => $lockedEvent->id,
                    'vendor_category_id' => $category->id,
                    'label' => $label,
                    'slug' => $this->makeUniqueSlug($lockedEvent, $label),
                    'description' => $data['description'] ?? null,
                    'display_order' => (int) $displayOrder,
                    'is_active' => (bool) ($data['is_active'] ?? true),
                    'is_public' => (bool) ($data['is_public'] ?? true),
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'archived_at' => null,
                ]);
            } catch (QueryException $exception) {
                $this->translateRowUnique($exception);
                throw $exception;
            }

            $this->audit->record(
                $lockedEvent->id,
                $actor,
                EventLayoutAuditLog::ACTION_ROW_CREATED,
                null,
                $this->rowSnapshot($row),
                $row->id,
            );

            return $row->fresh(['vendorCategory']);
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
                $locked->is_active = (bool) $data['is_active'];
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

            $space = Space::query()->find((int) $data['space_id']);
            if (! $space) {
                throw new InvalidArgumentException('Invalid space_id.');
            }

            $label = strtoupper(trim((string) $data['label']));
            $status = (string) ($data['operational_status'] ?? $data['status'] ?? EventSite::STATUS_ACTIVE);
            if (! in_array($status, EventSite::OPERATIONAL_STATUSES, true)) {
                throw new InvalidArgumentException('Invalid site status.');
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
                'space_id' => (int) $payload['space_id'],
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
