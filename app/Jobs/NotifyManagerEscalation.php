<?php

namespace App\Jobs;

use App\Models\AiEscalation;
use App\Models\User;
use App\Models\ZaloAccount;
use App\Services\ZaloService;
use App\Helpers\FcmHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyManagerEscalation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(
        public readonly int $escalationId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $escalation = AiEscalation::find($this->escalationId);
        if (!$escalation) return;

        $urgencyEmoji = match ($escalation->urgency) {
            'high'   => '🔴',
            'medium' => '🟡',
            'low'    => '🟢',
            default  => '⚠️',
        };

        // Lấy tất cả manager trong hệ thống
        $managers = User::admins()->get();

        if ($managers->isEmpty()) {
            Log::warning("Escalation #{$escalation->id}: Không có manager nào trong hệ thống.");
            return;
        }

        $notifiedCount = 0;

        foreach ($managers as $manager) {
            // 1. Gửi Zalo cá nhân (ưu tiên hàng đầu - nhanh nhất)
            if ($manager->zalo_id) {
                $this->sendZaloToManager($manager->zalo_id, $escalation, $urgencyEmoji);
                $notifiedCount++;
            }

            // 2. Gửi FCM push notification (backup)
            if ($manager->fcm_token) {
                \App\Services\NotificationService::notifyEscalation($escalation, $manager, $urgencyEmoji);
            }
        }

        Log::info("Escalation #{$escalation->id}: Đã thông báo {$notifiedCount}/{$managers->count()} manager(s) qua Zalo. Urgency: {$escalation->urgency}");
    }

    /**
     * Gửi tin Zalo cá nhân đến quản lý
     * Dùng OA của city liên quan (hoặc OA đầu tiên active)
     */
    protected function sendZaloToManager(string $managerZaloId, AiEscalation $escalation, string $urgencyEmoji): void
    {
        // Lấy ZaloAccount của khu vực tương ứng
        $zaloAccount = ZaloAccount::where('is_active', true)->first();
        if (!$zaloAccount) return;

        $message = $this->buildManagerZaloMessage($escalation, $urgencyEmoji);

        try {
            $zaloService = new ZaloService($zaloAccount);
            $zaloService->sendTextMessage($managerZaloId, $message);
            Log::info("Escalation #{$escalation->id}: Đã gửi Zalo cho manager {$managerZaloId}");
        } catch (\Exception $e) {
            Log::error("Escalation #{$escalation->id}: Lỗi gửi Zalo manager: " . $e->getMessage());
        }
    }


    /**
     * Xây dựng nội dung tin Zalo gửi cho quản lý
     */
    protected function buildManagerZaloMessage(AiEscalation $escalation, string $urgencyEmoji): string
    {
        // Lấy thông tin khách hàng từ nguồn liên quan
        $customerInfo  = $this->resolveCustomerInfo($escalation);
        $customerLabel = $customerInfo['label'];

        $lines = [
            "{$urgencyEmoji} **ESCALATION #{$escalation->id}**",
            "🕐 " . now()->format('H:i d/m/Y'),
            "",
            "📋 **Vấn đề:** {$escalation->reason}",
            "👤 **Khách:** {$customerLabel}",
        ];

        // Thông tin liên quan (Đơn hàng / Shop)
        if ($escalation->source_type && $escalation->source_id) {
            if (str_contains($escalation->source_type, 'Order') && $customerInfo['order']) {
                $order = $customerInfo['order'];
                $lines[] = "📦 **Đơn hàng:** #{$order->id} — {$order->pickup_address}";
                $lines[] = "📍 **Giao:** {$order->delivery_address}";
            } elseif (str_contains($escalation->source_type, 'Shop') && $customerInfo['shop']) {
                $shop = $customerInfo['shop'];
                $lines[] = "🏪 **Shop:** {$shop->name} — {$shop->phone}";
            }
        }

        $lines[] = "";
        $lines[] = "📝 **Tóm tắt hội thoại:**";
        $lines[] = $escalation->conversation_summary;
        $lines[] = "";
        $lines[] = "👉 Vui lòng liên hệ lại khách để hỗ trợ kịp thời!";

        return implode("\n", $lines);
    }

    /**
     * Lấy thông tin khách từ Order hoặc Shop liên quan
     * Trả về: label hiển thị + order/shop object
     */
    protected function resolveCustomerInfo(AiEscalation $escalation): array
    {
        $result = ['label' => $escalation->sender_id . ' (Zalo)', 'order' => null, 'shop' => null];

        if (!$escalation->source_type || !$escalation->source_id) {
            return $result;
        }

        if (str_contains($escalation->source_type, 'Order')) {
            $order = \App\Models\Order::find($escalation->source_id);
            if ($order) {
                $result['order'] = $order;
                // Lấy SĐT người gửi + tên nếu có
                $phone = $order->pickup_phone ?: $order->delivery_phone;
                $name  = $order->sender_name  ?: $order->receiver_name;
                if ($phone) {
                    $result['label'] = $phone . ($name ? " ({$name})" : '');
                }
            }
        } elseif (str_contains($escalation->source_type, 'Shop')) {
            $shop = \App\Models\Shop::find($escalation->source_id);
            if ($shop) {
                $result['shop']  = $shop;
                $result['label'] = "{$shop->name} — {$shop->phone}";
            }
        }

        return $result;
    }
}
