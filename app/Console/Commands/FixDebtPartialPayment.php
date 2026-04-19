<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DriverDebt;
use Illuminate\Support\Facades\DB;

class FixDebtPartialPayment extends Command
{
    protected $signature = 'debt:fix-partial-payment';
    protected $description = 'Sửa các công nợ đã thanh toán nhưng amount_paid < amount_due (thiếu tiền)';

    public function handle()
    {
        $this->info('🔍 Đang tìm các công nợ đã thanh toán nhưng thiếu tiền...');

        // Tìm các record có status = 'paid' nhưng amount_paid < amount_due
        $wrongDebts = DriverDebt::where('status', 'paid')
            ->whereRaw('amount_paid < amount_due')
            ->get();

        if ($wrongDebts->isEmpty()) {
            $this->info('✅ Không có công nợ nào cần sửa. Tất cả đều đúng!');
            return Command::SUCCESS;
        }

        $this->warn("⚠️  Tìm thấy {$wrongDebts->count()} công nợ có vấn đề:");
        $this->newLine();

        $fixedCount = 0;
        $totalMissing = 0;

        foreach ($wrongDebts as $debt) {
            $missing = $debt->amount_due - $debt->amount_paid;
            $totalMissing += $missing;

            $this->line("  📋 Công nợ #{$debt->id} (Tài xế #{$debt->driver_id}):");
            $this->line("     - Chiết khấu: " . number_format($debt->amount_due, 0, ',', '.') . " ₫");
            $this->line("     - Đã thanh toán: " . number_format($debt->amount_paid, 0, ',', '.') . " ₫");
            $this->line("     - ❌ Thiếu: " . number_format($missing, 0, ',', '.') . " ₫");
            
            // Sửa: set amount_paid = amount_due (đã thanh toán đủ)
            $debt->amount_paid = $debt->amount_due;
            $debt->status = 'paid'; // Đảm bảo status vẫn là paid
            $debt->save();
            
            $fixedCount++;
            $this->info("     ✅ Đã sửa: amount_paid = " . number_format($debt->amount_due, 0, ',', '.') . " ₫");
            $this->newLine();
        }

        $this->info("✅ Đã sửa {$fixedCount} công nợ.");
        $this->warn("💰 Tổng số tiền thiếu đã được sửa: " . number_format($totalMissing, 0, ',', '.') . " ₫");

        return Command::SUCCESS;
    }
}

