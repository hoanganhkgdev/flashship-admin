<?php

namespace App\Filament\Resources\ShiftResource\Pages;

use App\Filament\Resources\ShiftResource;
use App\Filament\Resources\ShiftResource\Widgets\ShiftOverviewWidget;
use App\Models\City;
use App\Models\Shift;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListShifts extends ListRecords
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Thêm ca làm việc')];
    }

    protected function getHeaderWidgets(): array
    {
        return [ShiftOverviewWidget::class];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('Tất cả')
                ->icon('heroicon-o-squares-2x2')
                ->badge(Shift::count()),
        ];

        $cities = City::whereHas('shifts')->orderBy('name')->get(['id', 'name']);

        foreach ($cities as $city) {
            $active = Shift::active()->forCity($city->id)->count();
            $total  = Shift::forCity($city->id)->count();

            $tabs[(string) $city->id] = Tab::make($city->name)
                ->icon('heroicon-o-map-pin')
                ->badge($active . '/' . $total)
                ->badgeColor($active > 0 ? 'success' : 'gray')
                ->modifyQueryUsing(fn(Builder $query) => $query->forCity($city->id));
        }

        $sharedTotal  = Shift::whereNull('city_id')->count();
        $sharedActive = Shift::active()->whereNull('city_id')->count();

        if ($sharedTotal > 0) {
            $tabs['shared'] = Tab::make('Dùng chung')
                ->icon('heroicon-o-globe-alt')
                ->badge($sharedActive . '/' . $sharedTotal)
                ->badgeColor($sharedActive > 0 ? 'success' : 'gray')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('city_id'));
        }

        return $tabs;
    }
}
