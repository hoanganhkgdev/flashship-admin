<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PricingRuleResource\Pages;
use App\Filament\Resources\PricingRuleResource\RelationManagers;
use App\Models\PricingRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PricingRuleResource extends Resource
{
    protected static ?string $model = PricingRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Bảng giá dịch vụ';
    protected static ?string $modelLabel = 'quy tắc giá';
    protected static ?string $pluralModelLabel = 'Bảng giá dịch vụ';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationGroup = 'CẤU HÌNH VẬN HÀNH';

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Cấu hình chung')
                    ->schema([
                        Forms\Components\Select::make('city_id')
                            ->label('Khu vực (HUB)')
                            ->relationship('city', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('service_type')
                            ->label('Loại dịch vụ')
                            ->options([
                                'delivery' => '🚚 Giao hàng nội ô',
                                'shopping' => '🛒 Mua hộ',
                                'bike'     => '🛵 Xe ôm công nghệ',
                                'topup'    => '💳 Nạp/Rút tiền',
                                'motor'    => '🏍️ Lái hộ (Xe máy)',
                                'car'      => '🚗 Lái hộ (Ô tô)',
                            ])
                            ->required()
                            ->live(),
                    ])->columns(2),

                Forms\Components\Section::make('Tham số tính toán')
                    ->schema([
                        // Khoảng cách (Dùng cho Giao hàng, Xe ôm, Lái hộ)
                        Forms\Components\TextInput::make('min_distance')
                            ->label('Km từ')
                            ->numeric()
                            ->default(0)
                            ->suffix('Km')
                            ->hidden(fn($get) => $get('service_type') === 'topup'),
                        Forms\Components\TextInput::make('max_distance')
                            ->label('Km đến')
                            ->numeric()
                            ->suffix('Km')
                            ->placeholder('Để trống nếu không giới hạn')
                            ->hidden(fn($get) => $get('service_type') === 'topup'),

                        // Số tiền (Dùng cho Nạp rút)
                        Forms\Components\TextInput::make('min_amount')
                            ->label('Tiền từ')
                            ->numeric()
                            ->prefix('đ')
                            ->visible(fn($get) => $get('service_type') === 'topup'),
                        Forms\Components\TextInput::make('max_amount')
                            ->label('Tiền đến')
                            ->numeric()
                            ->prefix('đ')
                            ->visible(fn($get) => $get('service_type') === 'topup'),

                        Forms\Components\TextInput::make('base_price')
                            ->label('Phí cố định (hoặc khởi điểm)')
                            ->numeric()
                            ->required()
                            ->prefix('đ'),
                        Forms\Components\TextInput::make('price_per_km')
                            ->label('Giá mỗi Km tiếp theo')
                            ->numeric()
                            ->default(0)
                            ->prefix('đ')
                            ->hidden(fn($get) => $get('service_type') === 'topup'),
                        Forms\Components\TextInput::make('extra_fee')
                            ->label('Phụ phí cố định (Lái hộ)')
                            ->numeric()
                            ->default(0)
                            ->prefix('đ')
                            ->visible(fn($get) => in_array($get('service_type'), ['motor', 'car'])),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Khu vực')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('service_type')
                    ->label('Dịch vụ')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'delivery' => '🚚 Giao hàng',
                        'shopping' => '🛒 Mua hộ',
                        'bike'     => '🛵 Xe ôm',
                        'topup'    => '💳 Nạp/Rút',
                        'motor'    => '🏍️ Lái hộ XM',
                        'car'      => '🚗 Lái hộ Ô tô',
                        default    => $state,
                    })
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('range')
                    ->label('Khoảng áp dụng')
                    ->getStateUsing(function ($record) {
                        if ($record->service_type === 'topup') {
                            return number_format($record->min_amount) . ' - ' . ($record->max_amount ? number_format($record->max_amount) : '∞') . ' đ';
                        }
                        return $record->min_distance . ' - ' . ($record->max_distance ?: '∞') . ' Km';
                    }),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('Cố định')
                    ->money('VND')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_per_km')
                    ->label('Giá/Km')
                    ->money('VND'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('city_id')
                    ->label('Khu vực')
                    ->relationship('city', 'name'),
                Tables\Filters\SelectFilter::make('service_type')
                    ->label('Dịch vụ')
                    ->options([
                        'delivery' => '🚚 Giao hàng',
                        'shopping' => '🛒 Mua hộ',
                        'bike'     => '🛵 Xe ôm',
                        'topup'    => '💳 Nạp/Rút',
                        'motor'    => '🏍️ Lái hộ XM',
                        'car'      => '🚗 Lái hộ Ô tô',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->groups([
                Tables\Grouping\Group::make('city.name')
                    ->label('Khu vực'),
                Tables\Grouping\Group::make('service_type')
                    ->label('Dịch vụ'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPricingRules::route('/'),
            'create' => Pages\CreatePricingRule::route('/create'),
            'edit' => Pages\EditPricingRule::route('/{record}/edit'),
        ];
    }
}
