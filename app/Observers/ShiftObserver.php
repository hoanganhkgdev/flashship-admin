<?php

namespace App\Observers;

use App\Models\Shift;
use App\Services\FirebaseRTDBService;
use Illuminate\Support\Facades\Log;

class ShiftObserver
{
    /**
     * Khi admin cập nhật thông tin ca (giờ bắt đầu/kết thúc),
     * tự động:
     * 1. Xóa cache isInShift() của tất cả tài xế dùng ca này
     * 2. Push Firebase profile để Flutter app cập nhật timer
     * 3. Nếu tài xế đang online mà hết ca → force offline
     */
    public function updated(Shift $shift): void
    {
        $changed = $shift->wasChanged(['start_time', 'end_time', 'is_active']);
        if (!$changed) return;

        Log::info("⏰ [ShiftObserver] Ca #{$shift->id} ({$shift->name}) đã thay đổi. Đang cập nhật tài xế...");

        // Lấy tất cả tài xế đang dùng ca này
        $drivers = $shift->users()
            ->where('status', 1)
            ->with(['shifts', 'plan'])
            ->get();

        foreach ($drivers as $driver) {
            // 1. Xóa cache để lần check tiếp theo dùng dữ liệu mới
            $driver->clearShiftCache();

            // 2. Kiểm tra nếu đang online mà hết ca → force offline
            if ($driver->is_online && !$driver->isInShift()) {
                $driver->update(['is_online' => false]);

                $driver->notify(new \App\Notifications\DriverAppNotification(
                    "Ca làm việc đã thay đổi",
                    "Ca {$shift->name} đã được cập nhật. Hệ thống tự động chuyển bạn sang Offline.",
                    "warning"
                ));

                Log::info("📴 [ShiftObserver] Driver #{$driver->id} bị offline do ca thay đổi.");
            }

            // 3. Push Firebase để Flutter app nhận shift data mới và reset timer
            try {
                FirebaseRTDBService::publishDriverProfile($driver->fresh(['shifts', 'plan']));
            } catch (\Throwable $e) {
                Log::error("Firebase sync lỗi cho driver #{$driver->id}: " . $e->getMessage());
            }
        }

        Log::info("✅ [ShiftObserver] Đã cập nhật {$drivers->count()} tài xế cho ca #{$shift->id}.");
    }
}
