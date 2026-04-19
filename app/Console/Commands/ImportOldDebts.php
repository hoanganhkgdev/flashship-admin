<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DriverDebt;
use App\Models\User;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportOldDebts extends Command
{
    /**
     * php artisan debts:import-old {file}
     * Ví dụ: php artisan debts:import-old Danh_sach_cong_no_tuan_2026_03_16_2026_03_22.xlsx
     */
    protected $signature = 'debts:import-old
                            {file : Đường dẫn file Excel (tương đối từ thư mục gốc dự án)}
                            {--dry-run : Thử chạy, không lưu vào database}
                            {--amount-unit=1000 : Đơn vị tiền trong file (mặc định 1000 = nghìn đồng)}
                            {--note= : Ghi chú cho tất cả các phiếu (để trống dùng mặc định)}';

    protected $description = 'Import công nợ cũ từ file Excel xuất từ hệ thống cũ (định dạng đã biết)';

    public function handle(): int
    {
        $filePath   = $this->argument('file');
        $isDryRun   = $this->option('dry-run');
        $amountUnit = (int) $this->option('amount-unit');
        $customNote = $this->option('note') ?: null;

        // Xác định đường dẫn file
        $fullPath = file_exists($filePath) ? $filePath : base_path($filePath);

        if (!file_exists($fullPath)) {
            $this->error("❌ Không tìm thấy file: {$fullPath}");
            return Command::FAILURE;
        }

        $this->info("📂 Đọc file: {$fullPath}");
        if ($isDryRun) {
            $this->warn('🔄 Chế độ DRY-RUN: Không lưu vào database');
        }

        // Đọc Excel
        $spreadsheet = IOFactory::load($fullPath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            $this->error('❌ File Excel trống');
            return Command::FAILURE;
        }

        // In header để kiểm tra
        $headers = $rows[0] ?? [];
        $this->info('📋 Cột trong file: ' . implode(' | ', $headers));
        $this->newLine();

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        // Bỏ dòng header
        $dataRows = array_slice($rows, 1);
        $bar = $this->output->createProgressBar(count($dataRows));
        $bar->start();

        foreach ($dataRows as $i => $row) {
            $rowNum = $i + 2;
            $bar->advance();

            // Cấu trúc file: [0]=driver_id, [1]=debt_type, [2]=Từ ngày, [3]=Đến ngày, [4]=Công nợ, [5]=Đã thanh toán, [6]=Trạng thái, [7]=Tên tài xế
            $driverId   = (int) trim($row[0] ?? '');
            $weekStart  = trim($row[2] ?? '');
            $weekEnd    = trim($row[3] ?? '');
            $amountDue  = floatval(str_replace([',', ' '], '', trim($row[4] ?? '0'))) * $amountUnit;
            $amountPaid = floatval(str_replace([',', ' '], '', trim($row[5] ?? '0'))) * $amountUnit;
            $statusRaw  = trim($row[6] ?? '');
            $driverName = trim($row[7] ?? '');

            // Bỏ qua dòng trống
            if (!$driverId && !$driverName) {
                continue;
            }

            // Validate driver_id
            if (!$driverId) {
                $errors[] = "  Dòng {$rowNum}: Thiếu driver_id";
                $skipped++;
                continue;
            }

            if (!User::find($driverId)) {
                $errors[] = "  Dòng {$rowNum}: Không tìm thấy tài xế ID={$driverId} ({$driverName})";
                $skipped++;
                continue;
            }

            // Parse ngày (định dạng d/m/Y)
            try {
                $ws = Carbon::createFromFormat('d/m/Y', $weekStart)->toDateString();
                $we = Carbon::createFromFormat('d/m/Y', $weekEnd)->toDateString();
            } catch (\Exception $e) {
                // Thử parse tự động
                try {
                    $ws = Carbon::parse($weekStart)->toDateString();
                    $we = Carbon::parse($weekEnd)->toDateString();
                } catch (\Exception $e2) {
                    $errors[] = "  Dòng {$rowNum} (ID={$driverId}): Ngày không hợp lệ '{$weekStart}'";
                    $skipped++;
                    continue;
                }
            }

            // Map trạng thái
            $status = match (true) {
                in_array($statusRaw, ['Đã thanh toán', 'paid', 'Hoàn tất']) => 'paid',
                in_array($statusRaw, ['Quá hạn', 'overdue'])               => 'overdue',
                default                                                     => 'pending',
            };
            if ($amountPaid >= $amountDue && $amountDue > 0) {
                $status = 'paid';
            }

            // Kiểm tra trùng lặp
            $exists = DriverDebt::where('driver_id', $driverId)
                ->where('debt_type', 'weekly')
                ->where('week_start', $ws)
                ->where('week_end', $we)
                ->exists();

            if ($exists) {
                $errors[] = "  Dòng {$rowNum} (ID={$driverId} {$driverName}): Đã có công nợ tuần {$ws} → {$we}, bỏ qua";
                $skipped++;
                continue;
            }

            $note = $customNote ?: "Import từ hệ thống cũ — tuần {$weekStart} → {$weekEnd}";

            if (!$isDryRun) {
                DriverDebt::create([
                    'driver_id'   => $driverId,
                    'debt_type'   => 'weekly',
                    'week_start'  => $ws,
                    'week_end'    => $we,
                    'amount_due'  => (int) $amountDue,
                    'amount_paid' => (int) $amountPaid,
                    'status'      => $status,
                    'note'        => $note,
                ]);
            }

            $imported++;
        }

        $bar->finish();
        $this->newLine(2);

        // Kết quả
        $this->info("✅ Import thành công : {$imported} phiếu" . ($isDryRun ? ' (DRY-RUN)' : ''));

        if ($skipped > 0) {
            $this->warn("⚠️  Bỏ qua          : {$skipped} dòng");
            foreach ($errors as $err) {
                $this->line("<fg=yellow>{$err}</>");
            }
        }

        if (!$isDryRun && $imported > 0) {
            $this->newLine();
            $this->info("💰 Tổng tiền đã import: " . number_format(
                DriverDebt::whereIn('id', DriverDebt::latest()->take($imported)->pluck('id'))->sum('amount_due'),
                0, ',', '.'
            ) . ' ₫');
        }

        return Command::SUCCESS;
    }
}
