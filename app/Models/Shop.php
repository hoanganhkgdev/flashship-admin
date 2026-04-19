<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'address',
        'latitude',
        'longitude',
        'city_id',
        'zalo_id',
        'facebook_id',
        'is_active',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
