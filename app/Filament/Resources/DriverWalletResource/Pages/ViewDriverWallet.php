<?php

namespace App\Filament\Resources\DriverWalletResource\Pages;

use App\Filament\Resources\DriverWalletResource;
use App\Services\DriverWalletService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDriverWallet extends ViewRecord
{
    protected static string $resource = DriverWalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('adjust_credit')
                ->label('Cộng tiền')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('Số tiền cộng (₫)')
                        ->numeric()->minValue(1000)->required(),
                    Forms\Components\Textarea::make('description')
                        ->label('Lý do')->required()->rows(2),
                ])
                ->action(function (array $data) {
                    DriverWalletService::adjust(
                        $this->record->driver_id,
                        (float) $data['amount'],
                        'credit',
                        $data['description'],
                        'manual_credit_' . $this->record->driver_id . '_' . now()->timestamp
                    );
                    Notification::make()
                        ->title('Đã cộng ' . number_format($data['amount'], 0, ',', '.') . '₫ vào ví')
                        ->success()->send();
                    $this->record->refresh();
                }),

            Actions\Action::make('adjust_debit')
                ->label('Trừ tiền')
                ->icon('heroicon-o-minus-circle')
                ->color('danger')
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('Số tiền trừ (₫)')
                        ->numeric()->minValue(1000)->required(),
                    Forms\Components\Textarea::make('description')
                        ->label('Lý do')->required()->rows(2),
                ])
                ->action(function (array $data) {
                    try {
                        DriverWalletService::adjust(
                            $this->record->driver_id,
                            (float) $data['amount'],
                            'debit',
                            $data['description'],
                            'manual_debit_' . $this->record->driver_id . '_' . now()->timestamp,
                            true
                        );
                        Notification::make()
                            ->title('Đã trừ ' . number_format($data['amount'], 0, ',', '.') . '₫ khỏi ví')
                            ->success()->send();
                        $this->record->refresh();
                    } catch (\Exception $e) {
                        Notification::make()->title('Lỗi: ' . $e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
