<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AreaReportResource\Pages;
use App\Filament\Resources\AreaReportResource\RelationManagers;
use App\Models\AreaReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class AreaReportResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationGroup = 'BÁO CÁO';
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Báo cáo khu vực';
    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return Order::query()
                    ->whereNotNull('city_id')
                    ->selectRaw('
                        city_id as id,
                        city_id,
                        COUNT(*) as total_orders,
                        SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_orders,
                        SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders,
                        SUM(shipping_fee) as total_fee,
                        MAX(created_at) as last_order_date,
                        (
                            SELECT COUNT(*)
                            FROM users
                            JOIN model_has_roles ON users.id = model_has_roles.model_id
                            JOIN roles ON roles.id = model_has_roles.role_id
                            WHERE users.city_id = orders.city_id
                              AND roles.name = "driver"
                        ) as driver_count
                    ')
                    ->groupBy('city_id');
            })
            ->defaultSort('city_id', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('index')->label('STT')->rowIndex()->alignCenter(),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Khu vực Hub')
                    ->weight('bold')
                    ->color('primary')
                    ->icon('heroicon-m-map-pin'),
                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Tổng đơn')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('completed_orders')
                    ->label('Hoàn thành')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('cancelled_orders')
                    ->label('Đã hủy')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('driver_count')
                    ->label('Số lượng Shipper')
                    ->icon('heroicon-m-user-group')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_fee')
                    ->label('Doanh thu')
                    ->money('VND')
                    ->weight('bold')
                    ->color('success')
                    ->alignRight(),
            ])
            ->actions([])
            ->bulkActions([Tables\Actions\ExportBulkAction::make()])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Từ ngày'),
                        Forms\Components\DatePicker::make('until')->label('Đến ngày'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
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
            'index' => Pages\ListAreaReports::route('/'),
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
