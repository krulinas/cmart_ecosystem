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
        'participation_type',
        'community_backgrounds',
        'comments',
        'rating',
        'service_rating',
        'value_rating',
        'media_path',
        'helpful_count',
        'is_hidden',
        'reviewed_at',
        'reviewed_by',
        'official_reply_text',
        'official_reply_status',
        'official_reply_by',
        'official_reply_published_at',
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
        'helpful_count' => 'integer',
        'service_rating' => 'integer',
        'value_rating' => 'integer',
        'rating' => 'integer',
        'community_backgrounds' => 'array',
        'reviewed_at' => 'datetime',
        'official_reply_published_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedByUser()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function officialReplyByUser()
    {
        return $this->belongsTo(User::class, 'official_reply_by');
    }
}
