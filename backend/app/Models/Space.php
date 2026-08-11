<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Internal physical-site catalogue row for Carboot layout FKs.
 * Does not determine vendor booking price — use CarbootEvent::$site_price.
 */
class Space extends Model
{
    use HasFactory;

    public const PHYSICAL_PARKING_SITE = 'Physical Parking Site';

    protected $fillable = ['space_size', 'status', 'price'];

    /**
     * Single default physical parking site used for all Carboot layout generation.
     */
    public static function defaultPhysical(): self
    {
        return static::query()->firstOrCreate(
            ['space_size' => self::PHYSICAL_PARKING_SITE],
            [
                'status' => 'Available',
                // Deprecated residue column — never used for booking totals.
                'price' => 0,
            ],
        );
    }

    public static function resolveId(?int $spaceId = null): int
    {
        if ($spaceId) {
            $existing = static::query()->find($spaceId);
            if ($existing) {
                return (int) $existing->id;
            }
        }

        return (int) static::defaultPhysical()->id;
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function eventSites()
    {
        return $this->hasMany(EventSite::class);
    }
}
