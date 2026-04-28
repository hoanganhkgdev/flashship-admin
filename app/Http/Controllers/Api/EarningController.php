<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function weekly(Request $request): JsonResponse
    {
        $data = $this->orderService->getWeeklyEarnings($request->user()->id);

        return response()->json($data);
    }

    public function monthly(Request $request): JsonResponse
    {
        $data = $this->orderService->getMonthlyEarnings($request->user()->id);

        return response()->json($data);
    }

    public function recentOrders(Request $request): JsonResponse
    {
        $data = $this->orderService->getRecentOrders($request->user()->id);

        return response()->json($data);
    }

    public function kpi(Request $request): JsonResponse
    {
        $data = $this->orderService->getKpi($request->user());

        return response()->json([
            'success' => true,
            'message' => 'KPI tuần của tài xế',
            'data'    => $data,
        ]);
    }
}
