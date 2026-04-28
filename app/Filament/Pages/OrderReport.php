<?php

namespace App\Filament\Pages;

use App\Models\City;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

class OrderReport extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Báo cáo đơn hàng';
    protected static ?string $navigationGroup = 'BÁO CÁO';
    protected static ?int    $navigationSort  = 2;
    protected static string  $view            = 'filament.pages.order-report';

    public string $from;
    public string $until;
    public string $sortBy  = 'report_date';
    public string $sortDir = 'desc';
    public ?int   $cityId  = null;

    public function mount(): void
    {
        $tz          = 'Asia/Ho_Chi_Minh';
        $this->from  = Carbon::now($tz)->startOfMonth()->toDateString();
        $this->until = Carbon::now($tz)->endOfMonth()->toDateString();

        $user = auth()->user();
        if ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $this->cityId = $user->city_id;
        } else {
            $this->cityId = session('current_city_id') ?: null;
        }
    }

    public function updatedFrom(): void  { $this->resetPage(); }
    public function updatedUntil(): void { $this->resetPage(); }
    public function updatedCityId(): void { $this->resetPage(); }

    public function toggleSort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy  = $column;
            $this->sortDir = 'desc';
        }
        $this->resetPage();
    }

    private function baseQuery(): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('orders')
            ->selectRaw('
                DATE(created_at)                                        as report_date,
                COUNT(*)                                                as total_orders,
                SUM(CASE WHEN status = "completed"  THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = "cancelled"  THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(CASE WHEN status = "pending"    THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = "assigned"   THEN 1 ELSE 0 END) as assigned_orders,
                SUM(COALESCE(shipping_fee, 0))                         as total_ship_fee,
                SUM(COALESCE(bonus_fee, 0))                            as total_bonus_fee
            ')
            ->whereDate('created_at', '>=', $this->from)
            ->whereDate('created_at', '<=', $this->until)
            ->groupBy('report_date');

        if ($this->cityId) {
            $query->where('city_id', $this->cityId);
        }

        return $query;
    }

    public function getData(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $allowed = ['report_date', 'total_orders', 'completed_orders', 'cancelled_orders', 'total_ship_fee'];
        $col     = in_array($this->sortBy, $allowed) ? $this->sortBy : 'report_date';
        $dir     = $this->sortDir === 'asc' ? 'asc' : 'desc';

        return $this->baseQuery()->orderByRaw("{$col} {$dir}")->paginate(31);
    }

    public function getStats(): array
    {
        $query = DB::table('orders')
            ->whereDate('created_at', '>=', $this->from)
            ->whereDate('created_at', '<=', $this->until);

        if ($this->cityId) {
            $query->where('city_id', $this->cityId);
        }

        $agg = (clone $query)->selectRaw('
            COUNT(*)                                                as total_orders,
            SUM(CASE WHEN status = "completed"  THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = "cancelled"  THEN 1 ELSE 0 END) as cancelled,
            SUM(COALESCE(shipping_fee, 0))                         as ship,
            SUM(COALESCE(bonus_fee, 0))                            as bonus
        ')->first();

        $ship  = (float) ($agg->ship    ?? 0);
        $bonus = (float) ($agg->bonus   ?? 0);

        return [
            'total_orders'     => (int) ($agg->total_orders ?? 0),
            'completed_orders' => (int) ($agg->completed    ?? 0),
            'cancelled_orders' => (int) ($agg->cancelled    ?? 0),
            'total_ship'       => $ship,
            'total_bonus'      => $bonus,
            'total_income'     => $ship + $bonus,
        ];
    }

    public function getCities(): Collection
    {
        return City::orderBy('name')->get(['id', 'name']);
    }

    public function getPeriodLabel(): string
    {
        $from  = Carbon::parse($this->from)->format('d/m/Y');
        $until = Carbon::parse($this->until)->format('d/m/Y');
        return $from === $until ? $from : "{$from} – {$until}";
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager']);
    }
}
