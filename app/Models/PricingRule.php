<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    protected $fillable = [
        'city_id',
        'service_type',
        'min_distance',
        'max_distance',
        'base_price',
        'price_per_km',
        'extra_fee',
        'min_amount',
        'max_amount',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
