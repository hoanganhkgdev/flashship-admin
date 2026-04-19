<?php

namespace App\Filament\Resources\AreaReportResource\Pages;

use App\Filament\Resources\AreaReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAreaReports extends ListRecords
{
    protected static string $resource = AreaReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
