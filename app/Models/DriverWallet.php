<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverWallet extends Model
{
    protected $fillable = ['driver_id', 'balance'];

    public function transactions() {
        return $this->hasMany(DriverWalletTransaction::class, 'wallet_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}

