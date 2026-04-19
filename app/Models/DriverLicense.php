<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverLicense extends Model
{
    protected $fillable = ['user_id', 'image_path', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}