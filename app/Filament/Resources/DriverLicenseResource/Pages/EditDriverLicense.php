<?php

namespace App\Filament\Resources\DriverLicenseResource\Pages;

use App\Filament\Resources\DriverLicenseResource;
use App\Models\DriverLicense;
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function afterSave(): void
    {
        $license = $this->record;

        if ($license->status === DriverLicense::STATUS_APPROVED) {
            $license->user->update(['has_car_license' => true]);
        } else {
            $license->user->update(['has_car_license' => false]);
        }
    }
}
