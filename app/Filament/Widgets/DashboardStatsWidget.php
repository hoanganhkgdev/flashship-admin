<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsWidget extends BaseWidget
{
    protected static ?int $sort   = 2;
    protected int|string|array $columnSpan = 'full';

    protected function getCityId(): ?int
    {
        $user = auth()->user();
        return $user->hasRole('admin')
            ? (session('current_city_id') ? (int) session('current_city_id') : null)
            : (int) $user->city_id;
    }

    protected function orderQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $q = Order::query();
        if ($cityId = $this->getCityId()) {
            $q->where('city_id', $cityId);
        }
        return $q;
    }

    protected function driverQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $q = User::drivers();
        if ($cityId = $this->getCityId()) {
            $q->where('city_id', $cityId);
        }
        return $q;
    }

    protected function getStats(): array
    {
        $orders = $this->orderQuery();

        $todayTotal      = (clone $orders)->whereDate('created_at', today())->count();
        $monthTotal      = (clone $orders)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();
        $todayPending    = (clone $orders)->whereDate('created_at', today())->where('status', 'pending')->count();
        $todayAssigned   = (clone $orders)->whereDate('created_at', today())->whereIn('status', ['assigned', 'delivering'])->count();
        $todayCompleted  = (clone $orders)->whereDate('created_at', today())->where('status', 'completed')->count();
        $todayRevenue    = (clone $orders)->whereDate('completed_at', today())->where('status', 'completed')->sum('shipping_fee');
        $monthRevenue    = (clone $orders)->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)->where('status', 'completed')->sum('shipping_fee');
        $waitApproval    = (clone $orders)->where('status', 'delivered_pending')->count();

        $drivers         = $this->driverQuery();
        $onlineDrivers   = (clone $drivers)->where('is_online', true)->count();
        $activeDrivers   = (clone $drivers)->where('status', 1)->count();

        return [
            Stat::make('Tổng đơn hôm nay', $todayTotal)
                ->description('Tất cả đơn tạo trong ngày')
                ->icon('heroicon-o-shopping-bag')
                ->color('primary'),

            Stat::make('Tổng đơn tháng ' . now()->format('m'), $monthTotal)
                ->description('Tất cả đơn tháng ' . now()->format('m/Y'))
                ->icon('heroicon-o-calendar-days')
                ->color('primary'),

            Stat::make('Đơn chờ tài xế', $todayPending)
                ->description('Tạo hôm nay, chưa có người nhận')
                ->icon('heroicon-o-clock')
                ->color($todayPending > 0 ? 'warning' : 'gray'),

            Stat::make('Đang giao', $todayAssigned)
                ->description('Đã nhận + đang trên đường')
                ->icon('heroicon-o-truck')
                ->color($todayAssigned > 0 ? 'info' : 'gray'),

            Stat::make('Hoàn tất hôm nay', $todayCompleted)
                ->description('Đơn giao thành công')
                ->icon('heroicon-o-check-badge')
                ->color('success'),

            Stat::make('Doanh thu hôm nay', number_format($todayRevenue, 0, ',', '.') . '₫')
                ->description('Phí ship từ đơn hoàn tất')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Doanh thu tháng ' . now()->format('m'), number_format($monthRevenue, 0, ',', '.') . '₫')
                ->description('Tổng phí ship tháng ' . now()->format('m/Y'))
                ->icon('heroicon-o-chart-bar')
                ->color('success'),

            Stat::make('Chờ duyệt', $waitApproval)
                ->description('Freeship / phí 0 cần tổng đài duyệt')
                ->icon('heroicon-o-shield-exclamation')
                ->color($waitApproval > 0 ? 'danger' : 'gray'),

            Stat::make('Tài xế online', $onlineDrivers . ' / ' . $activeDrivers)
                ->description('Đang trực tuyến / tổng active')
                ->icon('heroicon-o-signal')
                ->color($onlineDrivers > 0 ? 'info' : 'gray'),
        ];
    }
}
