<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'space_id',
        'booking_date',
        'product_category',
        'product_details',
        'approval_status',
        'revision_comment',
        'whatsapp_link',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function space() {
        return $this->belongsTo(Space::class);
    }

    public function invoice() {
        return $this->hasOne(Invoice::class);
    }
}