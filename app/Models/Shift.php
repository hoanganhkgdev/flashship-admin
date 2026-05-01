<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'city_id',
        'code',
        'name',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'shift_user', 'shift_id', 'user_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCity(Builder $query, int $cityId): Builder
    {
        return $query->where('city_id', $cityId);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isNowInShift(): bool
    {
        $now   = Carbon::now();
        $start = Carbon::parse($this->start_time);
        $end   = Carbon::parse($this->end_time);

        if ($end->lessThan($start)) {
            // Overnight shift (e.g. 22:00 – 05:00)
            return $now->greaterThanOrEqualTo($start) || $now->lessThanOrEqualTo($end);
        }

        return $now->between($start, $end);
    }

    /**
     * Seconds remaining until this shift ends.
     * Caller should only invoke this when isNowInShift() is true.
     */
    public function secondsUntilEnd(): int
    {
        $now   = Carbon::now();
        $start = Carbon::parse($this->start_time);
        $end   = Carbon::parse($this->end_time);

        // Overnight: if we're in the post-midnight segment, end is today; else end is tomorrow
        if ($end->lessThan($start) && $now->greaterThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return max(0, (int) $now->diffInSeconds($end, false));
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getTimeRangeAttribute(): string
    {
        return substr($this->start_time, 0, 5) . ' – ' . substr($this->end_time, 0, 5);
    }
}
