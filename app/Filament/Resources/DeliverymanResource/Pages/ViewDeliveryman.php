<?php

namespace App\Filament\Resources\DeliverymanResource\Pages;

use App\Filament\Resources\DeliverymanResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDeliveryman extends ViewRecord
{
    protected static string $resource = DeliverymanResource::class;


    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([]); // bỏ form mặc định
    }
}
