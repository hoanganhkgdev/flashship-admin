<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverDebtResource\Pages;
use App\Models\DriverDebt;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class DriverDebtResource extends Resource
{
    protected static ?string $model = DriverDebt::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Thu phí tài xế';
    protected static ?string $modelLabel = 'phiếu thu phí';
    protected static ?string $pluralModelLabel = 'Lịch sử thu phí';
    protected static ?int $navigationSort = 3;
    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationGroup(): ?string
    {
        return 'TÀI CHÍNH';
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('driver_id')
                ->relationship('driver', 'name')
                ->label('Tài xế')
                ->searchable()
                ->required(),

            Forms\Components\Select::make('debt_type')
                ->label('Loại công nợ')
                ->options([
                    'commission' => 'Chiết khấu',
                    'weekly' => 'Theo tuần',
                ])
                ->default('commission')
                ->required()
                ->live()
                ->afterStateUpdated(function (Forms\Set $set) {
                    $set('date', null);
                    $set('week_start', null);
                    $set('week_end', null);
                }),

            Forms\Components\DatePicker::make('date')
                ->label('Ngày')
                ->displayFormat('d/m/Y')
                ->required(fn(Forms\Get $get) => $get('debt_type') === 'commission')
                ->visible(fn(Forms\Get $get) => $get('debt_type') === 'commission'),

            Forms\Components\DatePicker::make('week_start')
                ->label('Từ ngày')
                ->displayFormat('d/m/Y')
                ->required(fn(Forms\Get $get) => $get('debt_type') === 'weekly')
                ->visible(fn(Forms\Get $get) => $get('debt_type') === 'weekly'),

            Forms\Components\DatePicker::make('week_end')
                ->label('Đến ngày')
                ->displayFormat('d/m/Y')
                ->required(fn(Forms\Get $get) => $get('debt_type') === 'weekly')
                ->visible(fn(Forms\Get $get) => $get('debt_type') === 'weekly')
                ->after('week_start')
                ->rules([
                    fn(Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                        if ($get('debt_type') === 'weekly' && $get('week_start') && $value) {
                            $start = \Carbon\Carbon::parse($get('week_start'));
                            $end   = \Carbon\Carbon::parse($value);
                            if ($end->lt($start)) {
                                $fail('Đến ngày phải lớn hơn hoặc bằng Từ ngày.');
                            }
                        }
                    },
                ]),

            Forms\Components\TextInput::make('amount_due')
                ->label('Số tiền cần thu')
                ->numeric()
                ->suffix('₫')
                ->required(),

            Forms\Components\TextInput::make('amount_paid')
                ->label('Đã thanh toán')
                ->numeric()
                ->suffix('₫')
                ->default(0),

            Forms\Components\Select::make('status')
                ->label('Trạng thái')
                ->options([
                    'pending' => 'Chưa thanh toán',
                    'paid' => 'Đã thanh toán',
                    'overdue' => 'Quá hạn',
                ])
                ->default('pending')
                ->required(),

            Forms\Components\Textarea::make('note')
                ->label('Ghi chú')
                ->maxLength(255)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        // Default table - sẽ được override bởi các pages
        // Trả về table cơ bản, các pages sẽ override để gọi method riêng
        return $table;
    }

    public static function tableCommission(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('driver.profile_photo_path')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->driver?->name ?? '?') . '&color=ffffff&background=6d28d9')
                    ->size(40),

                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Tài xế')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn($record) => $record->driver?->phone ?? ''),

                Tables\Columns\TextColumn::make('date')
                    ->date('d/m/Y')
                    ->label('Ngày ghi nhận')
                    ->icon('heroicon-m-calendar')
                    ->color('gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('daily_earning')
                    ->label('Thu nhập ngày')
                    ->getStateUsing(fn($record) => $record->daily_earning)
                    ->money('VND')
                    ->alignRight()
                    ->color('primary')
                    ->weight('bold')
                    ->tooltip('Tổng tiền ship các đơn hoàn thành trong ngày'),

                Tables\Columns\TextColumn::make('daily_orders_count')
                    ->label('Đơn')
                    ->getStateUsing(fn($record) => $record->daily_orders_count)
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('commission_rate')
                    ->label('% CK')
                    ->getStateUsing(fn($record) => $record->commission_rate . '%')
                    ->alignCenter()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('calculated_commission')
                    ->label('CK thực')
                    ->getStateUsing(fn($record) => $record->calculated_commission)
                    ->money('VND')
                    ->alignRight()
                    ->color('orange')
                    ->tooltip('Thu nhập × % chiết khấu'),

                Tables\Columns\TextColumn::make('app_fee')
                    ->label('Phí App')
                    ->getStateUsing(fn($record) => $record->app_fee)
                    ->money('VND')
                    ->alignRight()
                    ->color('gray')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('amount_due')
                    ->label('Tổng cần thu')
                    ->money('VND')
                    ->alignRight()
                    ->weight('bold')
                    ->color('danger')
                    ->tooltip('CK thực + Phí App'),

                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Đã thu')
                    ->money('VND')
                    ->alignRight()
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->alignCenter()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending' => 'Chưa thu',
                        'overdue' => 'Quá hạn',
                        'paid'    => 'Hoàn tất',
                        default   => $state,
                    })
                    ->color(fn($state) => match ($state) {
                        'pending' => 'danger',
                        'overdue' => 'warning',
                        'paid'    => 'success',
                        default   => 'gray',
                    }),

            ])->actions([
                Tables\Actions\Action::make('collect_commission')
                    ->label('Thu tiền')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Thu tiền công nợ')
                    ->visible(fn($record) => in_array($record->status, ['pending', 'overdue']))
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Số tiền thu (₫)')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default(fn($record) => $record->amount_due - $record->amount_paid),
                        Forms\Components\Textarea::make('note')
                            ->label('Ghi chú')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        $remaining = $record->amount_due - $record->amount_paid;
                        $collected = min((float) $data['amount'], $remaining);
                        $record->amount_paid += $collected;
                        if ($record->amount_paid >= $record->amount_due) {
                            $record->status = 'paid';
                        }
                        if (!empty($data['note'])) {
                            $record->note = $data['note'];
                        }
                        $record->save();
                        Notification::make()
                            ->title('Đã thu ' . number_format($collected, 0, ',', '.') . '₫')
                            ->success()->send();
                    }),

                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Xuất Excel')
                    ->exports([
                        ExcelExport::make()
                            ->fromModel()
                            ->modifyQueryUsing(function ($query) {
                                $baseQuery = static::getEloquentQuery();
                                return $baseQuery->where('debt_type', 'commission');
                            })
                            ->withFilename('Danh_sach_cong_no_chiet_khau_' . now()->format('Y_m_d'))
                            ->withColumns([
                                Column::make('driver.name')->heading('Tài xế'),
                                Column::make('date')
                                    ->heading('Ngày')
                                    ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : ''),
                                Column::make('amount_due')
                                    ->heading('Chiết khấu')
                                    ->formatStateUsing(fn($state) => is_numeric($state) ? number_format($state, 0, ',', '.') : ''),
                                Column::make('amount_paid')
                                    ->heading('Đã thanh toán')
                                    ->formatStateUsing(fn($state) => is_numeric($state) ? number_format($state, 0, ',', '.') : ''),
                                Column::make('status')
                                    ->heading('Trạng thái')
                                    ->formatStateUsing(fn($state) => match ($state) {
                                        'pending' => 'Chưa thanh toán',
                                        'overdue' => 'Quá hạn',
                                        'paid' => 'Đã thanh toán',
                                        default => ucfirst($state ?? ''),
                                    }),
                            ])
                            ->except([
                                'id',
                                'week_start',
                                'week_end',
                                'ref_id',
                                'created_at',
                                'updated_at'
                            ])
                    ])
            ])
            ->defaultSort('date', 'desc');
    }

    public static function tableWeekly(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('driver.profile_photo_path')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->driver?->name ?? '?') . '&color=ffffff&background=6d28d9')
                    ->size(40),

                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Tài xế')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn($record) => $record->driver?->phone ?? ''),

                Tables\Columns\TextColumn::make('week_start')
                    ->label('Chu kỳ tuần')
                    ->getStateUsing(
                        fn($record) =>
                        Carbon::parse($record->week_start)->format('d/m') . ' - ' .
                        Carbon::parse($record->week_end)->format('d/m/Y')
                    )
                    ->icon('heroicon-m-calendar-days')
                    ->color('gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('amount_due')
                    ->label('Phí cố định')
                    ->money('VND')
                    ->alignRight()
                    ->weight('bold')
                    ->color('danger'),

                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Đã đóng')
                    ->money('VND')
                    ->alignRight()
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->alignCenter()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending' => 'Chưa thu',
                        'overdue' => 'Quá hạn',
                        'paid'    => 'Hoàn tất',
                        default   => $state,
                    })
                    ->color(fn($state) => match ($state) {
                        'pending' => 'danger',
                        'overdue' => 'warning',
                        'paid'    => 'success',
                        default   => 'gray',
                    }),

            ])->actions([
                Tables\Actions\Action::make('collect_weekly')
                    ->label('Thu tiền')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Thu tiền công nợ')
                    ->visible(fn($record) => in_array($record->status, ['pending', 'overdue']))
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Số tiền thu (₫)')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default(fn($record) => $record->amount_due - $record->amount_paid),
                        Forms\Components\Textarea::make('note')
                            ->label('Ghi chú')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        $remaining = $record->amount_due - $record->amount_paid;
                        $collected = min((float) $data['amount'], $remaining);
                        $record->amount_paid += $collected;
                        if ($record->amount_paid >= $record->amount_due) {
                            $record->status = 'paid';
                        }
                        if (!empty($data['note'])) {
                            $record->note = $data['note'];
                        }
                        $record->save();
                        Notification::make()
                            ->title('Đã thu ' . number_format($collected, 0, ',', '.') . '₫')
                            ->success()->send();
                    }),

                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->filters([
                Filter::make('week_start')
                    ->label('Lọc theo tuần')
                    ->form([
                        Forms\Components\DatePicker::make('week_start')
                            ->label('Chọn tuần')
                            ->displayFormat('d/m/Y')
                            ->placeholder('Chọn một ngày bất kỳ trong tuần')
                            ->helperText('Chọn một ngày bất kỳ trong tuần, hệ thống sẽ tự động lọc theo tuần đó (Thứ 2 - Chủ nhật). Để trống để xem tất cả.'),
                    ])
                    ->query(function ($query, array $data) {
                        // Chỉ áp dụng filter nếu có giá trị
                        if (isset($data['week_start']) && !empty($data['week_start'])) {
                            $date = Carbon::parse($data['week_start']);
                            $weekStart = $date->copy()->startOfWeek();
                            $weekEnd = $date->copy()->endOfWeek();

                            // Tìm các record có week_start nằm trong tuần được chọn
                            return $query->whereBetween('week_start', [
                                $weekStart->toDateString(),
                                $weekEnd->toDateString()
                            ]);
                        }

                        // Nếu không có filter, trả về query gốc (hiển thị tất cả)
                        return $query;
                    })
                    ->indicateUsing(function (array $data): ?string {
                        // Chỉ hiển thị indicator nếu có filter
                        if (!isset($data['week_start']) || empty($data['week_start'])) {
                            return null;
                        }

                        $date = Carbon::parse($data['week_start']);
                        $weekStart = $date->copy()->startOfWeek();
                        $weekEnd = $date->copy()->endOfWeek();
                        return 'Tuần: ' . $weekStart->format('d/m/Y') . ' - ' . $weekEnd->format('d/m/Y');
                    }),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Xuất Excel')
                    ->exports([
                        ExcelExport::make()
                            ->fromModel()
                            ->modifyQueryUsing(function ($query) {
                                // Sử dụng query từ getEloquentQuery để có filter city, sau đó thêm filter debt_type
                                $baseQuery = static::getEloquentQuery();
                                return $baseQuery->where('debt_type', 'weekly');
                            })
                            ->withFilename('Danh_sach_cong_no_tuan_' . now()->format('Y_m_d'))
                            ->withColumns([
                                Column::make('driver.name')->heading('Tài xế'),
                                Column::make('week_start')
                                    ->heading('Từ ngày')
                                    ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : ''),
                                Column::make('week_end')
                                    ->heading('Đến ngày')
                                    ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : ''),
                                Column::make('amount_due')
                                    ->heading('Công nợ')
                                    ->formatStateUsing(fn($state) => is_numeric($state) ? number_format($state, 0, ',', '.') : ''),
                                Column::make('amount_paid')
                                    ->heading('Đã thanh toán')
                                    ->formatStateUsing(fn($state) => is_numeric($state) ? number_format($state, 0, ',', '.') : ''),
                                Column::make('status')
                                    ->heading('Trạng thái')
                                    ->formatStateUsing(fn($state) => match ($state) {
                                        'pending' => 'Chưa thanh toán',
                                        'overdue' => 'Quá hạn',
                                        'paid' => 'Đã thanh toán',
                                        default => ucfirst($state ?? ''),
                                    }),
                            ])
                            ->except([
                                'id',
                                'date',
                                'ref_id',
                                'created_at',
                                'updated_at'
                            ])
                    ])
            ])
            ->defaultSort('week_start', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommissionDebts::route('/'),
            'commission' => Pages\ListCommissionDebts::route('/commission'),
            'weekly' => Pages\ListWeeklyDebts::route('/weekly'),
            'create' => Pages\CreateDriverDebt::route('/create'),
            'edit' => Pages\EditDriverDebt::route('/{record}/edit'),
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

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->hasRole('admin')) {
            if ($cityId = session('current_city_id')) {
                $query->whereHas('driver', fn($q) => $q->where('city_id', $cityId));
            }
        } elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $query->whereHas('driver', fn($q) => $q->where('city_id', $user->city_id));
        }

        // Không join daily_orders ở đây vì chỉ cần cho commission
        // Join sẽ được thực hiện trong getTableQuery() của ListCommissionDebts

        return $query;
    }
}
