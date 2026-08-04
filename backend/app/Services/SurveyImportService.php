<?php

namespace App\Services;

use App\Exceptions\DuplicateSurveyImportException;
use App\Exceptions\SurveyReplacementRequiredException;
use App\Models\AnalyticsResult;
use App\Models\CarbootEvent;
use App\Models\RawSurveyUpload;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Support\SurveySchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SurveyImportService
{
    public function __construct(
        private readonly AnalyticsPythonClient $python,
    ) {}

    /**
     * Validate-first CSV import. Destructive replacement occurs only after successful validation
     * and explicit replace_existing confirmation.
     *
     * @throws DuplicateSurveyImportException
     * @throws SurveyReplacementRequiredException
     */
    public function import(
        CarbootEvent $event,
        UploadedFile $file,
        User $uploader,
        bool $replaceExisting = false,
    ): RawSurveyUpload {
        $checksum = hash_file('sha256', $file->getRealPath());
        if (! is_string($checksum) || $checksum === '') {
            throw new RuntimeException('Unable to checksum the survey CSV.');
        }

        $activeBatch = $this->activeBatchForEventSchema($event->id, SurveySchema::NAME, SurveySchema::VERSION);

        // Byte-identical to the currently active CSV — keep existing dataset, no new rows.
        if ($activeBatch && hash_equals((string) $activeBatch->sha256, $checksum)) {
            throw new DuplicateSurveyImportException($activeBatch);
        }

        // Same checksum already kept as an active/completed dataset for this event+schema.
        $existingKeeper = RawSurveyUpload::query()
            ->where('carboot_event_id', $event->id)
            ->where('schema_name', SurveySchema::NAME)
            ->where('schema_version', SurveySchema::VERSION)
            ->where('sha256', $checksum)
            ->where(function ($query) {
                $query->whereIn('status', [
                    RawSurveyUpload::STATUS_COMPLETED,
                    RawSurveyUpload::STATUS_COMPLETED_WITH_ERRORS,
                ]);
                if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
                    $query->orWhere('is_active', true);
                }
            })
            ->orderBy('id')
            ->first();

        if ($existingKeeper) {
            throw new DuplicateSurveyImportException($existingKeeper);
        }

        if ($activeBatch && ! $replaceExisting) {
            throw new SurveyReplacementRequiredException($activeBatch);
        }

        // Validate completely before any mutation of the current dataset.
        $tempPath = $file->getRealPath();
        try {
            $preValidation = $this->python->validateSurveyCsv($tempPath, $file->getClientOriginalName());
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Survey validation failed. The existing dataset was left unchanged.',
                0,
                $e,
            );
        }

        $validRows = (int) ($preValidation['valid_rows'] ?? 0);
        if ($validRows <= 0) {
            throw new RuntimeException(
                'The uploaded file has no valid rows. The existing dataset was left unchanged.'
            );
        }

        $storedPath = $file->storeAs(
            'survey-imports/'.$event->id,
            now()->format('YmdHis').'_'.$checksum.'.csv',
            'local',
        );

        if (! $storedPath) {
            throw new RuntimeException('Unable to store the survey CSV privately.');
        }

        $batchAttrs = [
            'carboot_event_id' => $event->id,
            'uploaded_by' => $uploader->id,
            'schema_name' => SurveySchema::NAME,
            'schema_version' => SurveySchema::VERSION,
            'original_filename' => $file->getClientOriginalName(),
            'storage_disk' => 'local',
            'storage_path' => $storedPath,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize() ?: 0,
            'sha256' => $checksum,
            'status' => RawSurveyUpload::STATUS_PENDING,
        ];
        if (Schema::hasColumn('raw_survey_uploads', 'active_dedup_key')) {
            $batchAttrs['active_dedup_key'] = null;
        }
        if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
            $batchAttrs['is_active'] = false;
        }
        if (Schema::hasColumn('raw_survey_uploads', 'submission_source')) {
            $batchAttrs['submission_source'] = RawSurveyUpload::SOURCE_CSV_IMPORT;
        }

        $batch = RawSurveyUpload::query()->create($batchAttrs);

        try {
            // Pass previous active batch for transactional total replacement after validation.
            return $this->processBatch(
                $batch,
                $replaceExisting ? $activeBatch : null,
                $preValidation,
                $replaceExisting,
            );
        } catch (Throwable $e) {
            Log::warning('Survey import persistence failed after validation.', [
                'carboot_event_id' => $event->id,
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);
            // Failed new batch must not leave a half-imported active dataset.
            // Previous active batch (if any) remains untouched until processBatch succeeds.
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>|null  $preValidation
     */
    public function processBatch(
        RawSurveyUpload $batch,
        ?RawSurveyUpload $batchToReplace = null,
        ?array $preValidation = null,
        bool $totalReplace = false,
    ): RawSurveyUpload {
        $batch->update([
            'status' => RawSurveyUpload::STATUS_PROCESSING,
            'processing_started_at' => now(),
            'failure_message' => null,
        ]);

        $absolutePath = Storage::disk($batch->storage_disk)->path($batch->storage_path);

        try {
            $validation = $preValidation ?? $this->python->validateSurveyCsv($absolutePath, $batch->original_filename);
        } catch (Throwable $e) {
            $batch->update(array_merge($this->inactiveBatchAttrs(), [
                'status' => RawSurveyUpload::STATUS_FAILED,
                'failure_message' => $e->getMessage(),
                'processing_finished_at' => now(),
                'validation_summary' => [
                    'error' => $e->getMessage(),
                ],
            ]));

            throw $e;
        }

        $normalized = $validation['normalized_records'] ?? [];
        $rowErrors = $validation['row_errors'] ?? [];
        $validRows = (int) ($validation['valid_rows'] ?? count($normalized));
        $totalRows = (int) ($validation['total_rows'] ?? 0);
        $invalidRows = (int) ($validation['invalid_rows'] ?? max($totalRows - $validRows, 0));

        if ($validRows <= 0) {
            $batch->update(array_merge($this->inactiveBatchAttrs(), [
                'status' => RawSurveyUpload::STATUS_FAILED,
                'total_row_count' => $totalRows,
                'valid_row_count' => 0,
                'invalid_row_count' => $invalidRows,
                'failure_message' => 'No valid survey rows were imported.',
                'processing_finished_at' => now(),
                'validation_summary' => [
                    'schema_name' => $validation['schema_name'] ?? SurveySchema::NAME,
                    'schema_version' => $validation['schema_version'] ?? SurveySchema::VERSION,
                    'row_errors' => $rowErrors,
                ],
            ]));

            throw new RuntimeException('No valid survey rows were imported. The existing dataset was left unchanged.');
        }

        $storageCleanup = [];

        try {
            DB::transaction(function () use (
                $batch,
                $batchToReplace,
                $totalReplace,
                $normalized,
                $validation,
                $validRows,
                $totalRows,
                $invalidRows,
                $rowErrors,
                &$storageCleanup
            ) {
                // Total replacement: purge previous CSV dataset only after validation succeeded,
                // inside the same transaction that activates the new batch.
                if ($totalReplace || $batchToReplace) {
                    $storageCleanup = array_merge(
                        $storageCleanup,
                        $this->purgeOtherCsvBatchesInTransaction(
                            (int) $batch->carboot_event_id,
                            (string) $batch->schema_name,
                            (string) $batch->schema_version,
                            (int) $batch->id,
                        ),
                    );
                }

                SurveyResponse::query()->where('import_batch_id', $batch->id)->delete();

                $supportsSource = Schema::hasColumn('survey_responses', 'submission_source');
                $supportsActive = Schema::hasColumn('survey_responses', 'is_active');

                foreach ($normalized as $record) {
                    $payload = [
                        'carboot_event_id' => $batch->carboot_event_id,
                        'import_batch_id' => $batch->id,
                        'respondent_id' => $record['respondent_id'],
                        'source_row_number' => $record['source_row_number'],
                        'schema_name' => $record['schema_name'] ?? SurveySchema::NAME,
                        'schema_version' => $record['schema_version'] ?? SurveySchema::VERSION,
                        'product_categories' => $record['product_categories'] ?? null,
                        'product_categories_other_text' => $record['product_categories_other_text'] ?? null,
                        'item_conditions' => $record['item_conditions'] ?? null,
                        'has_difficulty' => $record['has_difficulty'] ?? null,
                        'difficulty_details' => $record['difficulty_details'] ?? null,
                        'event_info_sources' => $record['event_info_sources'] ?? null,
                        'event_info_sources_other_text' => $record['event_info_sources_other_text'] ?? null,
                        'items_sold_band' => $record['items_sold_band'] ?? null,
                        'gross_sales_band' => $record['gross_sales_band'] ?? null,
                        'unsold_item_actions' => $record['unsold_item_actions'] ?? null,
                        'sales_purpose' => $record['sales_purpose'] ?? null,
                        'experience_rating' => $record['experience_rating'] ?? null,
                        'improvement_areas' => $record['improvement_areas'] ?? null,
                        'improvement_areas_other_text' => $record['improvement_areas_other_text'] ?? null,
                        'comments_and_suggestions' => $record['comments_and_suggestions'] ?? null,
                        'supporting_activity_attracted_visitors' => $record['supporting_activity_attracted_visitors'] ?? null,
                        'supporting_activity_impacts' => $record['supporting_activity_impacts'] ?? null,
                        'supporting_activity_impacts_other_text' => $record['supporting_activity_impacts_other_text'] ?? null,
                        'import_auto_review_flags' => $record['import_auto_review_flags'] ?? null,
                        'import_review_notes' => $record['import_review_notes'] ?? null,
                        'validation_status' => $record['validation_status'] ?? 'valid',
                    ];
                    if ($supportsSource) {
                        $payload['submission_source'] = SurveyResponse::SOURCE_CSV_IMPORT;
                    }
                    if ($supportsActive) {
                        $payload['is_active'] = true;
                    }

                    SurveyResponse::query()->create($payload);
                }

                $status = RawSurveyUpload::STATUS_COMPLETED;
                if ($invalidRows > 0 && $validRows > 0) {
                    $status = RawSurveyUpload::STATUS_COMPLETED_WITH_ERRORS;
                }

                $completedAttrs = [
                    'status' => $status,
                    'total_row_count' => $totalRows,
                    'valid_row_count' => $validRows,
                    'invalid_row_count' => $invalidRows,
                    'validation_summary' => [
                        'schema_name' => $validation['schema_name'] ?? SurveySchema::NAME,
                        'schema_version' => $validation['schema_version'] ?? SurveySchema::VERSION,
                        'warnings' => $validation['warnings'] ?? [],
                        'row_errors' => $rowErrors,
                        'missing_headers' => $validation['missing_headers'] ?? [],
                        'unexpected_headers' => $validation['unexpected_headers'] ?? [],
                        'data_completeness' => $validation['data_completeness'] ?? [],
                    ],
                    'failure_message' => null,
                    'processing_finished_at' => now(),
                ];
                if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
                    $completedAttrs['is_active'] = true;
                }
                if (Schema::hasColumn('raw_survey_uploads', 'active_dedup_key')) {
                    $completedAttrs['active_dedup_key'] = RawSurveyUpload::makeActiveDedupKey(
                        $batch->schema_name,
                        $batch->schema_version,
                        $batch->sha256,
                    );
                }
                $batch->update($completedAttrs);

                if (Schema::hasTable('analytics_results')) {
                    AnalyticsResult::query()
                        ->where('carboot_event_id', $batch->carboot_event_id)
                        ->where('metric_key', SurveySchema::SURVEY_METRIC_KEY)
                        ->delete();
                }
            });
        } catch (Throwable $e) {
            $batch->update(array_merge($this->inactiveBatchAttrs(), [
                'status' => RawSurveyUpload::STATUS_FAILED,
                'failure_message' => 'Persistence failed.',
                'processing_finished_at' => now(),
            ]));
            throw $e;
        }

        foreach ($storageCleanup as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (Throwable $e) {
                Log::warning('Failed to delete replaced survey CSV file.', [
                    'carboot_event_id' => $batch->carboot_event_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $batch->fresh();
    }

    /**
     * Delete other CSV batches/responses for the event+schema, keeping $keepBatchId.
     *
     * @return list<array{disk: string, path: string}>
     */
    private function purgeOtherCsvBatchesInTransaction(
        int $eventId,
        string $schemaName,
        string $schemaVersion,
        int $keepBatchId,
    ): array {
        $batches = RawSurveyUpload::query()
            ->where('carboot_event_id', $eventId)
            ->where('schema_name', $schemaName)
            ->where('schema_version', $schemaVersion)
            ->where('id', '!=', $keepBatchId)
            ->lockForUpdate()
            ->get();

        $storageCleanup = [];
        $batchIds = $batches->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($batchIds === []) {
            return $storageCleanup;
        }

        $responseQuery = SurveyResponse::query()
            ->where('carboot_event_id', $eventId)
            ->whereIn('import_batch_id', $batchIds);

        if (Schema::hasColumn('survey_responses', 'submission_source')) {
            $responseQuery->where(function ($q) {
                $q->where('submission_source', SurveyResponse::SOURCE_CSV_IMPORT)
                    ->orWhereNull('submission_source');
            });
        }
        $responseQuery->delete();

        foreach ($batches as $old) {
            if ($old->storage_disk && $old->storage_path) {
                $storageCleanup[] = [
                    'disk' => $old->storage_disk,
                    'path' => $old->storage_path,
                ];
            }
        }

        RawSurveyUpload::query()->whereIn('id', $batchIds)->delete();

        return $storageCleanup;
    }

    /**
     * @return array<string, mixed>
     */
    private function inactiveBatchAttrs(): array
    {
        $attrs = [];
        if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
            $attrs['is_active'] = false;
        }
        if (Schema::hasColumn('raw_survey_uploads', 'active_dedup_key')) {
            $attrs['active_dedup_key'] = null;
        }

        return $attrs;
    }

    public function activeBatchForEventSchema(int $eventId, string $schemaName, string $schemaVersion): ?RawSurveyUpload
    {
        $query = RawSurveyUpload::query()
            ->where('carboot_event_id', $eventId)
            ->where('schema_name', $schemaName)
            ->where('schema_version', $schemaVersion)
            ->whereIn('status', [
                RawSurveyUpload::STATUS_COMPLETED,
                RawSurveyUpload::STATUS_COMPLETED_WITH_ERRORS,
            ]);

        if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->orderBy('id')->first();
    }
}
