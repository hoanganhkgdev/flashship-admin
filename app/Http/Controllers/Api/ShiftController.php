<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;

class ShiftController extends Controller
{
    public function index()
    {
        $shifts = Shift::query()
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get(['id', 'code', 'name', 'start_time', 'end_time']);

        return response()->json([
            'success' => true,
            'message' => 'Danh sách ca làm việc',
            'data'    => $shifts,
        ]);
    }
}
