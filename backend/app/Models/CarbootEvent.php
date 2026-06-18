<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

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
        'image_path',
    ];

    protected $appends = [
        'poster_url',
        'image_url',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_slots' => 'integer',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(EventImage::class, 'event_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(EventImage::class, 'event_id')
            ->where('is_primary', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function galleryImagesForApi(): array
    {
        $this->loadMissing('images');

        if ($this->images->isNotEmpty()) {
            return $this->images
                ->map(fn (EventImage $image) => $image->toApiArray())
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

    public function normalizedImagePath(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        $path = str_replace('\\', '/', trim($this->image_path));
        $path = preg_replace('#^public/#', '', $path);

        return ltrim($path, '/') ?: null;
    }

    public function getPosterUrlAttribute(): ?string
    {
        $path = $this->primaryImagePath();

        return $path ? asset('storage/' . $path) : null;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->poster_url;
    }

    public function registeredUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user')
            ->withPivot('registered_at')
            ->withTimestamps();
    }

    public function syncCapacityStatus(): void
    {
        if ($this->max_slots === null) {
            return;
        }

        $count = $this->registeredUsers()->count();

        if ($count >= $this->max_slots) {
            $this->updateQuietly(['status' => 'Closed']);

            return;
        }

        $threshold = (int) ceil($this->max_slots * 0.8);
        if ($count >= $threshold) {
            $this->updateQuietly(['status' => 'Almost Full']);

            return;
        }

        if ($this->status !== 'Available') {
            $this->updateQuietly(['status' => 'Available']);
        }
    }

    protected static function booted(): void
    {
        static::deleting(function (self $event) {
            $event->loadMissing('images');

            foreach ($event->images as $image) {
                $image->delete();
            }

            if ($event->image_path) {
                Storage::disk('public')->delete($event->image_path);
            }
        });
    }
}
