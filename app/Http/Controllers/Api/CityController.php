<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;

class CityController extends Controller
{
    public function index()
    {
        $cities = City::select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(function ($city) {
                // Lấy gói đăng ký (plan) đang hoạt động của thành phố này
                $plan = \App\Models\Plan::where('city_id', $city->id)
                    ->where('is_active', 1)
                    ->first();
                
                // Gán type vào city để app Flutter nhận biết
                $city->type = $plan ? $plan->type : 'chiết khấu';
                return $city;
            });

        return response()->json([
            'success' => true,
            'message' => 'Danh sách thành phố',
            'data'    => $cities,
        ]);
    }
}
