<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\AiOrderService;
use App\Models\FacebookAccount;
use App\Models\Shop;
use App\Services\FacebookService;
use Illuminate\Support\Facades\Log;

class ProcessFacebookMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 90;

    protected array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
        $this->onQueue('facebook');
    }

    public function handle()
    {
        if (!isset($this->payload['entry'])) {
            return;
        }

        foreach ($this->payload['entry'] as $entry) {
            $pageId = $entry['id'] ?? null;
            if (!$pageId)
                continue;

            $account = FacebookAccount::where('page_id', $pageId)->where('is_active', true)->first();
            $cityId = $account ? $account->city_id : null;

            if (isset($entry['messaging'])) {
                foreach ($entry['messaging'] as $messaging) {
                    $senderId = $messaging['sender']['id'] ?? null;
                    $message = $messaging['message']['text'] ?? '';

                    if ($senderId && !empty($message)) {
                        $this->processMessage($senderId, $message, $account, $cityId);
                    }
                }
            }
        }
    }

    protected function processMessage($senderId, $message, $account, $cityId)
    {
        try {
            $shop = Shop::where('facebook_id', $senderId)->first();
            $aiService = app(AiOrderService::class);
            $order = $aiService->createFromText($message, $cityId, $shop);

            if ($order) {
                $fbService = new FacebookService($account);
                $replyText = "✅ Flashship đã nhận đơn hàng của bạn!\n";
                $replyText .= "📍 Giao đến: " . ($order->delivery_address ?: 'Đang cập nhật') . "\n";

                if ($order->shipping_fee > 0) {
                    $replyText .= "💰 Phí ship: " . number_format($order->shipping_fee) . "đ\n";
                }

                $replyText .= "\n🚀 Tài xế sẽ sớm liên hệ lấy hàng!\n(ID: #{$order->id})";

                $fbService->sendTextMessage($senderId, $replyText);
            }
        } catch (\Exception $e) {
            Log::error('ProcessFacebookMessage Job Error: ' . $e->getMessage());
        }
    }
}
