<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    public function getDriverOrders(User $user): array
    {
        $pending = collect([]);
        if ($user->city_id && $user->isInShift()) {
            $pending = Order::with('city')
                ->where('city_id', $user->city_id)
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->get();
        }

        $assigned = Order::with('city')
            ->where('delivery_man_id', $user->id)
            ->whereIn('status', ['assigned', 'delivering', 'delivered_pending'])
            ->orderByDesc('id')
            ->get();

        $completed = Order::with('city')
            ->where('delivery_man_id', $user->id)
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->paginate(20);

        return [
            'pending'            => $pending,
            'assigned'           => $assigned,
            'completed'          => $completed->items(),
            'completed_has_more' => $completed->hasMorePages(),
        ];
    }

    public function getDashboardStats(User $user): array
    {
        $row = Order::where('delivery_man_id', $user->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(status = 'completed') as completed,
                SUM(status = 'pending')   as pending
            ")
            ->first();

        return [
            'total_orders'     => (int) ($row->total ?? 0),
            'completed_orders' => (int) ($row->completed ?? 0),
            'pending_orders'   => (int) ($row->pending ?? 0),
        ];
    }

    public function acceptOrder(Order $order, User $user): array
    {
        if ($order->status !== 'pending') {
            return ['success' => false, 'message' => 'Đơn đã có người nhận hoặc không khả dụng', 'status' => 409];
        }

        if (!$user->isInShift()) {
            return ['success' => false, 'message' => 'Bạn đã ngoài giờ ca làm việc, không thể nhận đơn.', 'status' => 403];
        }

        $activeOrders = Order::where('delivery_man_id', $user->id)->where('status', 'assigned')->count();
        if ($activeOrders >= 3) {
            return ['success' => false, 'message' => 'Bạn chỉ được nhận tối đa 3 đơn cùng lúc', 'status' => 400];
        }

        if ($order->service_type === 'car' && !$user->has_car_license) {
            return ['success' => false, 'message' => 'Đơn này yêu cầu tài xế có bằng lái ô tô. Vui lòng cập nhật thông tin để nhận đơn.', 'status' => 403];
        }

        $affected = DB::table('orders')
            ->where('id', $order->id)
            ->where('status', 'pending')
            ->update([
                'status'          => 'assigned',
                'delivery_man_id' => $user->id,
                'updated_at'      => now(),
            ]);

        if ($affected === 0) {
            return ['success' => false, 'message' => 'Đơn đã có người nhận trước bạn.', 'status' => 409];
        }

        $orderId = $order->id;
        $userId  = $user->id;

        dispatch(function () use ($orderId, $userId) {
            $freshOrder = Order::find($orderId);
            if (!$freshOrder) return;

            // DB::table() bypasses Eloquent Observer — publish Firebase manually
            FirebaseRTDBService::publishOrder($freshOrder);

            Log::info("✅ Driver #{$userId} accepted order #{$orderId}");
        })->afterResponse();

        return ['success' => true, 'order' => $order->fresh(), 'status' => 200];
    }

    public function completeOrder(Order $order, User $user): array
    {
        if ((int) $order->delivery_man_id !== (int) $user->id) {
            Log::warning("🚨 Unauthorized complete attempt: Order #{$order->id} (Assigned: {$order->delivery_man_id}) vs User #{$user->id}");
            return ['success' => false, 'message' => 'Bạn không có quyền hoàn thành đơn này.', 'status' => 403];
        }

        if ($order->status === 'cancelled') {
            return ['success' => false, 'message' => 'Đơn hàng đã bị hủy, không thể hoàn thành.', 'status' => 400];
        }

        if ($order->status === 'completed') {
            return ['success' => true, 'message' => 'Đơn này đã hoàn thành trước đó.', 'data' => $order, 'status' => 200];
        }

        $updateData = [];

        // Freeship hoặc phí = 0 → chờ tổng đài duyệt
        if ($order->is_freeship || $order->shipping_fee == 0) {
            $updateData['status']       = 'delivered_pending';
            $updateData['delivered_at'] = now();
            $msg = 'Đã báo hoàn thành. Vui lòng đợi Tổng đài xác nhận và duyệt đơn.';
        } else {
            $updateData['status']       = 'completed';
            $updateData['completed_at'] = now();
            $msg = 'Hoàn thành đơn thành công';
        }

        $order->update($updateData);

        // Xóa cache stats để lần gọi tiếp theo lấy dữ liệu mới
        Cache::forget("driver_stats_{$user->id}");

        Log::info("✅ Order #{$order->id} status changed to {$updateData['status']} by driver #{$user->id}");

        return ['success' => true, 'message' => $msg, 'data' => $order->fresh(), 'status' => 200];
    }

    public function createOrder(array $data, User $user): Order
    {
        $code = 'FS' . now()->format('ymdHis') . strtoupper(Str::random(3));

        return Order::create([
            'code'         => $code,
            'service_type' => $data['service_type'],
            'order_note'   => $data['order_note'],
            'city_id'      => $user->city_id,
            'shipping_fee' => $data['shipping_fee'],
            'bonus_fee'    => $data['bonus_fee'] ?? 0,
            'is_freeship'  => $data['is_freeship'] ?? false,
            'status'       => 'pending',
        ]);
        // OrderObserver::created() → dispatchNewOrder() runs after response
    }

    // -------------------------------------------------------------------------
    // Post-save side effects (called from OrderObserver afterResponse closures)
    // -------------------------------------------------------------------------

    /**
     * Đẩy đơn mới lên Firebase RTDB và gửi FCM cho tài xế phù hợp.
     * Được gọi từ OrderObserver::created() sau khi response đã gửi.
     */
    public function dispatchNewOrder(int $orderId): void
    {
        $order = Order::find($orderId);
        if (!$order) return;

        if ($order->status === 'pending') {
            FirebaseRTDBService::publishOrder($order);
            $drivers = $this->getOnlineDriversInCity($order->city_id);
            if ($drivers->isNotEmpty()) {
                NotificationService::notifyNewOrder($order, $drivers);
            }
        } elseif ($order->status === 'assigned' && $order->delivery_man_id) {
            // Admin tạo đơn và gán tài xế ngay
            FirebaseRTDBService::publishOrder($order);
            $driver = User::find($order->delivery_man_id);
            if ($driver) NotificationService::notifyOrderAssigned($order, $driver);
        }
    }

    /**
     * Xử lý các side effect sau khi đơn được cập nhật (Firebase RTDB, FCM, ví tài xế).
     * Được gọi từ OrderObserver::updated() sau khi response đã gửi.
     *
     * @param string $oldStatus  Trạng thái đơn trước khi update (bắt buộc capture trước afterResponse)
     * @param bool   $changedStatus  Trạng thái có thay đổi không
     * @param bool   $changedFields  Các field quan trọng khác có thay đổi không
     */
    public function dispatchOrderUpdate(int $orderId, string $oldStatus, bool $changedStatus, bool $changedFields): void
    {
        $order = Order::find($orderId);
        if (!$order) return;

        if ($changedStatus) {
            $this->handleStatusChange($order, $oldStatus);
        }

        if (!$changedStatus && $changedFields) {
            $this->handleFieldChange($order);
        }
    }

    /**
     * Trả về tài xế đang online, đúng ca, trong thành phố.
     * Tự động set is_online = false cho tài xế hết ca.
     */
    public function getOnlineDriversInCity(?int $cityId): Collection
    {
        if (!$cityId) return collect();

        return User::drivers()
            ->where('city_id', $cityId)
            ->where('status', 1)
            ->where('is_online', true)
            ->whereNotNull('fcm_token')
            ->get()
            ->filter(function (User $driver) {
                if ($driver->isInShift()) return true;
                $driver->update(['is_online' => false]);
                return false;
            });
    }

    private function handleStatusChange(Order $order, string $oldStatus): void
    {
        $newStatus  = $order->status;
        $cityId     = $order->city_id;
        $driverId   = $order->delivery_man_id;

        if ($newStatus === 'delivered_pending') {
            NotificationService::notifyDeliveredPending($order);
        }

        if ($newStatus === 'pending' && $oldStatus !== 'pending') {
            FirebaseRTDBService::publishOrder($order);
            $drivers = $this->getOnlineDriversInCity($cityId);
            if ($drivers->isNotEmpty()) NotificationService::notifyNewOrder($order, $drivers);
        }

        if (in_array($newStatus, ['assigned', 'delivering'])) {
            FirebaseRTDBService::publishOrder($order);
        }

        if (in_array($newStatus, ['completed', 'cancelled', 'delivered_pending'])) {
            FirebaseRTDBService::removeOrder($order);
        }

        if ($newStatus === 'completed' && $oldStatus === 'delivered_pending' && $driverId) {
            $driver = User::find($driverId);
            if ($driver) NotificationService::notifyOrderApproved($order, $driver);
        }

        if ($newStatus === 'completed' && $driverId) {
            $shippingFee = (float) ($order->shipping_fee ?? 0);
            $bonusFee    = (float) ($order->bonus_fee ?? 0);

            if ($order->is_freeship && $shippingFee > 0) {
                DriverWalletService::adjust(
                    $driverId, $shippingFee, 'credit',
                    "Ship Freeship #{$order->id}",
                    "order_{$order->id}_shipping"
                );
            }
            if ($bonusFee > 0) {
                DriverWalletService::adjust(
                    $driverId, $bonusFee, 'credit',
                    "Bonus #{$order->id}",
                    "order_{$order->id}_bonus"
                );
            }
        }
    }

    private function handleFieldChange(Order $order): void
    {
        $status   = $order->status;
        $cityId   = $order->city_id;
        $driverId = $order->delivery_man_id;

        if (in_array($status, ['pending', 'assigned', 'delivering'])) {
            FirebaseRTDBService::publishOrder($order);
        }

        if ($status === 'pending') {
            $drivers = $this->getOnlineDriversInCity($cityId);
            if ($drivers->isNotEmpty()) NotificationService::notifyOrderUpdated($order, $drivers);
        } elseif (in_array($status, ['assigned', 'delivering']) && $driverId) {
            $driver = User::find($driverId);
            if ($driver) NotificationService::notifyOrderUpdated($order, [$driver]);
        }
    }

    public function getCompletedOrders(User $user, int $page = 1, int $perPage = 20): array
    {
        $paginator = Order::with('city')
            ->where('delivery_man_id', $user->id)
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'completed'    => $paginator->items(),
            'has_more'     => $paginator->hasMorePages(),
            'current_page' => $paginator->currentPage(),
            'total'        => $paginator->total(),
        ];
    }

    public function getWeeklyEarnings(int $driverId): array
    {
        $start = Carbon::now()->startOfWeek();
        return $this->buildDailyEarnings($driverId, $start, $start->copy()->endOfWeek(), 7);
    }

    public function getMonthlyEarnings(int $driverId): array
    {
        $start = Carbon::now()->startOfMonth();
        return $this->buildDailyEarnings($driverId, $start, $start->copy()->endOfMonth(), $start->daysInMonth);
    }

    private function buildDailyEarnings(int $driverId, Carbon $start, Carbon $end, int $days): array
    {
        $data = DB::table('orders')
            ->selectRaw('DATE(completed_at) as date, SUM(shipping_fee) as shipping, SUM(bonus_fee) as bonus')
            ->where('delivery_man_id', $driverId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $day      = $start->copy()->addDays($i)->toDateString();
            $row      = $data->get($day);
            $result[] = [
                'date'     => $day,
                'total'    => (float) (($row->shipping ?? 0) + ($row->bonus ?? 0)),
                'shipping' => (float) ($row->shipping ?? 0),
                'bonus'    => (float) ($row->bonus ?? 0),
            ];
        }

        return $result;
    }

    public function getRecentOrders(int $driverId): array
    {
        return Order::where('delivery_man_id', $driverId)
            ->orderByDesc('id')
            ->take(5)
            ->get(['id', 'status', 'shipping_fee', 'bonus_fee', 'created_at'])
            ->map(fn($o) => [
                'id'           => $o->id,
                'status'       => $o->status,
                'shipping_fee' => $o->shipping_fee,
                'bonus_fee'    => $o->bonus_fee,
                'created_at'   => $o->created_at->toDateTimeString(),
            ])
            ->toArray();
    }

    public function getKpi(User $driver): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek   = Carbon::now()->endOfWeek();

        $row = Order::where('delivery_man_id', $driver->id)
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->selectRaw("
                COUNT(*) as orders_done,
                SUM(CASE WHEN status = 'completed' THEN shipping_fee ELSE 0 END) as earnings_shipping,
                SUM(CASE WHEN status = 'completed' THEN bonus_fee    ELSE 0 END) as earnings_bonus
            ")
            ->first();

        $shipping = (float) ($row->earnings_shipping ?? 0);
        $bonus    = (float) ($row->earnings_bonus ?? 0);

        return [
            'orders_done'       => (int) ($row->orders_done ?? 0),
            'orders_target'     => 20,
            'earnings_done'     => $shipping + $bonus,
            'earnings_shipping' => $shipping,
            'earnings_bonus'    => $bonus,
            'earnings_target'   => 2000000,
        ];
    }
}
