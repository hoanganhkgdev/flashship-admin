<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RevenueReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected Collection $data,
        protected string $mode
    ) {}

    public function collection(): Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Kỳ báo cáo',
            'Tổng đơn',
            'Hoàn thành',
            'Đã hủy',
            'Tỉ lệ HT (%)',
            'Doanh thu ship (đ)',
            'Bonus (đ)',
            'Tổng doanh thu (đ)',
        ];
    }

    public function map($row): array
    {
        $rate = $row->total_orders > 0
            ? round($row->completed_orders / $row->total_orders * 100, 1)
            : 0;

        if ($this->mode === 'month') {
            $label = 'Tháng ' . $row->mo . '/' . $row->yr;
        } else {
            $start = Carbon::parse($row->week_start)->format('d/m/Y');
            $end   = Carbon::parse($row->week_end)->format('d/m/Y');
            $label = "Tuần {$row->week_num}/{$row->yr} ({$start} - {$end})";
        }

        return [
            $label,
            (int)   $row->total_orders,
            (int)   $row->completed_orders,
            (int)   $row->cancelled_orders,
            $rate,
            (float) $row->total_ship_fee,
            (float) $row->total_bonus_fee,
            (float) $row->total_revenue,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
