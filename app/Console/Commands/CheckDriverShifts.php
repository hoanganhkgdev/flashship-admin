<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FirebaseRTDBService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckDriverShifts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'driver:check-shifts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quét và tự động Offline tài xế nếu hết ca làm việc';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Lấy tất cả driver đang online — để isInShift() tự quyết định
        // (commission/custom_rate/no-plan → isInShift() trả về true → không bị offline)
        $onlineDrivers = User::drivers()
            ->where('is_online', true)
            ->with(['shifts', 'plan'])
            ->get();

        Log::info("[Auto-Offline] Bắt đầu quét: {$onlineDrivers->count()} driver đang online.");
        $this->info("Tìm thấy {$onlineDrivers->count()} driver đang online.");

        $offlineCount = 0;

        foreach ($onlineDrivers as $driver) {
            // Xóa cache để đảm bảo kiểm tra với dữ liệu mới nhất
            $driver->clearShiftCache();

            // 2. Kiểm tra xem hiện tại có nằm trong ca nào không
            if (!$driver->isInShift()) {
                // 3. Nếu HẾT CA -> Chuyển sang Offline
                $driver->update(['is_online' => false]);

                // 🔔 THÊM THÔNG BÁO VÀO LỊCH SỬ CHO TÀI XẾ
                $shiftNames = $driver->shifts->pluck('name')->implode(', ');
                try {
                    $driver->notify(new \App\Notifications\DriverAppNotification(
                        "Đã hết ca làm việc",
                        "Hệ thống đã tự động chuyển bạn sang trạng thái Offline do hết ca {$shiftNames}.",
                        "warning"
                    ));
                } catch (\Throwable $e) {
                    Log::warning("[Auto-Offline] Gửi thông báo lỗi driver #{$driver->id}: " . $e->getMessage());
                }

                // 🚀 Đẩy Profile mới lên Firebase RTDB để App nhận tín hiệu
                try {
                    FirebaseRTDBService::publishDriverProfile($driver);
                    FirebaseRTDBService::deleteDriverLocation($driver->id);
                } catch (\Throwable $e) {
                    Log::warning("[Auto-Offline] Firebase sync lỗi driver #{$driver->id}: " . $e->getMessage());
                }

                Log::info("[Auto-Offline] Driver #{$driver->id} ({$driver->name}) offline do hết ca.");
                $this->info("✓ Driver #{$driver->id} đã được Offline.");
                $offlineCount++;
            }
        }

        if ($offlineCount > 0) {
            $this->warn("Đã tự động Offline xong $offlineCount tài xế.");
        }
    }
}
