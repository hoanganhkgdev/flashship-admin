<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverWalletResource\Pages;
use App\Filament\Resources\DriverWalletResource\RelationManagers;
use App\Models\DriverWallet;
use App\Services\DriverWalletService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;

class DriverWalletResource extends Resource
{
    protected static ?string $model = DriverWallet::class;
    protected static ?string $navigationIcon = 'heroicon-o-wallet';
    protected static ?string $navigationLabel = 'Số dư ví Shipper';
    protected static ?string $modelLabel = 'ví tài xế';
    protected static ?string $pluralModelLabel = 'Quản lý ví tài xế';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'TÀI CHÍNH';
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

                    Components\TextEntry::make('driver.plan.name')
                        ->label('Gói cước')
                        ->badge()
                        ->color(fn($record) => match ($record->driver?->plan?->type) {
                            'weekly'     => 'primary',
                            'commission' => 'warning',
                            'partner'    => 'info',
                            'free'       => 'gray',
                            default      => 'gray',
                        })
                        ->default('Chưa có gói'),

                    Components\TextEntry::make('driver.status')
                        ->label('Trạng thái tài xế')
                        ->badge()
                        ->formatStateUsing(fn($state) => match ((int) $state) {
                            0 => 'Chờ duyệt',
                            1 => 'Hoạt động',
                            2 => 'Bị khóa',
                            default => $state,
                        })
                        ->color(fn($state) => match ((int) $state) {
                            0 => 'warning',
                            1 => 'success',
                            2 => 'danger',
                            default => 'gray',
                        }),
                ]),

            Components\Section::make('Số dư & giao dịch')
                ->columns(4)
                ->schema([
                    Components\TextEntry::make('balance')
                        ->label('Số dư hiện tại')
                        ->money('VND')
                        ->weight(FontWeight::Bold)
                        ->size(Components\TextEntry\TextEntrySize::Large)
                        ->color(fn($state) => $state < 0 ? 'danger' : 'success'),

                    Components\TextEntry::make('transactions_count')
                        ->label('Tổng giao dịch')
                        ->getStateUsing(fn($record) => $record->transactions()->count() . ' giao dịch')
                        ->icon('heroicon-o-list-bullet'),

                    Components\TextEntry::make('total_credited')
                        ->label('Tổng đã cộng')
                        ->getStateUsing(fn($record) => number_format(
                            $record->transactions()->where('type', 'credit')->sum('amount'), 0, ',', '.'
                        ) . '₫')
                        ->color('success')
                        ->icon('heroicon-o-arrow-up-circle'),

                    Components\TextEntry::make('total_debited')
                        ->label('Tổng đã trừ')
                        ->getStateUsing(fn($record) => number_format(
                            $record->transactions()->where('type', 'debit')->sum('amount'), 0, ',', '.'
                        ) . '₫')
                        ->color('danger')
                        ->icon('heroicon-o-arrow-down-circle'),
                ]),
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

                Tables\Columns\TextColumn::make('driver.city.name')
                    ->label('Khu vực')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('driver.plan.name')
                    ->label('Gói cước')
                    ->badge()
                    ->color(fn($record) => match ($record->driver?->plan?->type) {
                        'weekly'     => 'primary',
                        'commission' => 'warning',
                        'partner'    => 'info',
                        'free'       => 'gray',
                        default      => 'gray',
                    })
                    ->default('—')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Số dư khả dụng')
                    ->money('VND')
                    ->sortable()
                    ->alignCenter()
                    ->weight('bold')
                    ->color(fn($state) => $state < 0 ? 'danger' : 'success')
                    ->description(fn($record) => 'Cập nhật: ' . $record->updated_at?->diffForHumans()),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->dateTime('d/m/Y H:i')
                    ->alignCenter()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('balance', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('balance_status')
                    ->label('Tình trạng ví')
                    ->placeholder('Tất cả ví')
                    ->trueLabel('Ví đang nợ (Âm tiền)')
                    ->falseLabel('Ví đang dư (Dương tiền)')
                    ->queries(
                        true: fn($query) => $query->where('balance', '<', 0),
                        false: fn($query) => $query->where('balance', '>=', 0),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('adjust_credit')
                    ->label('Cộng tiền')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Cộng tiền vào ví')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Số tiền cộng (₫)')
                            ->numeric()->minValue(1000)->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Lý do')->required()->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        DriverWalletService::adjust(
                            $record->driver_id,
                            (float) $data['amount'],
                            'credit',
                            $data['description'],
                            'manual_credit_' . $record->driver_id . '_' . now()->timestamp
                        );
                        Notification::make()
                            ->title('Đã cộng ' . number_format($data['amount'], 0, ',', '.') . '₫ vào ví')
                            ->success()->send();
                    }),

                Tables\Actions\Action::make('adjust_debit')
                    ->label('Trừ tiền')
                    ->icon('heroicon-o-minus-circle')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Trừ tiền khỏi ví')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Số tiền trừ (₫)')
                            ->numeric()->minValue(1000)->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Lý do')->required()->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            DriverWalletService::adjust(
                                $record->driver_id,
                                (float) $data['amount'],
                                'debit',
                                $data['description'],
                                'manual_debit_' . $record->driver_id . '_' . now()->timestamp,
                                true
                            );
                            Notification::make()
                                ->title('Đã trừ ' . number_format($data['amount'], 0, ',', '.') . '₫ khỏi ví')
                                ->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Lỗi: ' . $e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('transactions')
                    ->label('Lịch sử giao dịch')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Xem lịch sử giao dịch')
                    ->url(fn($record) => self::getUrl('view', ['record' => $record])),

                Tables\Actions\Action::make('view_driver')
                    ->label('Xem hồ sơ tài xế')
                    ->icon('heroicon-o-user-circle')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Xem hồ sơ tài xế')
                    ->url(fn($record) => DeliverymanResource::getUrl('view', ['record' => $record->driver_id])),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDriverWallets::route('/'),
            'view'  => Pages\ViewDriverWallet::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
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
        $query = parent::getEloquentQuery()->with(['driver.city', 'driver.plan']);

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
