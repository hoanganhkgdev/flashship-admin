<?php

namespace App\Models;

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

    public function drivers()
    {
        return $this->hasMany(User::class, 'city_id')->whereHas('roles', fn($q) => $q->where('name', 'driver'));
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
