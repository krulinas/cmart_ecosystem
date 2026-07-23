<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportRequest extends Model
{
    protected $fillable = [
        'carboot_event_id',
        'requested_by',
        'report_type',
        'message',
        'preferred_due_date',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'declined_by',
        'declined_at',
        'decline_reason',
        'cancelled_by',
        'cancelled_at',
        'response_message',
        'fulfilled_at',
    ];

    protected $casts = [
        'preferred_due_date' => 'date',
        'acknowledged_at' => 'datetime',
        'declined_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    public function carbootEvent(): BelongsTo
    {
        return $this->belongsTo(CarbootEvent::class, 'carboot_event_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function declinedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declined_by');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ReportWorkflowAudit::class);
    }
}
