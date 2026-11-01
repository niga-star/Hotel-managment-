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
        'email',
        'phone',
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

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'guest_id', 'id');
    }
}
