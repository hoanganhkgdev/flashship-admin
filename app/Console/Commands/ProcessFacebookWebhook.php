<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiOrderService;
use App\Models\FacebookAccount;
use App\Models\Shop;
use App\Services\FacebookService;
use Illuminate\Support\Facades\Log;

class ProcessFacebookWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:process {payload}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Xử lý tin nhắn Facebook Messenger bằng AI ở chế độ chạy ngầm';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $payload = json_decode($this->argument('payload'), true);

        if (!$payload || !isset($payload['entry'])) {
            Log::error('Facebook Background: Invalid payload');
            return;
        }

        foreach ($payload['entry'] as $entry) {
            $pageId = $entry['id'] ?? null;
            if (!$pageId)
                continue;

            // Truy xuất Account theo Page ID hoặc dùng mặc định
            $account = FacebookAccount::where('page_id', $pageId)->where('is_active', true)->first();
            $cityId = $account ? $account->city_id : null;

            if (isset($entry['messaging'])) {
                foreach ($entry['messaging'] as $messaging) {
                    $senderId = $messaging['sender']['id'] ?? null;
                    $message = $messaging['message']['text'] ?? '';

                    if ($senderId && !empty($message)) {
                        $this->handleTextMessage($senderId, $message, $account, $cityId);
                    }
                }
            }
        }
    }

    protected function handleTextMessage($senderId, $message, $account, $cityId)
    {
        try {
            Log::info("Facebook Background: Đang xử lý tin nhắn từ {$senderId}");

            // 1. Xác định Shop
            $shop = Shop::where('facebook_id', $senderId)->first();

            // 2. Phân tích tạo đơn bằng AI
            $aiService = app(AiOrderService::class);
            $order = $aiService->createFromText($message, $cityId, $shop);

            if ($order) {
                Log::info("Facebook Background: Đã tạo đơn hàng #{$order->id} thành công.");

                // 3. Gửi phản hồi lại cho khách
                $fbService = new FacebookService($account);

                $replyText = "✅ Flashship đã nhận đơn hàng của bạn!\n";
                $replyText .= "📍 Giao đến: " . ($order->delivery_address ?: 'Đang cập nhật') . "\n";

                if ($order->shipping_fee > 0) {
                    $replyText .= "💰 Phí ship: " . number_format($order->shipping_fee) . "đ\n";
                }

                if ($order->status === 'draft') {
                    $replyText .= "\n⚠️ Đơn hàng thiếu thông tin, vui lòng kiểm tra trên App hoặc đợi hỗ trợ.\n\n(ID đơn hàng: #{$order->id})";
                } else {
                    $replyText .= "\n🚀 Tài xế sẽ sớm liên hệ lấy hàng!\n\n(ID đơn hàng: #{$order->id})";
                }

                $fbService->sendTextMessage($senderId, $replyText);
            }
        } catch (\Exception $e) {
            Log::error('Facebook Background Error: ' . $e->getMessage());
        }
    }
}
