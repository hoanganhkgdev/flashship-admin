<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawRequestResource\Pages;
use App\Models\WithdrawRequest;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Services\DriverWalletService;
use App\Services\PayOSService;
use Filament\Notifications\Notification;

use App\Helpers\FcmHelper;
use App\Services\BankCodeService;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Support\Enums\FontWeight;

class WithdrawRequestResource extends Resource
{
    protected static ?string $model = WithdrawRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Lịch sử rút tiền';
    protected static ?string $modelLabel = 'yêu cầu rút tiền';
    protected static ?string $pluralModelLabel = 'Danh sách yêu cầu rút tiền';
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }


    public static function getNavigationGroup(): ?string
    {
        return 'TÀI CHÍNH';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Đang chờ',
                    'approved' => 'Đã duyệt',
                    'rejected' => 'Từ chối',
                    'failed' => 'Thất bại',
                ])
                ->required(),
            Forms\Components\Textarea::make('note'),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Thông tin yêu cầu')
                    ->schema([
                        Components\Grid::make(3)->schema([
                            Components\TextEntry::make('driver.name')
                                ->label('Tài xế yêu cầu')
                                ->weight(FontWeight::Bold)
                                ->color('primary'),
                            Components\TextEntry::make('amount')
                                ->label('Số tiền rút')
                                ->money('VND')
                                ->weight(FontWeight::Bold)
                                ->color('success'),
                            Components\TextEntry::make('status')
                                ->label('Trạng thái')
                                ->badge()
                                ->formatStateUsing(fn($state) => match ($state) {
                                    'pending' => 'Đang chờ',
                                    'approved' => 'Thành công',
                                    'rejected' => 'Đã từ chối',
                                    'failed' => 'Thất bại',
                                    default => $state,
                                })
                                ->colors([
                                    'warning' => 'pending',
                                    'success' => 'approved',
                                    'danger' => 'rejected',
                                    'gray' => 'failed',
                                ]),
                        ]),
                    ]),

                Components\Section::make('Thông tin thụ hưởng')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        Components\Grid::make(3)->schema([
                            Components\TextEntry::make('driver.bank.bank_owner')
                                ->label('Tên chủ tài khoản')
                                ->placeholder('Chưa cài đặt'),
                            Components\TextEntry::make('driver.bank.bank_code')
                                ->label('Ngân hàng')
                                ->placeholder('N/A'),
                            Components\TextEntry::make('driver.bank.bank_account')
                                ->label('Số tài khoản')
                                ->placeholder('N/A')
                                ->copyable(),
                        ]),
                    ]),

                Components\Section::make('Ghi chú hệ thống')
                    ->schema([
                        Components\TextEntry::make('note')
                            ->label('Nội dung/Lý do')
                            ->placeholder('Không có ghi chú')
                            ->columnSpanFull(),
                    ])->visible(fn($record) => filled($record->note)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('stt')
                    ->label('STT')
                    ->rowIndex()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Tài xế')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn($record) => $record->driver?->phone ?? ''),

                Tables\Columns\TextColumn::make('bank_info')
                    ->label('Thông tin thụ hưởng')
                    ->getStateUsing(function ($record) {
                        $bank = $record->driver?->bank;
                        if (!$bank)
                            return 'Chưa cài đặt';
                        return $bank->bank_owner ?? 'N/A';
                    })
                    ->description(function ($record) {
                        $bank = $record->driver?->bank;
                        if (!$bank)
                            return null;
                        $parts = array_filter([
                            $bank->bank_name ?? $bank->bank_code,
                            $bank->bank_account,
                        ]);
                        return \Illuminate\Support\Str::limit(implode(' · ', $parts), 20);
                    })
                    ->limit(20)
                    ->tooltip(fn($state) => $state)
                    ->color('primary'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền rút')
                    ->money('VND')
                    ->alignCenter()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->alignCenter()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending'  => 'Đang chờ',
                        'approved' => 'Thành công',
                        'rejected' => 'Đã từ chối',
                        'failed'   => 'Thất bại',
                        default    => $state,
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                        'gray'    => 'failed',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Thời gian yêu cầu')
                    ->dateTime('d/m/Y H:i')
                    ->alignCenter()
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Xoá đã chọn'),
                ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'pending' => 'Đang chờ',
                        'approved' => 'Thành công',
                        'rejected' => 'Đã từ chối',
                        'failed' => 'Thất bại',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve_payout')
                    ->label('Duyệt & Chuyển tiền (PayOS)')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Duyệt & Chuyển tiền')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        $payos = new PayOSService('payout');
                        $bank = $record->driver->bank;

                        if (!$bank || !$bank->bank_code || !$bank->bank_account) {
                            Notification::make()->title('Tài xế chưa cấu hình ngân hàng')->danger()->send();
                            return;
                        }

                        $result = $payos->createPayout(
                            "WD-" . $record->id,
                            (int) $record->amount,
                            $bank->bank_code,
                            $bank->bank_account,
                            "Payout ID " . $record->id
                        );

                        if (isset($result['code']) && $result['code'] === '00') {
                            $record->update(['status' => 'approved']);
                            \App\Services\NotificationService::notifyWithdrawStatus($record, 'approved');

                            Notification::make()->title('Chuyển khoản thành công')->success()->send();
                        } else {
                            $errorMessage = $result['message'] ?? 'Lỗi hệ thống PayOS';
                            $record->update(['status' => 'failed', 'note' => $errorMessage]);
                            Notification::make()->title('Chuyển khoản thất bại')->body($errorMessage)->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('retry_payout')
                    ->label('Thử lại qua PayOS')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Thử lại chuyển khoản')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'failed')
                    ->action(function ($record) {
                        $payos = new PayOSService('payout');
                        $bank = $record->driver->bank;

                        if (!$bank || !$bank->bank_code || !$bank->bank_account) {
                            Notification::make()->title('Tài xế chưa cấu hình ngân hàng')->danger()->send();
                            return;
                        }

                        $result = $payos->createPayout(
                            "WD-" . $record->id . "-" . time(),
                            (int) $record->amount,
                            $bank->bank_code,
                            $bank->bank_account,
                            "Retry Payout ID " . $record->id
                        );

                        if (isset($result['code']) && $result['code'] === '00') {
                            $record->update(['status' => 'approved', 'note' => null]);
                            \App\Services\NotificationService::notifyWithdrawStatus($record, 'approved');

                            Notification::make()->title('Chuyển khoản thành công')->success()->send();
                        } else {
                            $errorMessage = $result['message'] ?? 'Lỗi hệ thống PayOS';
                            $record->update(['note' => $errorMessage]);
                            Notification::make()->title('Chuyển khoản vẫn thất bại')->body($errorMessage)->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Từ chối yêu cầu')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Từ chối & hoàn tiền')
                    ->requiresConfirmation()
                    ->visible(fn($record) => in_array($record->status, ['pending', 'failed']))
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Lý do từ chối')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'note' => $data['note'],
                        ]);
                        DriverWalletService::adjust(
                            $record->driver_id,
                            $record->amount,
                            'credit',
                            'Hoàn tiền yêu cầu rút #' . $record->id . ': ' . $data['note'],
                            'withdraw_reject_' . $record->id
                        );

                        \App\Services\NotificationService::notifyWithdrawStatus($record, 'rejected');

                        Notification::make()->title('Đã từ chối và hoàn tiền')->success()->send();
                    }),

                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->tooltip('Xem chi tiết'),

                Tables\Actions\Action::make('view_driver')
                    ->label('Xem hồ sơ tài xế')
                    ->icon('heroicon-o-user-circle')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Xem hồ sơ tài xế')
                    ->url(fn($record) => DeliverymanResource::getUrl('view', ['record' => $record->driver_id])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawRequests::route('/'),
            'edit' => Pages\EditWithdrawRequest::route('/{record}/edit'),
        ];
    }


    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin']);
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin']);
    }

    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin',]);
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // 👑 Admin: xem theo vùng đang chọn
        if ($user->hasRole('admin') && session()->has('current_city_id')) {
            $cityId = session('current_city_id');
            $query->whereHas('driver', fn($q) => $q->where('city_id', $cityId));
        }
        // 👨‍💼 Manager / Dispatcher: cố định theo city_id của họ
        elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $cityId = $user->city_id;
            $query->whereHas('driver', fn($q) => $q->where('city_id', $cityId));
        }

        return $query;
    }
}
