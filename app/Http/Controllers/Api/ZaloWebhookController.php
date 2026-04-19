<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Console\Commands\ProcessZaloWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZaloWebhookController extends Controller
{
    /**
     * Tiếp nhận và xử lý webhook từ Zalo OA
     *
     * Không dùng Queue — xử lý trực tiếp trong request hiện tại.
     * fastcgi_finish_request() trả về 200 cho Zalo ngay lập tức,
     * PHP tiếp tục chạy xử lý AI ở background mà Zalo không chờ.
     * Tương thích: shared hosting, VPS, bất kỳ môi trường có PHP-FPM.
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        // ── 🛡️ DEDUPLICATION: Ngăn xử lý trùng lặp (Zalo thường retry nếu chưa nhận 200 trong 5s) ──
        $msgId = $payload['message']['msg_id'] ?? null;
        if (!$msgId) {
            // Nếu không có msg_id (vd: event follow), dùng tổ hợp event + sender + timestamp làm key
            $msgId = md5(($payload['event_name'] ?? '') . ($payload['sender']['id'] ?? '') . ($payload['timestamp'] ?? ''));
        }

        $cacheKey = "zalo_webhook_processed_{$msgId}";
        if (\Cache::has($cacheKey)) {
            Log::info("Zalo Webhook: [DUPLICATE] Bỏ qua msg_id={$msgId}");
            return response()->json(['status' => 'duplicate', 'message' => 'Already processing']);
        }
        
        // Lưu cache trong 10 phút để đảm bảo không bị trùng lặp khi Zalo retry
        \Cache::put($cacheKey, true, now()->addMinutes(10));

        Log::info('Zalo Webhook: Nhận yêu cầu mới', ['msg_id' => $msgId, 'event' => $payload['event_name'] ?? 'unknown']);

        // ── Trả response 200 cho Zalo NGAY — tránh timeout ──
        $responseBody = json_encode(['status' => 'success', 'message' => 'Processing', 'msg_id' => $msgId]);

        if (ob_get_level()) ob_end_clean();

        header('Connection: close');
        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($responseBody));

        echo $responseBody;

        // Flush response tới client (Zalo) ngay lập tức
        flush();

        // fastcgi_finish_request(): ngắt kết nối với client, PHP vẫn tiếp tục chạy
        // Hỗ trợ trên hầu hết shared hosting có PHP-FPM
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // ── Xử lý AI sau khi Zalo đã nhận 200 ──
        try {
            ignore_user_abort(true);       // Không dừng dù client đã disconnect
            set_time_limit(120);           // Cho phép chạy tối đa 2 phút

            $processor = new ProcessZaloWebhook();
            $processor->processPayloadDirect($payload);

        } catch (\Throwable $e) {
            Log::error('Zalo Webhook Process Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
