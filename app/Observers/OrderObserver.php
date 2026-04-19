<?php

namespace App\Observers;

use App\Models\Order;
use App\Jobs\SendZaloOrderNotification;
use App\Services\FirebaseRTDBService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Ghi lịch sử ngay (nhẹ, chỉ là DB write)
        $order->histories()->create([
            'user_id'     => auth()->id(),
            'type'        => 'created',
            'description' => $order->is_ai_created
                ? 'Đơn hàng được tạo tự động bởi AI'
                : 'Đơn hàng được tạo thủ công bởi quản trị viên',
        ]);

        // 🚀 Tất cả tác vụ nặng (Firebase, FCM) chạy SAU KHI response đã gửi
        $orderId     = $order->id;
        $isAiCreated = $order->is_ai_created;
        $platformId  = $order->sender_platform_id;
        $shopId      = $order->shop_id;

        dispatch(function () use ($orderId, $isAiCreated, $platformId, $shopId) {
            $order = \App\Models\Order::find($orderId);
            if (!$order) return;

            // 📡 Đẩy đơn lên Firebase RTDB và thông báo FCM cho tài xế
            if ($order->status === 'pending') {
                FirebaseRTDBService::publishOrder($order);
                $drivers = \App\Models\User::drivers()
                    ->where('city_id', $order->city_id)
                    ->where('status', 1)
                    ->where('is_online', true)
                    ->whereNotNull('fcm_token')
                    ->get()
                    ->filter(fn($u) => $u->isInShift() ?: ($u->update(['is_online' => false]) && false));
                if ($drivers->isNotEmpty()) NotificationService::notifyNewOrder($order, $drivers);
            }

            if ($isAiCreated && ($platformId || $shopId)) {
                SendZaloOrderNotification::dispatch($orderId, 'created')->delay(now()->addSeconds(2));
            }
        })->afterResponse();
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        $orderId       = $order->id;
        $newStatus     = $order->status;
        $oldStatus     = $order->getOriginal('status');
        $cityId        = $order->city_id;
        $driverId      = $order->delivery_man_id;
        $shippingFee   = $order->shipping_fee;
        $bonusFee      = $order->bonus_fee;
        $isFreeship    = $order->is_freeship;
        $platformId    = $order->sender_platform_id;
        $shopId        = $order->shop_id;
        $userId        = auth()->id();
        $changedStatus = $order->wasChanged('status');
        $changedFields = $order->wasChanged(['delivery_address', 'shipping_fee', 'order_note', 'pickup_address', 'bonus_fee', 'is_freeship']);
        $changedDriver = $order->wasChanged('delivery_man_id');

        // Ghi lịch sử ngay (nhẹ, chỉ là DB write)
        if ($changedStatus) {
            $statusLabels = [
                'draft'             => 'Chờ soát',
                'pending'           => 'Chờ xử lý',
                'assigned'          => 'Đã nhận',
                'delivering'        => 'Đang giao',
                'completed'         => 'Hoàn tất',
                'cancelled'         => 'Đã hủy',
                'delivered_pending' => 'Chờ duyệt',
            ];
            $label = $statusLabels[$newStatus] ?? $newStatus;
            $order->histories()->create([
                'user_id'     => $userId,
                'type'        => 'status_change',
                'description' => "Trạng thái đơn đổi thành: {$label}",
                'metadata'    => ['old' => $oldStatus, 'new' => $newStatus],
            ]);
        }

        if ($changedDriver) {
            $driverName = $order->driver?->name ?? '(không rõ)';
            $order->histories()->create([
                'user_id'     => $userId,
                'type'        => 'assign_driver',
                'description' => $order->driver ? "Đã gán tài xế: {$driverName}" : "Đã gỡ tài xế",
                'metadata'    => ['driver_id' => $driverId],
            ]);
        }

        // 🚀 Tất cả tác vụ nặng (Firebase, FCM, Zalo) chạy SAU KHI response đã gửi
        dispatch(function () use (
            $orderId, $newStatus, $oldStatus, $cityId, $driverId,
            $shippingFee, $bonusFee, $isFreeship,
            $platformId, $shopId, $changedStatus, $changedFields
        ) {
            $order = \App\Models\Order::find($orderId);
            if (!$order) return;

            if ($changedStatus) {
                // 🔔 Thông báo admin/dispatcher khi đơn freeship chờ duyệt
                if ($newStatus === 'delivered_pending') {
                    $adminQuery = \App\Models\User::role(['admin', 'dispatcher', 'manager'])
                        ->whereNotNull('fcm_token');
                    // dispatcher chỉ thấy vùng của họ; admin thấy tất cả
                    if ($cityId) {
                        $adminQuery->where(function ($q) use ($cityId) {
                            $q->where('city_id', $cityId)->orWhereHas('roles', fn($r) => $r->where('name', 'admin'));
                        });
                    }
                    $admins = $adminQuery->get();
                    if ($admins->isNotEmpty()) {
                        \App\Helpers\FcmHelper::sendToUsers(
                            $admins,
                            "📋 Đơn #{$orderId} chờ xác nhận",
                            "Tài xế đã báo hoàn thành. Vui lòng kiểm tra và duyệt đơn.",
                            [
                                'type'     => 'delivered_pending',
                                'order_id' => (string) $orderId,
                                'city_id'  => (string) $cityId,
                            ],
                            "delivered_pending_{$orderId}"
                        );
                    }
                    Log::info("🔔 [OrderObserver] FCM sent to " . $admins->count() . " admin(s) for delivered_pending order #{$orderId}");
                }

                // Firebase RTDB
                if ($newStatus === 'pending' && $oldStatus !== 'pending') {
                    FirebaseRTDBService::publishOrder($order);
                    $drivers = \App\Models\User::drivers()
                        ->where('city_id', $cityId)->where('status', 1)
                        ->where('is_online', true)->whereNotNull('fcm_token')
                        ->get()->filter(fn($u) => $u->isInShift() ?: ($u->update(['is_online' => false]) && false));
                    if ($drivers->isNotEmpty()) NotificationService::notifyNewOrder($order, $drivers);
                }

                if (in_array($newStatus, ['assigned', 'delivering'])) {
                    FirebaseRTDBService::publishOrder($order);
                }

                if (in_array($newStatus, ['completed', 'cancelled'])) {
                    FirebaseRTDBService::removeOrder($order);
                }

                // Thông báo cho tài xế khi đơn delivered_pending được duyệt
                if ($newStatus === 'completed' && $oldStatus === 'delivered_pending' && $driverId) {
                    $driver = \App\Models\User::find($driverId);
                    if ($driver) {
                        \App\Helpers\FcmHelper::sendToUsers(
                            [$driver],
                            "✅ Đơn #{$orderId} đã được xác nhận",
                            "Tổng đài đã duyệt hoàn thành đơn của bạn.",
                            ['type' => 'order_completed', 'order_id' => (string) $orderId],
                            "order_completed_{$orderId}"
                        );
                        $driver->notify(new \App\Notifications\DriverAppNotification(
                            "✅ Đơn #{$orderId} đã được xác nhận",
                            "Tổng đài đã duyệt hoàn thành đơn của bạn.",
                            'success'
                        ));
                    }
                }

                // Điều chỉnh ví tài xế khi hoàn thành
                if ($newStatus === 'completed' && $driverId) {
                    if ($isFreeship && $shippingFee > 0) {
                        \App\Services\DriverWalletService::adjust(
                            $driverId, $shippingFee, 'credit',
                            'Ship Freeship #' . $orderId,
                            'order_' . $orderId . '_shipping'
                        );
                    }
                    if ($bonusFee > 0) {
                        \App\Services\DriverWalletService::adjust(
                            $driverId, $bonusFee, 'credit',
                            'Bonus #' . $orderId,
                            'order_' . $orderId . '_bonus'
                        );
                    }
                }

                // Zalo notification
                $notifyEvents = [
                    'assigned'   => 'assigned',
                    'delivering' => 'delivering',
                    'completed'  => 'completed',
                    'cancelled'  => 'cancelled',
                ];
                if (isset($notifyEvents[$newStatus]) && ($platformId || $shopId)) {
                    SendZaloOrderNotification::dispatch($orderId, $notifyEvents[$newStatus])
                        ->delay(now()->addSeconds(1));
                }
            }

            // Cập nhật realtime khi sửa thông tin đơn (phí, địa chỉ...)
            if (!$changedStatus && $changedFields) {
                if (in_array($newStatus, ['pending', 'assigned', 'delivering'])) {
                    FirebaseRTDBService::publishOrder($order);
                }
                if ($newStatus === 'pending') {
                    $drivers = \App\Models\User::drivers()
                        ->where('city_id', $cityId)->where('status', 1)
                        ->where('is_online', true)->whereNotNull('fcm_token')
                        ->get()->filter(fn($u) => $u->isInShift() ?: ($u->update(['is_online' => false]) && false));
                    if ($drivers->isNotEmpty()) NotificationService::notifyOrderUpdated($order, $drivers);
                } elseif (in_array($newStatus, ['assigned', 'delivering']) && $order->driver) {
                    NotificationService::notifyOrderUpdated($order, [$order->driver]);
                }
            }
        })->afterResponse();
    }

    public function deleted(Order $order): void
    {
        $orderId = $order->id;
        dispatch(function () use ($orderId) {
            $order = \App\Models\Order::withTrashed()->find($orderId);
            if ($order) FirebaseRTDBService::removeOrder($order);
        })->afterResponse();
    }
}
