<?php

namespace App\Imports;

use App\Models\DriverDebt;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DriverCommissionDebtImport implements ToModel, SkipsEmptyRows
{
    /**
     * Cấu trúc file Excel:
     * Cột A (0): ID Tài xế
     * Cột B (1): Ngày đối soát (dd/mm/yyyy)
     * Cột C (2): Số tiền chiết khấu (VNĐ)
     * Cột D (3): Đã thanh toán (VNĐ) - tuỳ chọn
     * Cột E (4): Trạng thái (Chưa thanh toán / Đã thanh toán / Quá hạn)
     */
    public function model(array $row)
    {
        Log::info('📥 [Import CK] Raw row: ' . json_encode($row));

        // Bỏ qua dòng tiêu đề hoặc dòng không có ID hợp lệ
        if (!isset($row[0]) || !is_numeric($row[0])) {
            Log::info('⏭ [Import CK] Bỏ qua - không phải ID số: ' . json_encode($row[0] ?? null));
            return null;
        }

        $driverId = (int) $row[0]; // Cột A
        $date     = $this->transformDate($row[1] ?? null); // Cột B

        Log::info("🔍 [Import CK] driverId={$driverId} | date={$date} | raw_date=" . json_encode($row[1] ?? null));

        if (!$driverId || !$date) {
            Log::warning("⚠ [Import CK] Bỏ qua - thiếu dữ liệu: driverId={$driverId} date={$date}");
            return null;
        }

        // Tránh trùng lặp: cùng tài xế + cùng ngày
        $exists = DriverDebt::where('driver_id', $driverId)
            ->where('debt_type', 'commission')
            ->where('date', $date)
            ->exists();

        if ($exists) {
            Log::info("🔄 [Import CK] Bỏ qua (đã tồn tại): driver={$driverId} date={$date}");
            return null;
        }

        try {
            Log::info("✅ [Import CK] Tạo bản ghi: driver={$driverId} date={$date}");
            return new DriverDebt([
                'driver_id'   => $driverId,
                'debt_type'   => 'commission',
                'date'        => $date,
                'week_start'  => null,
                'week_end'    => null,
                'amount_due'  => (float) str_replace(['.', ','], '', $row[2] ?? 0), // Cột C
                'amount_paid' => (float) str_replace(['.', ','], '', $row[3] ?? 0), // Cột D
                'status'      => $this->mapStatus($row[4] ?? 'Chưa thanh toán'),    // Cột E
                'note'        => 'Import từ file Excel ngày ' . now()->format('d/m/Y'),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ [Import CK] Lỗi tạo bản ghi: ' . json_encode($row) . ' - ' . $e->getMessage());
            return null;
        }
    }

    private function transformDate($value)
    {
        if (empty($value)) return null;

        // Nếu là số → Excel Serial Date
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        // Nếu là chuỗi → parse d/m/Y
        try {
            return Carbon::createFromFormat('d/m/Y', trim($value))->format('Y-m-d');
        } catch (\Exception $e) {
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
