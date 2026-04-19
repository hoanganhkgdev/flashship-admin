<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderReportResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\SelectFilter;
use App\Models\City;

class OrderReportResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationGroup = 'BÁO CÁO';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Báo cáo đơn hàng';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = Order::query()
                    ->selectRaw('
                        DATE(created_at) as report_date,
                        COUNT(*) as total_orders,
                        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_orders,
                        SUM(CASE WHEN status = "assigned" THEN 1 ELSE 0 END) as assigned_orders,
                        SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_orders,
                        SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders,
                        SUM(COALESCE(shipping_fee, 0)) as total_ship_fee,
                        SUM(COALESCE(bonus_fee, 0)) as total_bonus_fee
                    ')
                    ->groupBy('report_date')
                    ->havingRaw('COUNT(*) > 0')
                    ->orderByDesc('report_date');

                // 🟢 Thêm lọc theo vùng (city)
                $user = auth()->user();

                if ($user->hasRole('admin')) {
                    if (session()->has('current_city_id')) {
                        $query->where('city_id', session('current_city_id'));
                    }
                } elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
                    $query->where('city_id', $user->city_id);
                }

                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('index')->label('STT')->rowIndex()->alignCenter(),
                Tables\Columns\TextColumn::make('report_date')
                    ->label('Ngày')
                    ->alignCenter()
                    ->weight('bold')
                    ->icon('heroicon-m-calendar')
                    ->getStateUsing(fn($record) => date('d/m/Y', strtotime($record->report_date))),
                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Tổng đơn')
                    ->alignCenter()
                    ->weight('bold')
,
                Tables\Columns\TextColumn::make('pending_orders')
                    ->label('Đơn mới')
                    ->icon('heroicon-m-sparkles')
                    ->color('info')
                    ->alignCenter()
,
                Tables\Columns\TextColumn::make('assigned_orders')
                    ->label('Đang giao')
                    ->icon('heroicon-m-truck')
                    ->color('warning')
                    ->alignCenter()
,
                Tables\Columns\TextColumn::make('completed_orders')
                    ->label('Hoàn thành')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->alignCenter()
,
                Tables\Columns\TextColumn::make('cancelled_orders')
                    ->label('Đã hủy')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->alignCenter()
,
                Tables\Columns\TextColumn::make('total_bonus_fee')
                    ->label('Doanh thu Bonus')
                    ->alignRight()
                    ->money('VND')
                    ->color('secondary')
,

                Tables\Columns\TextColumn::make('total_ship_fee')
                    ->label('Doanh thu Ship')
                    ->alignRight()
                    ->money('VND')
                    ->weight('bold')
                    ->color('success')
,
            ])
            ->defaultSort('report_date', 'desc')
            ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrderReports::route('/'),
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
