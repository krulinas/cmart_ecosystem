<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class NewsPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'excerpt',
        'body',
        'category',
        'image_url',
        'image_path',
        'published_at',
        'is_published',
        'author_id',
    ];

    protected $appends = [
        'banner_url',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(NewsImage::class, 'news_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(NewsImage::class, 'news_id')
            ->where('is_primary', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function galleryImagesForApi(): array
    {
        $this->loadMissing('images');

        if ($this->images->isNotEmpty()) {
            return $this->images
                ->map(fn (NewsImage $image) => $image->toApiArray())
                ->values()
                ->all();
        }

        $legacyPath = $this->normalizedImagePath();
        if ($legacyPath) {
            return [[
                'id' => null,
                'image_path' => $legacyPath,
                'image_url' => asset('storage/' . $legacyPath),
                'sort_order' => 0,
                'is_primary' => true,
            ]];
        }

        if ($this->image_url) {
            return [[
                'id' => null,
                'image_path' => null,
                'image_url' => $this->image_url,
                'sort_order' => 0,
                'is_primary' => true,
            ]];
        }

        return [];
    }

    public function primaryImagePath(): ?string
    {
        $this->loadMissing('images');

        $primary = $this->images->firstWhere('is_primary', true)
            ?? $this->images->sortBy('sort_order')->first();

        if ($primary) {
            return $primary->normalizedImagePath();
        }

        return $this->normalizedImagePath();
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

    public function getBannerUrlAttribute(): ?string
    {
        $images = $this->galleryImagesForApi();
        if ($images !== []) {
            $primary = collect($images)->firstWhere('is_primary', true) ?? $images[0];

            return $primary['image_url'] ?? null;
        }

        return null;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $post) {
            $post->loadMissing('images');

            foreach ($post->images as $image) {
                $image->delete();
            }

            if ($post->image_path) {
                Storage::disk('public')->delete($post->image_path);
            }
        });
    }
}
