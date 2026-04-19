<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\DriverDebt;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CalculateDailyCommission extends Command
{
    protected $signature = 'debt:calculate-daily-commission {--date= : Ngày tính công nợ (YYYY-MM-DD)}';
    protected $description = 'Tính công nợ chiết khấu theo ngày vào 12h khuya (tính cho ngày hôm trước)';

    public function handle()
    {
        // Lấy ngày tính toán từ option, nếu không có thì mặc định là ngày hôm qua
        $inputDate = $this->option('date');
        $calcDate = $inputDate ? Carbon::parse($inputDate, 'Asia/Ho_Chi_Minh') : Carbon::yesterday('Asia/Ho_Chi_Minh');
        $dateString = $calcDate->toDateString();

        // ⏰ Chạy cho ngày được chọn (00:00 → 23:59 theo giờ VN)
        $start = $calcDate->copy()->startOfDay()->toDateTimeString();
        $end = $calcDate->copy()->endOfDay()->toDateTimeString();

        $this->info("🔄 Bắt đầu tính công nợ chiết khấu cho ngày: {$dateString} ({$start} → {$end})");

        // Lấy tất cả tài xế có gói chiết khấu % ĐANG ACTIVE HOẶC có % riêng
        $drivers = User::drivers()
            ->where(function ($query) {
                $query->whereHas('plan', fn($q) => $q->where('type', 'commission')->where('is_active', true))
                    ->orWhereNotNull('custom_commission_rate');
            })
            ->with(['plan'])
            ->where('status', 1)
            ->get();

        $this->info("👥 Tìm thấy {$drivers->count()} tài xế gói chiết khấu active");

        $totalProcessed = 0;
        $totalDebtAmount = 0;

        foreach ($drivers as $driver) {
            $plan = $driver->plan;

            // ✅ Đọc commission_rate từ driver, hoặc từ plan, fallback 15% nếu chưa set
            $commissionRate = $driver->custom_commission_rate ?? $plan->commission_rate ?? 15;

            // ✅ Đếm đơn TRƯỚC — điều kiện quyết định có tính nợ không
            $completedCount = Order::where('delivery_man_id', $driver->id)
                ->whereBetween('completed_at', [$start, $end])
                ->where('status', 'completed')
                ->count();

            // ✅ Không có đơn nào → chỉ xóa bản ghi pending (không xóa paid/overdue)
            if ($completedCount <= 0) {
                DriverDebt::where('driver_id', $driver->id)
                    ->where('debt_type', 'commission')
                    ->where('date', $dateString)
                    ->where('status', 'pending')
                    ->delete();
                $this->line("  ⏭ Tài xế #{$driver->id} không có đơn ngày {$dateString} (Đã xóa nợ pending nếu có)");
                continue;
            }

            // Tính tổng thu nhập
            $totalEarning = Order::where('delivery_man_id', $driver->id)
                ->whereBetween('completed_at', [$start, $end])
                ->where('status', 'completed')
                ->sum('shipping_fee');

            // Phí app: CHỂ tính khi tài xế CÓ đơn trong ngày
            $appFee = in_array($driver->city_id, [1, 2]) ? 3000 : 0;

            // Tính tiền chiết khấu
            $commissionAmount = $totalEarning * $commissionRate / 100;
            $debtAmount = $commissionAmount + $appFee;

            $note = $appFee > 0
                ? "Chiết khấu {$commissionRate}% × " . number_format($totalEarning) . "đ + Phí App 3.000đ ({$completedCount} đơn)"
                : "Chiết khấu {$commissionRate}% × " . number_format($totalEarning) . "đ ({$completedCount} đơn)";

            // Tránh tạo trùng — nếu đã tồn tại thì update lại
            $existingDebt = DriverDebt::where('driver_id', $driver->id)
                ->where('debt_type', 'commission')
                ->where('date', $dateString)
                ->first();

            if ($existingDebt) {
                // Không cập nhật debt đã paid/overdue — tránh làm inconsistent dữ liệu
                if ($existingDebt->status !== 'pending') {
                    $this->line("  ⏭ Tài xế #{$driver->id} ngày {$dateString}: debt đã {$existingDebt->status}, bỏ qua.");
                    continue;
                }
                $oldAmount = $existingDebt->amount_due;
                $existingDebt->update([
                    'amount_due' => $debtAmount,
                    'note' => $note,
                ]);
                $this->line("  🔄 Tài xế #{$driver->id}: Cập nhật {$oldAmount}đ → {$debtAmount}đ (CK={$commissionRate}%, Phí App={$appFee}đ)");

                if ($oldAmount != $debtAmount) {
                    $this->sendDebtNotification($driver, $existingDebt, $dateString, $totalEarning, $completedCount, $commissionRate, $appFee);
                }
            } else {
                $debt = DriverDebt::create([
                    'driver_id' => $driver->id,
                    'debt_type' => 'commission',
                    'date' => $dateString,
                    'amount_due' => $debtAmount,
                    'amount_paid' => 0,
                    'status' => 'pending',
                    'note' => $note,
                ]);
                $this->info("  ✅ Tài xế #{$driver->id}: {$completedCount} đơn | Ship=" . number_format($totalEarning) . "đ | CK={$commissionRate}% | Phí={$appFee}đ | Nợ=" . number_format($debtAmount) . "đ");
                $this->sendDebtNotification($driver, $debt, $dateString, $totalEarning, $completedCount, $commissionRate, $appFee);
            }

            $totalProcessed++;
            $totalDebtAmount += $debtAmount;
        }

        $this->info("✅ Hoàn thành! Đã xử lý {$totalProcessed} tài xế, tổng công nợ: " . number_format($totalDebtAmount) . "đ");

        Log::info("Debt calculation completed for {$dateString}: {$totalProcessed} drivers, total debt: " . number_format($totalDebtAmount) . "đ");
    }

    protected function sendDebtNotification($driver, $debt, $date, $totalEarning, $orderCount, $rate, $appFee = 0)
    {
        $playerId = $driver->fcm_token;
        if (!$playerId) {
            Log::info("⚠️ Tài xế {$driver->id} ({$driver->name}) chưa có FCM Token, bỏ qua thông báo.");
            return;
        }

        $dateFormatted = Carbon::parse($date)->format('d/m/Y');
        $title = '💸 Công nợ chiết khấu ngày ' . $dateFormatted;

        $commissionAmount = $totalEarning * $rate / 100;
        $body = "Tổng thu nhập: " . number_format($totalEarning, 0, ',', '.') . "đ ({$orderCount} đơn)\n" .
            "Chiết khấu {$rate}%: " . number_format($commissionAmount, 0, ',', '.') . "đ";

        if ($appFee > 0) {
            $body .= "\nPhí App duy trì: " . number_format($appFee, 0, ',', '.') . "đ";
        }

        $body .= "\n------------------\n";
        $body .= "Tổng cộng cần nộp: " . number_format($debt->amount_due, 0, ',', '.') . "đ";

        $data = [
            'type' => 'commission_debt_created',
            'debt_id' => (string) $debt->id,
            'amount_due' => (string) $debt->amount_due,
            'date' => (string) $date,
        ];

        try {
            \App\Helpers\FcmHelper::sendToMultiple([$playerId], $title, $body, $data);
            Log::info("✅ Đã gửi thông báo FCM cho tài xế #{$driver->id} - Công nợ: " . number_format($debt->amount_due) . "đ");
        } catch (\Throwable $e) {
            Log::error("❌ Lỗi gửi FCM cho tài xế #{$driver->id}: " . $e->getMessage());
        }
    }
}

