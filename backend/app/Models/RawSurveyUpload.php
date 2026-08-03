<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawSurveyUpload extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_EXCLUDED = 'excluded';

    public const STATUS_ARCHIVED = 'archived';

    public const SOURCE_CSV_IMPORT = 'csv_import';

    protected $fillable = [
        'carboot_event_id',
        'uploaded_by',
        'schema_name',
        'schema_version',
        'original_filename',
        'storage_disk',
        'storage_path',
        'mime_type',
        'file_size',
        'sha256',
        'active_dedup_key',
        'status',
        'is_active',
        'submission_source',
        'duplicate_of_id',
        'superseded_at',
        'superseded_by_id',
        'excluded_at',
        'archived_at',
        'restored_from_status',
        'total_row_count',
        'valid_row_count',
        'invalid_row_count',
        'validation_summary',
        'failure_message',
        'processing_started_at',
        'processing_finished_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_active' => 'boolean',
        'total_row_count' => 'integer',
        'valid_row_count' => 'integer',
        'invalid_row_count' => 'integer',
        'validation_summary' => 'array',
        'processing_started_at' => 'datetime',
        'processing_finished_at' => 'datetime',
        'superseded_at' => 'datetime',
        'excluded_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function humanStatusLabel(): string
    {
        if ($this->is_active && in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_COMPLETED_WITH_ERRORS,
        ], true)) {
            return 'Active';
        }

        return match ($this->status) {
            self::STATUS_EXCLUDED => 'Excluded',
            self::STATUS_DUPLICATE => 'Duplicate',
            self::STATUS_SUPERSEDED => 'Superseded',
            self::STATUS_ARCHIVED => 'Archived',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_COMPLETED, self::STATUS_COMPLETED_WITH_ERRORS => 'Excluded',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public static function makeActiveDedupKey(string $schemaName, string $schemaVersion, string $sha256): string
    {
        return $schemaName.'|'.$schemaVersion.'|'.$sha256;
    }

    public function carbootEvent(): BelongsTo
    {
        return $this->belongsTo(CarbootEvent::class, 'carboot_event_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function surveyResponses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class, 'import_batch_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function shortenedChecksum(): ?string
    {
        if (! $this->sha256) {
            return null;
        }

        return substr($this->sha256, 0, 12);
    }
}
