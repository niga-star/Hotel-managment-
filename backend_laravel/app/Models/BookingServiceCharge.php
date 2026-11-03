<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingServiceCharge extends Model
{
    use HasFactory;

    protected $table = 'booking_service_charges';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'booking_id',
        'service_type',
        'description',
        'amount_etb',
        'amount_usd',
        'quantity',
        'added_by',
    ];

    protected $casts = [
        'amount_etb' => 'float',
        'amount_usd' => 'float',
        'quantity' => 'integer',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }
}
