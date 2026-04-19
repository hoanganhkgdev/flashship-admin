<?php

namespace App\Console\Commands;

use App\Models\ZaloAccount;
use App\Services\ZaloService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ZaloRefreshTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zalo:refresh-token {--oa_id= : Refresh specifically for this OA ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Làm mới Access Token cho tất cả Zalo OA đang hoạt động';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $oaId = $this->option('oa_id');

        $query = ZaloAccount::where('is_active', true);

        if ($oaId) {
            $query->where('oa_id', $oaId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info('Không tìm thấy tài khoản Zalo OA nào đang hoạt động để làm mới.');
            return 0;
        }

        $this->info('Đang bắt đầu làm mới token cho ' . $accounts->count() . ' tài khoản...');

        foreach ($accounts as $account) {
            /** @var ZaloAccount $account */
            $this->info("Đang xử lý OA: {$account->name} ({$account->oa_id})");

            $zaloService = new ZaloService($account);
            if ($zaloService->refreshAccessToken($account)) {
                $this->info("✅ Làm mới thành công cho OA {$account->name}");
            } else {
                $this->error("❌ Thất bại khi làm mới cho OA {$account->name}. Kiểm tra logs.");
            }
        }

        $this->info('Hoàn tất quá trình làm mới token.');
        return 0;
    }
}
