<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IncomeReportResource\Pages;
use App\Models\City;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IncomeReportResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationGroup = 'BÁO CÁO';
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Báo cáo thu nhập';
    protected static ?int $navigationSort = 1;

    // ── Tạo query chính với khoảng thời gian chỉ định ──────────────────────
    private static function buildQuery(string $from, string $until): Builder
    {
        $start = Carbon::parse($from, 'Asia/Ho_Chi_Minh')->startOfDay()->toDateTimeString();
        $end = Carbon::parse($until, 'Asia/Ho_Chi_Minh')->endOfDay()->toDateTimeString();

        // Đơn trong kỳ lọc
        $periodSub = Order::query()
            ->selectRaw('delivery_man_id, COUNT(*) as completed_orders, SUM(COALESCE(shipping_fee, 0)) as day_revenue, SUM(COALESCE(bonus_fee, 0)) as day_bonus')
            ->where('status', 'completed')
            ->whereNotNull('delivery_man_id')
            ->whereBetween('completed_at', [$start, $end])
            ->groupBy('delivery_man_id');

        // ✅ Tổng đơn và tổng thu nhập toàn thời gian (không phụ thuộc filter)
        $allTimeSub = Order::query()
            ->selectRaw('delivery_man_id, COUNT(*) as all_time_orders, SUM(COALESCE(shipping_fee, 0)) as all_time_revenue, SUM(COALESCE(bonus_fee, 0)) as all_time_bonus')
            ->where('status', 'completed')
            ->whereNotNull('delivery_man_id')
            ->groupBy('delivery_man_id');

        // ✅ Tổng đơn trong tháng hiện tại (giờ VN, không phụ thuộc filter)
        $monthStart = Carbon::now('Asia/Ho_Chi_Minh')->startOfMonth()->toDateTimeString();
        $monthEnd = Carbon::now('Asia/Ho_Chi_Minh')->endOfMonth()->toDateTimeString();
        $monthlySub = Order::query()
            ->selectRaw('delivery_man_id, COUNT(*) as month_orders, SUM(COALESCE(shipping_fee, 0)) as month_revenue, SUM(COALESCE(bonus_fee, 0)) as month_bonus')
            ->where('status', 'completed')
            ->whereNotNull('delivery_man_id')
            ->whereBetween('completed_at', [$monthStart, $monthEnd])
            ->groupBy('delivery_man_id');


        $query = User::query()
            ->role('driver')
            ->leftJoinSub($periodSub, 'ds', 'ds.delivery_man_id', '=', 'users.id')
            ->leftJoinSub($allTimeSub, 'at', 'at.delivery_man_id', '=', 'users.id')
            ->leftJoinSub($monthlySub, 'mt', 'mt.delivery_man_id', '=', 'users.id')
            ->selectRaw('
                users.id,
                users.name                      as driver_name,
                users.phone                     as driver_phone,
                users.city_id,
                COALESCE(ds.completed_orders, 0) as day_orders,
                COALESCE(ds.day_revenue, 0)      as day_revenue,
                COALESCE(ds.day_bonus, 0)        as day_bonus,
                COALESCE(at.all_time_orders, 0)   as all_time_orders,
                COALESCE(at.all_time_revenue, 0)  as all_time_revenue,
                COALESCE(at.all_time_bonus, 0)    as all_time_bonus,
                COALESCE(mt.month_orders, 0)      as month_orders,
                COALESCE(mt.month_revenue, 0)     as month_revenue,
                COALESCE(mt.month_bonus, 0)       as month_bonus
            ')
            ->where('users.status', 1);

        // Lọc theo khu vực theo role
        $authUser = auth()->user();
        if ($authUser->hasRole('admin')) {
            if (session()->has('current_city_id')) {
                $query->where('users.city_id', session('current_city_id'));
            }
        } elseif ($authUser->hasAnyRole(['manager', 'dispatcher'])) {
            $query->where('users.city_id', $authUser->city_id);
        }

        return $query->orderByDesc('all_time_revenue');
    }

    public static function table(Table $table): Table
    {
        $defaultFrom = null;
        $defaultUntil = null;

        return $table
            ->query(function (\Livewire\Component $livewire) {
                // ✅ Đọc filter state từ Livewire component
                $date = data_get($livewire, 'tableFilters.date.date');

                $from = $date ?? Carbon::now('Asia/Ho_Chi_Minh')->toDateString();
                $until = $date ?? Carbon::now('Asia/Ho_Chi_Minh')->toDateString();

                return self::buildQuery($from, $until);
            })
            ->defaultSort('all_time_revenue', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('index')
                    ->label('#')
                    ->rowIndex()
                    ->alignLeft()
                    ->width(50),

                Tables\Columns\TextColumn::make('driver_name')
                    ->label('Tài xế')
                    ->weight('bold')
                    ->searchable(query: fn(Builder $query, string $search) => $query->where('users.name', 'like', "%{$search}%")),

                Tables\Columns\TextColumn::make('all_time_orders')
                    ->label('Đơn Tổng')
                    ->icon('heroicon-m-clipboard-document-list')
                    ->color('info')
                    ->alignLeft()
,

                Tables\Columns\TextColumn::make('all_time_bonus')
                    ->label('Bonus Tổng')
                    ->formatStateUsing(fn($state) => number_format((float) $state) . '₫')
                    ->color('secondary')
                    ->alignLeft()

,

                Tables\Columns\TextColumn::make('all_time_revenue')
                    ->label('Ship Tổng')
                    ->formatStateUsing(fn($state) => number_format((float) $state) . '₫')
                    ->weight('bold')
                    ->color('info')
                    ->alignLeft()

,

                Tables\Columns\TextColumn::make('month_orders')
                    ->label('Đơn Tháng')
                    ->icon('heroicon-m-calendar-days')
                    ->color('warning')
                    ->alignLeft()
,

                Tables\Columns\TextColumn::make('month_bonus')
                    ->label('Bonus tháng')
                    ->formatStateUsing(fn($state) => number_format((float) $state) . '₫')
                    ->color('secondary')
                    ->alignLeft()

,

                Tables\Columns\TextColumn::make('month_revenue')
                    ->label('Ship tháng')
                    ->formatStateUsing(fn($state) => number_format((float) $state) . '₫')
                    ->weight('bold')
                    ->color('warning')
                    ->alignLeft()

,

                Tables\Columns\TextColumn::make('day_orders')
                    ->label('Đơn Ngày')
                    ->icon('heroicon-m-sun')
                    ->color('success')
                    ->alignLeft()
,

                Tables\Columns\TextColumn::make('day_bonus')
                    ->label('Bonus Ngày')
                    ->formatStateUsing(fn($state) => number_format((float) $state) . '₫')
                    ->color('secondary')
                    ->alignLeft()

,

                Tables\Columns\TextColumn::make('day_revenue')
                    ->label('Ship Ngày')
                    ->formatStateUsing(fn($state) => $state > 0 ? number_format((float) $state) . '₫' : '—')
                    ->color(fn($state) => $state > 0 ? 'success' : 'gray')
                    ->alignLeft()
,
            ])
            ->filters([
                Filter::make('date')
                    ->label('Chọn ngày')
                    ->form([
                        Forms\Components\DatePicker::make('date')
                            ->label('Ngày')
                            ->placeholder('Chọn ngày cụ thể...')
                            ->displayFormat('d/m/Y')
                            ->default(now()->toDateString()),
                    ])
                    ->query(function (Builder $query): Builder {
                        // Filter chỉ làm trigger re-render;
                        // logic thực được xử lý trong ->query() của table
                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        if ($data['date'] ?? null) {
                            return ['Ngày: ' . Carbon::parse($data['date'])->format('d/m/Y')];
                        }
                        return [];
                    }),

                Tables\Filters\SelectFilter::make('city')
                    ->label('Khu vực')
                    ->options(City::orderBy('name')->pluck('name', 'id'))
                    ->query(fn(Builder $q, array $data) => $data['value']
                        ? $q->where('users.city_id', $data['value'])
                        : $q)
                    ->visible(fn() => auth()->user()?->hasRole('admin')),
            ])
            ->actions([])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListIncomeReports::route('/')];
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager']);
    }

    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }
    public static function canDelete($record): bool
    {
        return false;
    }
}
