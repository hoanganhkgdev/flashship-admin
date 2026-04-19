<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\DeliverymanResource\Pages;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Alignment;

class DeliverymanResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Tài xế';
    protected static ?string $modelLabel = 'tài xế';
    protected static ?string $pluralModelLabel = 'tài xế';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\Section::make('Thông tin định danh')
                        ->schema([
                            Forms\Components\FileUpload::make('profile_photo_path')
                                ->label('Ảnh đại diện')
                                ->image()
                                ->avatar()
                                ->directory('avatars')
                                ->imageEditor()
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('name')
                                ->label('Họ và tên tài xế')
                                ->required()
                                ->placeholder('Ví dụ: Nguyễn Văn A'),
                            Forms\Components\TextInput::make('phone')
                                ->label('Số điện thoại')
                                ->tel()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->placeholder('090xxxxxxx'),
                            Forms\Components\TextInput::make('password')
                                ->label('Mật khẩu')
                                ->password()
                                ->revealable()
                                ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                                ->dehydrated(fn($state) => filled($state))
                                ->required(fn(string $context): bool => $context === 'create')
                                ->placeholder('Bỏ trống nếu không đổi'),
                        ])->columnSpan(1),

                    Forms\Components\Section::make('Cấu hình vận hành')
                        ->schema([
                            Forms\Components\Select::make('city_id')
                                ->label('Khu vực trực thuộc')
                                ->relationship('city', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    if (!$state) return;
                                    // Tự động gán gói cước theo khu vực (commission hoặc weekly)
                                    $plan = \App\Models\Plan::where('is_active', 1)
                                        ->where('city_id', $state)
                                        ->first();
                                    $set('plan_id', $plan?->id);
                                    // Không reset shifts — giữ nguyên ca đã gán
                                }),

                            // plan_id luôn ẩn — tự động gán từ afterStateUpdated của city_id
                            Forms\Components\Hidden::make('plan_id'),

                            Forms\Components\TextInput::make('custom_commission_rate')
                                ->label('Chiết khấu hoa hồng riêng (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.1)
                                ->placeholder('Bỏ trống để dùng gói khu vực')
                                ->helperText('Thiết lập % chiết khấu riêng cho tài xế này. Nếu có, hệ thống sẽ bỏ qua gói khu vực.'),

                            Forms\Components\Select::make('shifts')
                                ->label('Ca làm việc đăng ký')
                                ->multiple()
                                ->relationship(
                                    name: 'shifts',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn(Builder $query, Forms\Get $get) => $query
                                        ->where(function ($q) use ($get) {
                                            $cityId = $get('city_id');
                                            $q->where('city_id', $cityId)
                                                ->orWhereNull('city_id');
                                        })
                                )
                                ->preload(),
                                // 🔧 TẠM THỜI: hiện shift cho mọi khu vực — gán ca "Cả ngày" cho tài xế CT
                                // TODO: Khôi phục hidden/required sau khi app mới lên App Store

                            Forms\Components\Select::make('status')
                                ->label('Trạng thái tài khoản')
                                ->options([
                                    0 => 'Chờ duyệt',
                                    1 => 'Hoạt động',
                                    2 => 'Bị khóa',
                                ])
                                ->default(0)
                                ->required()
                                ->native(false),
                        ])->columnSpan(2),
                ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make()
                    ->schema([
                        Components\Split::make([
                            Components\Grid::make(2)
                                ->schema([
                                    Components\Group::make([
                                        Components\ImageEntry::make('profile_photo_path')
                                            ->label(false)
                                            ->circular()
                                            ->size(100)
                                            ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=FFFFFF&background=03A9F4'),
                                    ])->grow(false),
                                    Components\Group::make([
                                        Components\TextEntry::make('name')
                                            ->label(false)
                                            ->weight(FontWeight::Bold)
                                            ->size(Components\TextEntry\TextEntrySize::Large),
                                        Components\TextEntry::make('id')
                                            ->label('ID Tài xế')
                                            ->prefix('#')
                                            ->color('gray'),
                                        Components\TextEntry::make('status')
                                            ->label(false)
                                            ->badge()
                                            ->formatStateUsing(fn($state) => match ((int) $state) {
                                                0 => 'CHỜ DUYỆT',
                                                1 => 'HOẠT ĐỘNG',
                                                2 => 'BỊ KHÓA',
                                                default => 'KHÔNG XÁC ĐỊNH',
                                            })
                                            ->color(fn($state) => match ((int) $state) {
                                                0 => 'warning',
                                                1 => 'success',
                                                2 => 'danger',
                                                default => 'gray',
                                            }),
                                    ]),
                                ])->columnSpan(2),

                            Components\Section::make('Kết nối hệ thống')
                                ->schema([
                                    Components\IconEntry::make('is_online')
                                        ->label('Trạng thái kết nối')
                                        ->boolean()
                                        ->trueIcon('heroicon-s-signal')
                                        ->falseIcon('heroicon-o-signal-slash')
                                        ->trueColor('success')
                                        ->falseColor('gray'),
                                    Components\TextEntry::make('last_login_at')
                                        ->label('Hoạt động lần cuối')
                                        ->dateTime('H:i d/m/Y')
                                        ->placeholder('Chưa ghi nhận'),
                                ])->columnSpan(1),
                        ])->from('md'),
                    ]),

                Components\Grid::make(3)
                    ->schema([
                        Components\Section::make('Hiệu suất vận hành')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                Components\TextEntry::make('orders_count')
                                    ->label('Tổng đơn nhận')
                                    ->state(fn($record) => $record->orders()->count())
                                    ->weight(FontWeight::Bold)
                                    ->color('primary'),
                                Components\TextEntry::make('completed_orders_count')
                                    ->label('Chuyến thành công')
                                    ->state(fn($record) => $record->orders()->where('status', 'completed')->count())
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),
                                Components\TextEntry::make('total_earnings')
                                    ->label('Tổng thu nhập')
                                    ->state(fn($record) => \App\Models\DriverEarning::where('driver_id', $record->id)->sum('amount'))
                                    ->money('VND')
                                    ->weight(FontWeight::Bold)
                                    ->color('warning'),
                            ])->columns(3)
                            ->columnSpan(1),

                        Components\Section::make('Ví & Tài chính')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Components\TextEntry::make('wallet.balance')
                                    ->label('Số dư khả dụng')
                                    ->money('VND')
                                    ->weight(FontWeight::Bold)
                                    ->color(fn($state) => ($state ?? 0) < 0 ? 'danger' : 'success')
                                    ->size(Components\TextEntry\TextEntrySize::Large),
                                Components\TextEntry::make('plan.name')
                                    ->label('Gói cước')
                                    ->badge()
                                    ->color('info'),
                            ])->columnSpan(1),

                        Components\Section::make('Đào tạo & Phân vùng')
                            ->icon('heroicon-o-academic-cap')
                            ->schema([
                                Components\TextEntry::make('city.name')
                                    ->label('Khu vực trực thuộc')
                                    ->icon('heroicon-m-map-pin'),
                                Components\TextEntry::make('shifts')
                                    ->label('Ca làm việc')
                                    ->formatStateUsing(fn($record) => $record->shifts->pluck('name')->join(', '))
                                    ->icon('heroicon-m-clock'),
                            ])->columnSpan(1),
                    ]),

                Components\Grid::make(2)
                    ->schema([
                        Components\Section::make('Thông tin liên hệ')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Components\TextEntry::make('phone')
                                    ->label('Số điện thoại')
                                    ->copyable()
                                    ->icon('heroicon-m-phone'),
                                Components\TextEntry::make('email')
                                    ->label('Địa chỉ Email')
                                    ->placeholder('Chưa có email'),
                                Components\TextEntry::make('address')
                                    ->label('Địa chỉ cư trú')
                                    ->placeholder('Chưa cập nhật địa chỉ'),
                            ])->columnSpan(1),

                        Components\Section::make('Hồ sơ pháp lý')
                            ->icon('heroicon-o-document-check')
                            ->schema([
                                Components\RepeatableEntry::make('driverLicenses')
                                    ->label(false)
                                    ->schema([
                                        Components\Grid::make(2)->schema([
                                            Components\ImageEntry::make('image_path')
                                                ->label('Ảnh giấy tờ')
                                                ->square(),
                                            Components\TextEntry::make('status')
                                                ->label('Trạng thái duyệt')
                                                ->badge()
                                                ->color(fn($state) => match ($state) {
                                                    'pending' => 'warning',
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    default => 'gray',
                                                }),
                                        ]),
                                    ]),
                            ])->columnSpan(1),
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

                Tables\Columns\ImageColumn::make('profile_photo_path')
                    ->label('Avatar')
                    ->circular()
                    ->size(45),

                Tables\Columns\TextColumn::make('name')
                    ->label('Họ tên')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn($record) => "ID: #{$record->id}")
                    ->copyable()
                    ->copyMessage('Đã sao chép tên tài xế'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Liên hệ')
                    ->icon('heroicon-m-phone')
                    ->color('primary')
                    ->description(fn($record) => $record->phone ? "Zalo: 0" . substr($record->phone, -9) : null)
                    ->url(fn($record) => $record->phone ? "https://zalo.me/0" . substr($record->phone, -9) : null)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('city.name')
                    ->label('Khu vực & Ca')
                    ->icon('heroicon-m-map-pin')
                    ->description(function ($record) {
                        return $record->shifts->pluck('name')->join(', ');
                    })
                    ->color('gray'),

                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Gói cước')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-briefcase')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('wallet.balance')
                    ->label('Ví hiện tại')
                    ->money('VND')
                    ->color(fn($state) => $state < 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->alignCenter()
                    ->formatStateUsing(fn($state) => match ((int) $state) {
                        0 => 'Chờ duyệt',
                        1 => 'Đang chạy',
                        2 => 'Bị khóa',
                        default => 'Không xác định',
                    })
                    ->color(fn($state) => match ((int) $state) {
                        0 => 'warning',
                        1 => 'success',
                        2 => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_online')
                    ->label('Sóng')
                    ->boolean()
                    ->trueIcon('heroicon-s-signal')
                    ->falseIcon('heroicon-o-signal-slash')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn($state) => $state ? 'Đang trực tuyến' : 'Ngoại tuyến')
                    ->alignCenter(),
            ])
            // 🔹 Bổ sung phần FILTERS tại đây
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('approve')
                        ->label('Duyệt tài xế')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn($record) => $record->status === 0)
                        ->requiresConfirmation()
                        ->action(fn($record) => $record->update(['status' => 1])),

                    Tables\Actions\Action::make('block')
                        ->label('Khóa tài khoản')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->visible(fn($record) => $record->status === 1)
                        ->requiresConfirmation()
                        ->action(fn($record) => $record->update(['status' => 2, 'is_online' => false])),

                    Tables\Actions\EditAction::make(),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('gray')
                    ->button()
                    ->label('Tùy chọn'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn($record) => DeliverymanResource::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliverymen::route('/'),
            'create' => Pages\CreateDeliveryman::route('/create'),
            'edit' => Pages\EditDeliveryman::route('/{record}/edit'),
            'view' => Pages\ViewDeliveryman::route('/{record}'),
            'simulator' => Pages\DriverSimulator::route('/simulator'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager', 'dispatcher']);
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin']);
    }

    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager', 'dispatcher']);
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->role('driver')
            ->with(['city:id,name', 'shifts:id,name', 'plan:id,name,type', 'wallet:id,driver_id,balance']); // eager load tránh N+1

        $user = auth()->user();

        // 👑 Admin: xem theo vùng đang chọn
        if ($user->hasRole('admin')) {
            if (session()->has('current_city_id')) {
                $query->where('city_id', session('current_city_id'));
            }
        }
        // 👨‍💼 Manager / Dispatcher: cố định theo city_id của họ
        elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $query->where('city_id', $user->city_id);
        }

        return $query;
    }

}
