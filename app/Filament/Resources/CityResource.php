<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages;
use App\Filament\Resources\CityResource\Widgets\CityOverviewWidget;
use App\Models\City;
use App\Services\GeocodingService;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static ?string $navigationGroup  = 'CẤU HÌNH VẬN HÀNH';
    protected static ?string $navigationIcon   = 'heroicon-o-building-office';
    protected static ?string $navigationLabel  = 'Khu vực (HUB)';
    protected static ?string $modelLabel       = 'khu vực';
    protected static ?string $pluralModelLabel = 'Danh sách khu vực';
    protected static ?int    $navigationSort   = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('activePlan');
    }

    // =========================================================================
    // FORM
    // =========================================================================

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Thông tin chung')->schema([
                Grid::make(2)->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Tên khu vực')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                            if ($operation !== 'create' || empty($state)) return;
                            $code = collect(explode(' ', $state))
                                ->filter()
                                ->map(fn($w) => mb_substr($w, 0, 1))
                                ->join('');
                            $set('code', Str::upper(Str::ascii($code)));
                        }),

                    Forms\Components\TextInput::make('code')
                        ->label('Mã vùng (HN, HCM...)')
                        ->maxLength(50),
                ]),

                Forms\Components\Toggle::make('status')
                    ->label('Đang hoạt động')
                    ->default(true)
                    ->inline(false),
            ]),

            Section::make('Toạ độ trung tâm (HUB)')
                ->description('Chọn địa chỉ để hệ thống tự điền toạ độ, hoặc nhập thủ công.')
                ->schema([
                    Forms\Components\Select::make('search_address')
                        ->label('Tìm địa chỉ trung tâm')
                        ->placeholder('Bắt đầu nhập tên địa điểm...')
                        ->searchable()
                        ->getSearchResultsUsing(fn(string $search) => GeocodingService::autocomplete($search))
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if (empty($state)) return;
                            $location = GeocodingService::geocodeAddress($state);
                            if ($location) {
                                $set('latitude', $location['lat']);
                                $set('longitude', $location['lng']);
                                Notification::make()->title('Đã định vị toạ độ!')->success()->send();
                            }
                        })
                        ->dehydrated(false),

                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->label('Vĩ độ (Latitude)')
                            ->required()
                            ->numeric()
                            ->placeholder('10.045...'),

                        Forms\Components\TextInput::make('longitude')
                            ->label('Kinh độ (Longitude)')
                            ->required()
                            ->numeric()
                            ->placeholder('105.746...'),
                    ]),
                ]),
        ]);
    }

    // =========================================================================
    // TABLE
    // =========================================================================

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->width('50px')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Khu vực')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn($record) => $record->latitude
                        ? "📍 {$record->latitude}, {$record->longitude}"
                        : 'Chưa có toạ độ'
                    ),

                Tables\Columns\TextColumn::make('code')
                    ->label('Mã')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('activePlan.type')
                    ->label('Gói hiện tại')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        \App\Models\Plan::TYPE_WEEKLY     => 'Cước tuần',
                        \App\Models\Plan::TYPE_COMMISSION => 'Chiết khấu %',
                        \App\Models\Plan::TYPE_PARTNER    => 'Đối tác',
                        \App\Models\Plan::TYPE_FREE       => 'Miễn phí',
                        default                           => $state ?? 'Chưa có gói',
                    })
                    ->color(fn($state) => match ($state) {
                        \App\Models\Plan::TYPE_WEEKLY     => 'info',
                        \App\Models\Plan::TYPE_COMMISSION => 'warning',
                        \App\Models\Plan::TYPE_PARTNER    => 'success',
                        \App\Models\Plan::TYPE_FREE       => 'gray',
                        default                           => 'gray',
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('drivers_count')
                    ->label('Tài xế')
                    ->counts('drivers')
                    ->badge()
                    ->color('primary')
                    ->alignCenter()
                    ->icon('heroicon-m-user-group'),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Tổng đơn')
                    ->counts('orders')
                    ->badge()
                    ->color('secondary')
                    ->alignCenter()
                    ->icon('heroicon-m-shopping-bag'),

                Tables\Columns\ToggleColumn::make('status')
                    ->label('Hoạt động')
                    ->alignCenter()
                    ->beforeStateUpdated(function ($record) {
                        if (!static::adminOnly()) {
                            Notification::make()->title('Không có quyền thực hiện.')->danger()->send();
                            return false;
                        }
                    }),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Trạng thái')
                    ->placeholder('Tất cả'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('google_maps')
                        ->label('Xem bản đồ')
                        ->icon('heroicon-o-map')
                        ->color('success')
                        ->url(fn($record) => "https://www.google.com/maps?q={$record->latitude},{$record->longitude}")
                        ->openUrlInNewTab()
                        ->visible(fn($record) => $record->latitude && $record->longitude),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->label('Tuỳ chọn'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // =========================================================================
    // PAGES & WIDGETS
    // =========================================================================

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCities::route('/'),
            'create' => Pages\CreateCity::route('/create'),
            'edit'   => Pages\EditCity::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [CityOverviewWidget::class];
    }

    // =========================================================================
    // PERMISSIONS
    // =========================================================================

    public static function canViewAny(): bool       { return static::adminOnly(); }
    public static function canCreate(): bool        { return static::adminOnly(); }
    public static function canEdit($record): bool   { return static::adminOnly(); }
    public static function canDelete($record): bool { return static::adminOnly(); }

    private static function adminOnly(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }
}
