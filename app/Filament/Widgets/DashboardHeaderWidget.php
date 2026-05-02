<?php

namespace App\Filament\Widgets;

use App\Models\City;
use App\Models\User;
use Filament\Widgets\Widget;

class DashboardHeaderWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-header-widget';
    protected static ?int $sort   = 1;
    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $user   = auth()->user();
        $cityId = $user->hasRole('admin')
            ? session('current_city_id')
            : $user->city_id;

        $city = City::find($cityId);

        $onlineQuery = User::drivers()->where('is_online', true);
        if ($cityId) {
            $onlineQuery->where('city_id', $cityId);
        }

        return [
            'cityName'          => $city?->name ?? 'Toàn quốc',
            'onlineDriversCount' => $onlineQuery->count(),
        ];
    }
}
