<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftResource\Pages;
use App\Filament\Resources\ShiftResource\Widgets\ShiftOverviewWidget;
use App\Models\Shift;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static ?string $navigationGroup  = 'CẤU HÌNH VẬN HÀNH';
    protected static ?string $navigationIcon   = 'heroicon-o-clock';
    protected static ?string $navigationLabel  = 'Ca làm việc';
    protected static ?string $modelLabel       = 'ca làm việc';
    protected static ?string $pluralModelLabel = 'Danh sách ca làm việc';
    protected static ?int    $navigationSort   = 3;

    public static function getNavigationBadge(): ?string
    {
        return (string) Shift::active()->count() ?: null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withCount('users');
        $user  = auth()->user();

        if ($user->hasRole('admin')) {
            if ($cityId = session('current_city_id')) {
                $query->where('city_id', $cityId);
            }
        } elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $query->where('city_id', $user->city_id);
        }

        return $query;
    }

    // =========================================================================
    // FORM  (used by Edit page)
    // =========================================================================

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin ca')->schema([
                Forms\Components\Select::make('city_id')
                    ->label('Khu vực')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Để trống nếu ca áp dụng cho tất cả khu vực.'),

                Forms\Components\TextInput::make('name')
                    ->label('Tên ca')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('VD: Ca sáng, Ca chiều, Ca tối...'),

                Forms\Components\TextInput::make('code')
                    ->label('Mã ca')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->helperText('Dùng "full" để đánh dấu ca cả ngày (tính phí Full-time).'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Đang hoạt động')
                    ->default(true)
                    ->inline(false),
            ])->columns(2),

            Forms\Components\Section::make('Khung giờ')->schema([
                Forms\Components\TimePicker::make('start_time')
                    ->label('Giờ bắt đầu')
                    ->required()
                    ->seconds(false),

                Forms\Components\TimePicker::make('end_time')
                    ->label('Giờ kết thúc')
                    ->required()
                    ->seconds(false)
                    ->helperText('Nếu giờ kết thúc < giờ bắt đầu → ca qua đêm.'),
            ])->columns(2),
        ]);
    }

    // =========================================================================
    // TABLE
    // =========================================================================

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('city_id')
            ->columns([
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Khu vực')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Dùng chung')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Ca làm việc')
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->description(fn(Shift $r) => $r->code === 'full' ? 'Full-time — tính phí cả tuần' : 'Mã: ' . $r->code),

                Tables\Columns\TextColumn::make('time_range')
                    ->label('Khung giờ')
                    ->icon('heroicon-o-clock')
                    ->description(fn(Shift $r) => $r->end_time < $r->start_time ? 'Ca qua đêm' : null)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Tài xế')
                    ->icon('heroicon-o-user')
                    ->badge()
                    ->color(fn(int $state) => $state > 0 ? 'success' : 'gray')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Hoạt động')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('city_id')
                    ->label('Khu vực')
                    ->relationship('city', 'name'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Trạng thái')
                    ->trueLabel('Đang hoạt động')
                    ->falseLabel('Đã tắt'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // =========================================================================
    // PAGES & PERMISSIONS
    // =========================================================================

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            'edit'   => Pages\EditShift::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [ShiftOverviewWidget::class];
    }

    public static function canViewAny(): bool       { return static::adminOnly(); }
    public static function canCreate(): bool        { return static::adminOnly(); }
    public static function canEdit($record): bool   { return static::adminOnly(); }
    public static function canDelete($record): bool { return static::adminOnly(); }

    private static function adminOnly(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }
}
