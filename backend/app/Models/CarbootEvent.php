<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarbootEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'starts_at',
        'ends_at',
        'status',
        'description',
        'max_slots',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_slots' => 'integer',
    ];
}
