<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverEarning extends Model
{
    protected $fillable = [
        'driver_id',
        'order_id',
        'amount',
        'date',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
