<?php

namespace App\Filament\Resources\PlanResource\Widgets;

use App\Models\Plan;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlanOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $weekly     = Plan::active()->weekly()->count();
        $commission = Plan::active()->commission()->count();
        $partner    = Plan::active()->partner()->count();
        $free       = Plan::active()->free()->count();

        return [
            Stat::make('Cước tuần', $weekly)
                ->description('Chia ca — Full 420k / Part 300k')
                ->icon('heroicon-o-calendar-days')
                ->color('info'),

            Stat::make('Chiết khấu %', $commission)
                ->description('Chạy tự do — trừ % theo đơn')
                ->icon('heroicon-o-percent-badge')
                ->color('warning'),

            Stat::make('Tài xế đối tác', $partner)
                ->description('Không có phí cố định — phí set theo tài xế')
                ->icon('heroicon-o-user-group')
                ->color('success'),

            Stat::make('Miễn phí', $free)
                ->description('Tổng đài, quản lý, admin')
                ->icon('heroicon-o-gift')
                ->color('gray'),
        ];
    }
}
