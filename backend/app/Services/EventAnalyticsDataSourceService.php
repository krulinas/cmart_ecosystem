<?php

namespace App\Services;

use App\Models\AnalyticsResult;
use App\Models\CarbootEvent;
use App\Models\RawSurveyUpload;
use App\Models\SurveyResponse;
use App\Support\SurveySchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Soft-state Data Source Manager for event analytics (mode + CSV batch lifecycle).
 * Never deletes system records or raw CSV files.
 */
class EventAnalyticsDataSourceService
{
    public const MODE_COMBINED = 'combined';

    public const MODE_SYSTEM_ONLY = 'system_only';

    public const MODE_CSV_ONLY = 'csv_only';

    public const MODES = [
        self::MODE_COMBINED,
        self::MODE_SYSTEM_ONLY,
        self::MODE_CSV_ONLY,
    ];

    /**
     * @return array{event: CarbootEvent, analytics_source_mode: string}
     */
    public function setSourceMode(CarbootEvent $event, string $mode): array
    {
        $mode = strtolower(trim($mode));
        if (! in_array($mode, self::MODES, true)) {
            throw new RuntimeException('Invalid analytics source mode.');
        }

        if (! Schema::hasColumn('carboot_events', 'analytics_source_mode')) {
            throw new RuntimeException(
                'Analytics source mode is not available until the Data Source Manager migration is applied.'
            );
        }

        $event->update(['analytics_source_mode' => $mode]);
        $this->invalidateSurveyCache((int) $event->id);

        return [
            'event' => $event->fresh(),
            'analytics_source_mode' => $mode,
        ];
    }

    public function activateBatch(CarbootEvent $event, RawSurveyUpload $batch): RawSurveyUpload
    {
        $this->assertBatchBelongsToEvent($event, $batch);
        $this->assertBatchUsable($batch);

        return DB::transaction(function () use ($event, $batch) {
            $locked = RawSurveyUpload::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            $this->deactivateSiblings($event, $locked);

            $attrs = [
                'status' => $this->completedStatusFor($locked),
                'excluded_at' => null,
                'archived_at' => null,
            ];
            if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
                $attrs['is_active'] = true;
            }
            if (Schema::hasColumn('raw_survey_uploads', 'active_dedup_key')) {
                $attrs['active_dedup_key'] = RawSurveyUpload::makeActiveDedupKey(
                    $locked->schema_name,
                    $locked->schema_version,
                    $locked->sha256,
                );
            }
            if (Schema::hasColumn('raw_survey_uploads', 'duplicate_of_id')) {
                $attrs['duplicate_of_id'] = null;
            }
            if (Schema::hasColumn('raw_survey_uploads', 'superseded_at')) {
                $attrs['superseded_at'] = null;
            }
            if (Schema::hasColumn('raw_survey_uploads', 'superseded_by_id')) {
                $attrs['superseded_by_id'] = null;
            }

            $locked->update($attrs);
            $this->setResponsesActive($locked->id, true);
            $this->invalidateSurveyCache((int) $event->id);

            return $locked->fresh();
        });
    }

    public function excludeBatch(CarbootEvent $event, RawSurveyUpload $batch): RawSurveyUpload
    {
        $this->assertBatchBelongsToEvent($event, $batch);

        if (in_array($batch->status, [RawSurveyUpload::STATUS_FAILED, RawSurveyUpload::STATUS_PENDING], true)) {
            throw new RuntimeException('This import cannot be excluded from analytics.');
        }

        return DB::transaction(function () use ($event, $batch) {
            $locked = RawSurveyUpload::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $wasActive = (bool) ($locked->is_active ?? false);

            $attrs = [
                'status' => RawSurveyUpload::STATUS_EXCLUDED,
                'restored_from_status' => $this->rememberCompletedStatus($locked),
            ];
            if (Schema::hasColumn('raw_survey_uploads', 'excluded_at')) {
                $attrs['excluded_at'] = now();
            }
            if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
                $attrs['is_active'] = false;
            }
            if (Schema::hasColumn('raw_survey_uploads', 'active_dedup_key')) {
                $attrs['active_dedup_key'] = null;
            }
            $locked->update($attrs);
            $this->setResponsesActive($locked->id, false);

            if ($wasActive) {
                $this->tryActivatePreviousSuperseded($event, $locked);
            }

            $this->invalidateSurveyCache((int) $event->id);

            return $locked->fresh();
        });
    }

    /**
     * Organizer "Remove CSV from Analytics": deactivate active CSV (no restore of prior batch),
     * keep raw file / metadata for duplicate protection, switch mode to system_only.
     *
     * @return array{batch: ?RawSurveyUpload, analytics_source_mode: string, event: CarbootEvent}
     */
    public function removeCsvFromAnalytics(CarbootEvent $event): array
    {
        return DB::transaction(function () use ($event) {
            $active = $this->currentActiveBatch($event);

            if ($active) {
                $locked = RawSurveyUpload::query()->whereKey($active->id)->lockForUpdate()->firstOrFail();

                $attrs = [
                    'status' => RawSurveyUpload::STATUS_EXCLUDED,
                    'restored_from_status' => $this->rememberCompletedStatus($locked),
                ];
                if (Schema::hasColumn('raw_survey_uploads', 'excluded_at')) {
                    $attrs['excluded_at'] = now();
                }
                if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
                    $attrs['is_active'] = false;
                }
                if (Schema::hasColumn('raw_survey_uploads', 'active_dedup_key')) {
                    $attrs['active_dedup_key'] = null;
                }
                $locked->update($attrs);
                $this->setResponsesActive($locked->id, false);
                $active = $locked->fresh();
            }

            // Deactivate any other unexpectedly active CSV batches for this event.
            if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
                $others = RawSurveyUpload::query()
                    ->where('carboot_event_id', $event->id)
                    ->where('is_active', true)
                    ->when($active, fn ($q) => $q->where('id', '!=', $active->id))
                    ->lockForUpdate()
                    ->get();

                foreach ($others as $other) {
                    $otherAttrs = [
                        'status' => RawSurveyUpload::STATUS_EXCLUDED,
                    ];
                    if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
                        $otherAttrs['is_active'] = false;
                    }
                    if (Schema::hasColumn('raw_survey_uploads', 'active_dedup_key')) {
                        $otherAttrs['active_dedup_key'] = null;
                    }
                    if (Schema::hasColumn('raw_survey_uploads', 'excluded_at')) {
                        $otherAttrs['excluded_at'] = now();
                    }
                    $other->update($otherAttrs);
                    $this->setResponsesActive($other->id, false);
                }
            }

            $this->invalidateSurveyCache((int) $event->id);

            if (Schema::hasColumn('carboot_events', 'analytics_source_mode')) {
                $modeResult = $this->setSourceMode($event->fresh(), self::MODE_SYSTEM_ONLY);
            } else {
                $modeResult = [
                    'analytics_source_mode' => self::MODE_SYSTEM_ONLY,
                    'event' => $event->fresh(),
                ];
            }

            return [
                'batch' => $active,
                'analytics_source_mode' => $modeResult['analytics_source_mode'],
                'event' => $modeResult['event'],
            ];
        });
    }

    public function archiveBatch(CarbootEvent $event, RawSurveyUpload $batch): RawSurveyUpload
    {
        $this->assertBatchBelongsToEvent($event, $batch);

        if ($batch->status === RawSurveyUpload::STATUS_FAILED) {
            throw new RuntimeException('Failed imports cannot be archived for analytics recovery.');
        }

        return DB::transaction(function () use ($event, $batch) {
            $locked = RawSurveyUpload::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $wasActive = (bool) ($locked->is_active ?? false);

            $attrs = [
                'status' => RawSurveyUpload::STATUS_ARCHIVED,
                'restored_from_status' => $this->rememberCompletedStatus($locked),
            ];
            if (Schema::hasColumn('raw_survey_uploads', 'archived_at')) {
                $attrs['archived_at'] = now();
            }
            if (Schema::hasColumn('raw_survey_uploads', 'excluded_at')) {
                $attrs['excluded_at'] = null;
            }
            if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
                $attrs['is_active'] = false;
            }
            if (Schema::hasColumn('raw_survey_uploads', 'active_dedup_key')) {
                $attrs['active_dedup_key'] = null;
            }
            $locked->update($attrs);
            $this->setResponsesActive($locked->id, false);

            if ($wasActive) {
                $this->tryActivatePreviousSuperseded($event, $locked);
            }

            $this->invalidateSurveyCache((int) $event->id);

            return $locked->fresh();
        });
    }

    public function restoreBatch(CarbootEvent $event, RawSurveyUpload $batch): RawSurveyUpload
    {
        $this->assertBatchBelongsToEvent($event, $batch);

        if (! in_array($batch->status, [
            RawSurveyUpload::STATUS_ARCHIVED,
            RawSurveyUpload::STATUS_EXCLUDED,
            RawSurveyUpload::STATUS_SUPERSEDED,
            RawSurveyUpload::STATUS_DUPLICATE,
        ], true)) {
            throw new RuntimeException('Only excluded, archived, superseded, or duplicate batches can be restored into analytics.');
        }

        return $this->activateBatch($event, $batch);
    }

    /**
     * Undo: deactivate current CSV batch and restore the most recent valid superseded batch.
     */
    public function undoImport(CarbootEvent $event): RawSurveyUpload
    {
        $active = $this->currentActiveBatch($event);
        if (! $active) {
            throw new RuntimeException('Undo is unavailable: there is no active CSV import for this event.');
        }

        $previous = RawSurveyUpload::query()
            ->where('carboot_event_id', $event->id)
            ->where('schema_name', $active->schema_name)
            ->where('schema_version', $active->schema_version)
            ->where('id', '<', $active->id)
            ->whereIn('status', [
                RawSurveyUpload::STATUS_SUPERSEDED,
                RawSurveyUpload::STATUS_EXCLUDED,
                RawSurveyUpload::STATUS_COMPLETED,
                RawSurveyUpload::STATUS_COMPLETED_WITH_ERRORS,
            ])
            ->where(function ($q) {
                if (Schema::hasColumn('raw_survey_uploads', 'archived_at')) {
                    $q->whereNull('archived_at');
                }
            })
            ->orderByDesc('id')
            ->first();

        if (! $previous) {
            throw new RuntimeException(
                'Undo is unavailable: no previous valid survey import exists for this event.'
            );
        }

        return DB::transaction(function () use ($event, $active, $previous) {
            $lockedActive = RawSurveyUpload::query()->whereKey($active->id)->lockForUpdate()->firstOrFail();
            $lockedPrev = RawSurveyUpload::query()->whereKey($previous->id)->lockForUpdate()->firstOrFail();

            $activeAttrs = ['status' => RawSurveyUpload::STATUS_SUPERSEDED];
            if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
                $activeAttrs['is_active'] = false;
            }
            if (Schema::hasColumn('raw_survey_uploads', 'active_dedup_key')) {
                $activeAttrs['active_dedup_key'] = null;
            }
            if (Schema::hasColumn('raw_survey_uploads', 'superseded_at')) {
                $activeAttrs['superseded_at'] = now();
            }
            if (Schema::hasColumn('raw_survey_uploads', 'superseded_by_id')) {
                $activeAttrs['superseded_by_id'] = $lockedPrev->id;
            }
            $lockedActive->update($activeAttrs);
            $this->setResponsesActive($lockedActive->id, false);

            return $this->activateBatch($event, $lockedPrev);
        });
    }

    public function currentActiveBatch(CarbootEvent $event): ?RawSurveyUpload
    {
        if (! Schema::hasTable('raw_survey_uploads')) {
            return null;
        }

        $query = RawSurveyUpload::query()
            ->where('carboot_event_id', $event->id)
            ->whereIn('status', [
                RawSurveyUpload::STATUS_COMPLETED,
                RawSurveyUpload::STATUS_COMPLETED_WITH_ERRORS,
            ]);

        if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
            $query->where('is_active', true);
        }

        // Earliest qualifying batch (also prevents pre-provenance double-import inflation).
        return $query->orderBy('id')->first();
    }

    /**
     * @return list<string>
     */
    public function availableActions(RawSurveyUpload $batch, ?RawSurveyUpload $activeBatch): array
    {
        $actions = [];
        $isActive = $activeBatch && (int) $activeBatch->id === (int) $batch->id;

        if (! $isActive && in_array($batch->status, [
            RawSurveyUpload::STATUS_COMPLETED,
            RawSurveyUpload::STATUS_COMPLETED_WITH_ERRORS,
            RawSurveyUpload::STATUS_EXCLUDED,
            RawSurveyUpload::STATUS_ARCHIVED,
            RawSurveyUpload::STATUS_SUPERSEDED,
            RawSurveyUpload::STATUS_DUPLICATE,
        ], true)) {
            $actions[] = 'use_in_analytics';
            $actions[] = 'restore';
        }

        if ($isActive || in_array($batch->status, [
            RawSurveyUpload::STATUS_COMPLETED,
            RawSurveyUpload::STATUS_COMPLETED_WITH_ERRORS,
            RawSurveyUpload::STATUS_DUPLICATE,
            RawSurveyUpload::STATUS_SUPERSEDED,
        ], true)) {
            if ($batch->status !== RawSurveyUpload::STATUS_EXCLUDED) {
                $actions[] = 'exclude';
            }
        }

        if ($batch->status !== RawSurveyUpload::STATUS_ARCHIVED
            && $batch->status !== RawSurveyUpload::STATUS_FAILED
            && $batch->status !== RawSurveyUpload::STATUS_PENDING
        ) {
            $actions[] = 'archive';
        }

        if ($isActive) {
            $actions[] = 'replace';
            $actions[] = 'undo';
        }

        return array_values(array_unique($actions));
    }

    private function deactivateSiblings(CarbootEvent $event, RawSurveyUpload $keep): void
    {
        $siblings = RawSurveyUpload::query()
            ->where('carboot_event_id', $event->id)
            ->where('schema_name', $keep->schema_name)
            ->where('schema_version', $keep->schema_version)
            ->where('id', '!=', $keep->id)
            ->where(function ($q) {
                $q->whereIn('status', [
                    RawSurveyUpload::STATUS_COMPLETED,
                    RawSurveyUpload::STATUS_COMPLETED_WITH_ERRORS,
                ]);
                if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
                    $q->orWhere('is_active', true);
                }
            })
            ->lockForUpdate()
            ->get();

        foreach ($siblings as $sibling) {
            $attrs = [
                'status' => RawSurveyUpload::STATUS_SUPERSEDED,
            ];
            if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
                $attrs['is_active'] = false;
            }
            if (Schema::hasColumn('raw_survey_uploads', 'active_dedup_key')) {
                $attrs['active_dedup_key'] = null;
            }
            if (Schema::hasColumn('raw_survey_uploads', 'superseded_at')) {
                $attrs['superseded_at'] = now();
            }
            if (Schema::hasColumn('raw_survey_uploads', 'superseded_by_id')) {
                $attrs['superseded_by_id'] = $keep->id;
            }
            $sibling->update($attrs);
            $this->setResponsesActive($sibling->id, false);
        }
    }

    private function tryActivatePreviousSuperseded(CarbootEvent $event, RawSurveyUpload $removed): void
    {
        $previous = RawSurveyUpload::query()
            ->where('carboot_event_id', $event->id)
            ->where('schema_name', $removed->schema_name)
            ->where('schema_version', $removed->schema_version)
            ->where('id', '!=', $removed->id)
            ->whereIn('status', [RawSurveyUpload::STATUS_SUPERSEDED])
            ->orderByDesc('id')
            ->first();

        if ($previous) {
            $this->activateBatch($event, $previous);
        }
    }

    private function setResponsesActive(int $batchId, bool $active): void
    {
        if (! Schema::hasTable('survey_responses')) {
            return;
        }

        if (Schema::hasColumn('survey_responses', 'is_active')) {
            SurveyResponse::query()
                ->where('import_batch_id', $batchId)
                ->update(['is_active' => $active]);
        }
    }

    private function completedStatusFor(RawSurveyUpload $batch): string
    {
        if ($batch->restored_from_status
            && in_array($batch->restored_from_status, [
                RawSurveyUpload::STATUS_COMPLETED,
                RawSurveyUpload::STATUS_COMPLETED_WITH_ERRORS,
            ], true)
        ) {
            return $batch->restored_from_status;
        }

        if ((int) ($batch->invalid_row_count ?? 0) > 0 && (int) ($batch->valid_row_count ?? 0) > 0) {
            return RawSurveyUpload::STATUS_COMPLETED_WITH_ERRORS;
        }

        return RawSurveyUpload::STATUS_COMPLETED;
    }

    private function rememberCompletedStatus(RawSurveyUpload $batch): ?string
    {
        if (in_array($batch->status, [
            RawSurveyUpload::STATUS_COMPLETED,
            RawSurveyUpload::STATUS_COMPLETED_WITH_ERRORS,
        ], true)) {
            return $batch->status;
        }

        return $batch->restored_from_status;
    }

    private function assertBatchBelongsToEvent(CarbootEvent $event, RawSurveyUpload $batch): void
    {
        if ((int) $batch->carboot_event_id !== (int) $event->id) {
            throw new RuntimeException('Survey import batch does not belong to this event.');
        }
    }

    private function assertBatchUsable(RawSurveyUpload $batch): void
    {
        if ((int) ($batch->valid_row_count ?? 0) <= 0
            && ! in_array($batch->status, [
                RawSurveyUpload::STATUS_COMPLETED,
                RawSurveyUpload::STATUS_COMPLETED_WITH_ERRORS,
                RawSurveyUpload::STATUS_EXCLUDED,
                RawSurveyUpload::STATUS_ARCHIVED,
                RawSurveyUpload::STATUS_SUPERSEDED,
                RawSurveyUpload::STATUS_DUPLICATE,
            ], true)
        ) {
            throw new RuntimeException('This import has no valid responses to use in analytics.');
        }

        if ($batch->status === RawSurveyUpload::STATUS_FAILED) {
            throw new RuntimeException('Failed imports cannot be used in analytics.');
        }
    }

    private function invalidateSurveyCache(int $eventId): void
    {
        if (! Schema::hasTable('analytics_results')) {
            return;
        }

        AnalyticsResult::query()
            ->where('carboot_event_id', $eventId)
            ->where('metric_key', SurveySchema::SURVEY_METRIC_KEY)
            ->delete();
    }
}
