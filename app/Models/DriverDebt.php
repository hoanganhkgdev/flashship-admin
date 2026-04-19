<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverDebt extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'week_start',
        'week_end',
        'amount_due',
        'amount_paid',
        'status',
        'debt_type',
        'ref_id',
        'date',
        'note',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Lấy tổng thu nhập trong ngày (tổng shipping_fee đơn hoàn thành)
     */
    public function getDailyEarningAttribute()
    {
        try {
            if (!$this->exists || $this->debt_type !== 'commission' || !$this->date || !$this->driver_id) {
                return 0;
            }

            $start = \Carbon\Carbon::parse($this->date, 'Asia/Ho_Chi_Minh')->startOfDay()->toDateTimeString();
            $end   = \Carbon\Carbon::parse($this->date, 'Asia/Ho_Chi_Minh')->endOfDay()->toDateTimeString();

            return \App\Models\Order::where('delivery_man_id', $this->driver_id)
                ->whereBetween('completed_at', [$start, $end])
                ->where('status', 'completed')
                ->sum('shipping_fee') ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Lấy số đơn hoàn thành trong ngày
     */
    public function getDailyOrdersCountAttribute()
    {
        try {
            if (!$this->exists || $this->debt_type !== 'commission' || !$this->date || !$this->driver_id) {
                return 0;
            }

            $start = \Carbon\Carbon::parse($this->date, 'Asia/Ho_Chi_Minh')->startOfDay()->toDateTimeString();
            $end   = \Carbon\Carbon::parse($this->date, 'Asia/Ho_Chi_Minh')->endOfDay()->toDateTimeString();

            return \App\Models\Order::where('delivery_man_id', $this->driver_id)
                ->whereBetween('completed_at', [$start, $end])
                ->where('status', 'completed')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Lấy % chiết khấu từ gói cước của tài xế
     */
    public function getCommissionRateAttribute()
    {
        try {
            return $this->driver?->plan?->commission_rate ?? 15;
        } catch (\Exception $e) {
            return 15;
        }
    }

    /**
     * Phí App theo khu vực (Cần Thơ & Rạch Giá)
     */
    public function getAppFeeAttribute()
    {
        $cityId = $this->driver?->city_id;
        return in_array($cityId, [1, 2]) ? 3000 : 0;
    }

    /**
     * Số tiền chiết khấu thuần = daily_earning × commission_rate%
     */
    public function getCalculatedCommissionAttribute()
    {
        try {
            $earning = $this->daily_earning;
            $rate    = $this->commission_rate;
            return $earning > 0 ? ($earning * $rate / 100) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    public function scopeByType($query, $type)
    {
        return $type && $type !== 'all' ? $query->where('debt_type', $type) : $query;
    }

    public function scopeByStatus($query, $status)
    {
        return $status && $status !== 'all' ? $query->where('status', $status) : $query;
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        if ($startDate) {
            $query->where(function ($q) use ($startDate) {
                $q->whereDate('date', '>=', $startDate)
                    ->orWhereDate('week_start', '>=', $startDate);
            });
        }

        if ($endDate) {
            $query->where(function ($q) use ($endDate) {
                $q->whereDate('date', '<=', $endDate)
                    ->orWhere(function ($sq) use ($endDate) {
                        $sq->whereNull('date')
                            ->whereDate('week_end', '<=', $endDate);
                    });
            });
        }

        return $query;
    }

    public function scopeByCity($query, $cityId)
    {
        return $cityId ? $query->whereHas('driver', fn($q) => $q->where('city_id', $cityId)) : $query;
    }
}
