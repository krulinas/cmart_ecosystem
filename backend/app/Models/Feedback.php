<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedbacks';

    protected $fillable = [
        'user_id',
        'reviewer_role',
        'comments',
        'rating',
        'service_rating',
        'value_rating',
        'media_path',
        'helpful_count',
        'is_hidden',
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
        'helpful_count' => 'integer',
        'service_rating' => 'integer',
        'value_rating' => 'integer',
        'rating' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
