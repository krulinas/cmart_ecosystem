<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneratedReport extends Model
{
    protected $fillable = [
        'carboot_event_id',
        'report_request_id',
        'report_type',
        'version',
        'status',
        'snapshot',
        'organizer_observations',
        'organizer_recommendations',
        'prepared_by',
        'published_by',
        'published_at',
        'revision_reason',
        'supersedes_report_id',
        'event_title_snapshot',
        'event_starts_at_snapshot',
        'event_ends_at_snapshot',
    ];

    protected $casts = [
        'version' => 'integer',
        'snapshot' => 'array',
        'published_at' => 'datetime',
        'event_starts_at_snapshot' => 'datetime',
        'event_ends_at_snapshot' => 'datetime',
    ];

    public function carbootEvent(): BelongsTo
    {
        return $this->belongsTo(CarbootEvent::class, 'carboot_event_id');
    }

    public function reportRequest(): BelongsTo
    {
        return $this->belongsTo(ReportRequest::class);
    }

    public function preparedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function publishedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function supersedesReport(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_report_id');
    }

    public function supersededByReports(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_report_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ReportWorkflowAudit::class);
    }
}
