<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZaloAccountResource\Pages;
use App\Filament\Resources\ZaloAccountResource\RelationManagers;
use App\Models\ZaloAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ZaloAccountResource extends Resource
{
    protected static ?string $model = ZaloAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Cấu hình Zalo OA';
    protected static ?string $modelLabel = 'Tài khoản Zalo OA';
    protected static ?string $pluralModelLabel = 'Tài khoản Zalo OA';
    protected static ?string $navigationGroup = 'CẤU HÌNH AI & OA';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Thông tin định danh')
                    ->description('Các thông tin này lấy từ Zalo OA Developer')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Tên gợi nhớ')
                            ->placeholder('Ví dụ: Flashship Rạch Giá')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('oa_id')
                            ->label('OA ID')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('city_id')
                            ->label('Trực thuộc khu vực')
                            ->relationship('city', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Forms\Components\Section::make('Xác thực (Token)')
                    ->description('Token dùng để gửi tin nhắn phản hồi cho khách')
                    ->schema([
                        Forms\Components\Textarea::make('access_token')
                            ->label('Access Token')
                            ->rows(3),
                        Forms\Components\Textarea::make('refresh_token')
                            ->label('Refresh Token')
                            ->rows(2),
                        Forms\Components\DateTimePicker::make('token_expires_at')
                            ->label('Ngày hết hạn Token'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Trạng thái hoạt động')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên OA')
                    ->searchable(),
                Tables\Columns\TextColumn::make('oa_id')
                    ->label('OA ID')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Khu vực')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Hoạt động')
                    ->boolean(),
                Tables\Columns\TextColumn::make('token_expires_at')
                    ->label('Hết hạn Token')
                    ->dateTime('H:i d/m/Y')
                    ->sortable()
                    ->color(fn($state) => $state && $state->isPast() ? 'danger' : 'success'),
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
            'index' => Pages\ListZaloAccounts::route('/'),
            'create' => Pages\CreateZaloAccount::route('/create'),
            'edit' => Pages\EditZaloAccount::route('/{record}/edit'),
        ];
    }
}
