<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ProcessZaloWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;



    /**
     * Số lần thử lại nếu Job thất bại
     */
    public int $tries = 3;

    /**
     * Timeout tối đa cho mỗi lần chạy (giây)
     */
    public int $timeout = 120;

    public function __construct(private array $payload)
    {
        $this->onQueue('zalo');
    }

    /**
     * Chạy lệnh xử lý Zalo webhook trong Queue Worker (có pcntl)
     */
    public function handle(): void
    {
        Log::info('ProcessZaloWebhookJob: Bắt đầu xử lý', ['payload' => $this->payload]);

        Artisan::call('zalo:process', [
            'payload' => json_encode($this->payload),
        ]);

        Log::info('ProcessZaloWebhookJob: Xử lý xong');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessZaloWebhookJob: Thất bại - ' . $exception->getMessage());
    }
}
