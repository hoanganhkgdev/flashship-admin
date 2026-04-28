<?php

namespace App\Filament\Resources\DeliverymanResource\Pages;

use App\Filament\Resources\DeliverymanResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDeliveryman extends ViewRecord
{
    protected static string $resource = DeliverymanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Duyệt tài xế')
                ->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn() => (int) $this->record->status === 0)
                ->requiresConfirmation()
                ->modalHeading('Duyệt tài xế')
                ->modalDescription(fn() => "Kích hoạt tài khoản cho {$this->record->name}?")
                ->action(function () {
                    $this->record->update(['status' => 1]);
                    Notification::make()->title('Đã duyệt tài xế')->success()->send();
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Actions\Action::make('block')
                ->label('Khóa tài khoản')
                ->icon('heroicon-o-lock-closed')->color('danger')
                ->visible(fn() => (int) $this->record->status === 1)
                ->requiresConfirmation()
                ->modalHeading('Khóa tài khoản')
                ->modalDescription(fn() => "Khóa và đưa {$this->record->name} về trạng thái ngoại tuyến?")
                ->action(function () {
                    $this->record->update(['status' => 2, 'is_online' => false]);
                    Notification::make()->title('Đã khóa tài khoản')->danger()->send();
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Actions\Action::make('unblock')
                ->label('Mở khóa')
                ->icon('heroicon-o-lock-open')->color('success')
                ->visible(fn() => (int) $this->record->status === 2)
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 1]);
                    Notification::make()->title('Đã mở khóa tài khoản')->success()->send();
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Actions\EditAction::make()->label('Chỉnh sửa'),
        ];
    }
}
