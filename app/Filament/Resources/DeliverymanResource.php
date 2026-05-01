<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliverymanResource\Pages;
use App\Filament\Resources\DeliverymanResource\Widgets\DriverOverviewWidget;
use App\Models\DriverLicense;
use App\Models\Plan;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeliverymanResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon  = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Tài xế';
    protected static ?string $modelLabel      = 'tài xế';
    protected static ?string $pluralModelLabel = 'tài xế';
    protected static ?int    $navigationSort  = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'QUẢN LÝ TÀI XẾ';
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = User::drivers()->where('status', 0)->count();
        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // =========================================================================
    // FORM
    // =========================================================================

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([

                Forms\Components\Section::make('Thông tin cá nhân')
                    ->description('Thông tin định danh và xác thực tài khoản')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\FileUpload::make('profile_photo_path')
                            ->label('Ảnh đại diện')
                            ->disk('public')
                            ->image()->avatar()->directory('avatars')
                            ->imageEditor()->columnSpanFull(),

                        Forms\Components\TextInput::make('name')
                            ->label('Họ và tên')->required()->placeholder('Nguyễn Văn A'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Số điện thoại')->tel()->required()
                            ->unique(ignoreRecord: true)->placeholder('090xxxxxxx'),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')->email()->nullable()->placeholder('email@example.com'),

                        Forms\Components\TextInput::make('address')
                            ->label('Địa chỉ')->nullable()->placeholder('Số nhà, đường, quận...'),

                        Forms\Components\TextInput::make('password')
                            ->label('Mật khẩu')->password()->revealable()
                            ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn($state) => filled($state))
                            ->required(fn(string $context): bool => $context === 'create')
                            ->placeholder('Bỏ trống nếu không đổi'),
                    ])->columnSpan(1),

                Forms\Components\Section::make('Cấu hình vận hành')
                    ->description('Khu vực, ca làm việc, gói cước và trạng thái')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Forms\Components\Select::make('city_id')
                            ->label('Khu vực trực thuộc')
                            ->relationship('city', 'name', fn(Builder $query) => $query->active())
                            ->searchable()->preload()->required()->live()
                            ->afterStateUpdated(function (Forms\Set $set) {
                                $set('plan_id', null);
                                $set('_plan_type', null);
                                $set('shifts', []);
                            }),

                        Forms\Components\Select::make('plan_id')
                            ->label('Gói cước')
                            ->options(fn(Forms\Get $get) => Plan::active()
                                ->forCity((int) $get('city_id'))
                                ->get()
                                ->mapWithKeys(fn($plan) => [
                                    $plan->id => $plan->name . ' — ' . match ($plan->type) {
                                        Plan::TYPE_WEEKLY     => 'Cước tuần',
                                        Plan::TYPE_COMMISSION => 'Chiết khấu %',
                                        Plan::TYPE_PARTNER    => 'Đối tác',
                                        Plan::TYPE_FREE       => 'Miễn phí',
                                        default               => $plan->type,
                                    },
                                ])
                                ->toArray()
                            )
                            ->required()->live()->native(false)
                            ->placeholder('Chọn gói cước...')
                            ->helperText('Gói cước đang áp dụng cho khu vực này.')
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                $set('_plan_type', Plan::find($state)?->type);
                                $set('shifts', []);
                            })
                            ->visible(fn(Forms\Get $get) => filled($get('city_id'))),

                        Forms\Components\Hidden::make('_plan_type')
                            ->default(fn($record) => $record?->plan?->type),

                        Forms\Components\TextInput::make('custom_commission_rate')
                            ->label('Chiết khấu riêng (%)')->numeric()
                            ->minValue(0)->maxValue(100)->step(0.1)->suffix('%')
                            ->required(fn(Forms\Get $get) => $get('_plan_type') === Plan::TYPE_PARTNER)
                            ->helperText('Tỷ lệ % áp dụng riêng cho tài xế này. Bắt buộc với gói đối tác.')
                            ->visible(fn(Forms\Get $get) => $get('_plan_type') === Plan::TYPE_PARTNER),

                        Forms\Components\Select::make('shifts')
                            ->label('Ca làm việc đăng ký')->multiple()
                            ->relationship(
                                name: 'shifts',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn(Builder $query, Forms\Get $get) => $query->where(
                                    fn($q) => $q->where('city_id', $get('city_id'))->orWhereNull('city_id')
                                )
                            )
                            ->preload()
                            ->visible(fn(Forms\Get $get) => $get('_plan_type') === Plan::TYPE_WEEKLY),

                        Forms\Components\Select::make('status')
                            ->label('Trạng thái tài khoản')
                            ->options([0 => 'Chờ duyệt', 1 => 'Hoạt động', 2 => 'Bị khóa'])
                            ->default(0)->required()->native(false),
                    ])->columnSpan(2),
            ]),
        ]);
    }

    // =========================================================================
    // INFOLIST
    // =========================================================================

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            // ── HERO ──────────────────────────────────────────────────────────
            Components\Section::make()->schema([
                Components\Split::make([
                    Components\Group::make([
                        Components\ImageEntry::make('profile_photo_path')
                            ->label(false)->circular()->size(120)
                            ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=FFFFFF&background=03A9F4'),
                        Components\TextEntry::make('name')
                            ->label(false)->weight(FontWeight::Bold)
                            ->size(Components\TextEntry\TextEntrySize::Large),
                        Components\TextEntry::make('id')
                            ->label('Mã tài xế')->prefix('#')->color('gray'),
                        Components\TextEntry::make('status')
                            ->label(false)->badge()
                            ->formatStateUsing(fn($state) => match ((int) $state) {
                                0 => 'CHỜ DUYỆT', 1 => 'HOẠT ĐỘNG', 2 => 'BỊ KHÓA', default => '—',
                            })
                            ->color(fn($state) => match ((int) $state) {
                                0 => 'warning', 1 => 'success', 2 => 'danger', default => 'gray',
                            }),
                        Components\IconEntry::make('has_car_license')
                            ->label('Hồ sơ xe')->boolean()
                            ->trueIcon('heroicon-s-shield-check')
                            ->falseIcon('heroicon-o-shield-exclamation')
                            ->trueColor('success')->falseColor('warning'),
                    ])->grow(false),

                    Components\Section::make()->schema([
                        Components\TextEntry::make('phone')
                            ->label('Điện thoại')->icon('heroicon-m-phone')->copyable(),
                        Components\TextEntry::make('city.name')
                            ->label('Khu vực')->icon('heroicon-m-map-pin'),
                        Components\IconEntry::make('is_online')
                            ->label('Kết nối')->boolean()
                            ->trueIcon('heroicon-s-signal')->falseIcon('heroicon-o-signal-slash')
                            ->trueColor('success')->falseColor('gray'),
                        Components\TextEntry::make('last_login_at')
                            ->label('Hoạt động lần cuối')
                            ->dateTime('H:i d/m/Y')->placeholder('Chưa ghi nhận'),
                        Components\TextEntry::make('created_at')
                            ->label('Tham gia từ')->date('d/m/Y'),
                    ]),
                ])->from('md'),
            ]),

            // ── ROW 2: HIỆU SUẤT + VÍ + PHÂN VÙNG ───────────────────────────
            Components\Grid::make(3)->schema([

                Components\Section::make('Hiệu suất vận hành')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        Components\TextEntry::make('orders_count')
                            ->label('Tổng đơn nhận')
                            ->state(fn($record) => $record->orders()->count())
                            ->weight(FontWeight::Bold)->color('primary'),
                        Components\TextEntry::make('completed_orders')
                            ->label('Chuyến thành công')
                            ->state(fn($record) => $record->orders()->where('status', 'completed')->count())
                            ->weight(FontWeight::Bold)->color('success'),
                        Components\TextEntry::make('total_earnings')
                            ->label('Tổng thu nhập')
                            ->state(fn($record) => \App\Models\DriverEarning::where('driver_id', $record->id)->sum('amount'))
                            ->money('VND')->weight(FontWeight::Bold)->color('warning'),
                    ])->columns(3)->columnSpan(1),

                Components\Section::make('Ví & Gói cước')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Components\TextEntry::make('wallet.balance')
                            ->label('Số dư khả dụng')->money('VND')
                            ->weight(FontWeight::Bold)
                            ->color(fn($state) => ($state ?? 0) < 0 ? 'danger' : 'success')
                            ->size(Components\TextEntry\TextEntrySize::Large),
                        Components\TextEntry::make('plan.name')
                            ->label('Gói cước')->badge()
                            ->color(fn(string $state, $record) => match ($record->plan?->type) {
                                Plan::TYPE_FREE       => 'gray',
                                Plan::TYPE_WEEKLY     => 'info',
                                Plan::TYPE_COMMISSION => 'warning',
                                Plan::TYPE_PARTNER    => 'success',
                                default               => 'info',
                            }),
                        Components\TextEntry::make('custom_commission_rate')
                            ->label('Chiết khấu riêng')->suffix('%')
                            ->placeholder('Theo gói')
                            ->visible(fn($record) => $record->plan?->type === Plan::TYPE_PARTNER),
                    ])->columnSpan(1),

                Components\Section::make('Phân vùng & Ca làm việc')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Components\TextEntry::make('city.name')
                            ->label('Khu vực')->icon('heroicon-m-map-pin'),
                        Components\TextEntry::make('shifts_label')
                            ->label('Ca làm việc')
                            ->state(fn($record) => $record->shifts->pluck('name')->join(', ') ?: '—')
                            ->icon('heroicon-m-clock'),
                    ])->columnSpan(1),
            ]),

            // ── ROW 3: LIÊN HỆ + HỒ SƠ PHÁP LÝ ──────────────────────────────
            Components\Grid::make(2)->schema([

                Components\Section::make('Thông tin liên hệ')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Components\TextEntry::make('phone')->label('Điện thoại')->copyable()->icon('heroicon-m-phone'),
                        Components\TextEntry::make('email')->label('Email')->placeholder('Chưa có'),
                        Components\TextEntry::make('address')->label('Địa chỉ')->placeholder('Chưa cập nhật'),
                    ])->columnSpan(1),

                Components\Section::make('Hồ sơ pháp lý')
                    ->icon('heroicon-o-document-check')
                    ->schema([
                        Components\RepeatableEntry::make('driverLicenses')->label(false)->schema([
                            Components\Grid::make(2)->schema([
                                Components\ImageEntry::make('image_path')->label('Ảnh giấy tờ')->square(),
                                Components\TextEntry::make('status')->label('Trạng thái duyệt')->badge()
                                    ->formatStateUsing(fn($state) => match ($state) {
                                        DriverLicense::STATUS_PENDING  => 'Chờ duyệt',
                                        DriverLicense::STATUS_APPROVED => 'Đã duyệt',
                                        DriverLicense::STATUS_REJECTED => 'Từ chối',
                                        default => $state,
                                    })
                                    ->color(fn($state) => match ($state) {
                                        DriverLicense::STATUS_PENDING  => 'warning',
                                        DriverLicense::STATUS_APPROVED => 'success',
                                        DriverLicense::STATUS_REJECTED => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                        ]),
                    ])->columnSpan(1),
            ]),
        ]);
    }

    // =========================================================================
    // TABLE
    // =========================================================================

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordUrl(fn($record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\ImageColumn::make('profile_photo_path')
                    ->label('')
                    ->circular()
                    ->size(44)
                    ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&background=03A9F4&color=fff'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Tài xế')
                    ->searchable()->sortable()
                    ->weight('bold')
                    ->description(fn($record) => "#{$record->id} · " . ($record->phone ?? '—')),

                Tables\Columns\IconColumn::make('is_online')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-s-signal')
                    ->falseIcon('heroicon-o-signal-slash')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn($state) => $state ? 'Đang trực tuyến' : 'Ngoại tuyến')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('city.name')
                    ->label('Khu vực & Ca')
                    ->icon('heroicon-m-map-pin')
                    ->badge()->color('gray')
                    ->description(fn($record) => $record->shifts->pluck('name')->join(' · ') ?: null),

                Tables\Columns\IconColumn::make('has_car_license')
                    ->label('Hồ sơ')
                    ->boolean()
                    ->trueIcon('heroicon-s-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn($state) => $state ? 'Hồ sơ đã xác minh' : 'Chưa xác minh hồ sơ')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('wallet.balance')
                    ->label('Số dư ví')
                    ->money('VND')
                    ->color(fn($state) => ($state ?? 0) < 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()->alignCenter()
                    ->formatStateUsing(fn($state) => match ((int) $state) {
                        0 => 'Chờ duyệt', 1 => 'Hoạt động', 2 => 'Bị khóa', default => '—',
                    })
                    ->color(fn($state) => match ((int) $state) {
                        0 => 'warning', 1 => 'success', 2 => 'danger', default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tham gia')
                    ->since()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('delete_requested_at')
                    ->label('Y/c xóa TK')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn($state) => $state ? 'Chờ duyệt xóa' : null)
                    ->placeholder('—')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái tài khoản')
                    ->options([0 => 'Chờ duyệt', 1 => 'Hoạt động', 2 => 'Bị khóa']),

                Tables\Filters\TernaryFilter::make('is_online')
                    ->label('Trạng thái kết nối')
                    ->trueLabel('Đang online')
                    ->falseLabel('Offline'),

                Tables\Filters\SelectFilter::make('plan_id')
                    ->label('Gói cước')
                    ->relationship('plan', 'name'),

                Tables\Filters\TernaryFilter::make('has_car_license')
                    ->label('Xác minh hồ sơ')
                    ->trueLabel('Đã xác minh')
                    ->falseLabel('Chưa xác minh'),

                Tables\Filters\Filter::make('pending_delete')
                    ->label('Đang chờ xóa tài khoản')
                    ->query(fn($query) => $query->whereNotNull('delete_requested_at'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Duyệt')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->size('sm')
                    ->visible(fn($record) => (int) $record->status === 0)
                    ->requiresConfirmation()
                    ->modalHeading('Duyệt tài xế')
                    ->modalDescription(fn($record) => "Duyệt và kích hoạt tài khoản cho {$record->name}?")
                    ->action(fn($record) => $record->update(['status' => 1])),

                Tables\Actions\Action::make('approve_delete')
                    ->label('Duyệt xóa TK')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->size('sm')
                    ->visible(fn($record) => $record->delete_requested_at !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Duyệt xóa tài khoản')
                    ->modalDescription(fn($record) => "Xóa vĩnh viễn tài khoản {$record->name}? Hành động này không thể hoàn tác.")
                    ->modalSubmitActionLabel('Đồng ý xóa')
                    ->action(fn($record) => $record->delete()),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('reject_delete')
                        ->label('Từ chối xóa TK')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->visible(fn($record) => $record->delete_requested_at !== null)
                        ->requiresConfirmation()
                        ->modalHeading('Từ chối yêu cầu xóa')
                        ->modalDescription(fn($record) => "Từ chối yêu cầu xóa tài khoản của {$record->name}?")
                        ->action(fn($record) => $record->update(['delete_requested_at' => null])),

                    Tables\Actions\Action::make('block')
                        ->label('Khóa tài khoản')
                        ->icon('heroicon-o-lock-closed')->color('danger')
                        ->visible(fn($record) => (int) $record->status === 1)
                        ->requiresConfirmation()
                        ->action(fn($record) => $record->update(['status' => 2, 'is_online' => false])),

                    Tables\Actions\Action::make('unblock')
                        ->label('Mở khóa')
                        ->icon('heroicon-o-lock-open')->color('success')
                        ->visible(fn($record) => (int) $record->status === 2)
                        ->requiresConfirmation()
                        ->action(fn($record) => $record->update(['status' => 1])),

                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_approve')
                        ->label('Duyệt đã chọn')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Duyệt tài xế đã chọn')
                        ->modalDescription('Kích hoạt tất cả tài xế được chọn?')
                        ->action(fn($records) => $records->each->update(['status' => 1])),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // =========================================================================
    // PAGES & WIDGETS & PERMISSIONS
    // =========================================================================

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDeliverymen::route('/'),
            'create' => Pages\CreateDeliveryman::route('/create'),
            'edit'   => Pages\EditDeliveryman::route('/{record}/edit'),
            'view'   => Pages\ViewDeliveryman::route('/{record}'),
        ];
    }

    public static function getWidgets(): array
    {
        return [DriverOverviewWidget::class];
    }

    public static function canViewAny(): bool       { return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager', 'dispatcher']); }
    public static function canCreate(): bool        { return auth()->check() && auth()->user()->hasRole('admin'); }
    public static function canEdit($record): bool   { return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager', 'dispatcher']); }
    public static function canDelete($record): bool { return auth()->check() && auth()->user()->hasRole('admin'); }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->role('driver')
            ->with(['city:id,name', 'shifts:id,name', 'plan:id,name,type', 'wallet:id,driver_id,balance']);

        $user = auth()->user();

        if ($user->hasRole('admin')) {
            if ($cityId = session('current_city_id')) {
                $query->where('city_id', $cityId);
            }
        } elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $query->where('city_id', $user->city_id);
        }

        return $query;
    }
}
