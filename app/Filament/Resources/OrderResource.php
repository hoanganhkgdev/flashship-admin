<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Helpers\FcmHelper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use Illuminate\Database\Eloquent\Builder;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\ActionSize;


class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Đơn hàng';
    protected static ?string $modelLabel = 'đơn hàng';
    protected static ?string $pluralModelLabel = 'Đơn hàng';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // ─── 0. LOẠI DỊCH VỤ ──────────────────────────────────────────────────
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\ToggleButtons::make('service_type')
                        ->hiddenLabel()
                        ->options([
                            'delivery' => 'Cửa Hàng',
                            'shopping' => 'Mua Hộ',
                            'topup' => 'Nạp Tiền',
                            'bike' => 'Xe Ôm',
                            'motor' => 'Lái Xe Máy',
                            'car' => 'Lái Xe Ô Tô',
                        ])
                        ->icons([
                            'delivery' => 'heroicon-m-shopping-bag',
                            'shopping' => 'heroicon-m-shopping-cart',
                            'topup' => 'heroicon-m-banknotes',
                            'bike' => 'heroicon-m-bolt',
                            'motor' => 'heroicon-m-identification',
                            'car' => 'heroicon-m-truck',
                        ])
                        ->colors([
                            'delivery' => 'primary',
                            'shopping' => 'info',
                            'topup' => 'success',
                            'bike' => 'warning',
                            'motor' => 'primary',
                            'car' => 'danger',
                        ])
                        ->inline()
                        ->default('delivery')
                        ->required()
                        ->live() // 👉 Kích hoạt phản hồi trực tiếp
                        ->afterStateUpdated(function (Forms\Set $set) {
                            // 👉 Xóa sạch nội dung cũ khi đổi loại dịch vụ
                            $set('order_note', '');
                            $set('shipping_fee', 0);
                            $set('bonus_fee', 0);
                            $set('is_freeship', false);
                        })
                        ->columnSpanFull(),
                ])->compact(),

            // ─── 1. PHẦN CHÍNH (NỘI DUNG & PHÍ) ───────────────────────────────────
            Forms\Components\Grid::make(12)->schema([
                // CỘT TRÁI (8/12): Ghi chú - Chỉ con người thao tác tại đây
                Forms\Components\Section::make('Nội dung đơn hàng (Ghi chú)')
                    ->schema([
                        Forms\Components\Textarea::make('order_note')
                            ->hiddenLabel()
                            ->placeholder("Dán văn bản từ Zalo/Facebook...\nVí dụ: Ship tô bún riêu qua 123 Lê Lợi. Giá ship 15k")
                            ->rows(12)
                            ->extraAttributes(['class' => 'font-mono text-base'])
                            ->live(debounce: 500) // 👉 Phản hồi ngay sau khi ngừng gõ 0.5s
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (empty($state))
                                    return;

                                // ⚡️ CHỈ BÓC TÁCH DUY NHẤT PHÍ SHIP (Regex Nâng cao)
                                // Hỗ trợ: 'Ship 15', 'Phí 15k', 'Tiền xe 15.000', 'Cước 15,5', 'Fee 15000'
                                $feeRegex = '/(?:Phí ship|Tiền ship|Ship|Phí|Tiền Xe|Cước|Fee):?\s*([\d\.,\s]+k?)/i';
                                if (preg_match($feeRegex, $state, $matchFee)) {
                                    $rawFee = strtolower(trim($matchFee[1]));
                                    // Loại bỏ dấu phẩy, dấu chấm, khoảng trắng
                                    $cleanFee = str_replace([',', '.', ' '], '', $rawFee);
                                    $hasK = str_contains($cleanFee, 'k');
                                    $numericPart = (float) str_replace('k', '', $cleanFee);

                                    // 👉 Nếu có chữ 'k' HOẶC số nhỏ hơn 1000 (ví dụ: 15, 15.5, 20) -> Nhân 1000
                                    $feeValue = ($hasK || ($numericPart > 0 && $numericPart < 1000))
                                        ? (int) ($numericPart * 1000)
                                        : (int) $numericPart;

                                    if ($feeValue > 0) {
                                        $set('shipping_fee', $feeValue);
                                        // 🧨 CHỈ CẬP NHẬT PHÍ, GIỮ NGUYÊN NOTE CHO dispatcher QUAN SÁT
                                    }
                                }
                            }),
                    ])->columnSpan(8),

                // CỘT PHẢI (4/12): Cước phí & Phụ thu
                Forms\Components\Section::make('Phí & Phụ thu')
                    ->schema([
                        Forms\Components\Select::make('city_id')
                            ->label('Khu vực (Thành phố)')
                            ->options(\App\Models\City::all()->pluck('name', 'id'))
                            ->default(fn() => session('current_city_id') ?? auth()->user()->city_id ?? \App\Models\City::first()?->id)
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('delivery_man_id')
                            ->label('Gán tài xế (tùy chọn)')
                            ->placeholder('— Không gán —')
                            ->options(function (Forms\Get $get) {
                                $cityId = $get('city_id');
                                return \App\Models\User::drivers()
                                    ->where('is_online', true)
                                    ->where('status', 1)
                                    ->when($cityId, fn($q) => $q->where('city_id', $cityId))
                                    ->get()
                                    ->mapWithKeys(fn($u) => [$u->id => "#{$u->id} {$u->name}"]);
                            })
                            ->searchable()
                            ->live()
                            ->nullable()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('shipping_fee')
                            ->label('Phí vận chuyển')
                            ->prefix('₫')
                            ->extraInputAttributes(['class' => 'font-bold text-primary-600 text-xl'])
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('bonus_fee')
                            ->label('Bonus (Phụ thu thêm)')
                            ->prefix('₫')
                            ->helperText('Ví dụ: Tiền mua hộ, Tip, Xăng xe...')
                            ->default(0),

                        Forms\Components\Toggle::make('is_freeship')
                            ->label('Freeship (Shop trả)')
                            ->onIcon('heroicon-m-check')
                            ->offIcon('heroicon-m-x-mark')
                            ->default(false),
                    ])->columnSpan(4),
            ]),

            // ─── 2. CHI TIẾT LỘ TRÌNH (ẨN ĐỐI VỚI ĐƠN THỦ CÔNG) ──────────────────
            // 💡 Chỉ hiển thị khi đơn đã bóc tách qua AI hoặc AI tự tạo
            Forms\Components\Section::make('Chi tiết địa chỉ (Dành cho AI)')
                ->description('AI sẽ tự điền phần này cho lộ trình thông minh.')
                ->collapsible()
                ->collapsed() // 👉 Mặc định luôn thu gọn
                ->hidden(fn($get) => !$get('is_ai_created')) // 👉 ẨN HOÀN TOÀN nếu không phải AI created/parsed
                ->icon('heroicon-m-map-pin')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Fieldset::make('ĐIỂM LẤY (A)')
                            ->schema([
                                Forms\Components\TextInput::make('pickup_address')->label('Địa chỉ lấy'),
                                Forms\Components\TextInput::make('pickup_phone')->label('SĐT lấy'),
                            ]),
                        Forms\Components\Fieldset::make('ĐIỂM GIAO (B)')
                            ->schema([
                                Forms\Components\TextInput::make('delivery_address')->label('Địa chỉ giao'),
                                Forms\Components\TextInput::make('delivery_phone')->label('SĐT giao'),
                            ]),
                    ]),
                ]),

            // CÁC TRƯỜNG ẨN
            Forms\Components\Hidden::make('is_ai_created')
                ->default(false),
        ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->poll('30s')
            ->recordUrl(null)
            ->columns([
                // 🔹 1. #ID ORDER & NGUỒN TẠO
                TextColumn::make('id')
                    ->label('#ID Order')
                    ->formatStateUsing(function ($state, $record) {
                        $icon = $record->is_ai_created
                            ? "<span title='Đơn hàng từ AI' class='text-[14px] opacity-70'>🤖</span>"
                            : "<span title='Đơn hàng hand' class='text-[14px] opacity-70'>👤</span>";
                        return "<div class='flex items-center gap-1.5 leading-none'>
                                    <div class='text-slate-700 text-[13px] tracking-tighter'>#{$state}</div>
                                    {$icon}
                                </div>";
                    })->html(),

                // 🔹 2. NỘI DUNG VẬN CHUYỂN
                TextColumn::make('order_note')
                    ->label('Order Notes')
                    ->searchable(['pickup_address', 'delivery_address', 'order_note'])
                    ->width('350px')
                    ->wrap()
                    ->html()
                    ->formatStateUsing(fn($state) => nl2br(e($state))),

                // 🔹 3. SERVICES
                TextColumn::make('shipping_fee')
                    ->label('Services')
                    ->alignLeft()
                    ->formatStateUsing(function ($state, $record) {
                        $bonus = $record->bonus_fee ?? 0;
                        $total = $state + $bonus;

                        $type = match ($record->service_type) {
                            'delivery' => '🛒',
                            'shopping' => '🛍️',
                            'topup' => '💰',
                            'bike' => '🛵',
                            'motor' => '🏍️',
                            'car' => '🚗',
                            default => '📦'
                        };
                        $serviceName = match ($record->service_type) {
                            'delivery' => 'Giao hàng',
                            'shopping' => 'Mua hộ',
                            'topup' => 'Nạp tiền',
                            'bike' => 'Xe ôm',
                            'motor' => 'Lái hộ xe',
                            'car' => 'Lái hộ ô tô',
                            default => ucfirst($record->service_type ?? 'Dịch vụ')
                        };

                        $feeDisplay = $record->is_freeship
                            ? "<span class='text-emerald-600 font-bold'>MIỄN PHÍ</span>"
                            : number_format($total, 0, ',', '.') . "₫";

                        $bonusLabel = $bonus > 0
                            ? "<div class='text-[10px] text-amber-600 mt-0.5 tracking-tight'>+ " . number_format($bonus, 0, ',', '.') . "₫ Bonus</div>"
                            : "";

                        return "<div class='flex flex-col gap-0.5 leading-none px-1'>
                                    <div class='text-[13px] text-slate-800 tracking-tight'>{$feeDisplay}</div>
                                    {$bonusLabel}
                                    <div class='flex items-center gap-1.5 text-slate-400 mt-1'>
                                        <span class='text-[11px] opacity-80'>{$type}</span>
                                        <span class='text-[10px] tracking-tight'>{$serviceName}</span>
                                    </div>
                                </div>";
                    })->html(),

                // 🔹 4. STATUS
                TextColumn::make('status')
                    ->label('Status')
                    ->alignLeft()
                    ->formatStateUsing(function ($state) {
                        $config = match ($state) {
                            'pending' => ['color' => '#0ea5e9', 'label' => 'Chờ xử lý'],
                            'assigned' => ['color' => '#f59e0b', 'label' => 'Đã nhận'],
                            'delivering' => ['color' => '#6366f1', 'label' => 'Đang giao'],
                            'delivered_pending' => ['color' => '#a855f7', 'label' => 'Chờ duyệt'],
                            'completed' => ['color' => '#22c55e', 'label' => 'Hoàn tất'],
                            'cancelled' => ['color' => '#ef4444', 'label' => 'Đã hủy'],
                            'pending_address' => ['color' => '#64748b', 'label' => 'Đợi xử lý địa chỉ'],
                            default => ['color' => '#64748b', 'label' => ucfirst($state)],
                        };
                        return "<span class='text-[11px] tracking-tight' style='color:{$config['color']};'>
                                    " . $config['label'] . "
                                </span>";
                    })->html(),

                // 🔹 5. DRIVER
                TextColumn::make('driver.name')
                    ->label('Driver')
                    ->alignLeft()
                    ->placeholder('Đang tìm...')
                    ->searchable()
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->driver)
                            return "<span class='text-[10px] text-slate-300 italic'>Chưa có xế</span>";

                        $isOnline = $record->driver->is_online ? 'bg-emerald-500' : 'bg-slate-300';
                        $phone = $record->driver->phone ?? '';

                        return "<div class='flex items-start gap-1.5 leading-tight'>
                                    <span class='w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0 {$isOnline}'></span>
                                    <div class='flex flex-col gap-0.5'>
                                        <div class='text-[12px] text-slate-700 truncate tracking-tight'>{$state}</div>
                                        " . ($phone ? "<a href='https://zalo.me/{$phone}' target='_blank' class='text-[10px] text-blue-500 hover:text-blue-600 hover:underline font-mono tracking-tighter'>{$phone}</a>" : "<span class='text-[10px] text-slate-400'>N/A</span>") . "
                                    </div>
                                </div>";
                    })->html(),

                // 🔹 6. TIME
                TextColumn::make('created_at')
                    ->label('Time')
                    ->alignLeft()
                    ->formatStateUsing(function ($state) {
                        return "<div class='flex flex-col gap-0.5 leading-none'>
                                    <div class='text-[12px] text-slate-700 tracking-tight'>" . $state->format('H:i') . "</div>
                                    <div class='text-[9px] text-slate-400 mt-1 tracking-tight'>" . $state->diffForHumans() . "</div>
                                </div>";
                    })->html(),
            ])
            ->filters([
                SelectFilter::make('city_id')
                    ->label('Thành phố')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Tất cả'),

                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'pending' => 'Chờ xử lý',
                        'assigned' => 'Đã nhận',
                        'delivering' => 'Đang giao',
                        'delivered_pending' => 'Chờ duyệt',
                        'completed' => 'Hoàn tất',
                        'cancelled' => 'Đã hủy',
                    ])
                    ->multiple(),

                SelectFilter::make('service_type')
                    ->label('Loại dịch vụ')
                    ->options([
                        'delivery' => 'Cửa hàng',
                        'shopping' => 'Mua hộ',
                        'topup' => 'Nạp tiền',
                        'bike' => 'Xe ôm',
                        'motor' => 'Lái xe máy',
                        'car' => 'Lái xe ô tô',
                    ]),

                SelectFilter::make('is_ai_created')
                    ->label('Nguồn đơn')
                    ->options([
                        '1' => 'Đơn từ AI',
                        '0' => 'Đơn Tổng đài',
                    ])
                    ->placeholder('Tất cả'),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('Từ ngày'),
                        DatePicker::make('until')->label('Đến ngày'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->defaultSort('id', 'desc')
            ->actions([


                Actions\Action::make('assign_driver')
                    ->label('')
                    ->tooltip('Gán tài xế')
                    ->icon('heroicon-m-user-plus')
                    ->color('info')
                    ->iconButton()
                    ->size(ActionSize::Small)
                    ->modalHeading('Chỉ định tài xế cho đơn hàng')
                    ->modalDescription('Lưu ý: Chỉ danh sách tài xế cùng khu vực mới được hiển thị.')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Select::make('delivery_man_id')
                            ->label('Chọn tài xế')
                            ->options(function ($record) {
                                return \App\Models\User::role('driver')
                                    ->where('city_id', $record->city_id)
                                    ->where('is_online', true)
                                    ->get()
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->placeholder('🔍 Tìm tài xế online...')
                            ->helperText('Danh sách chỉ hiển thị các tài xế đang trực tuyến trong khu vực.'),
                    ])
                    ->action(function (array $data, $record) {
                        $record->update([
                            'delivery_man_id' => $data['delivery_man_id'],
                            'status' => 'assigned',
                        ]);
                        $driver = \App\Models\User::find($data['delivery_man_id']);
                        if ($driver)
                            \App\Services\NotificationService::notifyOrderAssigned($record, $driver);
                        \Filament\Notifications\Notification::make()
                            ->title("Đã gán đơn #{$record->id} cho tài xế " . ($driver->name ?? ''))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('confirm')
                    ->label('')
                    ->tooltip('Xác nhận giao hàng')
                    ->icon('heroicon-m-check-badge')
                    ->size(ActionSize::Small)
                    ->iconButton()
                    ->visible(fn($record) => $record->status === 'delivered_pending')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'completed', 'completed_at' => now()]);
                        \Filament\Notifications\Notification::make()->title("Đã xác nhận hoàn thành đơn #{$record->id}")->success()->send();
                    }),

                Actions\Action::make('cancel')
                    ->label('')
                    ->tooltip('Hủy đơn')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->size(ActionSize::Small)
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(fn($record) => !in_array($record->status, ['cancelled', 'completed']))
                    ->action(function ($record) {
                        $record->update(['status' => 'cancelled']);
                        \Filament\Notifications\Notification::make()->title("Đơn #{$record->id} đã bị hủy")->danger()->send();
                    }),

                Actions\EditAction::make()
                    ->label('')
                    ->tooltip('Sửa')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->size(ActionSize::Small)
                    ->iconButton(),

                Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Xóa')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->size(ActionSize::Small)
                    ->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Grid::make(12)->schema([
                    // --- CỘT TRÁI (8) ---
                    Components\Group::make([
                        // KHỐI LỘ TRÌNH
                        Components\Section::make('Chi tiết lộ trình vận chuyển')
                            ->icon('heroicon-o-map')
                            ->schema([
                                Components\Split::make([
                                    Components\Group::make([
                                        Components\TextEntry::make('pickup_address')
                                            ->label('Điểm lấy hàng (A)')
                                            ->weight(FontWeight::Bold)
                                            ->color('primary')
                                            ->size(Components\TextEntry\TextEntrySize::Large),
                                        Components\TextEntry::make('pickup_phone')
                                            ->label('SĐT & Người gửi')
                                            ->formatStateUsing(fn($record) => ($record->sender_name ?? 'N/A') . " (" . ($record->pickup_phone ?? '-') . ")")
                                            ->icon('heroicon-m-phone'),
                                    ]),
                                    Components\Group::make([
                                        Components\TextEntry::make('delivery_address')
                                            ->label('Điểm giao hàng (B)')
                                            ->weight(FontWeight::Bold)
                                            ->color('success')
                                            ->size(Components\TextEntry\TextEntrySize::Large),
                                        Components\TextEntry::make('delivery_phone')
                                            ->label('SĐT & Người nhận')
                                            ->formatStateUsing(fn($record) => ($record->receiver_name ?? 'N/A') . " (" . ($record->delivery_phone ?? '-') . ")")
                                            ->icon('heroicon-m-user'),
                                    ]),
                                ])->from('md'),
                            ]),

                        Components\Section::make('Nội dung hàng hóa & Ghi chú')
                            ->icon('heroicon-o-shopping-bag')
                            ->schema([
                                Components\TextEntry::make('order_note')
                                    ->label(false)
                                    ->html()
                                    ->formatStateUsing(fn($state) => nl2br(e($state ?: '(Trống)')))
                                    ->extraAttributes(['class' => 'bg-slate-50 p-4 rounded-lg border border-slate-200 font-mono text-sm leading-relaxed text-slate-700']),
                            ]),

                        // KHỐI LỊCH SỬ THỜI GIAN (TIMELINE)
                        Components\Section::make('Dòng thời gian đơn hàng')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Components\RepeatableEntry::make('histories')
                                    ->label(false)
                                    ->schema([
                                        Components\Grid::make(3)->schema([
                                            Components\TextEntry::make('description')
                                                ->label(false)
                                                ->weight(FontWeight::Bold)
                                                ->columnSpan(2),
                                            Components\Group::make([
                                                Components\TextEntry::make('created_at')
                                                    ->label(false)
                                                    ->dateTime('H:i d/m/Y')
                                                    ->color('gray')
                                                    ->alignRight(),
                                                Components\TextEntry::make('user.name')
                                                    ->label(false)
                                                    ->placeholder('Hệ thống')
                                                    ->color('primary')
                                                    ->size(Components\TextEntry\TextEntrySize::Small)
                                                    ->alignRight(),
                                            ]),
                                        ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])->columnSpan(8),

                    // --- CỘT PHẢI (4) ---
                    Components\Group::make([
                        // TRẠNG THÁI ĐƠN
                        Components\Section::make('Trạng thái hiện tại')
                            ->schema([
                                Components\TextEntry::make('status')
                                    ->label(false)
                                    ->badge()
                                    ->size(Components\TextEntry\TextEntrySize::Large)
                                    ->alignment(Alignment::Center)
                                    ->icon(fn($state) => match ($state) {
                                        'draft' => 'heroicon-m-sparkles',
                                        'pending' => 'heroicon-m-clock',
                                        'assigned' => 'heroicon-m-check-badge',
                                        'delivering' => 'heroicon-m-truck',
                                        'completed' => 'heroicon-m-check-badge',
                                        'cancelled' => 'heroicon-m-x-circle',
                                        default => 'heroicon-m-question-mark-circle',
                                    })
                                    ->colors([
                                        'info' => 'draft',
                                        'warning' => 'pending',
                                        'primary' => 'assigned',
                                        'indigo' => 'delivering',
                                        'success' => 'completed',
                                        'danger' => 'cancelled',
                                    ])
                                    ->formatStateUsing(fn($state) => match ($state) {
                                        'draft' => 'ĐƠN AI ĐỢI DUYỆT',
                                        'pending' => 'CHỜ XỬ LÝ',
                                        'assigned' => 'ĐÃ CÓ TÀI XẾ',
                                        'delivering' => 'ĐANG GIAO HÀNG',
                                        'completed' => 'HOÀN TẤT',
                                        'cancelled' => 'ĐÃ HỦY ĐƠN',
                                        default => strtoupper($state),
                                    }),
                            ]),

                        // ĐỐI SOÁT TÀI CHÍNH
                        Components\Section::make('Đối soát tài chính')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Components\TextEntry::make('shipping_fee')
                                    ->label('Phí vận chuyển')
                                    ->money('VND')
                                    ->weight(FontWeight::Bold)
                                    ->color('primary'),
                                Components\TextEntry::make('bonus_fee')
                                    ->label('Phí Tip / Bonus')
                                    ->money('VND')
                                    ->weight(FontWeight::Bold)
                                    ->color('success')
                                    ->visible(fn($state) => $state > 0),
                                Components\TextEntry::make('is_freeship')
                                    ->label('Hình thức thanh toán phí')
                                    ->formatStateUsing(fn($state) => $state ? 'Khách đã trả (Freeship)' : 'Thu tiền mặt (COD)')
                                    ->badge()
                                    ->color(fn($state) => $state ? 'success' : 'warning'),
                            ]),

                        // THÔNG TIN SHIPPER
                        Components\Section::make('Hồ sơ Shipper')
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Components\Group::make([
                                    Components\ImageEntry::make('driver.avatar')
                                        ->label(false)
                                        ->circular()
                                        ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->driver->name ?? 'S') . '&color=FFFFFF&background=03A9F4')
                                        ->extraAttributes(['class' => 'flex justify-center mb-2']),
                                    Components\TextEntry::make('driver.name')
                                        ->label(false)
                                        ->weight(FontWeight::Bold)
                                        ->alignment(Alignment::Center)
                                        ->placeholder('Đang tìm shipper...'),
                                    Components\TextEntry::make('driver.phone')
                                        ->label(false)
                                        ->alignment(Alignment::Center)
                                        ->icon('heroicon-m-chat-bubble-left-right')
                                        ->color('info')
                                        ->url(fn($record) => $record->driver ? "https://zalo.me/{$record->driver->phone}" : null)
                                        ->openUrlInNewTab()
                                        ->visible(fn($record) => isset($record->driver)),
                                ]),
                            ]),

                        // THÔNG TIN HỆ THỐNG
                        Components\Section::make('Định danh hệ thống')
                            ->compact()
                            ->schema([
                                Components\TextEntry::make('id')->label('Mã đơn hàng')->copyable()->prefix('#'),
                                Components\TextEntry::make('service_type')->label('Dịch vụ')->badge()->color('gray'),
                                Components\TextEntry::make('is_ai_created')->label('Nguồn đơn')->formatStateUsing(fn($state) => $state ? 'Trí tuệ nhân tạo (AI)' : 'Điều hành viên (CMS)'),
                            ]),
                    ])->columnSpan(4),
                ]),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'dispatcher', 'manager']);
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'dispatcher']);
    }

    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'dispatcher']);
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'dispatcher']);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // 🔹 Nếu là admin => lọc theo khu vực đang chọn (nếu có), không thì hiện tất cả
        if ($user->hasRole('admin')) {
            if (session()->has('current_city_id')) {
                $query->where('city_id', session('current_city_id'));
            }
        }
        // 🔹 Còn lại (manager, dispatcher...) => chỉ xem vùng cố định của họ
        elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $query->where('city_id', $user->city_id);
        }

        return $query;
    }
}
