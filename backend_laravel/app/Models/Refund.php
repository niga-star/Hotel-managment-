<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $table = 'refunds';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'refund_number',
        'payment_id',
        'booking_id',
        'amount_etb',
        'amount_usd',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'processed_at',
    ];

    protected $casts = [
        'amount_etb' => 'float',
        'amount_usd' => 'float',
        'processed_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by', 'id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }
}
