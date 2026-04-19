<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\{TextColumn, IconColumn};
use Filament\Forms\Components\{TextInput, Select, FileUpload};
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'CÀI ĐẶT HỆ THỐNG';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Nhân sự & Phân quyền';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->required()
                ->label('Tên'),

            TextInput::make('email')
                ->email()
                ->required()
                ->label('Email'),

            TextInput::make('phone')
                ->tel()                // input kiểu điện thoại
                ->numeric()            // chỉ cho nhập số
                ->minLength(9)
                ->maxLength(11)
                ->required()
                ->label('Số điện thoại'),

            // 🔹 Chọn role thay vì user_type
            Select::make('roles')
                ->label('Loại user')
                ->options(Role::all()->pluck('name', 'name'))
                ->searchable()
                ->required()
                ->relationship('roles', 'name')
                ->preload(),

            // 🔹 Thêm city_id
            Select::make('city_id')
                ->label('Khu vực')
                ->options(\App\Models\City::pluck('name', 'id'))
                ->searchable()
                ->required(),

            TextInput::make('password')
                ->password()
                ->revealable()
                ->dehydrateStateUsing(fn($state) => !empty($state) ? bcrypt($state) : null)
                ->dehydrated(fn($state) => filled($state))
                ->label('Mật khẩu'),

            // 📱 Zalo cá nhân để nhận cảnh báo từ AI
            \Filament\Forms\Components\Section::make('Zalo Thông Báo AI')
                ->description('Khi AI nhận cảnh báo từ khách (khiếu nại, mất hàng...), hệ thống sẽ gửi tin nhắn Zalo trực tiếp vào đây.')
                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                ->schema([
                    TextInput::make('zalo_id')
                        ->label('Zalo ID cá nhân')
                        ->placeholder('VD: 123456789012345')
                        ->helperText('Để lấy Zalo ID: Mở Zalo → Settings → Account → Copy mã số. Hoặc nhắn thử 1 tin vào OA Flashship → xem trong bảng ai_conversations.')
                        ->maxLength(50),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('index')
                ->label('STT')
                ->rowIndex()
                ->alignCenter(),
            Tables\Columns\ImageColumn::make('profile_photo_path')
                ->label('Ảnh')
                ->circular()
                ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF'),
            Tables\Columns\TextColumn::make('name')
                ->label('Họ tên')
                ->weight('bold')
                ->searchable(),
            Tables\Columns\TextColumn::make('email')
                ->label('Email')
                ->icon('heroicon-m-envelope')
                ->color('gray'),
            Tables\Columns\TextColumn::make('phone')
                ->label('Liên hệ')
                ->icon('heroicon-m-chat-bubble-left-right')
                ->color('primary')
                ->url(fn($record) => $record->phone ? "https://zalo.me/0" . substr($record->phone, -9) : null)
                ->openUrlInNewTab()
                ->description(fn($record) => $record->phone),

            Tables\Columns\TextColumn::make('roles.name')
                ->label('Vai trò')
                ->badge()
                ->formatStateUsing(function ($state) {
                    $map = [
                        'admin' => 'Quản trị',
                        'dispatcher' => 'Tổng đài',
                        'accountant' => 'Kế toán',
                        'driver' => 'Tài xế',
                        'manager' => 'Quản lý'
                    ];
                    return $map[$state] ?? $state;
                })
                ->colors([
                    'danger' => 'admin',
                    'warning' => 'manager',
                    'success' => 'accountant',
                    'info' => 'dispatcher',
                    'gray' => 'driver',
                ]),

            Tables\Columns\IconColumn::make('status')
                ->boolean()
                ->label('H.Động')
                ->alignCenter(),

            Tables\Columns\IconColumn::make('zalo_id')
                ->label('Zalo Alert')
                ->icon(fn ($state) => $state ? 'heroicon-o-chat-bubble-left-ellipsis' : 'heroicon-o-x-mark')
                ->color(fn ($state) => $state ? 'success' : 'gray')
                ->tooltip(fn ($record) => $record->zalo_id ? 'Zalo: ' . $record->zalo_id : 'Chưa cài đặt Zalo')
                ->alignCenter(),
        ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => UserResource\Pages\ListUsers::route('/'),
            'create' => UserResource\Pages\CreateUser::route('/create'),
            'edit' => UserResource\Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * 🔹 Chỉ query ra user thuộc nhóm admin/dispatcher/accountant
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role(['admin', 'dispatcher', 'accountant', 'manager']);
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
