<?php

namespace App\Filament\Resources\WithdrawRequestResource\Pages;

use App\Filament\Resources\WithdrawRequestResource;
use App\Services\DriverWalletService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWithdrawRequest extends ViewRecord
{
    protected static string $resource = WithdrawRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve_payout')
                ->label('Duyệt & Chuyển tiền')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->status === 'pending')
                ->action(function () {
                    $result = WithdrawRequestResource::executePayout($this->record, false);
                    $n = Notification::make()->title($result['message']);
                    $result['success'] ? $n->success()->send() : $n->danger()->send();
                    $this->record->refresh();
                }),

            Actions\Action::make('retry_payout')
                ->label('Thử lại chuyển khoản')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->status === 'failed')
                ->action(function () {
                    $result = WithdrawRequestResource::executePayout($this->record, true);
                    $n = Notification::make()->title($result['message']);
                    $result['success'] ? $n->success()->send() : $n->danger()->send();
                    $this->record->refresh();
                }),

            Actions\Action::make('reject')
                ->label('Từ chối & Hoàn tiền')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn() => in_array($this->record->status, ['pending', 'failed']))
                ->form([
                    Forms\Components\Textarea::make('note')
                        ->label('Lý do từ chối')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $record = $this->record;
                    $record->update(['status' => 'rejected', 'note' => $data['note']]);
                    DriverWalletService::adjust(
                        $record->driver_id,
                        $record->amount,
                        'credit',
                        'Hoàn tiền yêu cầu rút #' . $record->id . ': ' . $data['note'],
                        'withdraw_reject_' . $record->id
                    );
                    \App\Services\NotificationService::notifyWithdrawStatus($record, 'rejected');
                    Notification::make()->title('Đã từ chối và hoàn tiền vào ví')->success()->send();
                    $this->record->refresh();
                }),
        ];
    }
}
