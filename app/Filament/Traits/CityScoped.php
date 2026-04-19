<?php

namespace App\Filament\Traits;

use Illuminate\Database\Eloquent\Builder;

trait CityScoped
{
    public static function modifyQueryUsing(Builder $query): Builder
    {
        // Nếu admin đã chọn vùng
        if ($city = session('current_city_id')) {
            $query->where('city_id', $city);
        }

        return $query;
    }
}