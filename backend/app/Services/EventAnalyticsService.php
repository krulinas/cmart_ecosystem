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

        $operational = $includeSystem
            ? $this->safeOperationalSnapshot($event)
            : $this->excludedOperationalSnapshot();

        // Survey membership is mode-filtered (combined / csv_only / system_only).
        $survey = $this->surveyBundle($event, $recompute, $mode);

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
                'permanent_delete_supported' => true,
                'soft_lifecycle_deprecated' => true,
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
            'survey-results', 'survey_results', 'feedback-summary', 'feedback_summary' => $base + [
                'section' => 'feedback-summary',
                'survey' => $overview['survey']['sections'] ?? null,
                'survey_status' => $overview['survey']['status'] ?? 'unavailable',
                'respondent_count' => $overview['survey']['respondent_count'] ?? 0,
                'operational_feedback' => $overview['operational']['sections']['feedback'] ?? null,
                'event_performance' => $overview['operational']['sections']['event_performance'] ?? null,
            ],
            'vendor-comments', 'vendor_comments', 'comments', 'vendor-feedback', 'vendor_feedback' => $base + [
                'section' => 'vendor-feedback',
                'survey' => $overview['survey']['sections']['experience'] ?? null,
                'qualitative' => $overview['survey']['sections']['experience']['qualitative_comments']
                    ?? $overview['survey']['sections']['experience']['comments_and_suggestions']
                    ?? null,
                'survey_status' => $overview['survey']['status'] ?? 'unavailable',
                'operational_feedback' => $overview['operational']['sections']['feedback'] ?? null,
            ],
            'data-sources', 'data_sources', 'data-quality', 'data_quality' => $base + [
                'section' => 'data-sources',
                'data_readiness' => $overview['data_readiness'],
                'latest_import' => $this->latestImportSummary($event),
                'analytics_source_mode' => $overview['analytics_source_mode']
                    ?? EventAnalyticsDataSourceService::MODE_SYSTEM_ONLY,
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
            'message' => __('api.system_data_is_excluded_by_the_current_analytics_source_mode'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function excludedSurveyBundle(): array
    {
        return [
            'status' => 'excluded',
            'state' => 'excluded',
            'degraded' => false,
            'available' => false,
            'included_in_analytics' => false,
            'message' => __('api.survey_csv_is_excluded_by_the_current_source_mode__e5bce451'),
            'respondent_count' => 0,
            'sections' => [],
            'unavailable_metrics' => ['vendor_survey'],
        ];
    }

    private function sourceMode(CarbootEvent $event): string
    {
        if (! Schema::hasColumn('carboot_events', 'analytics_source_mode')) {
            return EventAnalyticsDataSourceService::MODE_SYSTEM_ONLY;
        }

        $mode = (string) ($event->analytics_source_mode ?: EventAnalyticsDataSourceService::MODE_SYSTEM_ONLY);

        return in_array($mode, EventAnalyticsDataSourceService::MODES, true)
            ? $mode
            : EventAnalyticsDataSourceService::MODE_SYSTEM_ONLY;
    }

    private function undoAvailable(CarbootEvent $event): bool
    {
        // Soft undo lifecycle is deprecated; permanent delete + re-upload is the recovery path.
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeOperationalSnapshot(CarbootEvent $event): array
    {
        try {
            $snapshot = $this->aggregator->build($event);
            $sections = $snapshot['sections'] ?? [];

            if (isset($sections['feedback']) && is_array($sections['feedback'])) {
                $sections['feedback'] = $this->attachLiveFeedbackComments(
                    $event->id,
                    $sections['feedback'],
                );
            }

            return [
                'available' => true,
                'status' => 'ready',
                'included_in_analytics' => true,
                'sections' => $sections,
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
     * Live anonymized comments for Analytics Hub only (never frozen into reports).
     *
     * @param  array<string, mixed>  $feedback
     * @return array<string, mixed>
     */
    private function attachLiveFeedbackComments(int $eventId, array $feedback): array
    {
        if (! Schema::hasTable('feedbacks') || ! Schema::hasColumn('feedbacks', 'carboot_event_id')) {
            return $feedback;
        }

        $rows = \Illuminate\Support\Facades\DB::table('feedbacks')
            ->where('carboot_event_id', $eventId)
            ->where(function ($query) {
                $query->whereNull('is_hidden')->orWhere('is_hidden', false);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get([
                'participation_type',
                'rating',
                'comments',
                'community_backgrounds',
                'created_at',
                'updated_at',
            ]);

        $mapRow = function ($row, string $authorLabel) {
            return [
                'author_label' => $authorLabel,
                'rating' => (int) $row->rating,
                'participation_type' => $row->participation_type,
                'submitted_at' => $row->updated_at ?: $row->created_at,
                'comments' => is_string($row->comments) ? $row->comments : '',
            ];
        };

        $feedback['anonymous_vendor_comments'] = $rows
            ->where('participation_type', 'vendor')
            ->take(50)
            ->map(fn ($row) => $mapRow($row, 'Vendor respondent'))
            ->values()
            ->all();

        $feedback['anonymous_non_vendor_comments'] = $rows
            ->where('participation_type', '!=', 'vendor')
            ->take(50)
            ->map(fn ($row) => $mapRow($row, 'Community respondent'))
            ->values()
            ->all();

        return $feedback;
    }

    /**
     * @return array<string, mixed>
     */
    private function surveyBundle(CarbootEvent $event, bool $recompute = false, ?string $mode = null): array
    {
        $mode = SurveyResponse::normalizeAnalyticsMode($mode ?? $this->sourceMode($event));

        if (! Schema::hasTable('survey_responses') || ! Schema::hasTable('raw_survey_uploads')) {
            return [
                'status' => 'unavailable',
                'degraded' => true,
                'available' => false,
                'message' => __('api.survey_storage_tables_are_not_migrated_yet'),
                'respondent_count' => 0,
                'sections' => [],
                'unavailable_metrics' => ['vendor_survey'],
            ];
        }

        $latestBatch = $this->activeImportBatch($event);

        $responseCount = SurveyResponse::query()
            ->forAnalytics($event->id, $mode)
            ->count();

        if ($responseCount === 0) {
            return [
                'status' => 'missing_source',
                'state' => 'missing_source',
                'degraded' => false,
                'available' => false,
                'message' => __('api.no_survey_responses_are_available_for_the_selected_071cac1b'),
                'respondent_count' => 0,
                'import_batch_id' => $mode === EventAnalyticsDataSourceService::MODE_SYSTEM_ONLY
                    ? null
                    : $latestBatch?->id,
                'analytics_source_mode' => $mode,
                'sections' => [],
                'unavailable_metrics' => ['vendor_survey'],
            ];
        }

        $fingerprint = $this->surveyFingerprint($event, $latestBatch, $mode);
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
                'analytics_source_mode' => $mode,
            ]);
        }

        try {
            $records = SurveyResponse::query()
                ->forAnalytics($event->id, $mode)
                ->get()
                ->map(fn (SurveyResponse $row) => $row->only([
                    'respondent_id',
                    'source_row_number',
                    'product_categories',
                    'product_categories_other_text',
                    'item_conditions',
                    'has_difficulty',
                    'difficulty_details',
                    'event_info_sources',
                    'event_info_sources_other_text',
                    'items_sold_band',
                    'gross_sales_band',
                    'unsold_item_actions',
                    'sales_purpose',
                    'experience_rating',
                    'improvement_areas',
                    'improvement_areas_other_text',
                    'comments_and_suggestions',
                    'supporting_activity_attracted_visitors',
                    'supporting_activity_impacts',
                    'supporting_activity_impacts_other_text',
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
                'analytics_source_mode' => $mode,
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
                'analytics_source_mode' => $mode,
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
        // Survey KPIs always evaluate for the selected mode (including system_only).
        $includeSurvey = true;

        return [
            [
                'key' => 'approved_vendors',
                'label' => 'Approved bookings',
                'value' => $includeSystem ? ($pipeline['approved_count'] ?? null) : null,
                'source' => 'operational',
                'state' => ! $includeSystem
                    ? 'excluded'
                    : (($operational['available'] ?? false)
                        ? (((int) ($pipeline['approved_count'] ?? 0) === 0) ? 'zero' : 'available')
                        : 'unavailable'),
                'display' => ! $includeSystem
                    ? 'Bookings excluded by source mode'
                    : (! ($operational['available'] ?? false)
                        ? 'Booking data unavailable'
                        : (string) ($pipeline['approved_count'] ?? 0)),
            ],
            [
                'key' => 'collected_fees',
                'label' => 'Collected platform fees (RM)',
                'value' => $includeSystem ? ($payments['collected'] ?? null) : null,
                'source' => 'operational',
                'note' => 'Platform fees, not vendor gross sales',
                'state' => ! $includeSystem
                    ? 'excluded'
                    : (($operational['available'] ?? false) ? 'available' : 'unavailable'),
                'display' => ! $includeSystem
                    ? 'Payments excluded by source mode'
                    : (! ($operational['available'] ?? false)
                        ? 'Payment data unavailable'
                        : (string) ($payments['collected'] ?? 0)),
            ],
            [
                'key' => 'survey_respondents',
                'label' => 'Survey respondents',
                'value' => $includeSurvey ? ($survey['respondent_count'] ?? 0) : null,
                'source' => 'survey',
                'state' => ! $includeSurvey
                    ? 'excluded'
                    : match ($survey['status'] ?? '') {
                        'ready' => ((int) ($survey['respondent_count'] ?? 0) === 0) ? 'zero' : 'available',
                        'missing_source', 'empty' => 'missing_source',
                        'excluded' => 'excluded',
                        'degraded' => 'degraded',
                        default => 'unavailable',
                    },
                'display' => ! $includeSurvey
                    ? 'Survey excluded by source mode'
                    : match ($survey['status'] ?? '') {
                        'ready' => (string) ($survey['respondent_count'] ?? 0),
                        'missing_source', 'empty' => 'No survey responses for selected mode',
                        'degraded' => 'Survey analytics unavailable',
                        default => 'Survey data unavailable',
                    },
            ],
            [
                'key' => 'survey_status',
                'label' => 'Survey analytics status',
                'value' => $includeSurvey ? ($survey['status'] ?? 'unavailable') : 'excluded',
                'source' => 'survey',
                'state' => ! $includeSurvey ? 'excluded' : ($survey['status'] ?? 'unavailable'),
            ],
        ];
    }

    private function surveyFingerprint(CarbootEvent $event, ?RawSurveyUpload $batch, ?string $mode = null): string
    {
        $mode = SurveyResponse::normalizeAnalyticsMode($mode ?? $this->sourceMode($event));
        $count = SurveyResponse::query()->forAnalytics($event->id, $mode)->count();
        $maxId = SurveyResponse::query()->forAnalytics($event->id, $mode)->max('id') ?? 0;

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
        $feedbackSection = $operational['sections']['feedback'] ?? [];
        $feedbackCount = (int) ($feedbackSection['response_count'] ?? 0);
        $feedbackUpdated = null;

        $sources[] = [
            'type' => 'system_database',
            'label' => 'System Booking Data',
            'updated_at' => now()->toIso8601String(),
            'record_count' => (int) ($operational['sections']['booking_pipeline']['total_bookings'] ?? 0),
            'sources' => ['bookings', 'invoices', 'event_sites', 'item_reservations'],
            'status' => ($operational['available'] ?? false)
                ? (((int) ($operational['sections']['booking_pipeline']['total_bookings'] ?? 0) > 0)
                    ? 'connected_with_records'
                    : 'connected_no_records')
                : 'not_connected',
            'availability_label' => ($operational['available'] ?? false)
                ? (((int) ($operational['sections']['booking_pipeline']['total_bookings'] ?? 0) > 0)
                    ? 'Connected with records'
                    : 'Connected but no records')
                : 'Not connected',
            'included_in_analytics' => $includeSystem && ($operational['available'] ?? false),
            'inclusion_label' => $includeSystem ? 'Included in analytics' : 'Excluded from analytics',
        ];

        $sources[] = [
            'type' => 'in_app_feedback',
            'label' => 'In-app Feedback',
            'updated_at' => $feedbackUpdated,
            'record_count' => $feedbackCount,
            'status' => Schema::hasTable('feedbacks') && Schema::hasColumn('feedbacks', 'carboot_event_id')
                ? ($feedbackCount > 0 ? 'connected_with_records' : 'connected_no_records')
                : 'not_applicable',
            'availability_label' => Schema::hasTable('feedbacks') && Schema::hasColumn('feedbacks', 'carboot_event_id')
                ? ($feedbackCount > 0 ? 'Connected with records' : 'Connected but no records')
                : 'Not applicable',
            'included_in_analytics' => $includeSystem,
            'inclusion_label' => $includeSystem ? 'Included in analytics' : 'Excluded from analytics',
        ];

        $batch = $this->activeImportBatch($event);
        if ($batch) {
            $csvIncluded = $includeSurvey && ($survey['status'] ?? null) === 'ready';
            $sources[] = [
                'type' => 'csv_import',
                'label' => 'Imported Survey CSV',
                'batch_id' => $batch->id,
                'original_filename' => $includeSurvey ? $batch->original_filename : null,
                'schema_name' => $batch->schema_name,
                'schema_version' => $batch->schema_version,
                'imported_at' => optional($batch->processing_finished_at ?? $batch->created_at)?->toIso8601String(),
                'updated_at' => optional($batch->processing_finished_at ?? $batch->created_at)?->toIso8601String(),
                'record_count' => $includeSurvey
                    ? (int) ($survey['respondent_count'] ?? $batch->valid_row_count ?? 0)
                    : 0,
                'respondent_count' => $includeSurvey
                    ? (int) ($survey['respondent_count'] ?? $batch->valid_row_count ?? 0)
                    : 0,
                'status' => $csvIncluded ? 'connected_with_records' : 'connected_no_records',
                'availability_label' => $csvIncluded ? 'Connected with records' : 'Connected but no records',
                'included_in_analytics' => $csvIncluded,
                'inclusion_label' => $includeSurvey ? 'Included in analytics' : 'Excluded from analytics',
            ];
        } else {
            $sources[] = [
                'type' => 'csv_import',
                'label' => 'Imported Survey CSV',
                'record_count' => 0,
                'status' => 'connected_no_records',
                'availability_label' => 'Connected but no records',
                'included_in_analytics' => false,
                'inclusion_label' => $includeSurvey ? 'No CSV connected' : 'Excluded from analytics',
            ];
        }

        // Hide CSV card entirely in system_only mode.
        if ($mode === EventAnalyticsDataSourceService::MODE_SYSTEM_ONLY) {
            $sources = array_values(array_filter(
                $sources,
                fn (array $s) => $s['type'] !== 'csv_import'
            ));
        }

        if ($mode === EventAnalyticsDataSourceService::MODE_CSV_ONLY) {
            foreach ($sources as &$source) {
                if ($source['type'] === 'system_database' || $source['type'] === 'in_app_feedback') {
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
