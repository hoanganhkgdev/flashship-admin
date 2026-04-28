<?php

namespace App\Filament\Resources\WithdrawRequestResource\Widgets;

use App\Models\WithdrawRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class WithdrawOverviewWidget extends BaseWidget
{
    protected function baseQuery(): Builder
    {
        $query = WithdrawRequest::query();
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

    protected function getStats(): array
    {
        $base          = $this->baseQuery();
        $pending       = (clone $base)->where('status', 'pending')->count();
        $pendingAmount = (clone $base)->where('status', 'pending')->sum('amount');
        $failed        = (clone $base)->where('status', 'failed')->count();
        $today         = (clone $base)->where('status', 'approved')->whereDate('updated_at', today())->count();
        $todayAmount   = (clone $base)->where('status', 'approved')->whereDate('updated_at', today())->sum('amount');

        return [
            Stat::make('Đang chờ duyệt', $pending . ' yêu cầu')
                ->description('Tổng: ' . number_format($pendingAmount, 0, ',', '.') . '₫')
                ->icon('heroicon-o-clock')
                ->color($pending > 0 ? 'warning' : 'gray'),

            Stat::make('Duyệt hôm nay', $today . ' yêu cầu')
                ->description('Đã chuyển: ' . number_format($todayAmount, 0, ',', '.') . '₫')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Thất bại', $failed . ' yêu cầu')
                ->description('Cần xử lý lại')
                ->icon('heroicon-o-x-circle')
                ->color($failed > 0 ? 'danger' : 'gray'),
        ];
    }
}
