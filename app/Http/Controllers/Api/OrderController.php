<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->hasRole(['admin', 'dispatcher'])) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xem danh sách đơn hàng',
            ], 403);
        }

        $query = Order::with(['driver', 'city'])->latest();

        if ($user->hasRole('dispatcher')) {
            if (!$user->city_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản tổng đài chưa được gán khu vực',
                ], 403);
            }
            $query->where('city_id', $user->city_id);
        }

        $orders = $query->paginate(20);

        return response()->json([
            'success' => true,
            'message' => $user->hasRole('dispatcher')
                ? 'Danh sách đơn hàng trong khu vực của bạn'
                : 'Danh sách tất cả đơn hàng',
            'data' => $orders,
        ]);
    }

    public function myOrders(): JsonResponse
    {
        try {
            $data = $this->orderService->getDriverOrders(Auth::user());

            return response()->json([
                'success' => true,
                'message' => 'Danh sách đơn hàng của bạn',
                ...$data,
            ]);
        } catch (\Throwable $e) {
            Log::error('Lỗi myOrders: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách đơn: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function dashboard(): JsonResponse
    {
        $data = $this->orderService->getDashboardStats(Auth::user());

        return response()->json([
            'success' => true,
            'message' => 'Dashboard driver',
            'data'    => $data,
        ]);
    }

    public function accept(Order $order): JsonResponse
    {
        $result = $this->orderService->acceptOrder($order, Auth::user());
        $status = $result['status'];
        unset($result['status']);

        return response()->json(
            $result['success']
                ? ['success' => true, 'message' => 'Nhận đơn thành công', 'order' => $result['order']]
                : $result,
            $status
        );
    }

    public function complete(Order $order): JsonResponse
    {
        $result = $this->orderService->completeOrder($order, Auth::user());
        $status = $result['status'];
        unset($result['status']);

        return response()->json($result, $status);
    }

    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->city_id) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản chưa được gán khu vực (city_id)',
            ], 403);
        }

        try {
            $order = $this->orderService->createOrder($request->validated(), $user);

            return response()->json([
                'success' => true,
                'message' => 'Tạo đơn hàng thành công',
                'data'    => $order,
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ Lỗi tạo đơn hàng: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Lỗi khi tạo đơn hàng'], 500);
        }
    }

    public function completedOrders(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $data = $this->orderService->getCompletedOrders(Auth::user(), $page);

        return response()->json(['success' => true, ...$data]);
    }
}
