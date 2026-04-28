<?php

namespace App\Filament\Resources\ShiftResource\Widgets;

use App\Models\Plan;
use App\Models\Shift;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ShiftOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $total    = Shift::count();
        $active   = Shift::active()->count();
        $overnight = Shift::whereColumn('end_time', '<', 'start_time')->count();
        $drivers  = User::role('driver')
            ->whereHas('plan', fn($q) => $q->where('type', Plan::TYPE_WEEKLY))
            ->whereHas('shifts')
            ->count();

        return [
            Stat::make('Ca đang hoạt động', $active)
                ->description($total > 0 ? "Trên tổng {$total} ca đã tạo" : 'Chưa có ca nào')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Ca qua đêm', $overnight)
                ->description('Khung giờ vắt qua 00:00')
                ->icon('heroicon-o-moon')
                ->color('warning'),

            Stat::make('Tài xế đã gán ca', $drivers)
                ->description('Tài xế gói tuần đang có ca')
                ->icon('heroicon-o-user-group')
                ->color('info'),
        ];
    }
}
