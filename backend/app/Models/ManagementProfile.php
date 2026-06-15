<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'staff_code',
        'tier',
        'position_title',
        'department',
        'branch_name',
        'is_active',
    ];

    protected $casts = [
        'tier' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
