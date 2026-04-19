<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'city_id',
        'type',
        'weekly_fee_full',
        'weekly_fee_part',
        'commission_rate',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'weekly_fee_full' => 'integer',
        'weekly_fee_part' => 'integer',
        'commission_rate' => 'float',
    ];

    public function city(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function drivers()
    {
        return $this->hasMany(User::class, 'plan_id');
    }
}
