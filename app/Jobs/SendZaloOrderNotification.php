<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ZaloAccount;
use App\Services\ZaloService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendZaloOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(
        public readonly int $orderId,
        public readonly string $event, // 'created' | 'assigned' | 'delivering' | 'completed' | 'cancelled'
    ) {}

    public function handle(): void
    {
        $order = Order::with(['shop', 'driver', 'city'])->find($this->orderId);
        if (!$order) return;

        // Xác định người nhận thông báo (Shop hoặc Khách lẻ) và platform
        [$recipientZaloId, $zaloAccount] = $this->resolveRecipient($order);

        if (!$recipientZaloId || !$zaloAccount) {
            Log::info("ZaloNotify: Bỏ qua đơn #{$order->id} - không có Zalo ID hoặc OA account.");
            return;
        }

        $message = $this->buildMessage($order);
        if (!$message) return;

        $zaloService = new ZaloService($zaloAccount);
        $sent = $zaloService->sendTextMessage($recipientZaloId, $message);

        if ($sent) {
            Log::info("ZaloNotify ✅ [{$this->event}]: Đã gửi thông báo đơn #{$order->id} → Zalo {$recipientZaloId}");
        } else {
            Log::warning("ZaloNotify ❌ [{$this->event}]: Gửi thất bại đơn #{$order->id}");
        }
    }

    /**
     * Xác định Zalo ID của người nhận thông báo
     * - Nếu là đơn Shop → lấy zalo_id của Shop
     * - Nếu là đơn khách lẻ qua Zalo → lấy sender_platform_id
     */
    protected function resolveRecipient(Order $order): array
    {
        // Lấy ZaloAccount theo city_id của đơn
        $zaloAccount = ZaloAccount::where('city_id', $order->city_id)
            ->where('is_active', true)
            ->first();

        if (!$zaloAccount) return [null, null];

        // Ưu tiên Shop
        if ($order->shop_id && $order->shop && $order->shop->zalo_id) {
            return [$order->shop->zalo_id, $zaloAccount];
        }

        // Khách lẻ qua Zalo
        if ($order->platform === 'zalo' && $order->sender_platform_id) {
            return [$order->sender_platform_id, $zaloAccount];
        }

        return [null, null];
    }

    /**
     * Xây dựng nội dung tin nhắn theo từng sự kiện
     */
    protected function buildMessage(Order $order): ?string
    {
        $orderId = $order->id;
        $driver = $order->driver;

        return match ($this->event) {
            'created' => $this->msgCreated($order),
            'assigned' => $driver
                ? "🚀 Đơn hàng #{$orderId} đã có tài xế nhận!\n👤 Tài xế: **{$driver->name}**\n📞 SĐT: {$driver->phone}\nTài xế đang trên đường đến lấy hàng nhé!"
                : null,
            'delivering' => "📦 Tài xế đang giao đơn hàng #{$orderId} đến địa chỉ của người nhận. Anh/chị thông báo người nhận chuẩn bị nhé!",
            'completed'  => "✅ Đơn hàng #{$orderId} đã giao thành công!\nCảm ơn bạn đã tin dùng Flashship. Cần ship thêm cứ nhắn em nhé! 🚀",
            'cancelled'  => "❌ Đơn hàng #{$orderId} đã bị hủy.\nAnh/chị cần hỗ trợ thêm cứ nhắn em nhé!",
            default => null,
        };
    }

    protected function msgCreated(Order $order): string
    {
        $deliveryAddr = mb_strtolower($order->delivery_address ?? '');
        $isPending    = empty($deliveryAddr) || str_contains($deliveryAddr, 'sẽ cung cấp sau');
        $feeText      = ($order->shipping_fee > 0 && !$isPending)
                        ? number_format($order->shipping_fee) . 'đ'
                        : 'Đang tính...';

        $lines = ["✅ Đơn #{$order->id} đã được tạo thành công!"];

        // Điểm lấy
        $pickup = $order->pickup_address ?? '';
        if ($pickup && !str_contains(mb_strtolower($pickup), 'sẽ cung cấp sau')) {
            $lines[] = "🏁 Lấy: {$pickup}";
            if ($order->pickup_phone) {
                $lines[] = "☎️ {$order->pickup_phone}";
            }
        }

        // Điểm giao
        if ($isPending) {
            $lines[] = "📍 Giao: Sẽ cung cấp sau";
        } else {
            $lines[] = "📍 Giao: {$order->delivery_address}";
            if ($order->delivery_phone) {
                $lines[] = "☎️ {$order->delivery_phone}";
            }
        }

        // Phí
        $lines[] = "💰 Phí ship: {$feeText}";
        $lines[] = "⏳ Đang tìm tài xế, bạn đợi em chút nhé!";

        return implode("\n", $lines);
    }
}

