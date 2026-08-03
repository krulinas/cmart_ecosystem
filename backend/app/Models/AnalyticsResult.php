<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsResult extends Model
{
    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_STALE = 'stale';

    protected $fillable = [
        'carboot_event_id',
        'metric_key',
        'calculation_version',
        'payload',
        'source_fingerprint',
        'import_batch_id',
        'status',
        'computed_at',
        'failure_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'computed_at' => 'datetime',
    ];

    public function carbootEvent(): BelongsTo
    {
        return $this->belongsTo(CarbootEvent::class, 'carboot_event_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(RawSurveyUpload::class, 'import_batch_id');
    }
}
