<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'message',
        'audience',
        'city_id',
        'level',
        'starts_at',
        'ends_at',
    ];

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];
}