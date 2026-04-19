<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static ?string $navigationGroup = 'CẤU HÌNH VẬN HÀNH';
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Khu vực (HUB)';
    protected static ?string $modelLabel = 'khu vực';
    protected static ?string $pluralModelLabel = 'Danh sách khu vực';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Thông tin chung')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Tên tỉnh/thành phố')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                                if ($operation !== 'create') {
                                    return;
                                }
                                if (empty($state)) {
                                    return;
                                }

                                // Tự động tạo mã từ chữ cái đầu (VD: Cần Thơ -> CT)
                                $words = explode(' ', $state);
                                $code = '';
                                foreach ($words as $word) {
                                    if (!empty($word)) {
                                        $code .= mb_substr($word, 0, 1);
                                    }
                                }
                                $set('code', \Illuminate\Support\Str::upper(\Illuminate\Support\Str::ascii($code)));
                            }),

                        Forms\Components\TextInput::make('code')
                            ->label('Mã code (HN, HCM...)')
                            ->maxLength(50),
                    ]),

                    Forms\Components\Toggle::make('status')
                        ->label('Kích hoạt')
                        ->default(true),
                ]),

            Section::make('Tâm khu vực (HUB)')
                ->description('Nhập địa chỉ trung tâm (Ví dụ: Chợ Rạch Giá) để hệ thống tự động xác định tọa độ.')
                ->schema([
                    Forms\Components\Select::make('search_address')
                        ->label('Tìm kiếm địa chỉ trung tâm')
                        ->placeholder('🔍 Bắt đầu nhập để tìm kiếm địa điểm...')
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search) {
                            if (empty($search))
                                return [];

                            $apiKey = config('services.google.maps_key');
                            $response = \Illuminate\Support\Facades\Http::get("https://maps.googleapis.com/maps/api/place/autocomplete/json", [
                                'input' => $search,
                                'key' => $apiKey,
                                'language' => 'vi',
                                'components' => 'country:vn'
                            ]);

                            return collect($response->json()['predictions'] ?? [])
                                ->mapWithKeys(fn($p) => [$p['description'] => $p['description']])
                                ->toArray();
                        })
                        ->reactive()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if (empty($state))
                                return;

                            try {
                                $apiKey = config('services.google.maps_key');
                                $response = \Illuminate\Support\Facades\Http::get("https://maps.googleapis.com/maps/api/geocode/json", [
                                    'address' => $state,
                                    'key' => $apiKey,
                                ]);

                                $data = $response->json();

                                if ($data['status'] === 'OK') {
                                    $location = $data['results'][0]['geometry']['location'];
                                    $set('latitude', $location['lat']);
                                    $set('longitude', $location['lng']);

                                    \Filament\Notifications\Notification::make()
                                        ->title('Đã định vị tọa độ từ địa chỉ!')
                                        ->success()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                            }
                        })
                        ->dehydrated(false),

                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->label('Vĩ độ (Latitude)')
                            ->required()
                            ->numeric()
                            ->placeholder('Ví dụ: 10.045...'),

                        Forms\Components\TextInput::make('longitude')
                            ->label('Kinh độ (Longitude)')
                            ->required()
                            ->numeric()
                            ->placeholder('Ví dụ: 105.746...'),
                    ]),
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

                Tables\Columns\TextColumn::make('name')
                    ->label('Khu vực (HUB)')
                    ->searchable()
                    ->description(fn($record) => "Tâm HUB: {$record->latitude}, {$record->longitude}")
                    ->icon('heroicon-m-map-pin')
                    ->iconColor('success')
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Mã vùng')
                    ->badge()
                    ->colors(['indigo'])
                    ->searchable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('drivers_count')
                    ->label('Tài xế')
                    ->counts('drivers')
                    ->badge()
                    ->color('info')
                    ->alignCenter()
                    ->icon('heroicon-m-user-group'),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Tổng đơn')
                    ->counts('orders')
                    ->badge()
                    ->color('secondary')
                    ->alignCenter()
                    ->icon('heroicon-m-shopping-bag'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Hoạt động')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? 'Mở cửa' : 'Đóng cửa')
                    ->colors([
                        'success' => 1,
                        'danger' => 0,
                    ])
                    ->alignCenter(),

            ])
            ->defaultSort('id', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Trạng thái hoạt động')
                    ->placeholder('Tất cả'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('google_maps')
                        ->label('Xem bản đồ')
                        ->icon('heroicon-o-map')
                        ->color('success')
                        ->url(fn($record) => "https://www.google.com/maps?q={$record->latitude},{$record->longitude}")
                        ->openUrlInNewTab(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->label('Tùy chọn'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCities::route('/'),
            'create' => Pages\CreateCity::route('/create'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
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
        return auth()->check() && auth()->user()->hasAnyRole(['admin']);
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }
}
