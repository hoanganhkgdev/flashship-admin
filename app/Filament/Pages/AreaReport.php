<?php

namespace App\Filament\Pages;

use App\Models\User;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AreaReport extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Báo cáo khu vực';
    protected static ?string $navigationGroup = 'BÁO CÁO';
    protected static ?int    $navigationSort  = 3;
    protected static string  $view            = 'filament.pages.area-report';

    public string $from;
    public string $until;
    public string $sortBy  = 'city_name';
    public string $sortDir = 'asc';

    public function mount(): void
    {
        $tz          = 'Asia/Ho_Chi_Minh';
        $this->from  = Carbon::now($tz)->startOfMonth()->toDateString();
        $this->until = Carbon::now($tz)->endOfMonth()->toDateString();
    }

    public function updatedFrom(): void  {}
    public function updatedUntil(): void {}

    public function toggleSort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy  = $column;
            $this->sortDir = 'desc';
        }
    }

    public function getData(): Collection
    {
        // Đơn theo khu vực trong kỳ — 1 query với JOIN
        $orders = DB::table('orders')
            ->join('cities', 'cities.id', '=', 'orders.city_id')
            ->whereNotNull('orders.city_id')
            ->whereDate('orders.created_at', '>=', $this->from)
            ->whereDate('orders.created_at', '<=', $this->until)
            ->selectRaw('
                orders.city_id,
                cities.name                                                     as city_name,
                COUNT(*)                                                        as total_orders,
                SUM(CASE WHEN orders.status = "completed"  THEN 1 ELSE 0 END)  as completed_orders,
                SUM(CASE WHEN orders.status = "cancelled"  THEN 1 ELSE 0 END)  as cancelled_orders,
                SUM(CASE WHEN orders.status = "pending"    THEN 1 ELSE 0 END)  as pending_orders,
                SUM(COALESCE(orders.shipping_fee, 0))                           as total_ship_fee,
                SUM(COALESCE(orders.bonus_fee, 0))                              as total_bonus_fee
            ')
            ->groupBy('orders.city_id', 'cities.name')
            ->get()
            ->keyBy('city_id');

        // Số tài xế theo khu vực — query riêng, không phụ thuộc date
        $driverCounts = User::role('driver')
            ->whereNotNull('city_id')
            ->selectRaw('city_id, COUNT(*) as count')
            ->groupBy('city_id')
            ->pluck('count', 'city_id');

        // Merge driver count vào từng khu vực
        $rows = $orders->map(function ($row) use ($driverCounts) {
            $row->driver_count = (int) ($driverCounts[$row->city_id] ?? 0);
            $row->rate = $row->total_orders > 0
                ? round($row->completed_orders / $row->total_orders * 100)
                : 0;
            return $row;
        });

        // Sort in PHP (collection nhỏ, không cần SQL)
        $allowed = ['city_name', 'total_orders', 'completed_orders', 'cancelled_orders', 'total_ship_fee', 'driver_count', 'rate'];
        $col     = in_array($this->sortBy, $allowed) ? $this->sortBy : 'city_name';

        return $this->sortDir === 'asc'
            ? $rows->sortBy($col)->values()
            : $rows->sortByDesc($col)->values();
    }

    public function getStats(): array
    {
        $agg = DB::table('orders')
            ->whereNotNull('city_id')
            ->whereDate('created_at', '>=', $this->from)
            ->whereDate('created_at', '<=', $this->until)
            ->selectRaw('
                COUNT(DISTINCT city_id)                                     as active_cities,
                COUNT(*)                                                    as total_orders,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END)      as completed_orders,
                SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END)      as cancelled_orders,
                SUM(COALESCE(shipping_fee, 0))                              as total_ship_fee,
                SUM(COALESCE(bonus_fee, 0))                                 as total_bonus_fee
            ')
            ->first();

        $ship  = (float) ($agg->total_ship_fee ?? 0);
        $bonus = (float) ($agg->total_bonus_fee ?? 0);
        $total = (int)   ($agg->total_orders   ?? 0);
        $done  = (int)   ($agg->completed_orders ?? 0);

        return [
            'active_cities'    => (int)   ($agg->active_cities    ?? 0),
            'total_orders'     => $total,
            'completed_orders' => $done,
            'cancelled_orders' => (int)   ($agg->cancelled_orders ?? 0),
            'total_ship'       => $ship,
            'total_bonus'      => $bonus,
            'total_income'     => $ship + $bonus,
            'rate'             => $total > 0 ? round($done / $total * 100) : 0,
        ];
    }

    public function getPeriodLabel(): string
    {
        $from  = Carbon::parse($this->from)->format('d/m/Y');
        $until = Carbon::parse($this->until)->format('d/m/Y');
        return $from === $until ? $from : "{$from} – {$until}";
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }
}
