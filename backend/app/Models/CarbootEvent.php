<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_slots' => 'integer',
    ];

    public function getPosterUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    /**
     * Community users who registered for this event (event_user pivot).
     */
    public function registeredUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user')
            ->withPivot('registered_at')
            ->withTimestamps();
    }

    /**
     * Recompute the public status column from current registration count vs max_slots.
     *
     * Called inside the registration transaction after a new user is attached.
     */
    public function syncCapacityStatus(): void
    {
        if ($this->max_slots === null) {
            return;
        }

        $count = $this->registeredUsers()->count();

        if ($count >= $this->max_slots) {
            // updateQuietly() skips the observer — capacity changes are not staff cancellations.
            $this->updateQuietly(['status' => 'Closed']);

            return;
        }

        // "Almost Full" when at least 80% of slots are taken.
        $threshold = (int) ceil($this->max_slots * 0.8);
        if ($count >= $threshold) {
            $this->updateQuietly(['status' => 'Almost Full']);

            return;
        }

        if ($this->status !== 'Available') {
            $this->updateQuietly(['status' => 'Available']);
        }
    }
}
