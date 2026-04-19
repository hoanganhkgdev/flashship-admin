<?php

namespace App\Filament\Resources\DeliverymanResource\Pages;

use App\Filament\Resources\DeliverymanResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDeliveryman extends CreateRecord
{
    protected static string $resource = DeliverymanResource::class;

    protected function afterCreate(): void
    {
        // Gán role driver cho user mới
        $this->record->assignRole('driver');
    }
}
