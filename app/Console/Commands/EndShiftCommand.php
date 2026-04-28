<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\User;
use App\Services\FirebaseRTDBService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EndShiftCommand extends Command
{
    protected $signature = 'shift:end {code}';
    protected $description = 'Kết thúc ca làm việc, ép in_shift và is_online = false cho tài xế';

    public function handle()
    {
        $code = $this->argument('code');

        // ✅ Chỉ ép offline tài xế gói weekly (Rạch Giá)
        // Tài xế gói commission (Cần Thơ, ...) không bị ảnh hưởng
        $drivers = User::role('driver')
            ->whereHas('shifts', fn($q) => $q->where('code', $code))
            ->whereHas('plan', fn($q) => $q->where('type', Plan::TYPE_WEEKLY))
            ->where('is_online', true)
            ->with(['shifts', 'plan'])
            ->get();

        foreach ($drivers as $driver) {
            $driver->update(['is_online' => false]);
            $driver->clearShiftCache();

            // 🔔 Thông báo cho tài xế biết họ đã bị offline
            $driver->notify(new \App\Notifications\DriverAppNotification(
                "Đã hết ca làm việc",
                "Hệ thống đã tự động chuyển bạn sang Offline do hết ca {$code}.",
                "warning"
            ));

            // 🔥 Sync Firebase để App tài xế & Admin map cập nhật ngay
            try {
                FirebaseRTDBService::publishDriverProfile($driver->fresh(['shifts', 'plan']));
                FirebaseRTDBService::deleteDriverLocation($driver->id);
            } catch (\Throwable $e) {
                Log::error("Firebase sync lỗi khi end-shift driver #{$driver->id}: " . $e->getMessage());
            }

            $this->info("⏰ Driver {$driver->id} ({$driver->name}) OFF vì hết ca {$code}");
        }

        $this->info("✅ Đã kết thúc ca {$code}, tổng: " . $drivers->count());
    }
}
