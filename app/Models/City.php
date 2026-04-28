<?php

namespace App\Models;

use App\Models\Plan;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'status',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'status'    => 'boolean',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    public function drivers()
    {
        return $this->hasMany(User::class, 'city_id')->drivers();
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function plans()
    {
        return $this->hasMany(Plan::class);
    }

    public function activePlan()
    {
        return $this->hasOne(Plan::class)->where('is_active', true);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }
}
