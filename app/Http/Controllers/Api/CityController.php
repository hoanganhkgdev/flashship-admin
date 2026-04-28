<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CityService;

class CityController extends Controller
{
    public function __construct(private CityService $cities) {}

    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Danh sách thành phố',
            'data'    => $this->cities->getForApp(),
        ]);
    }
}
