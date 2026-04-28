<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\DriverDebt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenerateDriverDebts extends Command
{
    protected $signature = 'driver:generate-debts {--city= : Lọc theo city_id (bỏ trống = toàn hệ thống)}';
    protected $description = 'Tạo công nợ hàng tuần cho tất cả tài xế';

    public function handle()
    {
        $weekStart = Carbon::now('Asia/Ho_Chi_Minh')->startOfWeek(); // Thứ 2
        $weekEnd   = Carbon::now('Asia/Ho_Chi_Minh')->endOfWeek();   // Chủ Nhật

        $cityId    = $this->option('city') ? (int) $this->option('city') : null;
        $cityLabel = $cityId ? " (city_id={$cityId})" : ' (toàn hệ thống)';

        // 🔹 Đếm số tài khoản không active để log
        $inactiveCount = User::drivers()
            ->whereHas('plan', fn($q) => $q->where('type', 'weekly'))
            ->when($cityId, fn($q) => $q->where('city_id', $cityId))
            ->where('status', '!=', 1)
            ->count();

        $drivers = User::drivers()
            ->whereHas('plan', fn($q) => $q->where('type', 'weekly'))
            ->whereNull('custom_commission_rate')
            ->when($cityId, fn($q) => $q->where('city_id', $cityId))
            ->with(['shifts', 'plan', 'city'])
            ->where('status', 1)
            ->get();

        $this->info("🔄 Tạo công nợ tuần {$weekStart->format('d/m')} → {$weekEnd->format('d/m')}{$cityLabel}");

        if ($inactiveCount > 0) {
            Log::info("⚠️ Đã bỏ qua {$inactiveCount} tài khoản không active khi tạo công nợ tuần");
        }

        // Fetch settings trước loop — tránh N+1 query
        $defaultFeeFull = (int) optional(\App\Models\Setting::where('key', 'default_weekly_fee_full')->first())->value ?: 420000;
        $defaultFeePart = (int) optional(\App\Models\Setting::where('key', 'default_weekly_fee_part')->first())->value ?: 350000;

        foreach ($drivers as $driver) {
            $plan = $driver->plan;
            $city = $driver->city;

            // Bỏ qua tài xế gói free
            if ($plan?->type === 'free') {
                $this->line("  ⏭ Tài xế #{$driver->id} gói free — bỏ qua.");
                continue;
            }

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

            $weekNote = ($hasFull ? 'Cả ngày' : 'Bán thời gian') .
                ' — Tuần ' . $weekStart->format('d/m') . ' → ' . $weekEnd->format('d/m/Y') .
                ': ' . number_format($amountDue) . '₫';

            $debt = DriverDebt::create([
                'driver_id' => $driver->id,
                'debt_type' => 'weekly',
                'week_start' => $weekStart,
                'week_end' => $weekEnd,
                'amount_due' => $amountDue,
                'amount_paid' => 0,
                'status' => 'pending',
                'note' => $weekNote,
            ]);

            // 🔔 Gửi FCM thông báo công nợ mới ngay sau khi tạo
            $this->sendDebtNotification($driver, $debt);
        }

        $this->info("✅ Đã tạo công nợ cho " . $drivers->count() . " tài xế.");
    }

    protected function sendDebtNotification($driver, $debt): void
    {
        if (!$driver->fcm_token) {
            Log::warning("⚠️ Tài xế #{$driver->id} chưa có FCM Token, bỏ qua.");
            return;
        }

        \App\Services\NotificationService::notifyWeeklyDebtCreated($driver, $debt);
    }
}
