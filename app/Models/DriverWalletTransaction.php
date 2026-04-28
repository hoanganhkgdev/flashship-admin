<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverWalletTransaction extends Model
{
    protected $fillable = ['wallet_id', 'type', 'amount', 'description', 'reference'];

    protected $casts = [
        'amount'     => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function wallet()
    {
        return $this->belongsTo(DriverWallet::class, 'wallet_id');
    }
}
