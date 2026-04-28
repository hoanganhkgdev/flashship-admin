<?php

namespace App\Filament\Resources\CityResource\Widgets;

use App\Models\City;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CityOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $total   = City::count();
        $active  = City::active()->count();
        $drivers = User::drivers()->count();

        return [
            Stat::make('Tổng khu vực', $total)
                ->description('Toàn hệ thống')
                ->icon('heroicon-o-building-office')
                ->color('gray'),

            Stat::make('Đang hoạt động', $active)
                ->description($total > 0 ? round($active / $total * 100) . '% khu vực mở' : '—')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Tổng tài xế', $drivers)
                ->description('Trên tất cả khu vực')
                ->icon('heroicon-o-user-group')
                ->color('info'),
        ];
    }
}
