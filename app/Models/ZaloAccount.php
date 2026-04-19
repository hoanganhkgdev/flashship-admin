<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZaloAccount extends Model
{
    protected $fillable = [
        'name',
        'oa_id',
        'city_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'token_expires_at' => 'datetime',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
