<?php

namespace App\Http\Controllers\AdminApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Danh sách tất cả đơn theo khu vực
     */
    public function index()
    {
        $user = auth()->user();

        if (!$user->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Không có quyền'], 403);
        }

        $orders = Order::with('driver')->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function activeOrdersByDriver($driverId)
    {
        $orders = Order::withoutGlobalScopes()
            ->where('delivery_man_id', $driverId)
            ->whereIn('status', ['assigned', 'delivering'])
            ->orderByDesc('id')
            ->get(['id', 'status', 'pickup_address', 'delivery_address', 'shipping_fee', 'bonus_fee', 'order_note', 'created_at']);


        return response()->json([
            'success' => true,
            'data' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'code' => $order->id,
                    'status' => $order->status,
                    'pickup' => $order->pickup_address,
                    'delivery' => $order->delivery_address,
                    'fee' => $order->shipping_fee + ($order->bonus_fee ?? 0),
                    'order_note' => $order->order_note,
                    'created_at' => $order->created_at->format('H:i d/m'),
                ];
            }),
        ]);
    }
}