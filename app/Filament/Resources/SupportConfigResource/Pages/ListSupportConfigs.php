<?php

namespace App\Filament\Resources\SupportConfigResource\Pages;

use App\Filament\Resources\SupportConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupportConfigs extends ListRecords
{
    protected static string $resource = SupportConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
