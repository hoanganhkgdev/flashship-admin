<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\DriverDebt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotifyWeeklyPayment extends Command
{
    protected $signature = 'driver:notify-weekly';
    protected $description = 'Gửi thông báo cho tài xế gói theo tuần để thanh toán công nợ trước 21h Chủ nhật (nhắc lúc 20h)';

    public function handle()
    {
        $now = Carbon::now('Asia/Ho_Chi_Minh');

        // Lấy tuần hiện tại (từ Thứ 2 đến Chủ nhật)
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();

        $count = 0;

        // Lấy danh sách công nợ tuần của tuần hiện tại (chưa thanh toán)
        $debts = DriverDebt::where('debt_type', 'weekly')
            ->where('week_start', $weekStart->toDateString())
            ->where('status', 'pending')
            ->with('driver')
            ->get();

        foreach ($debts as $debt) {
            $driver = $debt->driver;
            if (!$driver || !$driver->plan || $driver->plan->type != 'weekly') {
                continue; // chỉ gói theo tuần
            }

            $playerId = $driver->fcm_token;
            if (!$playerId) {
                Log::warning("⚠️ Driver #{$driver->id} ({$driver->name}) không có FCM Token, bỏ qua.");
                continue;
            }

            $title = '💸 Nhắc thanh toán công nợ tuần';
            $body = "Bạn cần thanh toán " . number_format($debt->amount_due, 0, ',', '.') . "đ công nợ tuần từ " .
                $debt->week_start->format('d/m') . " đến " . $debt->week_end->format('d/m') . ".\n" .
                "Vui lòng thanh toán trước 21h tối hôm nay để tránh quá hạn.";

            $data = [
                'type' => 'weekly_debt_reminder',
                'debt_id' => (string) $debt->id,
                'amount_due' => (string) $debt->amount_due,
                'week_start' => (string) $debt->week_start->format('Y-m-d'),
                'week_end' => (string) $debt->week_end->format('Y-m-d'),
            ];

            try {
                \App\Helpers\FcmHelper::sendToMultiple([$playerId], $title, $body, $data);
                
                // ✅ Lưu vào Lịch sử thông báo trên App (Database)
                $driver->notify(new \App\Notifications\DriverAppNotification($title, $body, 'warning'));

                Log::info("✅ Đã gửi thông báo nhắc thanh toán FCM cho tài xế #{$driver->id} - Công nợ: " . number_format($debt->amount_due) . "đ");
                $count++;
            } catch (\Throwable $e) {
                Log::error("❌ Lỗi gửi FCM cho tài xế #{$driver->id}: " . $e->getMessage());
            }
        }

        $this->info("✅ Đã gửi thông báo nhắc thanh toán cho {$count} tài xế gói theo tuần (tuần từ {$weekStart->format('d/m')} đến {$weekEnd->format('d/m')}).");
        return Command::SUCCESS;
    }
}

