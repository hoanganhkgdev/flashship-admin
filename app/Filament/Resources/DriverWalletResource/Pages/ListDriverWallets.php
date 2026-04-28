<?php

namespace App\Filament\Resources\DriverWalletResource\Pages;

use App\Filament\Resources\DriverWalletResource;
use App\Filament\Resources\DriverWalletResource\Widgets\DriverWalletOverviewWidget;
use App\Models\DriverWallet;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDriverWallets extends ListRecords
{
    protected static string $resource = DriverWalletResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [DriverWalletOverviewWidget::class];
    }

    protected function cityQuery(): Builder
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

    public function getTabs(): array
    {
        $base     = $this->cityQuery();
        $negative = (clone $base)->where('balance', '<', 0)->count();
        $positive = (clone $base)->where('balance', '>=', 0)->count();

        return [
            'all' => Tab::make('Tất cả')
                ->icon('heroicon-o-wallet')
                ->badge($base->count()),

            'negative' => Tab::make('Ví đang nợ')
                ->icon('heroicon-o-exclamation-triangle')
                ->badge($negative)
                ->badgeColor($negative > 0 ? 'danger' : 'gray')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('balance', '<', 0)),

            'positive' => Tab::make('Còn tiền')
                ->icon('heroicon-o-check-circle')
                ->badge($positive)
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('balance', '>=', 0)),
        ];
    }
}
