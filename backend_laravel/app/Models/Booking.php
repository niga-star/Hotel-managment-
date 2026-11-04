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
        'rooms_count',
        'nightly_rate_etb',
        'nightly_rate_usd',
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
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'hold_expires_at',
        'key_card_number',
        'early_check_in_approved',
        'early_check_in_fee_etb',
        'late_check_out_approved',
        'late_check_out_fee_etb',
        'check_in_notes',
        'check_out_notes',
    ];

    protected static function booted()
    {
        static::creating(function ($booking) {
            if (empty($booking->id)) {
                $booking->id = 'bk-' . time() . '-' . rand(100, 999);
            }
            if (empty($booking->qr_verification_hash)) {
                $booking->qr_verification_hash = hash('sha256', ($booking->reference_code ?: uniqid()) . microtime());
            }
            if (!isset($booking->total_price_usd)) {
                $booking->total_price_usd = isset($booking->total_price_etb) ? round(((float)$booking->total_price_etb) / 64, 2) : 0.00;
            }
            if (!isset($booking->nightly_rate_etb)) {
                $booking->nightly_rate_etb = isset($booking->total_price_etb) ? (float)$booking->total_price_etb : 0.00;
            }
        });
    }

    protected $casts = [
        'adults_count' => 'integer',
        'children_count' => 'integer',
        'rooms_count' => 'integer',
        'nightly_rate_etb' => 'float',
        'nightly_rate_usd' => 'float',
        'total_price_etb' => 'float',
        'total_price_usd' => 'float',
        'is_walk_in' => 'boolean',
        'early_check_in_approved' => 'boolean',
        'early_check_in_fee_etb' => 'float',
        'late_check_out_approved' => 'boolean',
        'late_check_out_fee_etb' => 'float',
        'cancelled_at' => 'datetime',
        'actual_check_in_at' => 'datetime',
        'actual_check_out_at' => 'datetime',
        'hold_expires_at' => 'datetime',
    ];

    public function guest()
    {
        return $this->belongsTo(User::class, 'guest_id', 'id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'id');
    }

    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class, 'booking_id', 'id');
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'booking_rooms', 'booking_id', 'room_id')
            ->withPivot(['id', 'room_number', 'adults_count', 'children_count', 'nightly_rate_etb', 'total_price_etb', 'status']);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'booking_id', 'id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'booking_id', 'id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'booking_id', 'id');
    }

    public function serviceCharges()
    {
        return $this->hasMany(BookingServiceCharge::class, 'booking_id', 'id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'resource_id', 'id')
            ->where('resource_type', 'booking')
            ->orderBy('created_at', 'desc');
    }

    public function getTotalServiceChargesEtbAttribute(): float
    {
        return (float)$this->serviceCharges->sum('amount_etb');
    }

    public function getGrossTotalEtbAttribute(): float
    {
        return (float)$this->total_price_etb + $this->total_service_charges_etb;
    }

    public function getOutstandingBalanceEtbAttribute(): float
    {
        $verifiedPayments = $this->payments
            ->filter(fn($p) => in_array($p->status, ['verified', 'completed']))
            ->sum(fn($p) => max(0, (float)$p->amount_etb - (float)($p->refunded_amount_etb ?? 0)));
        return max(0, $this->gross_total_etb - (float)$verifiedPayments);
    }
}
