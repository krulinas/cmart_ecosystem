<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBookingPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'product_category',
        'specific_products',
        'tapak_count',
        'remember_enabled',
        'last_used_at',
    ];

    protected $casts = [
        'tapak_count' => 'integer',
        'remember_enabled' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
