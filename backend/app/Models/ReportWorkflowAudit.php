<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit row for report request / generated report workflow actions.
 */
class ReportWorkflowAudit extends Model
{
    public const ACTION_REQUEST_CREATED = 'report_request_created';
    public const ACTION_REQUEST_ACKNOWLEDGED = 'report_request_acknowledged';
    public const ACTION_REQUEST_PREPARATION_STARTED = 'report_request_preparation_started';
    public const ACTION_REQUEST_DECLINED = 'report_request_declined';
    public const ACTION_REQUEST_CANCELLED = 'report_request_cancelled';
    public const ACTION_REQUEST_FULFILLED = 'report_request_fulfilled';
    public const ACTION_DRAFT_GENERATED = 'generated_report_draft_generated';
    public const ACTION_DRAFT_REGENERATED = 'generated_report_draft_regenerated';
    public const ACTION_DRAFT_NARRATIVES_UPDATED = 'generated_report_narratives_updated';
    public const ACTION_DRAFT_DESTROYED = 'generated_report_draft_destroyed';
    public const ACTION_REVISION_CREATED = 'generated_report_revision_created';
    public const ACTION_PUBLISHED = 'generated_report_published';
    public const ACTION_VIEWED = 'generated_report_viewed';
    public const ACTION_DOWNLOADED = 'generated_report_downloaded';
    public const ACTION_EXTERNAL_ALERT_SIMULATED = 'external_alert_simulated';
    public const ACTION_EXTERNAL_ALERT_SKIPPED = 'external_alert_skipped';
    public const ACTION_ORGANIZERS_NOTIFIED = 'organizers_notified_in_app';
    public const ACTION_CMART_NOTIFIED = 'cmart_notified_in_app';

    protected $fillable = [
        'action',
        'actor_user_id',
        'report_request_id',
        'generated_report_id',
        'carboot_event_id',
        'metadata',
        'ip_address',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function reportRequest(): BelongsTo
    {
        return $this->belongsTo(ReportRequest::class);
    }

    public function generatedReport(): BelongsTo
    {
        return $this->belongsTo(GeneratedReport::class);
    }

    public function carbootEvent(): BelongsTo
    {
        return $this->belongsTo(CarbootEvent::class, 'carboot_event_id');
    }
}
