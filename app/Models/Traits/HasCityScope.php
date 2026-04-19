<?php

namespace App\Models\Traits;

use App\Models\Scopes\CityScoped;

trait HasCityScope
{
    protected static function bootHasCityScope()
    {
        static::addGlobalScope(new CityScoped);
    }
}