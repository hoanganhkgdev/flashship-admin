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
    protected $signature = 'debt:calculate-daily-commission {--date= : Ngày tính công nợ (YYYY-MM-DD)} {--city= : Lọc theo city_id (bỏ trống = toàn hệ thống)}';
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

        $cityId = $this->option('city') ? (int) $this->option('city') : null;
        $cityLabel = $cityId ? " (city_id={$cityId})" : ' (toàn hệ thống)';
        $this->info("🔄 Bắt đầu tính công nợ chiết khấu cho ngày: {$dateString} ({$start} → {$end}){$cityLabel}");

        // Lấy tất cả tài xế có gói chiết khấu % ĐANG ACTIVE HOẶC có % riêng
        // Loại trừ tài xế gói free — không tính công nợ, không tính phí app
        $drivers = User::drivers()
            ->where(function ($query) {
                $query->whereHas('plan', fn($q) => $q->where('type', 'commission')->where('is_active', true))
                    ->orWhereNotNull('custom_commission_rate');
            })
            ->whereDoesntHave('plan', fn($q) => $q->where('type', 'free'))
            ->when($cityId, fn($q) => $q->where('city_id', $cityId))
            ->with(['plan'])
            ->where('status', 1)
            ->get();

        $this->info("👥 Tìm thấy {$drivers->count()} tài xế gói chiết khấu active{$cityLabel}");

        $totalProcessed = 0;
        $totalDebtAmount = 0;

        foreach ($drivers as $driver) {
            $plan = $driver->plan;

            // Bỏ qua tài xế gói free — không tính công nợ, không tính phí app
            if ($plan?->type === 'free') {
                $this->line("  ⏭ Tài xế #{$driver->id} gói free — bỏ qua.");
                continue;
            }

            // ✅ Đọc commission_rate từ driver, hoặc từ plan, fallback 15% nếu chưa set
            $commissionRate = $driver->custom_commission_rate ?? $plan?->commission_rate ?? 15;

            // ✅ Gộp count + sum thành 1 query — tránh N+1
            $stats = Order::where('delivery_man_id', $driver->id)
                ->whereBetween('completed_at', [$start, $end])
                ->where('status', 'completed')
                ->selectRaw('COUNT(*) as total_count, SUM(shipping_fee) as total_earning')
                ->first();

            $completedCount = (int) ($stats->total_count ?? 0);
            $totalEarning   = (float) ($stats->total_earning ?? 0);

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

            // Phí app từ Settings — fallback 0 nếu chưa cấu hình
            $appFee = (int) optional(\App\Models\Setting::where('key', 'app_fee_city_' . $driver->city_id)->first())->value ?: 0;

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

    protected function sendDebtNotification($driver, $debt, $date, $totalEarning, $orderCount, $rate, $appFee = 0): void
    {
        if (!$driver->fcm_token) {
            Log::info("⚠️ Tài xế #{$driver->id} chưa có FCM Token, bỏ qua thông báo.");
            return;
        }

        \App\Services\NotificationService::notifyCommissionDebtCreated(
            $driver, $debt, $totalEarning, $orderCount, $rate, $appFee
        );

        Log::info("✅ Gửi FCM công nợ chiết khấu cho tài xế #{$driver->id} - " . number_format($debt->amount_due) . 'đ');
    }
}

