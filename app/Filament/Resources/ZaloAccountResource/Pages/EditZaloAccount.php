<?php

namespace App\Filament\Resources\ZaloAccountResource\Pages;

use App\Filament\Resources\ZaloAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditZaloAccount extends EditRecord
{
    protected static string $resource = ZaloAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
