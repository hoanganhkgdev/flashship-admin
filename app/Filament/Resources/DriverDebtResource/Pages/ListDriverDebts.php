<?php

namespace App\Filament\Resources\DriverDebtResource\Pages;

use App\Filament\Resources\DriverDebtResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDriverDebts extends ListRecords
{
    protected static string $resource = DriverDebtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
