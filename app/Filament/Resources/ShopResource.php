<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopResource\Pages;
use App\Filament\Resources\ShopResource\RelationManagers;
use App\Models\Shop;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Dotswan\MapPicker\Fields\Map;

class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';
    protected static ?string $navigationLabel = 'Danh sách đối tác';
    protected static ?string $modelLabel = 'Đối tác';
    protected static ?string $pluralModelLabel = 'Danh sách đối tác';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'QUẢN LÝ ĐƠN HÀNG';
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Thông tin cơ bản')
                    ->description('Thông tin định danh và liên hệ của Shop')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Tên Shop')
                                    ->required()
                                    ->placeholder('Ví dụ: Shop Bé Ba')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Số điện thoại')
                                    ->tel()
                                    ->placeholder('Ví dụ: 0912345678')
                                    ->maxLength(255),
                            ]),
                        Forms\Components\Select::make('city_id')
                            ->label('Khu vực (HUB)')
                            ->relationship('city', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('zalo_id')
                            ->label('Zalo OA ID')
                            ->helperText('ID này sẽ tự động cập nhật khi shop quét mã QR lần đầu')
                            ->disabled()
                            ->placeholder('Chưa kết nối'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Trạng thái hoạt động')
                            ->default(true)
                            ->required(),
                    ])->columnSpan(1),

                Forms\Components\Section::make('Địa chỉ lấy hàng')
                    ->description('Vị trí chính xác để tài xế qua lấy đồ')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label('Địa chỉ chi tiết')
                            ->required()
                            ->placeholder('Ví dụ: 123 Nguyễn Trung Trực, Rạch Giá')
                            ->maxLength(255),
                        Map::make('location')
                            ->label('Ghim vị trí trên bản đồ')
                            ->afterStateHydrated(function ($set, $record) {
                                if ($record) {
                                    $set('location', ['lat' => $record->latitude, 'lng' => $record->longitude]);
                                }
                            })
                            ->afterStateUpdated(function ($set, $state) {
                                $set('latitude', $state['lat']);
                                $set('longitude', $state['lng']);
                            })
                            ->extraControl([
                                'zoomControl' => true,
                                'mapTypeControl' => true,
                                'scaleControl' => true,
                                'streetViewControl' => true,
                                'rotateControl' => true,
                                'fullscreenControl' => true,
                                'searchBox' => true,
                            ])
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('latitude'),
                        Forms\Components\Hidden::make('longitude'),
                    ])->columnSpan(1),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Mã Shop')
                    ->sortable()
                    ->prefix('MS'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên Shop')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Khu vực')
                    ->sortable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Địa chỉ')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('zalo_id')
                    ->label('Zalo ID')
                    ->placeholder('Chưa kết nối')
                    ->badge()
                    ->color(fn($state) => $state ? 'success' : 'gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Hoạt động')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('city_id')
                    ->label('Lọc theo khu vực')
                    ->relationship('city', 'name'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('view_qr')
                        ->label('Mã QR định danh')
                        ->icon('heroicon-o-qr-code')
                        ->color('info')
                        ->modalHeading('Mã QR cho Shop')
                        ->modalSubmitAction(false)
                        ->modalContent(fn($record) => view('filament.components.shop-qr-modal', ['shop' => $record])),
                ])
            ])
            ->headerActions([
                Tables\Actions\Action::make('test_pricing')
                    ->label('Test Giá AI')
                    ->icon('heroicon-o-calculator')
                    ->color('success')
                    ->url(fn(): string => Pages\TestPricing::getUrl()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListShops::route('/'),
            'create' => Pages\CreateShop::route('/create'),
            'edit' => Pages\EditShop::route('/{record}/edit'),
            'test-pricing' => Pages\TestPricing::route('/test-pricing'),
        ];
    }
}
