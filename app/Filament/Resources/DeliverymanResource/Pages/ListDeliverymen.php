<?php

namespace App\Filament\Resources\DeliverymanResource\Pages;

use App\Filament\Resources\DeliverymanResource;
use App\Filament\Resources\DeliverymanResource\Widgets\DriverOverviewWidget;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDeliverymen extends ListRecords
{
    protected static string $resource = DeliverymanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Thêm tài xế'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [DriverOverviewWidget::class];
    }

    private function cityQuery(): Builder
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

    public function getTabs(): array
    {
        $base = $this->cityQuery();

        $all        = (clone $base)->count();
        $weekly     = (clone $base)->whereHas('plan', fn($q) => $q->where('type', 'weekly'))->count();
        $commission = (clone $base)->whereHas('plan', fn($q) => $q->where('type', 'commission'))->count();
        $partner    = (clone $base)->where(fn($q) => $q
            ->whereHas('plan', fn($p) => $p->where('type', 'partner'))
            ->orWhereNotNull('custom_commission_rate')
        )->count();

        return [
            'all' => Tab::make('Tất cả')
                ->icon('heroicon-o-users')
                ->badge($all),

            'weekly' => Tab::make('Gói tuần')
                ->icon('heroicon-o-calendar-days')
                ->badge($weekly)
                ->badgeColor('primary')
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->whereHas('plan', fn($q) => $q->where('type', 'weekly'))),

            'commission' => Tab::make('Chiết khấu %')
                ->icon('heroicon-o-receipt-percent')
                ->badge($commission)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->whereHas('plan', fn($q) => $q->where('type', 'commission'))),

            'partner' => Tab::make('Đối tác')
                ->icon('heroicon-o-user-group')
                ->badge($partner)
                ->badgeColor('info')
                ->modifyQueryUsing(fn(Builder $query) => $query->where(fn(Builder $q) => $q
                    ->whereHas('plan', fn($p) => $p->where('type', 'partner'))
                    ->orWhereNotNull('custom_commission_rate'))),
        ];
    }
}
