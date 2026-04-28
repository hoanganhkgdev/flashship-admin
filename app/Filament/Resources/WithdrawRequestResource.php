<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawRequestResource\Pages;
use App\Models\WithdrawRequest;
use App\Services\DriverWalletService;
use App\Services\PayOSService;
use Filament\Forms;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class WithdrawRequestResource extends Resource
{
    protected static ?string $model = WithdrawRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Lịch sử rút tiền';
    protected static ?string $modelLabel = 'yêu cầu rút tiền';
    protected static ?string $pluralModelLabel = 'Danh sách yêu cầu rút tiền';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'TÀI CHÍNH';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Xử lý PayOS payout — dùng chung cho approve và retry action.
     * Trả về ['success' => bool, 'message' => string]
     */
    public static function executePayout(WithdrawRequest $record, bool $isRetry = false): array
    {
        $bank = $record->driver?->bank;

        if (!$bank || !$bank->bank_code || !$bank->bank_account) {
            return ['success' => false, 'message' => 'Tài xế chưa cấu hình ngân hàng'];
        }

        $ref  = $isRetry ? "WD-{$record->id}-" . time() : "WD-{$record->id}";
        $desc = $isRetry ? "Retry Payout ID {$record->id}" : "Payout ID {$record->id}";

        $result = (new PayOSService('payout'))->createPayout(
            $ref,
            (int) $record->amount,
            $bank->bank_code,
            $bank->bank_account,
            $desc
        );

        if (isset($result['code']) && $result['code'] === '00') {
            $record->update(['status' => 'approved', 'note' => null]);
            \App\Services\NotificationService::notifyWithdrawStatus($record, 'approved');
            return ['success' => true, 'message' => 'Chuyển khoản thành công'];
        }

        $error = $result['message'] ?? 'Lỗi hệ thống PayOS';
        $record->update(['status' => 'failed', 'note' => $error]);
        return ['success' => false, 'message' => $error];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Components\Section::make('Thông tin tài xế')
                ->columns(4)
                ->schema([
                    Components\ImageEntry::make('driver.profile_photo_path')
                        ->label('')
                        ->circular()
                        ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->driver?->name ?? '?') . '&color=ffffff&background=6d28d9')
                        ->size(72),

                    Components\TextEntry::make('driver.name')
                        ->label('Tài xế')
                        ->weight(FontWeight::Bold)
                        ->color('primary'),

                    Components\TextEntry::make('driver.phone')
                        ->label('Số điện thoại'),

                    Components\TextEntry::make('driver.city.name')
                        ->label('Khu vực')
                        ->badge()
                        ->color('gray'),
                ]),

            Components\Section::make('Chi tiết yêu cầu')
                ->columns(3)
                ->schema([
                    Components\TextEntry::make('amount')
                        ->label('Số tiền rút')
                        ->money('VND')
                        ->weight(FontWeight::Bold)
                        ->size(Components\TextEntry\TextEntrySize::Large)
                        ->color('success'),

                    Components\TextEntry::make('wallet_balance')
                        ->label('Số dư ví hiện tại')
                        ->getStateUsing(fn($record) => \App\Models\DriverWallet::where('driver_id', $record->driver_id)->value('balance') ?? 0)
                        ->money('VND')
                        ->color(fn($state) => $state < 0 ? 'danger' : 'primary'),

                    Components\TextEntry::make('status')
                        ->label('Trạng thái')
                        ->badge()
                        ->formatStateUsing(fn($state) => match ($state) {
                            'pending'  => 'Đang chờ',
                            'approved' => 'Thành công',
                            'rejected' => 'Đã từ chối',
                            'failed'   => 'Thất bại',
                            default    => $state,
                        })
                        ->color(fn($state) => match ($state) {
                            'pending'  => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            'failed'   => 'gray',
                            default    => 'gray',
                        }),

                    Components\TextEntry::make('created_at')
                        ->label('Thời gian yêu cầu')
                        ->dateTime('d/m/Y H:i'),

                    Components\TextEntry::make('updated_at')
                        ->label('Cập nhật lần cuối')
                        ->dateTime('d/m/Y H:i')
                        ->color('gray'),
                ]),

            Components\Section::make('Thông tin thụ hưởng')
                ->icon('heroicon-o-credit-card')
                ->columns(3)
                ->schema([
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

            Components\Section::make('Ghi chú')
                ->schema([
                    Components\TextEntry::make('note')
                        ->label('Nội dung / Lý do')
                        ->placeholder('Không có ghi chú')
                        ->columnSpanFull(),
                ])->visible(fn($record) => filled($record->note)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('driver.profile_photo_path')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->driver?->name ?? '?') . '&color=ffffff&background=6d28d9')
                    ->size(44),

                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Tài xế')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn($record) => $record->driver?->phone ?? ''),

                Tables\Columns\TextColumn::make('bank_info')
                    ->label('Thông tin thụ hưởng')
                    ->getStateUsing(fn($record) => $record->driver?->bank?->bank_owner ?? 'Chưa cài đặt')
                    ->description(function ($record) {
                        $bank = $record->driver?->bank;
                        if (!$bank) return null;
                        return \Illuminate\Support\Str::limit(
                            implode(' · ', array_filter([$bank->bank_name ?? $bank->bank_code, $bank->bank_account])),
                            30
                        );
                    })
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
                    ->color(fn($state) => match ($state) {
                        'pending'  => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'failed'   => 'gray',
                        default    => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Thời gian yêu cầu')
                    ->dateTime('d/m/Y H:i')
                    ->alignCenter()
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'pending'  => 'Đang chờ',
                        'approved' => 'Thành công',
                        'rejected' => 'Đã từ chối',
                        'failed'   => 'Thất bại',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->label('Khoảng thời gian')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Từ ngày'),
                        Forms\Components\DatePicker::make('until')->label('Đến ngày'),
                    ])
                    ->query(fn($query, array $data) => $query
                        ->when($data['from'], fn($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'], fn($q, $d) => $q->whereDate('created_at', '<=', $d))
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('approve_payout')
                    ->label('Duyệt & Chuyển tiền')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Duyệt & Chuyển tiền qua PayOS')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        $result = static::executePayout($record, false);
                        $n = Notification::make()->title($result['message']);
                        $result['success'] ? $n->success()->send() : $n->danger()->send();
                    }),

                Tables\Actions\Action::make('retry_payout')
                    ->label('Thử lại')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Thử lại chuyển khoản qua PayOS')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'failed')
                    ->action(function ($record) {
                        $result = static::executePayout($record, true);
                        $n = Notification::make()->title($result['message']);
                        $result['success'] ? $n->success()->send() : $n->danger()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Từ chối')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Từ chối & hoàn tiền vào ví')
                    ->requiresConfirmation()
                    ->visible(fn($record) => in_array($record->status, ['pending', 'failed']))
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Lý do từ chối')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Xoá đã chọn'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawRequests::route('/'),
            'view'  => Pages\ViewWithdrawRequest::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager', 'dispatcher']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()->with(['driver.bank', 'driver.city', 'driver.plan']);

        $user = auth()->user();

        if ($user->hasRole('admin')) {
            if ($cityId = session('current_city_id')) {
                $query->whereHas('driver', fn($q) => $q->where('city_id', $cityId));
            }
        } elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $query->whereHas('driver', fn($q) => $q->where('city_id', $user->city_id));
        }

        return $query;
    }
}
