<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\ZaloService;
use App\Services\FacebookService;
use App\Models\ZaloAccount;
use App\Models\FacebookAccount;
use Filament\Notifications\Notification;
use App\Models\User;


class OrderController extends Controller
{
    /**
     * Danh sách tất cả đơn (chỉ admin/subadmin)
     */
    public function index()
    {
        $user = Auth::user();

        // 🔹 Chỉ admin & dispatcher mới được xem danh sách đơn hàng
        if (!$user->hasRole(['admin', 'dispatcher'])) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xem danh sách đơn hàng',
            ], 403);
        }

        // 🔹 Base query
        $query = Order::with(['driver', 'city'])->latest();

        // 🔹 Nếu là dispatcher → chỉ xem đơn trong khu vực của họ
        if ($user->hasRole('dispatcher')) {
            if (!$user->city_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản tổng đài chưa được gán khu vực',
                ], 403);
            }

            $query->where('city_id', $user->city_id);
        }

        // 🔹 Admin xem tất cả
        $orders = $query->paginate(20);

        return response()->json([
            'success' => true,
            'message' => $user->hasRole('dispatcher')
                ? 'Danh sách đơn hàng trong khu vực của bạn'
                : 'Danh sách tất cả đơn hàng',
            'data' => $orders,
        ]);
    }

    /**
     * Lấy danh sách đơn của tài xế đang đăng nhập
     */
    public function myOrders()
    {
        $user = Auth::user();

        try {
            // 🔹 Lấy tất cả các đơn pending trong khu vực của tài xế (bỏ qua scheduled)
            $pending = collect([]);
            if ($user->city_id && $user->isInShift()) {
                $pending = Order::with('city')
                    ->where('city_id', $user->city_id)
                    ->where('status', 'pending')   // scheduled bị ẩn hoàn toàn
                    ->orderByDesc('id')
                    ->get();
            }

            // 🔹 Đơn đang giao, đã nhận, hoặc chờ tổng đài duyệt
            $assigned = Order::with('city')
                ->where('delivery_man_id', $user->id)
                ->whereIn('status', ['assigned', 'delivering', 'delivered_pending'])
                ->orderByDesc('id')
                ->get();

            // 🔹 Đơn hoàn thành (Phân trang 20 đơn mỗi lần gọi)
            $completed = Order::with('city')
                ->where('delivery_man_id', $user->id)
                ->where('status', 'completed')
                ->orderByDesc('id')
                ->paginate(20);

            return response()->json([
                'success'             => true,
                'message'             => 'Danh sách đơn hàng của bạn',
                'pending'             => $pending,
                'assigned'            => $assigned,
                'completed'           => $completed->items(),
                'completed_has_more'  => $completed->hasMorePages(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Lỗi myOrders: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách đơn: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Dashboard driver (thống kê)
     */
    public function dashboard()
    {
        $user = Auth::user();

        $total = Order::where('delivery_man_id', $user->id)->count();
        $completed = Order::where('delivery_man_id', $user->id)->where('status', 'completed')->count();
        $pending = Order::where('delivery_man_id', $user->id)->where('status', 'pending')->count();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard driver',
            'data' => [
                'total_orders' => $total,
                'completed_orders' => $completed,
                'pending_orders' => $pending,
            ],
        ]);
    }

    /**
     * Driver nhận đơn
     */
    public function accept(Order $order)
    {
        $user = Auth::user();

        // ⛔ Nếu đơn đã có người nhận hoặc không còn trạng thái pending
        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Đơn đã có người nhận hoặc không khả dụng',
            ], 409);
        }

        // ⛔ Nếu ngoài ca (đối với gói tuần)
        if (!$user->isInShift()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã ngoài giờ ca làm việc, không thể nhận đơn.',
            ], 403);
        }

        // ✅ Giới hạn số đơn đang chạy
        $activeOrders = Order::where('delivery_man_id', $user->id)
            ->where('status', 'assigned')
            ->count();

        if ($activeOrders >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ được nhận tối đa 3 đơn cùng lúc',
            ], 400);
        }

        // ✅ Kiểm tra bằng lái nếu đơn yêu cầu xe ô tô
        if ($order->service_type === 'car' && !$user->has_car_license) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn này yêu cầu tài xế có bằng lái ô tô. Vui lòng cập nhật thông tin để nhận đơn.',
            ], 403);
        }

        // ✅ Nhận đơn
        // $order->update([
        //     'status'          => 'assigned',
        //     'delivery_man_id' => $user->id,
        // ]);

        // Cập nhật atomically: chỉ update nếu vẫn pending
        $affected = DB::table('orders')
            ->where('id', $order->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'assigned',
                'delivery_man_id' => $user->id,
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            // Không có dòng nào được cập nhật => có người khác đã nhận trước
            return response()->json([
                'success' => false,
                'message' => 'Đơn đã có người nhận trước bạn.',
            ], 409);
        }

        // ✅ Phản hồi ngay cho tài xế, các tác vụ nặng chạy sau
        $orderId   = $order->id;
        $userId    = $user->id;
        $driverName = $user->name ?? 'Tài xế';

        dispatch(function () use ($orderId, $userId) {
            $freshOrder = \App\Models\Order::find($orderId);
            if (!$freshOrder) return;

            // Xóa khỏi pool đơn chờ trên Firebase RTDB
            \App\Services\FirebaseRTDBService::removeOrder($freshOrder);

            // Phát sự kiện cho admin dashboard cập nhật realtime
            \App\Services\FirebaseRTDBService::publishOrderEvent($freshOrder);

            Log::info("📡 Driver #{$userId} accepted order #{$orderId} — Firebase RTDB updated");
        })->afterResponse();

        return response()->json([
            'success' => true,
            'message' => 'Nhận đơn thành công',
            'order'   => $order->fresh(),
        ]);

    }

    /**
     * Driver hoàn thành đơn
     */
    public function complete(Order $order, Request $request)
    {
        $user = Auth::user();

        // 🛡️ Ép kiểu về int để tránh lỗi so sánh giữa String và Int khi đổi hosting
        $deliveryManId = (int)$order->delivery_man_id;
        $currentUserId = (int)$user->id;

        if ($deliveryManId !== $currentUserId) {
            Log::warning("🚨 Unauthorized complete attempt: Order #{$order->id} (Assigned: {$deliveryManId}) vs User #{$currentUserId}");
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền hoàn thành đơn này.',
            ], 403);
        }

        // Không cho hoàn thành khi đã hủy
        if ($order->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng đã bị hủy, không thể hoàn thành.',
            ], 400);
        }

        // Không cho hoàn thành lại (tránh trừ công nợ 2 lần)
        if ($order->status === 'completed') {
            return response()->json([
                'success' => true,
                'message' => 'Đơn này đã hoàn thành trước đó.',
                'data' => $order,
            ]);
        }

        /**
         * -------------------------
         * XỬ LÝ TRẠNG THÁI HOÀN THÀNH
         * -------------------------
         */
        $updateData = [];

        // 🚩 QUY TẮC DUYỆT (MODERATION):
        // Giá cước được giữ nguyên theo Admin đã chốt. Tài xế không được phép tự sửa.
        // Nếu là đơn freeship HOẶC đơn có giá phí là 0đ -> Chờ duyệt.
        $currentFee = $order->shipping_fee;

        if ($order->is_freeship || $currentFee == 0) {
            $updateData['status'] = 'delivered_pending';
            $updateData['delivered_at'] = now(); // Thời điểm tài xế bấm "Hoàn thành"
            $msg = 'Đã báo hoàn thành. Vui lòng đợi Tổng đài xác nhận và duyệt đơn.';
        } else {
            // Còn lại đơn đã có giá cước hợp lệ -> Hoàn thành ngay
            $updateData['status'] = 'completed';
            $updateData['completed_at'] = now();
            $msg = 'Hoàn thành đơn thành công';
        }

        $order->update($updateData);

        // Thông báo tự động qua OrderObserver
        Log::info("✅ Order #{$order->id} status changed to {$updateData['status']} by driver #{$user->id}");

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data' => $order->fresh(),
        ]);
    }


    /**
     * Tạo đơn hàng mới (API)
     */
    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_type' => 'required|in:delivery,shopping,topup,bike,motor,car',
            'order_note' => 'required|string|max:1000',
            'shipping_fee' => 'required|numeric|min:0',
            'bonus_fee' => 'nullable|numeric|min:0',
            'is_freeship' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();

            if (!$user || !$user->city_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản chưa được gán khu vực (city_id)',
                ], 403);
            }

            // 🔹 Sinh mã đơn
            $code = 'FS' . now()->format('ymdHis') . strtoupper(Str::random(3));

            // 🔹 Tạo đơn hàng trong DB
            $order = Order::create([
                'code' => $code,
                'service_type' => $request->service_type,
                'order_note' => $request->order_note,
                'city_id' => $user->city_id,
                'shipping_fee' => $request->shipping_fee,
                'bonus_fee' => $request->bonus_fee ?? 0,
                'is_freeship' => $request->boolean('is_freeship'),
                'status' => 'pending',
            ]);

            // 🚀 ĐẨY ĐƠN LÊN FIREBASE REALTIME DATABASE 🚀
            \App\Services\FirebaseRTDBService::publishOrder($order);
            Log::info("📡 New order #{$order->id} created — Firebase RTDB published");

            return response()->json([
                'success' => true,
                'message' => 'Tạo đơn hàng thành công',
                'data' => $order,
            ]);
        } catch (\Throwable $e) {
            Log::error("❌ Lỗi tạo đơn hàng: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo đơn hàng',
            ], 500);
        }
    }

    /**
     * Lịch sử đơn hoàn thành của tài xế (có phân trang)
     */
    public function completedOrders(Request $request)
    {
        $user = Auth::user();
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;

        $paginator = Order::with('city')
            ->where('delivery_man_id', $user->id)
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success'      => true,
            'completed'    => $paginator->items(),
            'has_more'     => $paginator->hasMorePages(),
            'current_page' => $paginator->currentPage(),
            'total'        => $paginator->total(),
        ]);
    }

}
