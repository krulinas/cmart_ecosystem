<?php

namespace App\Services;

use App\Models\AnalyticsResult;
use App\Models\CarbootEvent;
use App\Models\RawSurveyUpload;
use App\Models\SurveyResponse;
use App\Support\SurveySchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Event analytics source mode + permanent CSV dataset lifecycle.
 *
 * Soft archive/restore/exclude lifecycle is deprecated for organizer UX.
 * Permanent deletion removes CSV-imported responses, batch rows, and raw files.
 * System operational data and system_submission responses are never deleted.
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

    /**
     * Permanently delete the event's current CSV survey dataset (and checksum twins).
     *
     * @return array{
     *   deleted: bool,
     *   deleted_batch_count: int,
     *   deleted_response_count: int,
     *   analytics_source_mode: string,
     *   event: CarbootEvent,
     *   summary: array{original_filename: ?string, respondent_count: int, schema_label: ?string}
     * }
     */
    public function permanentlyDeleteCurrentCsv(CarbootEvent $event): array
    {
        if (! Schema::hasTable('raw_survey_uploads') || ! Schema::hasTable('survey_responses')) {
            throw new RuntimeException('Survey import tables are not available.');
        }

        $active = $this->currentActiveBatch($event);
        if (! $active) {
            // Still force system_only and clear any stray CSV leftovers for this event/schema.
            $purge = $this->purgeCsvDatasetForEventSchema(
                $event,
                SurveySchema::NAME,
                SurveySchema::VERSION,
            );
            $modeResult = $this->forceSystemOnlyMode($event);

            return [
                'deleted' => $purge['deleted_batch_count'] > 0,
                'deleted_batch_count' => $purge['deleted_batch_count'],
                'deleted_response_count' => $purge['deleted_response_count'],
                'analytics_source_mode' => $modeResult['analytics_source_mode'],
                'event' => $modeResult['event'],
                'summary' => [
                    'original_filename' => null,
                    'respondent_count' => 0,
                    'schema_label' => SurveySchema::VERSION,
                ],
            ];
        }

        $summary = [
            'original_filename' => $active->original_filename,
            'respondent_count' => (int) ($active->valid_row_count ?? 0),
            'schema_label' => $active->schema_version ?: SurveySchema::VERSION,
        ];

        $purge = $this->purgeCsvDatasetForEventSchema(
            $event,
            (string) $active->schema_name,
            (string) $active->schema_version,
            (string) ($active->sha256 ?? ''),
        );

        $modeResult = $this->forceSystemOnlyMode($event);

        return [
            'deleted' => true,
            'deleted_batch_count' => $purge['deleted_batch_count'],
            'deleted_response_count' => $purge['deleted_response_count'],
            'analytics_source_mode' => $modeResult['analytics_source_mode'],
            'event' => $modeResult['event'],
            'summary' => $summary,
        ];
    }

    /**
     * Hard-delete all CSV-imported survey data for an event + schema (total replacement prep).
     *
     * @return array{deleted_batch_count: int, deleted_response_count: int}
     */
    public function purgeCsvDatasetForEventSchema(
        CarbootEvent $event,
        string $schemaName,
        string $schemaVersion,
        ?string $alsoMatchChecksum = null,
    ): array {
        $storageCleanup = [];

        $result = DB::transaction(function () use (
            $event,
            $schemaName,
            $schemaVersion,
            $alsoMatchChecksum,
            &$storageCleanup
        ) {
            $query = RawSurveyUpload::query()
                ->where('carboot_event_id', $event->id)
                ->where('schema_name', $schemaName)
                ->where('schema_version', $schemaVersion)
                ->lockForUpdate();

            $batches = $query->get();

            // Include checksum twins that may have drifted schema labels unexpectedly.
            if ($alsoMatchChecksum) {
                $twins = RawSurveyUpload::query()
                    ->where('carboot_event_id', $event->id)
                    ->where('schema_name', $schemaName)
                    ->where('sha256', $alsoMatchChecksum)
                    ->whereNotIn('id', $batches->pluck('id')->all())
                    ->lockForUpdate()
                    ->get();
                $batches = $batches->concat($twins);
            }

            $batchIds = $batches->pluck('id')->map(fn ($id) => (int) $id)->all();
            $deletedResponses = 0;

            if ($batchIds !== []) {
                $responseQuery = SurveyResponse::query()
                    ->where('carboot_event_id', $event->id)
                    ->whereIn('import_batch_id', $batchIds);

                // Never delete direct system submissions.
                if (Schema::hasColumn('survey_responses', 'submission_source')) {
                    $responseQuery->where(function ($q) {
                        $q->where('submission_source', SurveyResponse::SOURCE_CSV_IMPORT)
                            ->orWhereNull('submission_source');
                    });
                }

                $deletedResponses = (int) $responseQuery->delete();

                foreach ($batches as $batch) {
                    if ($batch->storage_disk && $batch->storage_path) {
                        $storageCleanup[] = [
                            'disk' => $batch->storage_disk,
                            'path' => $batch->storage_path,
                        ];
                    }
                }

                RawSurveyUpload::query()->whereIn('id', $batchIds)->delete();
            }

            $this->invalidateSurveyCache((int) $event->id);

            return [
                'deleted_batch_count' => count($batchIds),
                'deleted_response_count' => $deletedResponses,
            ];
        });

        foreach ($storageCleanup as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (Throwable $e) {
                Log::warning('Failed to delete survey CSV storage object after hard delete.', [
                    'carboot_event_id' => $event->id,
                    'disk' => $file['disk'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * @deprecated Soft lifecycle — organizer UI must not use this. Prefer permanent delete + re-upload.
     */
    public function activateBatch(CarbootEvent $event, RawSurveyUpload $batch): RawSurveyUpload
    {
        throw new RuntimeException(
            'Activate/restore is no longer supported. Upload a survey CSV again after deleting the previous dataset.'
        );
    }

    /**
     * @deprecated Soft exclude — use permanentlyDeleteCurrentCsv.
     */
    public function excludeBatch(CarbootEvent $event, RawSurveyUpload $batch): RawSurveyUpload
    {
        throw new RuntimeException(
            'Exclude is no longer supported. Use Delete CSV Data to permanently remove the survey dataset.'
        );
    }

    /**
     * @deprecated Soft remove — use permanentlyDeleteCurrentCsv.
     *
     * @return array{batch: ?RawSurveyUpload, analytics_source_mode: string, event: CarbootEvent}
     */
    public function removeCsvFromAnalytics(CarbootEvent $event): array
    {
        $result = $this->permanentlyDeleteCurrentCsv($event);

        return [
            'batch' => null,
            'analytics_source_mode' => $result['analytics_source_mode'],
            'event' => $result['event'],
        ];
    }

    /**
     * @deprecated Soft archive — not part of organizer UX.
     */
    public function archiveBatch(CarbootEvent $event, RawSurveyUpload $batch): RawSurveyUpload
    {
        throw new RuntimeException(
            'Archive is no longer supported. Use Delete CSV Data to permanently remove the survey dataset.'
        );
    }

    /**
     * @deprecated Soft restore — not part of organizer UX.
     */
    public function restoreBatch(CarbootEvent $event, RawSurveyUpload $batch): RawSurveyUpload
    {
        throw new RuntimeException(
            'Restore is no longer supported. Upload a survey CSV again after deleting the previous dataset.'
        );
    }

    /**
     * @deprecated Soft undo — not part of organizer UX.
     */
    public function undoImport(CarbootEvent $event): RawSurveyUpload
    {
        throw new RuntimeException(
            'Undo is no longer supported. Use Replace CSV or Delete CSV Data instead.'
        );
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

        return $query->orderBy('id')->first();
    }

    /**
     * @return list<string>
     */
    public function availableActions(RawSurveyUpload $batch, ?RawSurveyUpload $activeBatch): array
    {
        $actions = [];
        $isActive = $activeBatch && (int) $activeBatch->id === (int) $batch->id;

        if ($isActive) {
            $actions[] = 'replace';
            $actions[] = 'delete';
        }

        return $actions;
    }

    public function invalidateSurveyCache(int $eventId): void
    {
        if (! Schema::hasTable('analytics_results')) {
            return;
        }

        AnalyticsResult::query()
            ->where('carboot_event_id', $eventId)
            ->where('metric_key', SurveySchema::SURVEY_METRIC_KEY)
            ->delete();
    }

    /**
     * @return array{event: CarbootEvent, analytics_source_mode: string}
     */
    private function forceSystemOnlyMode(CarbootEvent $event): array
    {
        if (Schema::hasColumn('carboot_events', 'analytics_source_mode')) {
            return $this->setSourceMode($event->fresh(), self::MODE_SYSTEM_ONLY);
        }

        return [
            'analytics_source_mode' => self::MODE_SYSTEM_ONLY,
            'event' => $event->fresh(),
        ];
    }
}
