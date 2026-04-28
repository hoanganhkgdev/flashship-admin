<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\FirebaseRTDBService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(private OrderService $orderService) {}

    public function created(Order $order): void
    {
        $order->histories()->create([
            'user_id'     => auth()->id(),
            'type'        => 'created',
            'description' => 'Đơn hàng được tạo',
        ]);

        $orderId = $order->id;
        dispatch(fn() => $this->orderService->dispatchNewOrder($orderId))->afterResponse();
    }

    public function updated(Order $order): void
    {
        $changedStatus = $order->wasChanged('status');
        $changedDriver = $order->wasChanged('delivery_man_id');

        if ($changedStatus) {
            $newStatus = $order->status;
            $oldStatus = $order->getOriginal('status');
            $labels    = [
                'draft'             => 'Chờ soát',
                'pending'           => 'Chờ xử lý',
                'assigned'          => 'Đã nhận',
                'delivering'        => 'Đang giao',
                'completed'         => 'Hoàn tất',
                'cancelled'         => 'Đã hủy',
                'delivered_pending' => 'Chờ duyệt',
            ];
            $order->histories()->create([
                'user_id'     => auth()->id(),
                'type'        => 'status_change',
                'description' => 'Trạng thái đổi thành: ' . ($labels[$newStatus] ?? $newStatus),
                'metadata'    => ['old' => $oldStatus, 'new' => $newStatus],
            ]);
        }

        if ($changedDriver) {
            $driverName = $order->driver?->name ?? '(không rõ)';
            $order->histories()->create([
                'user_id'     => auth()->id(),
                'type'        => 'assign_driver',
                'description' => $order->driver ? "Đã gán tài xế: {$driverName}" : 'Đã gỡ tài xế',
                'metadata'    => ['driver_id' => $order->delivery_man_id],
            ]);
        }

        // Capture dirty-tracking booleans before afterResponse (model is refreshed inside)
        $oldStatus     = $order->getOriginal('status');
        $changedFields = $order->wasChanged([
            'delivery_address', 'shipping_fee', 'order_note',
            'pickup_address', 'bonus_fee', 'is_freeship',
        ]);
        $orderId = $order->id;

        dispatch(fn() => $this->orderService->dispatchOrderUpdate(
            $orderId, $oldStatus, $changedStatus, $changedFields
        ))->afterResponse();
    }

    public function deleted(Order $order): void
    {
        $orderId = $order->id;
        dispatch(function () use ($orderId) {
            $order = Order::withTrashed()->find($orderId);
            if ($order) FirebaseRTDBService::removeOrder($order);
        })->afterResponse();
    }
}
