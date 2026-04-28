<?php

namespace App\Filament\Resources\DriverLicenseResource\Pages;

use App\Filament\Resources\DriverLicenseResource;
use App\Models\DriverLicense;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDriverLicense extends ViewRecord
{
    protected static string $resource = DriverLicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Duyệt hồ sơ')
                ->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn() => $this->record->status === DriverLicense::STATUS_PENDING)
                ->requiresConfirmation()
                ->modalHeading('Duyệt hồ sơ bằng lái')
                ->modalDescription(fn() => "Xác nhận bằng lái xe của {$this->record->user?->name} hợp lệ?")
                ->action(function () {
                    $this->record->update(['status' => DriverLicense::STATUS_APPROVED]);
                    $this->record->user?->update(['has_car_license' => true]);
                    Notification::make()->title('Đã duyệt hồ sơ bằng lái')->success()->send();
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Actions\Action::make('reject')
                ->label('Từ chối')
                ->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn() => $this->record->status === DriverLicense::STATUS_PENDING)
                ->requiresConfirmation()
                ->modalHeading('Từ chối hồ sơ')
                ->modalDescription('Đánh dấu bằng lái không đạt. Tài xế có thể gửi lại hồ sơ mới.')
                ->action(function () {
                    $this->record->update(['status' => DriverLicense::STATUS_REJECTED]);
                    $this->record->user?->update(['has_car_license' => false]);
                    Notification::make()->title('Đã từ chối hồ sơ')->danger()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\EditAction::make()->label('Chỉnh sửa'),
        ];
    }
}
