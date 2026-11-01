<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'bookings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'reference_code',
        'guest_id',
        'room_id',
        'check_in_date',
        'check_out_date',
        'adults_count',
        'children_count',
        'total_price_etb',
        'total_price_usd',
        'booking_status',
        'payment_status',
        'is_walk_in',
        'qr_verification_hash',
        'notes',
        'actual_check_in_at',
        'checked_in_by',
        'actual_check_out_at',
        'checked_out_by',
        'hold_expires_at',
    ];

    public function guest()
    {
        return $this->belongsTo(User::class, 'guest_id', 'id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'booking_id', 'id');
    }
}
