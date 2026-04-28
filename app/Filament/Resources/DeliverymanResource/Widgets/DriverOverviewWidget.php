<?php

namespace App\Filament\Resources\DeliverymanResource\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class DriverOverviewWidget extends BaseWidget
{
    protected function baseQuery(): Builder
    {
        $query = User::drivers();
        $user  = auth()->user();

        if ($user->hasRole('admin')) {
            if ($cityId = session('current_city_id')) {
                $query->where('city_id', $cityId);
            }
        } elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $query->where('city_id', $user->city_id);
        }

        return $query;
    }

    protected function getStats(): array
    {
        $base    = $this->baseQuery();
        $total   = (clone $base)->count();
        $active  = (clone $base)->where('status', 1)->count();
        $pending = (clone $base)->where('status', 0)->count();
        $online  = (clone $base)->where('is_online', true)->count();

        return [
            Stat::make('Tổng tài xế', $total)
                ->description('Đã đăng ký hệ thống')
                ->icon('heroicon-o-user-group')
                ->color('gray'),

            Stat::make('Đang hoạt động', $active)
                ->description($total > 0 ? round($active / $total * 100) . '% tổng tài xế' : '—')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Chờ duyệt', $pending)
                ->description('Hồ sơ mới cần xem xét')
                ->icon('heroicon-o-clock')
                ->color($pending > 0 ? 'warning' : 'gray'),

            Stat::make('Đang online', $online)
                ->description('Đang trực tuyến ngay bây giờ')
                ->icon('heroicon-o-signal')
                ->color($online > 0 ? 'info' : 'gray'),
        ];
    }
}
