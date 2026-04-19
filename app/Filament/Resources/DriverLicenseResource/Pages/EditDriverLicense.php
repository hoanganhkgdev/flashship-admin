<?php

namespace App\Filament\Resources\DriverLicenseResource\Pages;

use App\Filament\Resources\DriverLicenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDriverLicense extends EditRecord
{
    protected static string $resource = DriverLicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $license = $this->record;

        if ($license->status === 'approved') {
            $license->user->update(['has_car_license' => true]);
        } else {
            $license->user->update(['has_car_license' => false]);
        }
    }
}
