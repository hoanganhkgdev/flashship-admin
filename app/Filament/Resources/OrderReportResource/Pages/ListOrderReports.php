<?php

namespace App\Filament\Resources\OrderReportResource\Pages;

use App\Filament\Resources\OrderReportResource;
use Filament\Resources\Pages\ListRecords;

class ListOrderReports extends ListRecords
{
    protected static string $resource = OrderReportResource::class;

    /**
     * Override để dùng report_date làm key thay cho id.
     */
    public function getTableRecordKey($record): string   // 👈 phải là public
    {
        return (string) $record->report_date;
    }
}
