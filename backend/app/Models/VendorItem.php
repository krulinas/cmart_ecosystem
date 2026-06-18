<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class VendorItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'category',
        'condition',
        'pricing_type',
        'price',
        'description',
        'image_path',
        'status',
    ];

    protected $appends = [
        'image_url',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ReuseItemImage::class, 'vendor_item_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ReuseItemImage::class, 'vendor_item_id')
            ->where('is_primary', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function galleryImagesForApi(): array
    {
        $this->loadMissing('images');

        if ($this->images->isNotEmpty()) {
            return $this->images
                ->map(fn (ReuseItemImage $image) => $image->toApiArray())
                ->values()
                ->all();
        }

        $legacyPath = $this->normalizedImagePath();
        if (!$legacyPath) {
            return [];
        }

        return [[
            'id' => null,
            'image_path' => $legacyPath,
            'image_url' => asset('storage/' . $legacyPath),
            'sort_order' => 0,
            'is_primary' => true,
        ]];
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

    public function getImageUrlAttribute(): ?string
    {
        $path = $this->primaryImagePath();

        if (!$path) {
            return null;
        }

        return asset('storage/' . $path);
    }

    /**
     * Relative public-disk path, e.g. reuse-items/example.jpg
     */
    public function normalizedImagePath(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        $path = str_replace('\\', '/', trim($this->image_path));
        $path = preg_replace('#^public/#', '', $path);

        return ltrim($path, '/') ?: null;
    }

    protected static function booted(): void
    {
        static::deleting(function (self $item) {
            $item->loadMissing('images');

            foreach ($item->images as $image) {
                $image->delete();
            }

            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
        });
    }
}
