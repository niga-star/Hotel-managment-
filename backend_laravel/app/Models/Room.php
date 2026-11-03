<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'rooms';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'room_number',
        'category_id',
        'title',
        'status',
        'housekeeping_status',
        'floor',
        'capacity',
        'price_etb',
        'price_usd',
        'is_active',
        'description',
    ];

    protected $casts = [
        'floor' => 'integer',
        'capacity' => 'integer',
        'price_etb' => 'float',
        'price_usd' => 'float',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(RoomCategory::class, 'category_id', 'id');
    }

    public function images()
    {
        return $this->hasMany(RoomImage::class, 'room_id', 'id')->orderBy('display_order');
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class, 'room_amenities', 'room_id', 'amenity_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'room_id', 'id');
    }

    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class, 'room_id', 'id');
    }

    /**
     * Compute dynamic operational status based on live reservations, occupancy,
     * housekeeping turnover, and maintenance information.
     */
    public function getDynamicStatusAttribute(): string
    {
        // 1. Maintenance takes absolute precedence
        if ($this->status === 'maintenance' || $this->housekeeping_status === 'maintenance') {
            return 'maintenance';
        }

        $today = now()->toDateString();

        // 2. Active checked-in guest occupying the room
        $hasActiveCheckedIn = $this->bookings()
            ->where('booking_status', 'checked_in')
            ->where('check_in_date', '<=', $today)
            ->where('check_out_date', '>=', $today)
            ->exists() || Booking::whereHas('bookingRooms', function ($br) {
                $br->where('room_id', $this->id);
            })
            ->where('booking_status', 'checked_in')
            ->where('check_in_date', '<=', $today)
            ->where('check_out_date', '>=', $today)
            ->exists();

        if ($hasActiveCheckedIn || $this->status === 'occupied') {
            return 'occupied';
        }

        // 3. Housekeeping cleaning turnover state
        if ($this->status === 'cleaning' || $this->housekeeping_status === 'dirty') {
            return 'cleaning';
        }

        // 4. Confirmed reservation for today or current period
        $hasConfirmedArrivalToday = $this->bookings()
            ->whereIn('booking_status', ['confirmed', 'pending_verification'])
            ->where('check_in_date', '<=', $today)
            ->where('check_out_date', '>', $today)
            ->exists() || Booking::whereHas('bookingRooms', function ($br) {
                $br->where('room_id', $this->id);
            })
            ->whereIn('booking_status', ['confirmed', 'pending_verification'])
            ->where('check_in_date', '<=', $today)
            ->where('check_out_date', '>', $today)
            ->exists();

        if ($hasConfirmedArrivalToday || $this->status === 'reserved') {
            return 'reserved';
        }

        // 5. Available
        return 'available';
    }
}
