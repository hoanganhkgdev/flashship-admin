<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\User;
use App\Models\PlanShiftRate;

class Shift extends Model
{
    protected $fillable = [
        'city_id',
        'code',
        'name',
        'start_time',
        'end_time',
        'is_active'
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'shift_user', 'shift_id', 'user_id');
    }

    public function isNowInShift(): bool
    {
        $now   = Carbon::now();
        $start = Carbon::parse($this->start_time);
        $end   = Carbon::parse($this->end_time);

        if ($end->lessThan($start)) {
            // Ca qua đêm (ví dụ 22:00 - 05:00)
            // Case A: 22:00 → 23:59  →  $now >= $start
            // Case B: 00:00 → 05:00  →  $now <= $end
            return $now->greaterThanOrEqualTo($start) || $now->lessThanOrEqualTo($end);
        }

        return $now->between($start, $end);
    }
}

