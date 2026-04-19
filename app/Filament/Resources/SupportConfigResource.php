<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportConfigResource\Pages;
use App\Models\SupportConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupportConfigResource extends Resource
{
    protected static ?string $model = SupportConfig::class;

    protected static ?string $navigationGroup = 'CÀI ĐẶT HỆ THỐNG';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Trung tâm hỗ trợ';
    protected static ?int $navigationSort = 3;
    protected static ?string $label = 'Cấu hình hỗ trợ';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('city_id')
                    ->label('Thành phố')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Tất cả (Global)'),

                Forms\Components\TextInput::make('title')
                    ->label('Tiêu đề')
                    ->required(),

                Forms\Components\TextInput::make('subtitle')
                    ->label('Mô tả ngắn'),

                Forms\Components\Select::make('icon')
                    ->label('Icon')
                    ->options([
                        'chat' => 'Chat (Zalo)',
                        'phone' => 'Phone (Hotline)',
                        'warning' => 'Warning (SOS)',
                        'tech' => 'Engineering (Kỹ thuật)',
                        'policy' => 'Policy (Quy định)',
                        'help' => 'Help (Trợ giúp)',
                        'book' => 'Book (Sổ tay)',
                        'youtube' => 'YouTube',
                    ])
                    ->default('help')
                    ->required(),

                Forms\Components\Select::make('type')
                    ->label('Loại hành động')
                    ->options([
                        'call' => 'Gọi điện',
                        'zalo' => 'Chat Zalo',
                        'link' => 'Mở liên kết web',
                        'screen' => 'Mở trang trong app (Internal)',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('value')
                    ->label('Giá trị hành động')
                    ->placeholder('Số điện thoại, URL hoặc ID trang app')
                    ->required(),

                Forms\Components\ColorPicker::make('color')
                    ->label('Màu sắc'),

                Forms\Components\TextInput::make('priority')
                    ->label('Thứ tự ưu tiên')
                    ->numeric()
                    ->default(0),

                Forms\Components\Toggle::make('is_active')
                    ->label('Đang hoạt động')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('city.name')->label('Thành phố')->default('Tất cả'),
                Tables\Columns\TextColumn::make('title')->label('Tiêu đề'),
                Tables\Columns\TextColumn::make('type')->label('Loại'),
                Tables\Columns\TextColumn::make('value')->label('Giá trị'),
                Tables\Columns\TextColumn::make('priority')->label('Sắp xếp'),
                Tables\Columns\ToggleColumn::make('is_active')->label('Bật/Tắt'),
            ])
            ->defaultSort('priority', 'asc')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportConfigs::route('/'),
            'create' => Pages\CreateSupportConfig::route('/create'),
            'edit' => Pages\EditSupportConfig::route('/{record}/edit'),
        ];
    }
}
