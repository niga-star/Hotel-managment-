<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = [
        'id',
        'transaction_id',
        'receipt_number',
        'booking_id',
        'payment_method',
        'is_advance_deposit',
        'amount_etb',
        'amount_usd',
        'refunded_amount_etb',
        'status',
        'refund_status',
        'parent_payment_id',
        'gateway_reference',
        'idempotency_key',
        'raw_response',
        'paid_at',
        'payment_date',
        'receipt_url',
        'claimed_amount_etb',
        'rejection_reason',
        'notes',
        'reviewed_by',
        'processed_by',
        'reviewed_at',
        'submitted_at',
    ];

    protected $casts = [
        'amount_etb' => 'float',
        'amount_usd' => 'float',
        'claimed_amount_etb' => 'float',
        'refunded_amount_etb' => 'float',
        'is_advance_deposit' => 'boolean',
        'paid_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'refundable_amount_etb',
    ];

    public function getRefundableAmountEtbAttribute()
    {
        return max(0, (float)$this->amount_etb - (float)($this->refunded_amount_etb ?? 0));
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'payment_id', 'id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by', 'id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id');
    }

    public function parentPayment()
    {
        return $this->belongsTo(Payment::class, 'parent_payment_id', 'id');
    }
}
