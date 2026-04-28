<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $query = Shift::active()->orderBy('start_time');

        if ($cityId = $request->integer('city_id')) {
            $query->where(fn($q) => $q->forCity($cityId)->orWhereNull('city_id'));
        }

        $shifts = $query->get(['id', 'code', 'name', 'start_time', 'end_time', 'city_id']);

        return response()->json([
            'success' => true,
            'message' => 'Danh sách ca làm việc',
            'data'    => $shifts,
        ]);
    }
}
