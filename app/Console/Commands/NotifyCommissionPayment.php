<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DriverDebt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotifyCommissionPayment extends Command
{
    protected $signature = 'driver:notify-commission';
    protected $description = 'Gửi thông báo cho tài xế gói chiết khấu (%) để thanh toán công nợ vào 7h sáng hôm sau';

    public function handle()
    {
        $today = Carbon::today('Asia/Ho_Chi_Minh');
        $yesterday = $today->copy()->subDay();
        $count = 0;

        // Lấy danh sách công nợ chiết khấu của ngày hôm qua (chưa thanh toán)
        $debts = DriverDebt::where('debt_type', 'commission')
            ->whereDate('date', $yesterday->toDateString())
            ->where('status', 'pending')
            ->with('driver')
            ->get();

        foreach ($debts as $debt) {
            $driver = $debt->driver;
            if (!$driver || (!$driver->custom_commission_rate && (!$driver->plan || $driver->plan->type != 'commission'))) {
                continue; // chỉ gói chiết khấu hoặc có % riêng
            }

            $playerId = $driver->fcm_token;
            if (!$playerId) {
                Log::warning("⚠️ Driver #{$driver->id} không có FCM Token, bỏ qua.");
                continue;
            }

            \App\Services\NotificationService::notifyCommissionDebtReminder($driver, $debt, $yesterday);
            Log::info("📩 Gửi FCM nhắc chiết khấu cho tài xế #{$driver->id}: " . number_format($debt->amount_due) . 'đ');
            $count++;
        }

        $this->info("✅ Đã gửi thông báo nhắc thanh toán cho {$count} tài xế gói chiết khấu (công nợ ngày {$yesterday->format('d/m/Y')}).");
        return Command::SUCCESS;
    }
}
