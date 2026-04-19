<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Request;

class CityScoped implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // Lấy city_id hiện tại từ container hoặc từ request
        $currentCityId = app()->bound('current_city_id')
            ? app('current_city_id')
            : null;

        $cityId = $currentCityId ?? Request::get('city_id');

        if (!empty($cityId)) {
            $builder->where($model->getTable() . '.city_id', (int) $cityId);
        }
    }
}