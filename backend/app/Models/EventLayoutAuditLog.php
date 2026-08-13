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

    public const ACTION_CANONICAL_SITE_RESTORED = 'event_site_canonical_restored';

    public const ACTION_LAYOUT_PUBLISHED = 'public_layout_published';

    public const ACTION_LAYOUT_UNPUBLISHED = 'public_layout_unpublished';

    public const ACTION_STANDARD_TEMPLATE_GENERATED = 'standard_parking_layout_generated';

    public const ACTION_OPEN_SITES_SET = 'layout_open_sites_set';

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
