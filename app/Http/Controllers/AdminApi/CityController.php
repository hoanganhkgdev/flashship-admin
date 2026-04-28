<?php

namespace App\Http\Controllers\AdminApi;

use App\Http\Controllers\Controller;
use App\Services\CityService;

class CityController extends Controller
{
    public function __construct(private CityService $cities) {}

    public function index()
    {
        return response()->json($this->cities->getForSelect());
    }
}
