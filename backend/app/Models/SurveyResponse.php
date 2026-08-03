<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SurveyResponse extends Model
{
    public const SOURCE_CSV_IMPORT = 'csv_import';

    public const SOURCE_SYSTEM_SUBMISSION = 'system_submission';

    protected $fillable = [
        'carboot_event_id',
        'import_batch_id',
        'submission_source',
        'vendor_user_id',
        'active_system_key',
        'respondent_id',
        'source_row_number',
        'schema_name',
        'schema_version',
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
        'import_auto_review_flags',
        'import_review_notes',
        'validation_status',
        'is_active',
    ];

    protected $casts = [
        'source_row_number' => 'integer',
        'product_categories' => 'array',
        'item_conditions' => 'array',
        'has_difficulty' => 'boolean',
        'event_info_sources' => 'array',
        'unsold_item_actions' => 'array',
        'improvement_areas' => 'array',
        'supporting_activity_impacts' => 'array',
        'is_active' => 'boolean',
    ];

    public function carbootEvent(): BelongsTo
    {
        return $this->belongsTo(CarbootEvent::class, 'carboot_event_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(RawSurveyUpload::class, 'import_batch_id');
    }

    public function vendorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    /**
     * Active analytics set: valid survey rows only (never bookings/invoices/sites).
     * Counts only the single active CSV batch (+ optional system submissions).
     */
    public function scopeForAnalytics(Builder $query, int $eventId): Builder
    {
        $query->where('carboot_event_id', $eventId)
            ->where('validation_status', 'valid');

        if (Schema::hasColumn('survey_responses', 'is_active')) {
            $query->where('is_active', true);
        }

        if (! Schema::hasTable('raw_survey_uploads')) {
            return $query;
        }

        $activeBatchId = self::resolveActiveCsvBatchId($eventId);
        $hasSource = Schema::hasColumn('survey_responses', 'submission_source');

        $query->where(function (Builder $inner) use ($activeBatchId, $hasSource) {
            if ($hasSource) {
                $inner->where('submission_source', self::SOURCE_SYSTEM_SUBMISSION);
            }

            if ($activeBatchId !== null) {
                $method = $hasSource ? 'orWhere' : 'where';
                $inner->{$method}(function (Builder $csv) use ($activeBatchId, $hasSource) {
                    $csv->where('import_batch_id', $activeBatchId);
                    if ($hasSource) {
                        $csv->where(function (Builder $src) {
                            $src->where('submission_source', self::SOURCE_CSV_IMPORT)
                                ->orWhereNull('submission_source');
                        });
                    }

                    // Prefer system submission when the same vendor_user_id already submitted in-app.
                    if ($hasSource && Schema::hasColumn('survey_responses', 'vendor_user_id')) {
                        $csv->where(function (Builder $unlinked) {
                            $unlinked->whereNull('vendor_user_id')
                                ->orWhereNotExists(function ($sub) {
                                    $sub->select(DB::raw(1))
                                        ->from('survey_responses as sys')
                                        ->whereColumn('sys.carboot_event_id', 'survey_responses.carboot_event_id')
                                        ->whereColumn('sys.vendor_user_id', 'survey_responses.vendor_user_id')
                                        ->where('sys.submission_source', self::SOURCE_SYSTEM_SUBMISSION)
                                        ->where('sys.validation_status', 'valid');
                                    if (Schema::hasColumn('survey_responses', 'is_active')) {
                                        $sub->where('sys.is_active', true);
                                    }
                                });
                        });
                    }
                });
            } elseif (! $hasSource) {
                $inner->whereRaw('0 = 1');
            }
        });

        return $query;
    }

    public static function resolveActiveCsvBatchId(int $eventId): ?int
    {
        if (! Schema::hasTable('raw_survey_uploads')) {
            return null;
        }

        $query = RawSurveyUpload::query()
            ->where('carboot_event_id', $eventId)
            ->whereIn('status', [
                RawSurveyUpload::STATUS_COMPLETED,
                RawSurveyUpload::STATUS_COMPLETED_WITH_ERRORS,
            ]);

        if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
            $query->where('is_active', true);
        }

        // Earliest id wins when multiple rows somehow qualify (also pre-provenance fallback).
        $id = $query->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }
}
