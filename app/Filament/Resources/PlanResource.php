<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Filament\Resources\PlanResource\RelationManagers;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationGroup = 'CẤU HÌNH VẬN HÀNH';
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel = 'Gói cước tài xế';
    protected static ?string $modelLabel = 'gói cước';
    protected static ?string $pluralModelLabel = 'Danh sách gói cước';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin cơ bản')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Tên gói')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Ví dụ: Đóng tiền tuần hoặc Chiết khấu %'),

                    Forms\Components\Select::make('city_id')
                        ->label('Khu vực (City)')
                        ->relationship('city', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Đang hoạt động')
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make('Cấu hình phí')
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('Loại hình thu phí')
                        ->options([
                            'weekly' => 'Thu tiền tuần (Cố định)',
                            'commission' => 'Chiết khấu % (Theo đơn)',
                        ])
                        ->required()
                        ->live()
                        ->default('weekly'),

                    Forms\Components\Group::make([
                        Forms\Components\TextInput::make('weekly_fee_full')
                            ->label('Phí tuần (Full-time)')
                            ->numeric()
                            ->prefix('VNĐ')
                            ->required()
                            ->visible(fn(Forms\Get $get) => $get('type') === 'weekly'),

                        Forms\Components\TextInput::make('weekly_fee_part')
                            ->label('Phí tuần (Part-time)')
                            ->numeric()
                            ->prefix('VNĐ')
                            ->required()
                            ->visible(fn(Forms\Get $get) => $get('type') === 'weekly'),
                    ])->columns(2)->visible(fn(Forms\Get $get) => $get('type') === 'weekly'),

                    Forms\Components\TextInput::make('commission_rate')
                        ->label('% Chiết khấu')
                        ->numeric()
                        ->suffix('%')
                        ->required()
                        ->visible(fn(Forms\Get $get) => $get('type') === 'commission'),
                ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('index')
                    ->label('STT')
                    ->rowIndex()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên gói cước')
                    ->weight('bold')
                    ->color('primary')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Khu vực Hub')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Loại phí')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'weekly' => 'Trọn gói tuần',
                        'commission' => 'Chiết khấu %',
                        default => $state,
                    })
                    ->colors([
                        'info' => 'weekly',
                        'warning' => 'commission',
                    ])
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('fee_info')
                    ->label('Cấu hình phí')
                    ->getStateUsing(function (Plan $record) {
                        if ($record->type === 'weekly') {
                            return 'Full: ' . number_format($record->weekly_fee_full) . 'đ / Part: ' . number_format($record->weekly_fee_part) . 'đ';
                        }
                        return $record->commission_rate . '% / đơn';
                    })
                    ->weight('bold')
                    ->color('success')
                    ->alignRight(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Sẵn dùng')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
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
