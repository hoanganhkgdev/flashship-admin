<?php

namespace App\Filament\Resources\DriverDebtResource\Pages;

use App\Filament\Resources\DriverDebtResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDriverDebt extends EditRecord
{
    protected static string $resource = DriverDebtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        // Redirect về commission nếu là commission, weekly nếu là weekly
        $debtType = $this->record->debt_type ?? 'commission';
        return $debtType === 'weekly' 
            ? DriverDebtResource::getUrl('weekly')
            : DriverDebtResource::getUrl('commission');
    }
}
