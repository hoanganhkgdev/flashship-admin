<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DriverCashReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected Collection $data,
        protected string $period
    ) {}

    public function collection(): Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'STT',
            'Tài xế',
            'SĐT',
            "Thu - đã đóng ({$this->period}) (đ)",
            "Chi - đã rút ({$this->period}) (đ)",
            'Chênh lệch (đ)',
        ];
    }

    public function map($row): array
    {
        $thu = (float) $row->thu;
        $chi = (float) $row->chi;
        return [
            $row->stt,
            $row->name,
            $row->phone,
            $thu,
            $chi,
            $thu - $chi,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
