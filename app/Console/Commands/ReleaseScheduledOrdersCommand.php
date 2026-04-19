<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class ReleaseScheduledOrdersCommand extends Command
{
    protected $signature   = 'orders:release-scheduled';
    protected $description = 'Chuyển đơn hẹn giờ (scheduled) sang pending khi đến giờ (30 phút trước scheduled_at)';

    public function handle(): void
    {
        // Tìm các đơn scheduled có scheduled_at <= now + 30 phút
        $orders = Order::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now()->addMinutes(30))
            ->get();

        if ($orders->isEmpty()) {
            Log::debug('ReleaseScheduledOrders: Không có đơn nào cần release.');
            return;
        }

        /** @var Order $order */
        foreach ($orders as $order) {
            $order->update(['status' => 'pending']);

            try {
                $fresh = $order->fresh();
                // ✅ Ghi vào RTDB (app mới)
                \App\Services\FirebaseRTDBService::publishOrder($fresh);
                Log::info("ReleaseScheduledOrders: ✅ Đơn #{$order->id} (hẹn {$order->scheduled_at}) → pending + RTDB.");
            } catch (\Throwable $e) {
                Log::error("ReleaseScheduledOrders: ❌ Đơn #{$order->id}: " . $e->getMessage());
            }
        }

        $this->info("Đã release {$orders->count()} đơn hẹn giờ → pending.");
        Log::info("ReleaseScheduledOrders: Release {$orders->count()} đơn.");
    }
}
