<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverWalletResource\Pages;
use App\Filament\Resources\DriverWalletResource\RelationManagers;
use App\Models\DriverWallet;
use App\Services\DriverWalletService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Form;
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

    // Form chỉ dùng cho EditAction (ít khi dùng trực tiếp)
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('driver_id')->disabled()->label('ID Tài xế'),
            Forms\Components\TextInput::make('balance')->numeric()->label('Số dư'),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Components\Section::make('Tổng quan ví')
                ->schema([
                    Components\Grid::make(3)->schema([
                        Components\TextEntry::make('driver.name')
                            ->label('Chủ ví')
                            ->weight(FontWeight::Bold)
                            ->color('primary'),
                        Components\TextEntry::make('driver.phone')
                            ->label('Số điện thoại'),
                        Components\TextEntry::make('driver.city.name')
                            ->label('Khu vực')
                            ->badge(),
                    ]),
                    Components\TextEntry::make('balance')
                        ->label('Số dư hiện tại')
                        ->money('VND')
                        ->weight(FontWeight::Bold)
                        ->size(Components\TextEntry\TextEntrySize::Large)
                        ->color(fn($state) => $state < 0 ? 'danger' : 'success'),
                ]),
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

                Tables\Columns\TextColumn::make('driver.city.name')
                    ->label('Khu vực')
                    ->badge()
                    ->color('gray')
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
                            ->numeric()
                            ->minValue(1000)
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Lý do')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        DriverWalletService::adjust(
                            $record->driver_id,
                            (float) $data['amount'],
                            'credit',
                            $data['description'],
                            'manual_credit_' . $record->driver_id . '_' . now()->timestamp
                        );
                        Notification::make()->title('Đã cộng ' . number_format($data['amount'], 0, ',', '.') . '₫ vào ví')->success()->send();
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
                            ->numeric()
                            ->minValue(1000)
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Lý do')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            DriverWalletService::adjust(
                                $record->driver_id,
                                (float) $data['amount'],
                                'debit',
                                $data['description'],
                                'manual_debit_' . $record->driver_id . '_' . now()->timestamp
                            );
                            Notification::make()->title('Đã trừ ' . number_format($data['amount'], 0, ',', '.') . '₫ khỏi ví')->success()->send();
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
        return auth()->check() && auth()->user()->hasAnyRole(['admin']);
    }

    public static function canCreate(): bool
    {
        return false; // Ví tự tạo khi tài xế đăng ký, không cần tạo thủ công
    }

    public static function canEdit($record): bool
    {
        return false; // Dùng action adjust thay vì edit trực tiếp
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['driver.city']); // Eager load để tránh N+1

        $user = auth()->user();

        if ($user->hasRole('admin') && session()->has('current_city_id')) {
            $cityId = session('current_city_id');
            $query->whereHas('driver', fn($q) => $q->where('city_id', $cityId));
        } elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $cityId = $user->city_id;
            $query->whereHas('driver', fn($q) => $q->where('city_id', $cityId));
        }

        return $query;
    }
}
