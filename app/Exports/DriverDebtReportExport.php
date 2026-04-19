<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DriverDebtReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $collection;

    public function __construct($collection)
    {
        $this->collection = $collection;
    }

    public function collection()
    {
        return $this->collection;
    }

    public function headings(): array
    {
        return [
            'Tên tài xế',
            'Số điện thoại',
            'Khu vực',
            'Số khoản',
            'Quá hạn (số khoản)',
            'Tổng công nợ',
            'Đã thanh toán',
            'Còn phải thu',
            'Sô tiền quá hạn',
            'Kỳ gần nhất',
        ];
    }

    public function map($row): array
    {
        return [
            $row->driver->name ?? 'Không xác định',
            $row->driver->phone ?? '',
            $row->driver->city->name ?? '',
            $row->debt_count,
            $row->overdue_count,
            $row->total_due,
            $row->total_paid,
            $row->outstanding,
            $row->overdue_amount,
            $row->last_period ? \Carbon\Carbon::parse($row->last_period)->format('d/m/Y') : '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
