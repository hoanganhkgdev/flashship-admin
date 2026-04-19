<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverWalletTransaction extends Model
{
    protected $fillable = ['wallet_id', 'type', 'amount', 'description', 'reference'];

    public function wallet() {
        return $this->belongsTo(DriverWallet::class, 'wallet_id');
    }
}

