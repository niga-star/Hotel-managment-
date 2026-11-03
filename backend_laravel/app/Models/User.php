<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'role',
        'is_verified',
        'full_name',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'nationality',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'id_type',
        'id_number',
        'id_issue_country',
        'id_expiry_date',
        'vip_status',
        'preferences',
        'notes',
        'is_active',
        'emergency_contact_name',
        'emergency_contact_phone',
        'password_hash',
        'verification_token',
        'verification_code',
        'verification_token_expires',
        'verification_attempts',
        'code_last_sent_at',
        'reset_password_token',
        'reset_password_expires',
        'avatar_url',
    ];

    protected $hidden = [
        'password_hash',
        'verification_token',
        'verification_code',
        'reset_password_token',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'preferences' => 'array',
        'date_of_birth' => 'date:Y-m-d',
        'id_expiry_date' => 'date:Y-m-d',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'guest_id', 'id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'resource_id', 'id');
    }

    /**
     * Masked ID number for privacy protection (e.g. '******4829').
     */
    public function getMaskedIdNumberAttribute(): ?string
    {
        if (empty($this->id_number)) {
            return null;
        }
        $len = strlen($this->id_number);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return str_repeat('*', $len - 4) . substr($this->id_number, -4);
    }

    /**
     * Number of completed stays (checked out).
     */
    public function getStaysCountAttribute(): int
    {
        return $this->bookings()->where('booking_status', 'checked_out')->count();
    }

    /**
     * Determines whether this guest is a returning guest (repeat visitor).
     */
    public function getIsReturningAttribute(): bool
    {
        return $this->bookings()
            ->whereIn('booking_status', ['checked_out', 'checked_in', 'confirmed'])
            ->count() >= 2;
    }
}
