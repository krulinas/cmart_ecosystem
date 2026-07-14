<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Space extends Model
{
    use HasFactory;

    protected $fillable = ['space_size', 'price', 'status'];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function eventSites()
    {
        return $this->hasMany(EventSite::class);
    }
}