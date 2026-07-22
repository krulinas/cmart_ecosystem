<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'role',
        /**
         * Vendor approval state for community users only.
         * TODO: Move to vendor_business_profiles or a dedicated vendor status field.
         */
        'vendor_status',
        'default_site_price',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'default_site_price' => 'decimal:2',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function feedbacks()
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Carboot events this user has registered for (event_user pivot).
     */
    public function registeredEvents()
    {
        return $this->belongsToMany(CarbootEvent::class, 'event_user')
            ->withPivot('registered_at')
            ->withTimestamps();
    }

    public function businessProfile()
    {
        return $this->hasOne(VendorBusinessProfile::class);
    }

    public function managementProfile()
    {
        return $this->hasOne(ManagementProfile::class);
    }

    public function vendorItems()
    {
        return $this->hasMany(VendorItem::class);
    }

    public function itemReservations()
    {
        return $this->hasMany(ItemReservation::class, 'reserving_user_id');
    }

    public function ownedItemReservations()
    {
        return $this->hasMany(ItemReservation::class, 'vendor_user_id');
    }

    public function bookingPreference()
    {
        return $this->hasOne(UserBookingPreference::class);
    }

    public function releasedBookingDayAllocations()
    {
        return $this->hasMany(BookingDayAllocation::class, 'released_by');
    }
}
