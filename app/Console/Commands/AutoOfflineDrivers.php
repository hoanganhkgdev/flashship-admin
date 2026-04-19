<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\FirebaseRTDBService;
use Illuminate\Support\Facades\Log;

class AutoOfflineDrivers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'drivers:auto-offline';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Quét và tự động tắt Online đối với tài xế đã hết ca làm việc';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Bắt đầu quét tài xế hết ca...');

        // Lấy tất cả tài xế đang Online
        $onlineDrivers = User::role('driver')
            ->where('is_online', true)
            ->with(['shifts', 'plan'])
            ->get();

        $count = 0;

        foreach ($onlineDrivers as $user) {
            // Xóa cache để đảm bảo kiểm tra với dữ liệu mới nhất
            $user->clearShiftCache();

            // Kiểm tra xem có đang trong ca không
            if (!$user->isInShift()) {
                $user->update(['is_online' => false]);

                // 🚀 Sync real-time lên Firebase để App tài xế tự động chuyển sang Offline
                try {
                    FirebaseRTDBService::publishDriverProfile($user->fresh(['shifts', 'plan']));
                    FirebaseRTDBService::deleteDriverLocation($user->id);
                } catch (\Exception $e) {
                    Log::error("Lỗi sync Firebase cho driver #{$user->id}: " . $e->getMessage());
                }

                $this->warn("Đã tắt Online cho tài xế #{$user->id} ({$user->name}) do hết ca.");
                $count++;
            }
        }

        if ($count > 0) {
            $this->info("Đã hoàn tất! Tổng cộng tắt {$count} tài xế.");
        } else {
            $this->info("Không có tài xế nào hết ca.");
        }
    }
}
