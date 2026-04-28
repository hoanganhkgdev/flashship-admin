<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FirebaseRTDBService;
use Illuminate\Console\Command;

class SyncDriverFirebase extends Command
{
    protected $signature = 'driver:sync-firebase {id? : ID tài xế cụ thể (bỏ trống = sync tất cả)}';

    protected $description = 'Re-sync profile tài xế lên Firebase RTDB để sửa data bị lỗi/sai';

    public function handle(): void
    {
        $id = $this->argument('id');

        $query = User::drivers()->with(['shifts', 'plan'])->where('status', 1);
        if ($id) {
            $query->where('id', $id);
        }

        $drivers = $query->get();

        if ($drivers->isEmpty()) {
            $this->error("Không tìm thấy tài xế" . ($id ? " với ID=$id" : ""));
            return;
        }

        $this->info("Đang sync {$drivers->count()} tài xế lên Firebase...");
        $bar = $this->output->createProgressBar($drivers->count());
        $bar->start();

        $ok = 0;
        foreach ($drivers as $driver) {
            try {
                FirebaseRTDBService::publishDriverProfile($driver->fresh(['shifts', 'plan']));
                $ok++;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("Lỗi driver #{$driver->id} ({$driver->name}): " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Sync xong $ok/{$drivers->count()} tài xế.");
    }
}
