<?php

namespace App\Services;

use App\Models\AnalyticsResult;
use App\Models\CarbootEvent;
use App\Models\RawSurveyUpload;
use App\Models\SurveyResponse;
use App\Support\SurveySchema;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class EventAnalyticsService
{
    public function __construct(
        private readonly PostEventSummaryAggregator $aggregator,
        private readonly AnalyticsPythonClient $python,
        private readonly EventAnalyticsDataSourceService $dataSources,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(CarbootEvent $event, bool $recompute = false): array
    {
        $mode = $this->sourceMode($event);
        $includeSystem = in_array($mode, [
            EventAnalyticsDataSourceService::MODE_COMBINED,
            EventAnalyticsDataSourceService::MODE_SYSTEM_ONLY,
        ], true);
        $includeSurvey = in_array($mode, [
            EventAnalyticsDataSourceService::MODE_COMBINED,
            EventAnalyticsDataSourceService::MODE_CSV_ONLY,
        ], true);

        $operational = $includeSystem
            ? $this->safeOperationalSnapshot($event)
            : $this->excludedOperationalSnapshot();

        $survey = $includeSurvey
            ? $this->surveyBundle($event, $recompute)
            : $this->excludedSurveyBundle();

        $readiness = $this->dataReadiness($event, $operational, $survey);
        $dataSources = $this->buildDataSources($event, $operational, $survey, $mode);

        return [
            'carboot_event_id' => $event->id,
            'analytics_source_mode' => $mode,
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'status' => $event->status,
                'starts_at' => optional($event->starts_at)?->toIso8601String(),
                'ends_at' => optional($event->ends_at)?->toIso8601String(),
            ],
            'computed_at' => now()->toIso8601String(),
            'data_sources' => $dataSources,
            'data_readiness' => $readiness,
            'operational' => $operational,
            'survey' => $survey,
            'kpis' => $this->buildKpis($operational, $survey, $mode),
            'unavailable_metrics' => array_values(array_unique(array_merge(
                $operational['unavailable'] ?? [],
                $survey['unavailable_metrics'] ?? [],
                ($survey['degraded'] ?? false) ? ['vendor_survey_analytics'] : [],
            ))),
            'import_history' => $this->importHistory($event),
            'data_source_manager' => [
                'modes' => EventAnalyticsDataSourceService::MODES,
                'selected_mode' => $mode,
                'active_batch_id' => $this->activeImportBatch($event)?->id,
                'undo_available' => $this->undoAvailable($event),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function section(CarbootEvent $event, string $section, bool $recompute = false): array
    {
        $overview = $this->overview($event, $recompute);
        $section = strtolower($section);

        $base = [
            'data_sources' => $overview['data_sources'] ?? [],
            'computed_at' => $overview['computed_at'] ?? null,
        ];

        return match ($section) {
            'vendors' => $base + [
                'section' => 'vendors',
                'operational_vendor_categories' => $overview['operational']['sections']['vendor_categories'] ?? null,
                'survey' => $overview['survey']['sections']['vendors'] ?? null,
                'survey_status' => $overview['survey']['status'] ?? 'unavailable',
                'data_readiness' => $overview['data_readiness'],
            ],
            'economics' => $base + [
                'section' => 'economics',
                'operational_payments' => $overview['operational']['sections']['payments'] ?? null,
                'survey' => $overview['survey']['sections']['economics'] ?? null,
                'survey_status' => $overview['survey']['status'] ?? 'unavailable',
                'note' => 'Invoice totals are platform fees. Survey gross sales remain categorical bands.',
            ],
            'items' => $base + [
                'section' => 'items',
                'operational_item_reservations' => $overview['operational']['sections']['item_reservations'] ?? null,
                'survey' => $overview['survey']['sections']['items'] ?? null,
                'survey_status' => $overview['survey']['status'] ?? 'unavailable',
            ],
            'experience' => $base + [
                'section' => 'experience',
                'operational_feedback' => $overview['operational']['sections']['feedback'] ?? null,
                'survey' => $overview['survey']['sections']['experience'] ?? null,
                'survey_status' => $overview['survey']['status'] ?? 'unavailable',
            ],
            'operations' => $base + [
                'section' => 'operations',
                'operational_booking_pipeline' => $overview['operational']['sections']['booking_pipeline'] ?? null,
                'operational_event_sites' => $overview['operational']['sections']['event_sites'] ?? null,
                'survey' => $overview['survey']['sections']['operations'] ?? null,
                'survey_status' => $overview['survey']['status'] ?? 'unavailable',
            ],
            'data-quality', 'data_quality' => $base + [
                'section' => 'data-quality',
                'data_readiness' => $overview['data_readiness'],
                'survey' => $overview['survey']['sections']['data_quality'] ?? null,
                'survey_status' => $overview['survey']['status'] ?? 'unavailable',
                'latest_import' => $this->latestImportSummary($event),
                'import_history' => $overview['import_history'] ?? [],
                'analytics_source_mode' => $overview['analytics_source_mode'] ?? EventAnalyticsDataSourceService::MODE_COMBINED,
                'data_source_manager' => $overview['data_source_manager'] ?? null,
            ],
            default => throw new RuntimeException('Unknown analytics section.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function recompute(CarbootEvent $event): array
    {
        return $this->overview($event, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function excludedOperationalSnapshot(): array
    {
        return [
            'available' => false,
            'status' => 'excluded',
            'included_in_analytics' => false,
            'sections' => [],
            'data_availability' => [],
            'unavailable' => ['operational_snapshot'],
            'message' => 'System Data is excluded by the current analytics source mode.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function excludedSurveyBundle(): array
    {
        return [
            'status' => 'excluded',
            'degraded' => false,
            'available' => false,
            'included_in_analytics' => false,
            'message' => 'CSV survey data is excluded by the current analytics source mode.',
            'respondent_count' => 0,
            'sections' => [],
            'unavailable_metrics' => ['vendor_survey'],
        ];
    }

    private function sourceMode(CarbootEvent $event): string
    {
        if (! Schema::hasColumn('carboot_events', 'analytics_source_mode')) {
            return EventAnalyticsDataSourceService::MODE_COMBINED;
        }

        $mode = (string) ($event->analytics_source_mode ?: EventAnalyticsDataSourceService::MODE_COMBINED);

        return in_array($mode, EventAnalyticsDataSourceService::MODES, true)
            ? $mode
            : EventAnalyticsDataSourceService::MODE_COMBINED;
    }

    private function undoAvailable(CarbootEvent $event): bool
    {
        $active = $this->activeImportBatch($event);
        if (! $active) {
            return false;
        }

        return RawSurveyUpload::query()
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
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function safeOperationalSnapshot(CarbootEvent $event): array
    {
        try {
            $snapshot = $this->aggregator->build($event);

            return [
                'available' => true,
                'status' => 'ready',
                'included_in_analytics' => true,
                'sections' => $snapshot['sections'] ?? [],
                'data_availability' => $snapshot['data_availability'] ?? [],
                'provisional' => $snapshot['provisional'] ?? null,
                'unavailable' => [],
            ];
        } catch (Throwable $e) {
            return [
                'available' => false,
                'status' => 'unavailable',
                'included_in_analytics' => false,
                'sections' => [],
                'data_availability' => [],
                'unavailable' => ['operational_snapshot'],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function surveyBundle(CarbootEvent $event, bool $recompute = false): array
    {
        if (! Schema::hasTable('survey_responses') || ! Schema::hasTable('raw_survey_uploads')) {
            return [
                'status' => 'unavailable',
                'degraded' => true,
                'available' => false,
                'message' => 'Survey storage tables are not migrated yet.',
                'respondent_count' => 0,
                'sections' => [],
                'unavailable_metrics' => ['vendor_survey'],
            ];
        }

        $latestBatch = $this->activeImportBatch($event);

        $responseCount = SurveyResponse::query()
            ->forAnalytics($event->id)
            ->count();

        if ($responseCount === 0) {
            return [
                'status' => 'empty',
                'degraded' => false,
                'available' => false,
                'message' => 'No valid vendor survey responses imported for this event.',
                'respondent_count' => 0,
                'import_batch_id' => $latestBatch?->id,
                'sections' => [],
                'unavailable_metrics' => ['vendor_survey'],
            ];
        }

        $fingerprint = $this->surveyFingerprint($event, $latestBatch);
        $cached = AnalyticsResult::query()
            ->where('carboot_event_id', $event->id)
            ->where('metric_key', SurveySchema::SURVEY_METRIC_KEY)
            ->where('calculation_version', SurveySchema::CALCULATION_VERSION)
            ->first();

        if (! $recompute && $cached && $cached->status === AnalyticsResult::STATUS_READY && $cached->source_fingerprint === $fingerprint) {
            return array_merge($cached->payload ?? [], [
                'status' => 'ready',
                'degraded' => false,
                'available' => true,
                'included_in_analytics' => true,
                'cached' => true,
            ]);
        }

        try {
            $records = SurveyResponse::query()
                ->forAnalytics($event->id)
                ->get()
                ->map(fn (SurveyResponse $row) => $row->only([
                    'respondent_id',
                    'source_row_number',
                    'product_categories',
                    'item_conditions',
                    'has_difficulty',
                    'difficulty_details',
                    'event_info_sources',
                    'items_sold_band',
                    'gross_sales_band',
                    'unsold_item_actions',
                    'sales_purpose',
                    'experience_rating',
                    'improvement_areas',
                    'comments_and_suggestions',
                    'supporting_activity_attracted_visitors',
                    'supporting_activity_impacts',
                ]))
                ->all();

            $payload = $this->python->aggregateSurvey([
                'carboot_event_id' => $event->id,
                'import_batch_id' => $latestBatch?->id,
                'source_fingerprint' => $fingerprint,
                'records' => $records,
            ]);

            AnalyticsResult::query()->updateOrCreate(
                [
                    'carboot_event_id' => $event->id,
                    'metric_key' => SurveySchema::SURVEY_METRIC_KEY,
                    'calculation_version' => SurveySchema::CALCULATION_VERSION,
                ],
                [
                    'payload' => $payload,
                    'source_fingerprint' => $fingerprint,
                    'import_batch_id' => $latestBatch?->id,
                    'status' => AnalyticsResult::STATUS_READY,
                    'computed_at' => now(),
                    'failure_message' => null,
                ],
            );

            return array_merge($payload, [
                'status' => 'ready',
                'degraded' => false,
                'available' => true,
                'included_in_analytics' => true,
                'cached' => false,
            ]);
        } catch (Throwable $e) {
            AnalyticsResult::query()->updateOrCreate(
                [
                    'carboot_event_id' => $event->id,
                    'metric_key' => SurveySchema::SURVEY_METRIC_KEY,
                    'calculation_version' => SurveySchema::CALCULATION_VERSION,
                ],
                [
                    'payload' => null,
                    'source_fingerprint' => $fingerprint,
                    'import_batch_id' => $latestBatch?->id,
                    'status' => AnalyticsResult::STATUS_FAILED,
                    'computed_at' => now(),
                    'failure_message' => $e->getMessage(),
                ],
            );

            return [
                'status' => 'degraded',
                'degraded' => true,
                'available' => false,
                'message' => $e->getMessage(),
                'respondent_count' => $responseCount,
                'import_batch_id' => $latestBatch?->id,
                'sections' => [],
                'unavailable_metrics' => ['vendor_survey_analytics'],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $operational
     * @param  array<string, mixed>  $survey
     * @return array<string, mixed>
     */
    private function dataReadiness(CarbootEvent $event, array $operational, array $survey): array
    {
        $checks = [
            'operational_core' => [
                'label' => 'Operational event snapshot',
                'ready' => (bool) ($operational['available'] ?? false),
                'detail' => ($operational['available'] ?? false)
                    ? 'Booking, payment, and site metrics can be aggregated.'
                    : ($operational['error'] ?? 'Operational snapshot unavailable.'),
            ],
            'survey_storage' => [
                'label' => 'Survey storage tables',
                'ready' => Schema::hasTable('survey_responses') && Schema::hasTable('raw_survey_uploads'),
                'detail' => Schema::hasTable('survey_responses')
                    ? 'Survey tables present.'
                    : 'Apply analytics migrations to enable survey import.',
            ],
            'survey_responses' => [
                'label' => 'Vendor survey responses',
                'ready' => ($survey['respondent_count'] ?? 0) > 0 && ($survey['status'] ?? '') === 'ready',
                'detail' => match ($survey['status'] ?? 'unavailable') {
                    'ready' => ($survey['respondent_count'] ?? 0).' active valid responses (duplicate/superseded imports excluded).',
                    'empty' => 'No active survey imported for this event yet.',
                    'degraded' => $survey['message'] ?? 'Survey analytics degraded.',
                    default => $survey['message'] ?? 'Survey analytics unavailable.',
                },
            ],
            'community_feedback_event_link' => [
                'label' => 'Community feedback event linkage',
                'ready' => Schema::hasColumn('feedbacks', 'carboot_event_id'),
                'detail' => Schema::hasColumn('feedbacks', 'carboot_event_id')
                    ? 'feedbacks.carboot_event_id is available.'
                    : 'Migration pending — community feedback remains global/unscoped.',
            ],
            'python_analytics' => [
                'label' => 'Python analytics service',
                'ready' => $this->python->isReachable(),
                'detail' => $this->python->isReachable()
                    ? 'Analytics service reachable at configured URL.'
                    : 'Python analytics service unreachable — survey compute will degrade.',
            ],
        ];

        $readyCount = collect($checks)->where('ready', true)->count();

        return [
            'carboot_event_id' => $event->id,
            'ready_count' => $readyCount,
            'total_checks' => count($checks),
            'checks' => $checks,
            'latest_import' => $this->latestImportSummary($event),
        ];
    }

    /**
     * @param  array<string, mixed>  $operational
     * @param  array<string, mixed>  $survey
     * @return array<int, array<string, mixed>>
     */
    private function buildKpis(array $operational, array $survey, string $mode): array
    {
        $pipeline = $operational['sections']['booking_pipeline'] ?? [];
        $payments = $operational['sections']['payments'] ?? [];
        $includeSystem = in_array($mode, [
            EventAnalyticsDataSourceService::MODE_COMBINED,
            EventAnalyticsDataSourceService::MODE_SYSTEM_ONLY,
        ], true);
        $includeSurvey = in_array($mode, [
            EventAnalyticsDataSourceService::MODE_COMBINED,
            EventAnalyticsDataSourceService::MODE_CSV_ONLY,
        ], true);

        return [
            [
                'key' => 'approved_vendors',
                'label' => 'Approved bookings',
                'value' => $includeSystem ? ($pipeline['approved_count'] ?? null) : null,
                'source' => 'operational',
                'display' => $includeSystem
                    ? (string) ($pipeline['approved_count'] ?? '—')
                    : 'Excluded',
            ],
            [
                'key' => 'collected_fees',
                'label' => 'Collected platform fees (RM)',
                'value' => $includeSystem ? ($payments['collected'] ?? null) : null,
                'source' => 'operational',
                'note' => 'Platform fees, not vendor gross sales',
                'display' => $includeSystem
                    ? (string) ($payments['collected'] ?? '—')
                    : 'Excluded',
            ],
            [
                'key' => 'survey_respondents',
                'label' => 'Survey respondents',
                'value' => $includeSurvey ? ($survey['respondent_count'] ?? 0) : 0,
                'source' => 'survey',
                'display' => ! $includeSurvey
                    ? 'Excluded'
                    : ((($survey['status'] ?? '') === 'ready')
                        ? (string) ($survey['respondent_count'] ?? 0)
                        : 'Unavailable'),
            ],
            [
                'key' => 'survey_status',
                'label' => 'Survey analytics status',
                'value' => $includeSurvey ? ($survey['status'] ?? 'unavailable') : 'excluded',
                'source' => 'survey',
            ],
        ];
    }

    private function surveyFingerprint(CarbootEvent $event, ?RawSurveyUpload $batch): string
    {
        $count = SurveyResponse::query()->forAnalytics($event->id)->count();
        $maxId = SurveyResponse::query()->forAnalytics($event->id)->max('id') ?? 0;
        $mode = $this->sourceMode($event);

        return sha1(implode(':', [
            $event->id,
            $mode,
            $batch?->id ?? 0,
            $batch?->sha256 ?? 'none',
            $batch?->is_active ? '1' : '0',
            $count,
            $maxId,
            SurveySchema::CALCULATION_VERSION,
        ]));
    }

    private function activeImportBatch(CarbootEvent $event): ?RawSurveyUpload
    {
        return $this->dataSources->currentActiveBatch($event);
    }

    /**
     * @param  array<string, mixed>  $operational
     * @param  array<string, mixed>  $survey
     * @return list<array<string, mixed>>
     */
    private function buildDataSources(CarbootEvent $event, array $operational, array $survey, string $mode): array
    {
        $sources = [];
        $includeSystem = in_array($mode, [
            EventAnalyticsDataSourceService::MODE_COMBINED,
            EventAnalyticsDataSourceService::MODE_SYSTEM_ONLY,
        ], true);
        $includeSurvey = in_array($mode, [
            EventAnalyticsDataSourceService::MODE_COMBINED,
            EventAnalyticsDataSourceService::MODE_CSV_ONLY,
        ], true);

        // Always describe System Data availability for provenance, with include/exclude flag.
        $sources[] = [
            'type' => 'system_database',
            'label' => 'System Data',
            'updated_at' => now()->toIso8601String(),
            'sources' => ['bookings', 'invoices', 'event_sites', 'item_reservations'],
            'status' => ($operational['available'] ?? false) ? 'active' : 'unavailable',
            'included_in_analytics' => $includeSystem && ($operational['available'] ?? false),
            'inclusion_label' => $includeSystem ? 'Included in analytics' : 'Excluded from analytics',
        ];

        $batch = $this->activeImportBatch($event);
        if ($batch) {
            $csvIncluded = $includeSurvey && ($survey['status'] ?? null) === 'ready';
            $sources[] = [
                'type' => 'csv_import',
                'label' => 'CSV Import',
                'batch_id' => $batch->id,
                'original_filename' => $includeSurvey ? $batch->original_filename : null,
                'schema_name' => $batch->schema_name,
                'schema_version' => $batch->schema_version,
                'imported_at' => optional($batch->processing_finished_at ?? $batch->created_at)?->toIso8601String(),
                'respondent_count' => $includeSurvey
                    ? (int) ($survey['respondent_count'] ?? $batch->valid_row_count ?? 0)
                    : 0,
                'status' => $csvIncluded ? 'active' : 'excluded',
                'included_in_analytics' => $csvIncluded,
                'inclusion_label' => $includeSurvey ? 'Included in analytics' : 'Excluded from analytics',
            ];
        }

        // Hide CSV filename entirely in system_only mode (requirement).
        if ($mode === EventAnalyticsDataSourceService::MODE_SYSTEM_ONLY) {
            $sources = array_values(array_filter(
                $sources,
                fn (array $s) => $s['type'] !== 'csv_import'
            ));
        }

        // Hide system card details when csv_only? Spec says show System/CSV/Mixed/No Data.
        // Keep system with Excluded label for csv_only.
        if ($mode === EventAnalyticsDataSourceService::MODE_CSV_ONLY) {
            foreach ($sources as &$source) {
                if ($source['type'] === 'system_database') {
                    $source['included_in_analytics'] = false;
                    $source['inclusion_label'] = 'Excluded from analytics';
                    $source['status'] = 'excluded';
                }
            }
            unset($source);
        }

        return $sources;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function importHistory(CarbootEvent $event): array
    {
        if (! Schema::hasTable('raw_survey_uploads')) {
            return [];
        }

        $active = $this->activeImportBatch($event);

        return RawSurveyUpload::query()
            ->where('carboot_event_id', $event->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (RawSurveyUpload $batch) use ($active) {
                $isActive = $active && (int) $active->id === (int) $batch->id;

                return [
                    'id' => $batch->id,
                    'original_filename' => $batch->original_filename,
                    'imported_at' => optional($batch->processing_finished_at ?? $batch->created_at)?->toIso8601String(),
                    'schema_name' => $batch->schema_name,
                    'schema_version' => $batch->schema_version,
                    'respondent_count' => (int) ($batch->valid_row_count ?? 0),
                    'total_row_count' => $batch->total_row_count,
                    'valid_row_count' => $batch->valid_row_count,
                    'invalid_row_count' => $batch->invalid_row_count,
                    'status' => $batch->status,
                    'status_label' => $isActive ? 'Active' : $batch->humanStatusLabel(),
                    'is_active' => $isActive,
                    'duplicate_of_id' => $batch->duplicate_of_id,
                    'superseded_by_id' => $batch->superseded_by_id,
                    'excluded_at' => optional($batch->excluded_at)?->toIso8601String(),
                    'archived_at' => optional($batch->archived_at)?->toIso8601String(),
                    'submission_source' => $batch->submission_source ?? RawSurveyUpload::SOURCE_CSV_IMPORT,
                    'checksum_short' => $batch->shortenedChecksum(),
                    'actions' => $this->dataSources->availableActions($batch, $active),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestImportSummary(CarbootEvent $event): ?array
    {
        $batch = $this->activeImportBatch($event);
        if (! $batch) {
            return null;
        }

        return [
            'id' => $batch->id,
            'status' => $batch->status,
            'status_label' => 'Active',
            'is_active' => true,
            'original_filename' => $batch->original_filename,
            'total_row_count' => $batch->total_row_count,
            'valid_row_count' => $batch->valid_row_count,
            'invalid_row_count' => $batch->invalid_row_count,
            'checksum_short' => $batch->shortenedChecksum(),
            'schema_version' => $batch->schema_version,
            'submission_source' => $batch->submission_source ?? RawSurveyUpload::SOURCE_CSV_IMPORT,
            'processing_finished_at' => optional($batch->processing_finished_at)?->toIso8601String(),
            'created_at' => optional($batch->created_at)?->toIso8601String(),
        ];
    }
}
