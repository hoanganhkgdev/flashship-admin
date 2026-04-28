<?php

namespace App\Filament\Resources\DriverDebtResource\Widgets;

use App\Models\DriverDebt;
use App\Models\Plan;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DriverDebtOverviewWidget extends BaseWidget
{
    protected function baseQuery(): Builder
    {
        $query = DriverDebt::query();
        $user  = auth()->user();

        if ($user->hasRole('admin')) {
            if ($cityId = session('current_city_id')) {
                $query->whereHas('driver', fn($q) => $q->where('city_id', $cityId));
            }
        } elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $query->whereHas('driver', fn($q) => $q->where('city_id', $user->city_id));
        }

        return $query;
    }

    protected function cityPlanTypes(): array
    {
        $cityId   = session('current_city_id') ?: null;
        $priority = ['commission', 'partner', 'weekly'];

        if (!$cityId) {
            return $priority;
        }

        $active = Plan::active()->forCity($cityId)->pluck('type')->unique()->toArray();

        return array_values(array_filter($priority, fn($t) => in_array($t, $active)));
    }

    protected function getStats(): array
    {
        $base  = $this->baseQuery();
        $types = $this->cityPlanTypes();
        $stats = [];

        // 1 query gom tất cả commission/partner stats (join users để phân biệt)
        $commissionAgg = (clone $base)
            ->where('debt_type', 'commission')
            ->join('users', 'users.id', '=', 'driver_debts.driver_id')
            ->selectRaw("
                CASE WHEN users.custom_commission_rate IS NULL THEN 'commission' ELSE 'partner' END AS sub_type,
                SUM(CASE WHEN driver_debts.status = 'pending' THEN 1 ELSE 0 END) AS pending_cnt,
                SUM(CASE WHEN driver_debts.status IN ('pending','overdue') THEN driver_debts.amount_due - driver_debts.amount_paid ELSE 0 END) AS remaining
            ")
            ->groupByRaw("CASE WHEN users.custom_commission_rate IS NULL THEN 'commission' ELSE 'partner' END")
            ->get()
            ->keyBy('sub_type');

        // 1 query cho weekly stats
        $weeklyAgg = (clone $base)
            ->where('debt_type', 'weekly')
            ->selectRaw("
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_cnt,
                SUM(CASE WHEN status IN ('pending','overdue') THEN amount_due - amount_paid ELSE 0 END) AS remaining
            ")
            ->first();

        // 1 query cho overdue (luôn hiển thị)
        $overdueTotal = (clone $base)->where('status', 'overdue')->count();

        if (in_array('commission', $types)) {
            $row        = $commissionAgg['commission'] ?? null;
            $pendingCnt = (int) ($row?->pending_cnt ?? 0);
            $amount     = (float) ($row?->remaining ?? 0);

            $stats[] = Stat::make('CK chưa thu', $pendingCnt . ' phiếu')
                ->description('Còn lại: ' . number_format($amount, 0, ',', '.') . '₫')
                ->icon('heroicon-o-receipt-percent')
                ->color($pendingCnt > 0 ? 'warning' : 'gray');
        }

        if (in_array('partner', $types)) {
            $row        = $commissionAgg['partner'] ?? null;
            $pendingCnt = (int) ($row?->pending_cnt ?? 0);
            $amount     = (float) ($row?->remaining ?? 0);

            $stats[] = Stat::make('Đối tác chưa thu', $pendingCnt . ' phiếu')
                ->description('Còn lại: ' . number_format($amount, 0, ',', '.') . '₫')
                ->icon('heroicon-o-user-group')
                ->color($pendingCnt > 0 ? 'info' : 'gray');
        }

        if (in_array('weekly', $types)) {
            $pendingCnt = (int) ($weeklyAgg?->pending_cnt ?? 0);
            $amount     = (float) ($weeklyAgg?->remaining ?? 0);

            $stats[] = Stat::make('Tuần chưa đóng', $pendingCnt . ' phiếu')
                ->description('Còn lại: ' . number_format($amount, 0, ',', '.') . '₫')
                ->icon('heroicon-o-calendar-days')
                ->color($pendingCnt > 0 ? 'primary' : 'gray');
        }

        $stats[] = Stat::make('Quá hạn', $overdueTotal . ' phiếu')
            ->description('Cần xử lý ngay')
            ->icon('heroicon-o-exclamation-triangle')
            ->color($overdueTotal > 0 ? 'danger' : 'gray');

        return $stats;
    }
}
