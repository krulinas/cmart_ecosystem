<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCategoryOverride extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'booking_id',
        'booking_category_id_snapshot',
        'booking_category_label_snapshot',
        'assigned_category_id_snapshot',
        'assigned_category_label_snapshot',
        'assigned_row_ids_snapshot',
        'assigned_row_labels_snapshot',
        'assigned_site_ids_snapshot',
        'assigned_site_labels_snapshot',
        'reason',
        'applied_by_user_id',
        'applied_at',
        'status',
        'active_lock',
        'revoked_by_user_id',
        'revoked_at',
        'revocation_reason',
    ];

    protected $casts = [
        'booking_category_id_snapshot' => 'integer',
        'assigned_category_id_snapshot' => 'integer',
        'assigned_row_ids_snapshot' => 'array',
        'assigned_row_labels_snapshot' => 'array',
        'assigned_site_ids_snapshot' => 'array',
        'assigned_site_labels_snapshot' => 'array',
        'applied_at' => 'datetime',
        'revoked_at' => 'datetime',
        'active_lock' => 'integer',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)->where('active_lock', 1);
    }
}
