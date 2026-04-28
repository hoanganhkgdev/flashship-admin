<?php

namespace App\Filament\Resources\DriverWalletResource\Widgets;

use App\Models\DriverWallet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class DriverWalletOverviewWidget extends BaseWidget
{
    protected function baseQuery(): Builder
    {
        $query = DriverWallet::query();
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
        $base     = $this->baseQuery();
        $total    = (clone $base)->count();
        $negative = (clone $base)->where('balance', '<', 0)->count();
        $sum      = (clone $base)->sum('balance');

        return [
            Stat::make('Tổng số ví', $total)
                ->description('Tài xế có ví trong hệ thống')
                ->icon('heroicon-o-wallet')
                ->color('primary'),

            Stat::make('Ví đang nợ', $negative)
                ->description('Số dư âm — cần thu hồi')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($negative > 0 ? 'danger' : 'gray'),

            Stat::make('Tổng số dư', number_format($sum, 0, ',', '.') . '₫')
                ->description($sum < 0 ? 'Hệ thống đang bị âm' : 'Tổng số dư khả dụng')
                ->icon('heroicon-o-banknotes')
                ->color($sum < 0 ? 'danger' : 'success'),
        ];
    }
}
