<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['driver_debt_id', 'order_code', 'amount', 'status', 'channel'];

    public function debt()
    {
        return $this->belongsTo(DriverDebt::class, 'driver_debt_id');
    }
}
