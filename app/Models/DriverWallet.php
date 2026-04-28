<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverWallet extends Model
{
    protected $fillable = ['driver_id', 'balance'];

    protected $casts = ['balance' => 'float'];

    public function transactions()
    {
        return $this->hasMany(DriverWalletTransaction::class, 'wallet_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function scopeNegative($query)
    {
        return $query->where('balance', '<', 0);
    }

    public function scopePositive($query)
    {
        return $query->where('balance', '>=', 0);
    }
}
