<?php

namespace App\Imports;

use App\Models\DriverDebt;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DriverDebtImport implements ToModel, SkipsEmptyRows
{
    public function model(array $row)
    {
        // Log raw row để debug
        Log::info('📥 [Import Tuần] Raw row: ' . json_encode($row));

        // Bỏ qua dòng tiêu đề hoặc dòng không có ID tài xế hợp lệ
        if (!isset($row[0]) || !is_numeric($row[0])) {
            Log::info('⏭ Bỏ qua dòng (không phải số): ' . ($row[0] ?? 'null'));
            return null;
        }

        $driverId  = (int) $row[0];
        $weekStart = $this->transformDate($row[2] ?? null);
        $weekEnd   = $this->transformDate($row[3] ?? null);

        Log::info("🔍 driverId={$driverId} | weekStart={$weekStart} | weekEnd={$weekEnd}");

        if (!$driverId || !$weekStart || !$weekEnd) {
            Log::warning("⚠ Bỏ qua dòng vì thiếu dữ liệu: driverId={$driverId} weekStart={$weekStart} weekEnd={$weekEnd}");
            return null;
        }

        // Tránh trùng lặp
        $exists = DriverDebt::where('driver_id', $driverId)
            ->where('debt_type', 'weekly')
            ->where('week_start', $weekStart)
            ->where('week_end', $weekEnd)
            ->exists();

        if ($exists) {
            Log::info("🔄 Bỏ qua (đã tồn tại): tài xế #{$driverId} tuần {$weekStart} ~ {$weekEnd}");
            return null;
        }

        $amountDue  = (float) str_replace(['.', ','], '', $row[4] ?? 0);
        $amountPaid = (float) str_replace(['.', ','], '', $row[5] ?? 0);
        $status     = $this->mapStatus($row[6] ?? 'Chưa thanh toán');

        Log::info("✅ Tạo công nợ: driver={$driverId} | due={$amountDue} | paid={$amountPaid} | status={$status}");

        try {
            return new DriverDebt([
                'driver_id'   => $driverId,
                'debt_type'   => 'weekly',
                'week_start'  => $weekStart,
                'week_end'    => $weekEnd,
                'amount_due'  => $amountDue,
                'amount_paid' => $amountPaid,
                'status'      => $status,
                'note'        => 'Import từ file Excel ngày ' . now()->format('d/m/Y'),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Lỗi tạo bản ghi: ' . json_encode($row) . ' - ' . $e->getMessage());
            return null;
        }
    }

    private function transformDate($value)
    {
        if (empty($value)) return null;

        // Nếu là số → Excel Serial Date
        if (is_numeric($value)) {
            try {
                $result = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
                Log::info("📅 Excel Serial → {$result}");
                return $result;
            } catch (\Exception $e) {
                Log::warning("⚠ Lỗi parse Excel Serial: {$value} | " . $e->getMessage());
                return null;
            }
        }

        // Nếu là chuỗi → parse d/m/Y
        try {
            $result = Carbon::createFromFormat('d/m/Y', trim($value))->format('Y-m-d');
            Log::info("📅 String d/m/Y → {$result}");
            return $result;
        } catch (\Exception $e) {
            Log::warning("⚠ Lỗi parse chuỗi ngày: '{$value}' | " . $e->getMessage());
            return null;
        }
    }

    private function mapStatus($text)
    {
        $text = mb_strtolower(trim($text));
        if (str_contains($text, 'đã') || str_contains($text, 'hoàn tất') || str_contains($text, 'paid')) return 'paid';
        if (str_contains($text, 'quá hạn') || str_contains($text, 'overdue')) return 'overdue';
        return 'pending';
    }
}
