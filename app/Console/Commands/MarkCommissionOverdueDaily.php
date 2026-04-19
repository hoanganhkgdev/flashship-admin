<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DriverDebt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MarkCommissionOverdueDaily extends Command
{
    protected $signature = 'debt:commission-overdue-daily';
    protected $description = 'Đánh dấu công nợ chiết khấu (commission) sang quá hạn vào 12h trưa hôm sau nếu chưa thanh toán';

    public function handle()
    {
        $today = Carbon::today('Asia/Ho_Chi_Minh');
        $yesterday = $today->copy()->subDay();

        // Logic: Công nợ của ngày hôm qua sẽ quá hạn vào 12h trưa hôm nay nếu chưa thanh toán
        // Ví dụ: Công nợ ngày 06/12 sẽ quá hạn vào 12h trưa ngày 07/12
        $debts = DriverDebt::where('debt_type', 'commission')
            ->where('status', 'pending')
            ->whereDate('date', $yesterday->toDateString())
            ->get();

        if ($debts->isEmpty()) {
            $this->info('✅ Không có công nợ chiết khấu nào cần chuyển sang OVERDUE.');
            return Command::SUCCESS;
        }

        // Đếm số công nợ và tài xế bị ảnh hưởng
        $affected = $debts->count();
        $driverIds = $debts->pluck('driver_id')->unique();

        $drivers = \App\Models\User::whereIn('id', $driverIds)->get();

        $overdueCount = 0;

        foreach ($drivers as $driver) {
            $driverDebts = $debts->where('driver_id', $driver->id);
            $totalOverdue = $driverDebts->sum(fn($d) => $d->amount_due - $d->amount_paid);

            // Đánh dấu toàn bộ sang OVERDUE (không tự trừ ví nữa)
            DriverDebt::whereIn('id', $driverDebts->pluck('id'))
                ->update(['status' => 'overdue']);

            $overdueCount += $driverDebts->count();

            // Lịch sử các ngày nợ
            $dates = $driverDebts->pluck('date')->map(function ($date) {
                return Carbon::parse($date)->format('d/m/Y');
            })->unique()->sort()->values();

            $datesText = $dates->count() > 3
                ? $dates->take(3)->implode(', ') . ' và ' . ($dates->count() - 3) . ' ngày khác'
                : $dates->implode(', ');

            // Gửi thông báo quá hạn
            if ($driver->fcm_token) {
                $title = '🚫 Công nợ chiết khấu đã quá hạn';
                $body = "Bạn có " . $driverDebts->count() . " công nợ quá hạn (tổng: " . number_format($totalOverdue, 0, ',', '.') . "đ)\n" .
                    "Các ngày: " . $datesText . "\n" .
                    "Vui lòng thanh toán để tiếp tục nhận đơn.";

                $data = [
                    'type' => 'commission_debt_overdue',
                    'count' => (string) $driverDebts->count(),
                    'total_amount' => (string) $totalOverdue,
                ];

                try {
                    \App\Helpers\FcmHelper::sendToMultiple([$driver->fcm_token], $title, $body, $data);
                    
                    // ✅ Lưu vào Lịch sử thông báo trên App (Database)
                    $driver->notify(new \App\Notifications\DriverAppNotification($title, $body, 'error'));

                    Log::info("✅ Đã gửi thông báo quá hạn FCM cho tài xế #{$driver->id}");
                } catch (\Throwable $e) {
                }
            }
        }

        Log::info("💰 [MarkCommissionOverdueDaily] Kết thúc. Overdue: {$overdueCount}.", [
            'time' => now()->toDateTimeString(),
        ]);

        $this->info("✅ Đã xử lý (công nợ ngày {$yesterday->format('d/m/Y')}). Overdue: {$overdueCount}.");
        return Command::SUCCESS;
    }
}
