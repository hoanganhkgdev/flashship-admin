<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getRedirectUrl(): string
    {
        // Quay lại danh sách đơn sau khi sửa
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['shipping_fee'] = CreateOrder::normalizeFee($data['shipping_fee'] ?? 0);
        $data['bonus_fee']    = CreateOrder::normalizeFee($data['bonus_fee'] ?? 0);

        // Set completed_at khi admin đổi tay status sang completed qua form
        if (($data['status'] ?? '') === 'completed' && $this->record->status !== 'completed') {
            $data['completed_at'] = now();
        }

        return $data;
    }
}

