<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 3.5 — append-only Organizer layout mutation audit row.
 */
class EventLayoutAuditLog extends Model
{
    public const ACTION_ROW_CREATED = 'layout_row_created';
    public const ACTION_ROW_UPDATED = 'layout_row_updated';
    public const ACTION_ROW_REORDERED = 'layout_row_reordered';
    public const ACTION_ROW_ARCHIVED = 'layout_row_archived';
    public const ACTION_ROW_UNARCHIVED = 'layout_row_unarchived';
    public const ACTION_ROW_DELETED = 'layout_row_deleted';
    public const ACTION_SITE_CREATED = 'event_site_created';
    public const ACTION_SITES_GENERATED = 'event_sites_generated';
    public const ACTION_SITE_UPDATED = 'event_site_updated';
    public const ACTION_SITE_REORDERED = 'event_site_reordered';
    public const ACTION_SITE_DELETED = 'event_site_deleted';

    protected $fillable = [
        'carboot_event_id',
        'actor_user_id',
        'action',
        'event_layout_row_id',
        'event_site_id',
        'before_snapshot',
        'after_snapshot',
        'metadata',
        'reason',
    ];

    protected $casts = [
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
        'metadata' => 'array',
    ];

    public function carbootEvent(): BelongsTo
    {
        return $this->belongsTo(CarbootEvent::class, 'carboot_event_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
