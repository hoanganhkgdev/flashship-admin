<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $fillable = [
        'user_id',
        'bank_code',
        'bank_name',
        'bank_account',
        'bank_owner',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
