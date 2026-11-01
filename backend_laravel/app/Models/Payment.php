<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'transaction_id',
        'booking_id',
        'payment_method',
        'amount_etb',
        'amount_usd',
        'claimed_amount_etb',
        'receipt_url',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'paid_at',
        'rejection_reason',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }
}
