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
}
