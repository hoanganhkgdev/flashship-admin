<?php

namespace App\Filament\Resources\WithdrawRequestResource\Pages;

use App\Filament\Resources\WithdrawRequestResource;
use App\Filament\Resources\WithdrawRequestResource\Widgets\WithdrawOverviewWidget;
use App\Models\WithdrawRequest;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListWithdrawRequests extends ListRecords
{
    protected static string $resource = WithdrawRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [WithdrawOverviewWidget::class];
    }

    protected function cityQuery(): Builder
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

    public function getTabs(): array
    {
        $base     = $this->cityQuery();
        $pending  = (clone $base)->where('status', 'pending')->count();
        $approved = (clone $base)->where('status', 'approved')->count();
        $rejected = (clone $base)->where('status', 'rejected')->count();
        $failed   = (clone $base)->where('status', 'failed')->count();

        return [
            'all' => Tab::make('Tất cả')
                ->icon('heroicon-o-list-bullet')
                ->badge($base->count()),

            'pending' => Tab::make('Đang chờ')
                ->icon('heroicon-o-clock')
                ->badge($pending)
                ->badgeColor($pending > 0 ? 'warning' : 'gray')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'pending')),

            'approved' => Tab::make('Thành công')
                ->icon('heroicon-o-check-circle')
                ->badge($approved)
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'approved')),

            'rejected' => Tab::make('Từ chối')
                ->icon('heroicon-o-x-circle')
                ->badge($rejected)
                ->badgeColor($rejected > 0 ? 'danger' : 'gray')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'rejected')),

            'failed' => Tab::make('Thất bại')
                ->icon('heroicon-o-exclamation-triangle')
                ->badge($failed)
                ->badgeColor($failed > 0 ? 'danger' : 'gray')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'failed')),
        ];
    }
}
