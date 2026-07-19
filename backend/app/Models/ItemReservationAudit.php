<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ItemReservationAudit extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_CREATED = 'reservation_created';

    public const ACTION_CANCELLED = 'reservation_cancelled';

    public const ACTION_CHARGE_CONFIRMATION_RECORDED = 'charge_confirmation_recorded';

    public const ACTION_CHARGE_WAIVED = 'charge_waived';

    public const ACTION_CONFIRMED = 'reservation_confirmed';

    public const ACTION_EXPIRED = 'reservation_expired';

    public const ACTION_COMPLETED = 'reservation_completed';

    protected $fillable = [
        'item_reservation_id',
        'actor_user_id',
        'action',
        'from_reservation_status',
        'to_reservation_status',
        'from_charge_status',
        'to_charge_status',
        'note',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function itemReservation(): BelongsTo
    {
        return $this->belongsTo(ItemReservation::class);
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Item reservation audits are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Item reservation audits are append-only.');
        });
    }
}
