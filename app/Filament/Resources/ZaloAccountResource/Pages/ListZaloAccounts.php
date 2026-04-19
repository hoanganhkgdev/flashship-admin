<?php

namespace App\Filament\Resources\ZaloAccountResource\Pages;

use App\Filament\Resources\ZaloAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListZaloAccounts extends ListRecords
{
    protected static string $resource = ZaloAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
