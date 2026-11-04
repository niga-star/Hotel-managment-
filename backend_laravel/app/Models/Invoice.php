<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'invoices';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'invoice_number',
        'booking_id',
        'guest_id',
        'issue_date',
        'due_date',
        'subtotal_etb',
        'tax_vat_etb',
        'tax_service_etb',
        'tax_tourism_etb',
        'total_amount_etb',
        'paid_amount_etb',
        'balance_due_etb',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal_etb' => 'float',
        'tax_vat_etb' => 'float',
        'tax_service_etb' => 'float',
        'tax_tourism_etb' => 'float',
        'total_amount_etb' => 'float',
        'paid_amount_etb' => 'float',
        'balance_due_etb' => 'float',
        'issue_date' => 'date',
        'due_date' => 'date',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function guest()
    {
        return $this->belongsTo(User::class, 'guest_id', 'id');
    }
}
