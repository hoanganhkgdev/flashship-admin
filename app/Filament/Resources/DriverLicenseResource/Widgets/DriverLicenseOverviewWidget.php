<?php

namespace App\Filament\Resources\DriverLicenseResource\Widgets;

use App\Models\DriverLicense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class DriverLicenseOverviewWidget extends BaseWidget
{
    protected function baseQuery(): Builder
    {
        $query = DriverLicense::query();
        $user  = auth()->user();

        if ($user->hasRole('admin')) {
            if ($cityId = session('current_city_id')) {
                $query->whereHas('user', fn($q) => $q->where('city_id', $cityId));
            }
        } elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $query->whereHas('user', fn($q) => $q->where('city_id', $user->city_id));
        }

        return $query;
    }

    protected function getStats(): array
    {
        $base     = $this->baseQuery();
        $pending  = (clone $base)->where('status', DriverLicense::STATUS_PENDING)->count();
        $approved = (clone $base)->where('status', DriverLicense::STATUS_APPROVED)->count();
        $rejected = (clone $base)->where('status', DriverLicense::STATUS_REJECTED)->count();

        return [
            Stat::make('Chờ duyệt', $pending)
                ->description('Hồ sơ cần kiểm tra')
                ->icon('heroicon-o-clock')
                ->color($pending > 0 ? 'warning' : 'gray'),

            Stat::make('Đã duyệt', $approved)
                ->description('Bằng lái hợp lệ')
                ->icon('heroicon-o-check-badge')
                ->color('success'),

            Stat::make('Từ chối', $rejected)
                ->description('Hồ sơ không đạt')
                ->icon('heroicon-o-x-circle')
                ->color($rejected > 0 ? 'danger' : 'gray'),
        ];
    }
}
