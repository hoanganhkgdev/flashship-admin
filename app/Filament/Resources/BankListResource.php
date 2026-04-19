<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankListResource\Pages;
use App\Models\BankList;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;

class BankListResource extends Resource
{
    protected static ?string $model = BankList::class;

    protected static ?string $navigationGroup = 'CÀI ĐẶT HỆ THỐNG';
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationLabel = 'Danh mục ngân hàng';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label('Mã BIN')
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('name')
                ->label('Tên ngân hàng')
                ->required()
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')
                ->label('ID')
                ->sortable(),

            Tables\Columns\TextColumn::make('code')
                ->label('Mã BIN')
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('name')
                ->label('Tên ngân hàng')
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Ngày tạo')
                ->dateTime('d/m/Y H:i'),

            Tables\Columns\TextColumn::make('updated_at')
                ->label('Cập nhật')
                ->dateTime('d/m/Y H:i'),
        ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\DeleteAction::make()->label('Xóa'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Xóa nhiều'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankLists::route('/'),
            'create' => Pages\CreateBankList::route('/create'),
            'edit' => Pages\EditBankList::route('/{record}/edit'),
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
