<?php

namespace App\Filament\Resources\DeliverymanResource\Pages;

use App\Filament\Resources\DeliverymanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

use Illuminate\Database\Eloquent\Builder;

class ListDeliverymen extends ListRecords
{
    protected static string $resource = DeliverymanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('driver_simulator')
                ->label('Giả lập Tài xế')
                ->icon('heroicon-o-truck')
                ->color('warning')
                ->url(fn(): string => DeliverymanResource::getUrl('simulator')),
            Actions\CreateAction::make(),
        ];
    }
}



