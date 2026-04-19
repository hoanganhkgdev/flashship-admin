<?php

namespace App\Filament\Resources\DriverWalletResource\Pages;

use App\Filament\Resources\DriverWalletResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDriverWallet extends EditRecord
{
    protected static string $resource = DriverWalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
