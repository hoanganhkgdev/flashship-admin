<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Filament\Resources\PlanResource\Widgets\PlanOverviewWidget;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationGroup  = 'CẤU HÌNH VẬN HÀNH';
    protected static ?string $navigationIcon   = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel  = 'Gói cước tài xế';
    protected static ?string $modelLabel       = 'gói cước';
    protected static ?string $pluralModelLabel = 'Danh sách gói cước';
    protected static ?int    $navigationSort   = 4;

    public static function getNavigationBadge(): ?string
    {
        return (string) Plan::active()->count() ?: null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
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
    // FORM
    // =========================================================================

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin cơ bản')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Tên gói')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('VD: Gói tuần Kiên Giang, Chiết khấu Cần Thơ...'),

                Forms\Components\Select::make('city_id')
                    ->label('Khu vực')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Đang hoạt động')
                    ->default(true)
                    ->inline(false),
            ])->columns(2),

            Forms\Components\Section::make('Cấu hình phí')->schema([
                Forms\Components\Select::make('type')
                    ->label('Loại hình thu phí')
                    ->options([
                        Plan::TYPE_WEEKLY     => 'Cước tuần — chia ca, thu phí cố định',
                        Plan::TYPE_COMMISSION => 'Chiết khấu % — chạy tự do, trừ % theo đơn',
                        Plan::TYPE_PARTNER    => 'Tài xế đối tác — % riêng từng người',
                        Plan::TYPE_FREE       => 'Miễn phí — dành cho tổng đài, quản lý, admin',
                    ])
                    ->required()
                    ->live()
                    ->default(Plan::TYPE_WEEKLY),

                // ── Cước tuần ──────────────────────────────────────────────
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('weekly_fee_full')
                        ->label('Phí Full-time (tất cả ca)')
                        ->numeric()
                        ->prefix('VNĐ')
                        ->default(420000)
                        ->helperText('VD: 420,000đ/tuần')
                        ->required(fn(Forms\Get $get) => $get('type') === Plan::TYPE_WEEKLY),

                    Forms\Components\TextInput::make('weekly_fee_part')
                        ->label('Phí Part-time (1 ca)')
                        ->numeric()
                        ->prefix('VNĐ')
                        ->default(300000)
                        ->helperText('VD: 300,000đ/tuần')
                        ->required(fn(Forms\Get $get) => $get('type') === Plan::TYPE_WEEKLY),
                ])->visible(fn(Forms\Get $get) => $get('type') === Plan::TYPE_WEEKLY),

                // ── Chiết khấu % ───────────────────────────────────────────
                Forms\Components\TextInput::make('commission_rate')
                    ->label('% Chiết khấu theo đơn')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->required(fn(Forms\Get $get) => $get('type') === Plan::TYPE_COMMISSION)
                    ->visible(fn(Forms\Get $get) => $get('type') === Plan::TYPE_COMMISSION),

                // ── Tài xế đối tác — không có phí cố định ─────────────────
                Forms\Components\Placeholder::make('partner_note')
                    ->label('')
                    ->content('Gói này không có phí cố định. Mức phí do quản lý thoả thuận và ghi trực tiếp trên hồ sơ từng tài xế.')
                    ->visible(fn(Forms\Get $get) => $get('type') === Plan::TYPE_PARTNER),

                Forms\Components\Textarea::make('description')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->nullable()
                    ->columnSpanFull(),
            ]),
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên gói')
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->description(fn(Plan $record) => $record->description),

                Tables\Columns\TextColumn::make('city.name')
                    ->label('Khu vực')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Loại phí')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        Plan::TYPE_WEEKLY     => 'Cước tuần',
                        Plan::TYPE_COMMISSION => 'Chiết khấu %',
                        Plan::TYPE_PARTNER    => 'Đối tác',
                        Plan::TYPE_FREE       => 'Miễn phí',
                        default               => $state,
                    })
                    ->color(fn($state) => match ($state) {
                        Plan::TYPE_WEEKLY     => 'info',
                        Plan::TYPE_COMMISSION => 'warning',
                        Plan::TYPE_PARTNER    => 'success',
                        Plan::TYPE_FREE       => 'gray',
                        default               => 'gray',
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('fee_info')
                    ->label('Cấu hình phí')
                    ->weight('bold')
                    ->color('success')
                    ->alignRight(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Hoạt động')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Loại phí')
                    ->options([
                        Plan::TYPE_WEEKLY     => 'Trọn gói tuần',
                        Plan::TYPE_COMMISSION => 'Chiết khấu %',
                        Plan::TYPE_PARTNER    => 'Tài xế đối tác',
                        Plan::TYPE_FREE       => 'Miễn phí',
                    ]),

                Tables\Filters\SelectFilter::make('city_id')
                    ->label('Khu vực')
                    ->relationship('city', 'name'),
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
            'index'  => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit'   => Pages\EditPlan::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [PlanOverviewWidget::class];
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
