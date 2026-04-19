<?php

namespace App\Filament\Resources\DeliverymanResource\Pages;

use App\Filament\Resources\DeliverymanResource;
use App\Models\Plan;
use App\Services\FirebaseRTDBService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryman extends EditRecord
{
    protected static string $resource = DeliverymanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterSave(): void
    {
        $driver = $this->record;
        
        // 1. Đồng bộ lên Firebase RTDB ngay lập tức
        \App\Services\FirebaseRTDBService::publishDriverProfile($driver);

        // 2. Gửi thông báo hệ thống cho tài xế
        $driver->notify(new \App\Notifications\DriverAppNotification(
            "Cập nhật ca làm việc",
            "Admin đã cập nhật thông tin ca trực hoặc hồ sơ của bạn. Vui lòng kiểm tra lại.",
            "info"
        ));
    }
}
