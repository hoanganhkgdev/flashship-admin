<?php

namespace App\Http\Controllers\AdminApi;

use App\Http\Controllers\Controller;
use App\Models\City;

class CityController extends Controller
{
    public function index()
    {
        return City::select('id', 'name')->orderBy('name')->get();
    }
}