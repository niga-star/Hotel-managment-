<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomCategory extends Model
{
    protected $table = 'room_categories';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'description',
        'base_price_etb',
        'base_price_usd',
    ];

    public function rooms()
    {
        return $this->hasMany(Room::class, 'category_id', 'id');
    }
}
