<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    const TYPE_WEEKLY     = 'weekly';
    const TYPE_COMMISSION = 'commission';
    const TYPE_PARTNER    = 'partner';
    const TYPE_FREE       = 'free';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'city_id',
        'type',
        'weekly_fee_full',
        'weekly_fee_part',
        'commission_rate',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'weekly_fee_full' => 'integer',
        'weekly_fee_part' => 'integer',
        'commission_rate' => 'float',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function drivers()
    {
        return $this->hasMany(User::class, 'plan_id');
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

    public function scopeWeekly(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_WEEKLY);
    }

    public function scopeCommission(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_COMMISSION);
    }

    public function scopePartner(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PARTNER);
    }

    public function scopeFree(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_FREE);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /** Gói tuần mới được gán ca làm việc */
    public function requiresShifts(): bool
    {
        return $this->type === self::TYPE_WEEKLY;
    }

    /** Tài xế đối tác có % riêng từng người */
    public function isPartner(): bool
    {
        return $this->type === self::TYPE_PARTNER;
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getFeeInfoAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_WEEKLY => 'Full: ' . number_format((int) $this->weekly_fee_full) . 'đ / Part: ' . number_format((int) $this->weekly_fee_part) . 'đ',
            self::TYPE_COMMISSION => "{$this->commission_rate}% / đơn",
            self::TYPE_PARTNER    => 'Chiết khấu riêng / tài xế',
            default               => '—',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_WEEKLY     => 'Trọn gói tuần',
            self::TYPE_COMMISSION => 'Chiết khấu %',
            self::TYPE_PARTNER    => 'Tài xế đối tác',
            self::TYPE_FREE       => 'Miễn phí',
            default               => $this->type,
        };
    }
}
