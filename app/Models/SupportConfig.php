<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'city_id',
        'title',
        'subtitle',
        'icon',
        'type',
        'value',
        'color',
        'priority',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer'
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
