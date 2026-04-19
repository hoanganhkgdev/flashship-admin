<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\DriverDebt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenerateDriverDebts extends Command
{
    protected $signature = 'driver:generate-debts';
    protected $description = 'Tạo công nợ hàng tuần cho tất cả tài xế';

    public function handle()
    {
        $weekStart = Carbon::now('Asia/Ho_Chi_Minh')->startOfWeek(); // Thứ 2
        $weekEnd   = Carbon::now('Asia/Ho_Chi_Minh')->endOfWeek();   // Chủ Nhật

        // 🔹 Đếm số tài khoản không active để log
        $inactiveCount = User::drivers()
            ->whereHas('plan', fn($q) => $q->where('type', 'weekly'))
            ->where('status', '!=', 1)
            ->count();

        $drivers = User::drivers()
            ->whereHas('plan', function ($query) {
                $query->where('type', 'weekly');
            })
            ->whereNull('custom_commission_rate') // 🛑 Bỏ qua tài xế đã set % chiết khấu riêng
            ->with(['shifts', 'plan', 'city'])
            ->where('status', 1)
            ->get();

        if ($inactiveCount > 0) {
            Log::info("⚠️ Đã bỏ qua {$inactiveCount} tài khoản không active khi tạo công nợ tuần");
        }

        // Fetch settings trước loop — tránh N+1 query
        $defaultFeeFull = (int) optional(\App\Models\Setting::where('key', 'default_weekly_fee_full')->first())->value ?: 420000;
        $defaultFeePart = (int) optional(\App\Models\Setting::where('key', 'default_weekly_fee_part')->first())->value ?: 350000;

        foreach ($drivers as $driver) {
            $plan = $driver->plan;
            $city = $driver->city;

            $hasFull = $driver->shifts->contains('code', 'full');

            if ($hasFull) {
                $amountDue = $plan->weekly_fee_full ?? $defaultFeeFull;
            } else {
                $amountDue = $plan->weekly_fee_part ?? $defaultFeePart;
            }

            $amountDue = (int) $amountDue;

            $exists = DriverDebt::where('driver_id', $driver->id)
                ->where('week_start', $weekStart)
                ->exists();

            if ($exists) {
                Log::info("⚠️ Công nợ tuần {$weekStart->format('d/m')} đã tồn tại cho tài xế {$driver->id}");
                continue;
            }

            // Tạo công nợ và lưu lại vào biến $debt
            $debt = DriverDebt::create([
                'driver_id' => $driver->id,
                'debt_type' => 'weekly',
                'week_start' => $weekStart,
                'week_end' => $weekEnd,
                'amount_due' => $amountDue,
                'amount_paid' => 0,
                'status' => 'pending',
            ]);

            // 🔔 Gửi FCM thông báo công nợ mới ngay sau khi tạo
            $this->sendDebtNotification($driver, $debt);
        }

        $this->info("✅ Đã tạo công nợ cho " . $drivers->count() . " tài xế.");
    }

    protected function sendDebtNotification($driver, $debt)
    {
        $playerId = $driver->fcm_token;
        if (!$playerId) {
            Log::warning("⚠️ Tài xế {$driver->id} chưa có FCM Token, bỏ qua.");
            return;
        }

        // 🧾 Chuẩn bị dữ liệu thông báo
        $title = '💰 Công nợ tuần mới';
        $body = 'Công nợ từ ' . $debt->week_start->format('d/m') . ' đến ' . $debt->week_end->format('d/m') .
            ' đã được tạo. Vui lòng thanh toán trước hạn.';

        $data = [
            'type' => 'driver_debt_created',
            'debt_id' => (string) $debt->id,
            'amount_due' => (string) $debt->amount_due,
            'week_start' => $debt->week_start->format('Y-m-d'),
            'week_end' => $debt->week_end->format('Y-m-d'),
        ];

        // ⚡️ Gửi qua helper (giống Order)
        try {
            \App\Helpers\FcmHelper::sendToMultiple([$playerId], $title, $body, $data);
        } catch (\Throwable $e) {
            Log::error("❌ Lỗi gửi FCM cho tài xế {$driver->id}: " . $e->getMessage());
        }
    }
}
