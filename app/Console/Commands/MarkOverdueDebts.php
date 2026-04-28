<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DriverDebt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MarkOverdueDebts extends Command
{
    protected $signature = 'debt:mark-overdue';
    protected $description = 'Chuyển công nợ tuần sang trạng thái quá hạn (overdue) sau 21h Chủ nhật nếu chưa thanh toán';

    public function handle()
    {
        $now = Carbon::now('Asia/Ho_Chi_Minh');

        // 🕐 Lấy mốc 21h tối Chủ nhật gần nhất
        $deadline = Carbon::parse('last sunday', 'Asia/Ho_Chi_Minh')->setTime(21, 0, 0);

        // Nếu hôm nay là Chủ nhật và đã sau 21h → deadline chính là hôm nay
        if ($now->isSunday() && $now->hour >= 21) {
            $deadline = $now->copy()->setTime(21, 0, 0);
        }

        // Nếu chưa tới hạn 21h CN thì bỏ qua
        if ($now->lessThan($deadline)) {
            $this->info('⏳ Chưa tới 21h Chủ nhật, bỏ qua.');
            return Command::SUCCESS;
        }

        // 🚫 Đánh dấu công nợ tuần quá hạn (chỉ weekly)
        $debts = DriverDebt::where('debt_type', 'weekly')
            ->whereNotIn('status', ['paid', 'overdue'])
            ->whereDate('week_end', '<=', $deadline->toDateString())
            ->get();

        if ($debts->isEmpty()) {
            $this->info('✅ Không có công nợ tuần nào cần chuyển sang OVERDUE.');
            return Command::SUCCESS;
        }

        $driverIds = $debts->pluck('driver_id')->unique();

        $drivers = \App\Models\User::whereIn('id', $driverIds)->get();

        $overdueCount = 0;

        foreach ($drivers as $driver) {
            $driverDebts = $debts->where('driver_id', $driver->id);
            $totalOverdue = $driverDebts->sum(fn($d) => $d->amount_due - $d->amount_paid);

            // Đánh dấu toàn bộ sang OVERDUE
            DriverDebt::whereIn('id', $driverDebts->pluck('id'))
                ->update(['status' => 'overdue']);

            $overdueCount += $driverDebts->count();

            $weeks = $driverDebts->map(function ($debt) {
                return Carbon::parse($debt->week_start)->format('d/m') . ' - ' . Carbon::parse($debt->week_end)->format('d/m');
            })->unique()->sort()->values();

            $weeksText = $weeks->count() > 3
                ? $weeks->take(3)->implode(', ') . ' và ' . ($weeks->count() - 3) . ' tuần khác'
                : $weeks->implode(', ');

            if ($driver->fcm_token) {
                \App\Services\NotificationService::notifyWeeklyDebtOverdue(
                    $driver, $driverDebts->count(), $totalOverdue, $weeksText
                );
                Log::info("✅ Gửi FCM công nợ tuần quá hạn cho tài xế #{$driver->id}");
            }
        }

        // 🧾 Ghi log ra file
        Log::info("💰 [MarkOverdueDebts] Kết thúc. Overdue: {$overdueCount}.", [
            'time' => $now->toDateTimeString(),
            'deadline' => $deadline->toDateTimeString(),
        ]);

        $this->info("✅ Đã xử lý (tính tới {$deadline->format('d/m/Y H:i')}). Overdue: {$overdueCount}.");

        return Command::SUCCESS;
    }
}
