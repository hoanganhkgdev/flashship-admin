<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueReportService
{
    public static function monthData(int $year, ?int $cityId): Collection
    {
        $query = DB::table('orders')
            ->selectRaw("
                DATE_FORMAT(created_at, '%Y-%m') AS period_key,
                YEAR(created_at) AS yr,
                MONTH(created_at) AS mo,
                COUNT(*)                                                AS total_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END)  AS completed_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END)  AS cancelled_orders,
                SUM(COALESCE(shipping_fee, 0))                         AS total_ship_fee,
                SUM(COALESCE(bonus_fee, 0))                            AS total_bonus_fee,
                SUM(COALESCE(shipping_fee, 0) + COALESCE(bonus_fee, 0)) AS total_revenue
            ")
            ->whereYear('created_at', $year)
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m'), YEAR(created_at), MONTH(created_at)")
            ->orderByRaw('yr, mo');

        if ($cityId) {
            $query->where('city_id', $cityId);
        }

        return $query->get();
    }

    public static function weekData(string $from, string $until, ?int $cityId): Collection
    {
        $tz    = 'Asia/Ho_Chi_Minh';
        $start = Carbon::parse($from, $tz)->startOfDay();
        $end   = Carbon::parse($until, $tz)->endOfDay();

        $query = DB::table('orders')
            ->selectRaw("
                YEARWEEK(created_at, 1)                                                       AS week_key,
                WEEK(created_at, 1)                                                           AS week_num,
                YEAR(created_at)                                                              AS yr,
                MIN(DATE(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY)))            AS week_start,
                MAX(DATE(DATE_ADD(created_at, INTERVAL (6 - WEEKDAY(created_at)) DAY)))      AS week_end,
                COUNT(*)                                                                       AS total_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END)                         AS completed_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END)                         AS cancelled_orders,
                SUM(COALESCE(shipping_fee, 0))                                                AS total_ship_fee,
                SUM(COALESCE(bonus_fee, 0))                                                   AS total_bonus_fee,
                SUM(COALESCE(shipping_fee, 0) + COALESCE(bonus_fee, 0))                       AS total_revenue
            ")
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw('YEARWEEK(created_at, 1), WEEK(created_at, 1), YEAR(created_at)')
            ->orderBy('week_key');

        if ($cityId) {
            $query->where('city_id', $cityId);
        }

        return $query->get();
    }
}
