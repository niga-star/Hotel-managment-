<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRoom extends Model
{
    protected $table = 'booking_rooms';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'booking_id',
        'room_id',
        'room_number',
        'adults_count',
        'children_count',
        'nightly_rate_etb',
        'nightly_rate_usd',
        'total_price_etb',
        'total_price_usd',
        'status',
        'notes',
    ];

    protected $casts = [
        'adults_count' => 'integer',
        'children_count' => 'integer',
        'nightly_rate_etb' => 'float',
        'nightly_rate_usd' => 'float',
        'total_price_etb' => 'float',
        'total_price_usd' => 'float',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'id');
    }
}
