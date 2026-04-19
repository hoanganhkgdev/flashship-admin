<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftResource\Pages;
use App\Filament\Resources\ShiftResource\RelationManagers;
use App\Models\Shift;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;
    protected static ?string $navigationGroup = 'CẤU HÌNH VẬN HÀNH';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Ca làm việc';
    protected static ?string $modelLabel = 'ca làm';
    protected static ?string $pluralModelLabel = 'Danh sách Ca làm';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('city_id')
                    ->label('Khu vực (City)')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Để trống nếu áp dụng cho tất cả khu vực'),

                Forms\Components\TextInput::make('name')
                    ->label('Tên ca')
                    ->required(),

                Forms\Components\TextInput::make('code')
                    ->label('Mã ca')
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->helperText('Dùng "full" để đánh dấu ca cả ngày (tính phí Full-time)'),

                Forms\Components\TimePicker::make('start_time')
                    ->label('Giờ bắt đầu')
                    ->required(),

                Forms\Components\TimePicker::make('end_time')
                    ->label('Giờ kết thúc')
                    ->required(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Kích hoạt')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Khu vực')
                    ->sortable()
                    ->placeholder('Dùng chung'),
                Tables\Columns\TextColumn::make('name')->label('Tên ca'),
                Tables\Columns\TextColumn::make('code')->label('Mã ca'),
                Tables\Columns\TextColumn::make('start_time')->label('Bắt đầu'),
                Tables\Columns\TextColumn::make('end_time')->label('Kết thúc'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Trạng thái'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            'edit' => Pages\EditShift::route('/{record}/edit'),
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
        return auth()->check() && auth()->user()->hasAnyRole(['admin',]);
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

}
