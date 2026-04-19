<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiEscalation;
use Illuminate\Support\Facades\Log;

class ProcessZaloWebhook extends Command
{
    protected $signature   = 'zalo:process {payload}';
    protected $description = 'Xử lý tin nhắn Zalo OA bằng AI ở chế độ chạy ngầm';

    /**
     * Từ khóa quản lý nhắn từ OA admin panel để kích hoạt lại AI.
     * Chỉ cần nhắn 1 trong các từ này → AI tiếp nhận lại ngay.
     */
    const MANAGER_RESUME_KEYWORDS = [
        'ai on', 'ai bật', 'bật ai', 'resume',
        'chốt', 'xong rồi', 'done', 'ok ai',
        'cho ai xử lý', 'trả về ai', 'ai tiếp nhận',
    ];

    // =========================================================================
    // ENTRY POINT
    // =========================================================================

    public function handle()
    {
        $payload = json_decode($this->argument('payload'), true);

        if (!$payload) {
            Log::error('Zalo Background: Invalid payload');
            return;
        }

        $this->processPayloadDirect($payload);
    }

    /**
     * Xử lý payload trực tiếp — không cần Artisan/Queue.
     * Dùng bởi ZaloWebhookController (chế độ không queue, shared hosting).
     */
    public function processPayloadDirect(array $payload): void
    {
        if (!$payload) {
            Log::error('Zalo: Invalid payload');
            return;
        }

        $eventName   = $payload['event_name'] ?? '';
        $shorterOaId = $payload['oa_id'] ?? ($payload['recipient']['id'] ?? null);

        Log::info("Zalo: Xử lý Event: '$eventName' từ OA ID: '$shorterOaId'");

        if ($eventName === 'follow' || $eventName === 'user_follow_oa') {
            $this->handleFollowEvent($payload, $shorterOaId);
            return;
        }
        if ($eventName === 'user_send_text') {
            $this->handleTextMessage($payload, $shorterOaId);
            return;
        }
        if ($eventName === 'user_send_image') {
            $this->handleImageMessage($payload, $shorterOaId);
            return;
        }
        if ($eventName === 'user_send_audio') {
            $this->handleAudioMessage($payload, $shorterOaId);
            return;
        }
        if ($eventName === 'oa_send_text') {
            $this->handleManagerMessage($payload, $shorterOaId);
            return;
        }
    }

    // =========================================================================
    // HANDLER: Quản lý nhắn từ khóa → kích hoạt lại AI
    // =========================================================================

    protected function handleManagerMessage(array $payload, ?string $shorterOaId)

    {
        // Trong oa_send_text: sender = OA, recipient = khách
        $customerId = $payload['recipient']['id'] ?? null;
        $message    = mb_strtolower(trim($payload['message']['text'] ?? ''));

        if (!$customerId || !$message) return;

        // Kiểm tra từ khóa kích hoạt
        $isResumeCommand = false;
        foreach (self::MANAGER_RESUME_KEYWORDS as $keyword) {
            if (str_contains($message, $keyword)) {
                $isResumeCommand = true;
                break;
            }
        }

        if (!$isResumeCommand) return;

        // Tìm escalation đang open của khách này
        $escalation = AiEscalation::where('sender_id', $customerId)
            ->where('platform', 'zalo')
            ->where('status', 'open')
            ->latest()
            ->first();

        if (!$escalation) {
            Log::info("Manager Resume: Không có escalation open cho khách {$customerId}");
            return;
        }

        // Đóng escalation → AI hoạt động lại
        $escalation->update([
            'status'          => 'resolved',
            'resolution_note' => 'Quản lý xử lý xong — AI tiếp nhận lại.',
            'resolved_at'     => now(),
        ]);

        Log::info("Manager Resume: ✅ Đóng escalation #{$escalation->id} cho khách {$customerId} → AI hoạt động lại.");

        // Thông báo cho khách biết AI đã sẵn sàng trở lại
        $account     = \App\Models\ZaloAccount::where('oa_id', $shorterOaId)->where('is_active', true)->first();
        $zaloService = new \App\Services\ZaloService($account);
        $zaloService->sendTextMessage(
            $customerId,
            "Dạ, vấn đề của anh/chị đã được xử lý xong ạ! 🎉 Anh/chị cần hỗ trợ thêm hoặc muốn đặt dịch vụ vận chuyển, cứ nhắn em nhé! 😊"
        );
    }

    // =========================================================================
    // HANDLER: Khách follow OA
    // =========================================================================

    protected function handleFollowEvent(array $payload, ?string $shorterOaId)
    {
        $followerId = $payload['follower']['id'] ?? null;

        $shopId = $payload['info']['referrer'] ??
            ($payload['tracking_code'] ??
                ($payload['follower']['tracking_code'] ??
                    ($payload['follower']['referrer'] ?? null)));

        if ($followerId && $shopId) {
            $shop = \App\Models\Shop::find($shopId);
            if ($shop) {
                $shop->update(['zalo_id' => $followerId]);
                Log::info("Zalo Background: Đã liên kết Shop '{$shop->name}' với Zalo ID {$followerId}");

                $account     = \App\Models\ZaloAccount::where('oa_id', $shorterOaId)->first();
                $zaloService = new \App\Services\ZaloService($account);
                $zaloService->sendTextMessage(
                    $followerId,
                    "Chào mừng Shop '{$shop->name}'! Cảm ơn bạn đã kết nối với Flashship. Bây giờ bạn có thể nhắn tin trực tiếp để lên đơn hàng."
                );
            }
        }
    }

    // =========================================================================
    // HANDLER: Khách gửi tin nhắn → AI xử lý
    // =========================================================================

    protected function handleTextMessage(array $payload, ?string $shorterOaId)
    {
        $senderId = $payload['sender']['id'] ?? 'unknown';
        $message  = $payload['message']['text'] ?? '';

        // Tự động liên kết Shop nếu có tracking_code
        $shopIdFromMeta = $payload['info']['referrer'] ??
            ($payload['tracking_code'] ??
                ($payload['message']['tracking_code'] ?? null));

        if ($shopIdFromMeta && $senderId != 'unknown') {
            $shop = \App\Models\Shop::find($shopIdFromMeta);
            if ($shop && empty($shop->zalo_id)) {
                $shop->update(['zalo_id' => $senderId]);
                Log::info("Zalo Background: Tự động liên kết Shop '{$shop->name}' qua tin nhắn đầu tiên.");
            }
        }

        // Xử lý contact card / sticker
        if (empty($message)) {
            // Thử extract SĐT từ contact card Zalo
            $attachments = $payload['message']['attachments'] ?? [];
            $contactPhone = null;
            foreach ($attachments as $att) {
                if (($att['type'] ?? '') === 'contact') {
                    $contactPhone = $att['payload']['phones'][0] ?? null;
                    break;
                }
            }
            if ($contactPhone) {
                $message = "SĐT: {$contactPhone}";
                Log::info("Zalo: Nhận contact card, extract SĐT: {$contactPhone}");
            } else {
                Log::info("Zalo: Sticker/file không xử lý được → bỏ qua");
                return;
            }
        }

        try {
            $account     = \App\Models\ZaloAccount::where('oa_id', $shorterOaId)->where('is_active', true)->first();
            $cityId      = $account ? $account->city_id : null;

            $chatService = app(\App\Services\ChatInteractionService::class);
            $result      = $chatService->handleMessage($message, $senderId, 'zalo', $cityId);

            $zaloService = new \App\Services\ZaloService($account);

            // Human Handoff: AI im lặng, nhường chỗ cho quản lý
            if (($result['type'] ?? '') === 'handoff_silence') {
                Log::info("Zalo Background: Human Handoff — AI im lặng cho {$senderId}");
                return;
            }

            if (isset($result['type']) && $result['type'] === 'menu') {
                $zaloService->sendListMessage($senderId, $result['title'], $result['options']);
            } else {
                $zaloService->sendTextMessage($senderId, $result['content'] ?? '');
            }

        } catch (\Exception $e) {
            Log::error('Zalo Background Error: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // HANDLER: Khách gửi hình ảnh → Gemini Vision OCR
    // =========================================================================

    protected function handleImageMessage(array $payload, ?string $shorterOaId): void
    {
        $senderId  = $payload['sender']['id'] ?? 'unknown';
        $imageUrl  = $payload['message']['attachments'][0]['payload']['url']
                  ?? ($payload['message']['attachments'][0]['payload']['thumbnail'] ?? null);

        if (!$imageUrl) {
            Log::info("Zalo Image: Không tìm thấy URL ảnh cho sender {$senderId}");
            return;
        }

        Log::info("Zalo Image: Nhận ảnh từ {$senderId}: {$imageUrl}");

        try {
            // Gọi Gemini Vision để đọc địa chỉ/SĐT từ ảnh
            $apiKey  = config('services.gemini.api_key', env('GEMINI_API_KEY'));
            $apiUrl  = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

            $response = \Illuminate\Support\Facades\Http::timeout(20)->post("{$apiUrl}?key={$apiKey}", [
                'contents' => [[
                    'parts' => [
                        [
                            'text' => 'Đây là ảnh từ shop giao hàng. Hãy đọc và trích xuất TOÀN BỘ thông tin giao hàng: địa chỉ giao, SĐT người nhận, tên người nhận, ghi chú (nếu có). Trả lời ngắn gọn dạng: "Địa chỉ: X | SĐT: Y | Tên: Z | Ghi chú: W". Nếu không có thông tin giao hàng thì trả lời "Không có thông tin giao hàng".'
                        ],
                        [
                            'inline_data' => [
                                'mime_type' => 'image/jpeg',
                                'data'      => base64_encode(
                                    \Illuminate\Support\Facades\Http::get($imageUrl)->body()
                                ),
                            ]
                        ]
                    ]
                ]]
            ]);

            $ocrText = null;
            if ($response->successful()) {
                $ocrText = $response->json('candidates.0.content.parts.0.text');
            }

            if (!$ocrText || str_contains(mb_strtolower($ocrText), 'không có thông tin')) {
                Log::info("Zalo Image OCR: Không đọc được địa chỉ từ ảnh của {$senderId}");
                return; // Ảnh không có info giao hàng → bỏ qua
            }

            Log::info("Zalo Image OCR: '{$ocrText}' từ sender {$senderId}");

            // Xử lý text vừa OCR như tin nhắn bình thường
            $account     = \App\Models\ZaloAccount::where('oa_id', $shorterOaId)->where('is_active', true)->first();
            $cityId      = $account?->city_id;
            $chatService = app(\App\Services\ChatInteractionService::class);
            $result      = $chatService->handleMessage(
                "[Từ ảnh] {$ocrText}",
                $senderId,
                'zalo',
                $cityId
            );

            $zaloService = new \App\Services\ZaloService($account);
            if (($result['type'] ?? '') !== 'handoff_silence') {
                $zaloService->sendTextMessage($senderId, $result['content'] ?? '');
            }

        } catch (\Exception $e) {
            Log::error("Zalo Image Handler Error: " . $e->getMessage());
        }
    }

    // =========================================================================
    // HANDLER: Khách gửi voice → Lấy transcript
    // =========================================================================

    protected function handleAudioMessage(array $payload, ?string $shorterOaId): void
    {
        $senderId = $payload['sender']['id'] ?? 'unknown';

        // Zalo đôi khi tự transcript voice → lấy nếu có
        $transcript = $payload['message']['attachments'][0]['payload']['transcript']
                   ?? ($payload['message']['msg_info']['transcript'] ?? null);

        if (!$transcript) {
            // Zalo chưa có transcript → thông báo nhẹ nhàng để shop nhắn lại text
            Log::info("Zalo Audio: Không có transcript cho sender {$senderId}");

            try {
                $account     = \App\Models\ZaloAccount::where('oa_id', $shorterOaId)->where('is_active', true)->first();
                $zaloService = new \App\Services\ZaloService($account);
                $zaloService->sendTextMessage(
                    $senderId,
                    "Dạ, em không nghe được voice ạ. Bạn nhắn lại địa chỉ + SĐT giao hàng dạng text giúp em nhé! 🙏"
                );
            } catch (\Exception $e) {
                Log::error("Zalo Audio Reply Error: " . $e->getMessage());
            }
            return;
        }

        Log::info("Zalo Audio Transcript: '{$transcript}' từ sender {$senderId}");

        // Có transcript → xử lý như tin nhắn text bình thường
        try {
            $account     = \App\Models\ZaloAccount::where('oa_id', $shorterOaId)->where('is_active', true)->first();
            $cityId      = $account?->city_id;
            $chatService = app(\App\Services\ChatInteractionService::class);
            $result      = $chatService->handleMessage($transcript, $senderId, 'zalo', $cityId);

            $zaloService = new \App\Services\ZaloService($account);
            if (($result['type'] ?? '') !== 'handoff_silence') {
                $zaloService->sendTextMessage($senderId, $result['content'] ?? '');
            }
        } catch (\Exception $e) {
            Log::error("Zalo Audio Handler Error: " . $e->getMessage());
        }
    }
}
