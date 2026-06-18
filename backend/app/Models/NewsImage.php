<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class NewsImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'news_id',
        'image_path',
        'sort_order',
        'is_primary',
    ];

    protected $appends = [
        'image_url',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function newsPost(): BelongsTo
    {
        return $this->belongsTo(NewsPost::class, 'news_id');
    }

    public function normalizedImagePath(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        $path = str_replace('\\', '/', trim($this->image_path));
        $path = preg_replace('#^public/#', '', $path);

        return ltrim($path, '/') ?: null;
    }

    public function getImageUrlAttribute(): ?string
    {
        $path = $this->normalizedImagePath();

        return $path ? asset('storage/' . $path) : null;
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'image_path' => $this->normalizedImagePath(),
            'image_url' => $this->image_url,
            'sort_order' => $this->sort_order,
            'is_primary' => (bool) $this->is_primary,
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $image) {
            if ($image->image_path) {
                Storage::disk('public')->delete($image->image_path);
            }
        });
    }
}
