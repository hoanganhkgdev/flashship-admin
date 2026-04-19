<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\AiConversation;
use App\Models\AiEscalation;
use Illuminate\Support\Facades\Log;

class ChatInteractionService
{
    protected AiOrderService $aiService;

    // Từ khóa khách chủ động muốn đặt hàng lại → thoát khỏi human handoff
    const RESUME_KEYWORDS = [
        'đặt hàng', 'đặt đơn', 'ship', 'giao hàng', 'mua hộ',
        'xe ôm', 'lái xe', 'nạp tiền', 'menu', 'dịch vụ',
        'order', 'book', 'dat hang', 'dat don',
    ];

    public function __construct(AiOrderService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Điểm vào chính — Xử lý tin nhắn từ các nền tảng (Zalo, Facebook...)
     */
    public function handleMessage(string $message, string $senderId, string $platform, ?int $cityId = null): array
    {
        // 1. Lệnh liên kết Shop (MS [ID] hoặc Link [ID])
        if (preg_match('/^(link|ms)\s*(\d+)$/i', $message, $matches)) {
            return $this->handleLinkShop($matches[2], $senderId, $platform);
        }

        // 2. Kiểm tra Human Handoff — có ANY escalation đang mở không?
        $openEscalationCount = AiEscalation::where('sender_id', $senderId)
            ->where('platform', $platform)
            ->where('status', 'open')
            ->count();

        if ($openEscalationCount > 0) {
            return $this->handleHumanHandoff($message, $senderId, $platform, $cityId);
        }

        // 3. Xác định Shop dựa trên ID nền tảng
        $shop = Shop::where("{$platform}_id", $senderId)->first();

        // 4. Đẩy vào AI Agent — AI tự gọi Tool và trả kết quả
        try {
            return $this->aiService->parseWithContext($message, $senderId, $shop, $cityId);
        } catch (\Exception $e) {
            Log::error("ChatInteractionService Error: " . $e->getMessage());
            return ['type' => 'text', 'content' => 'Dạ, hệ thống đang gặp sự cố nhỏ. Anh/chị thử lại sau ít giây nhé!'];
        }
    }

    /**
     * Xử lý khi đang trong chế độ Human Handoff.
     * - "reset" → đóng TẤT CẢ escalation + xóa session
     * - Resume keywords → đóng TẤT CẢ escalation → giao lại cho AI
     * - Tin thường → AI im lặng hoàn toàn
     */
    protected function handleHumanHandoff(string $message, string $senderId, string $platform, ?int $cityId): array
    {
        $lowerMsg = mb_strtolower(trim($message));

        // 1. "reset" → đóng escalation + xóa session hoàn toàn
        if ($lowerMsg === 'reset') {
            AiEscalation::where('sender_id', $senderId)
                ->where('platform', $platform)
                ->where('status', 'open')
                ->update(['status' => 'resolved', 'resolution_note' => 'Khách reset session.', 'resolved_at' => now()]);

            \App\Models\AiConversation::where('sender_id', $senderId)->delete();

            Log::info("Human Handoff: Khách [{$senderId}] gõ reset → Đóng escalation + xóa session.");
            return ['type' => 'text', 'content' => 'Dạ, em đã xóa thông tin cũ và kết thúc hỗ trợ trước. Anh/chị cần gì nhắn em nhé! 😊'];
        }

        // 2. Resume keywords → đóng escalation, giao lại AI
        $wantsService = false;
        foreach (self::RESUME_KEYWORDS as $keyword) {
            if (str_contains($lowerMsg, $keyword)) {
                $wantsService = true;
                break;
            }
        }

        if ($wantsService) {
            $closed = AiEscalation::where('sender_id', $senderId)
                ->where('platform', $platform)
                ->where('status', 'open')
                ->update([
                    'status'          => 'resolved',
                    'resolution_note' => 'Khách tự đặt đơn mới — AI tự động tiếp quản.',
                    'resolved_at'     => now(),
                ]);

            Log::info("Human Handoff: Khách [{$senderId}] resume AI → Đóng {$closed} escalation(s).");

            $shop = Shop::where("{$platform}_id", $senderId)->first();
            try {
                return $this->aiService->parseWithContext($message, $senderId, $shop, $cityId);
            } catch (\Exception $e) {
                Log::error("ChatInteractionService Resume Error: " . $e->getMessage());
                return ['type' => 'text', 'content' => 'Dạ, hệ thống đang gặp sự cố nhỏ. Anh/chị thử lại sau ít giây nhé!'];
            }
        }

        // 3. Tin thường → AI im lặng
        Log::info("Human Handoff: AI im lặng cho sender [{$senderId}].");
        return ['type' => 'handoff_silence', 'content' => ''];
    }

    /**
     * Liên kết Shop với nền tảng
     */
    protected function handleLinkShop(string $shopId, string $senderId, string $platform): array
    {
        $shop = \App\Models\Shop::find($shopId);
        if ($shop) {
            $shop->update(["{$platform}_id" => $senderId]);
            return ['type' => 'text', 'content' => "✅ Đã liên kết thành công Shop: **{$shop->name}**. Từ giờ bạn có thể lên đơn hàng trực tiếp qua đây!"];
        }
        return ['type' => 'text', 'content' => "❌ Không tìm thấy Shop có mã ID là {$shopId}. Vui lòng kiểm tra lại."];
    }
}
