<?php

namespace App\Filament\Pages;

use App\Models\City;
use App\Models\User;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

class IncomeReport extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon  = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Báo cáo thu nhập';
    protected static ?string $navigationGroup = 'BÁO CÁO';
    protected static ?int    $navigationSort  = 1;
    protected static string  $view            = 'filament.pages.income-report';

    public string  $mode    = 'month';
    public string  $month;
    public string  $date;
    public string  $search  = '';
    public string  $sortBy  = 'total';
    public string  $sortDir = 'desc';
    public ?int    $cityId  = null;

    public function mount(): void
    {
        $tz          = 'Asia/Ho_Chi_Minh';
        $this->month = now($tz)->format('Y-m');
        $this->date  = now($tz)->toDateString();

        $user = auth()->user();
        if ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $this->cityId = $user->city_id;
        } else {
            $this->cityId = session('current_city_id') ?: null;
        }
    }

    public function updatedMode(): void    { $this->resetPage(); }
    public function updatedMonth(): void   { $this->resetPage(); }
    public function updatedDate(): void    { $this->resetPage(); }
    public function updatedSearch(): void  { $this->resetPage(); }
    public function updatedCityId(): void  { $this->resetPage(); }

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

    private function dateRange(): array
    {
        $tz = 'Asia/Ho_Chi_Minh';
        if ($this->mode === 'month') {
            $start = Carbon::parse($this->month . '-01', $tz)->startOfMonth();
            return [$start, $start->copy()->endOfMonth()];
        }
        $start = Carbon::parse($this->date, $tz)->startOfDay();
        return [$start, $start->copy()->endOfDay()];
    }

    public function getData(): LengthAwarePaginator
    {
        [$start, $end] = $this->dateRange();

        $sub = DB::table('orders')
            ->selectRaw('
                delivery_man_id,
                COUNT(*)                            as orders,
                SUM(COALESCE(shipping_fee, 0))      as ship,
                SUM(COALESCE(bonus_fee, 0))         as bonus
            ')
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->groupBy('delivery_man_id');

        $query = User::role('driver')
            ->leftJoinSub($sub, 'o', 'o.delivery_man_id', '=', 'users.id')
            ->selectRaw('
                users.id,
                users.name,
                users.phone,
                users.city_id,
                COALESCE(o.orders, 0)                              as orders,
                COALESCE(o.ship, 0)                                as ship,
                COALESCE(o.bonus, 0)                               as bonus,
                COALESCE(o.ship, 0) + COALESCE(o.bonus, 0)        as total
            ')
            ->where('users.status', 1);

        if ($this->cityId) {
            $query->where('users.city_id', $this->cityId);
        }

        if ($this->search !== '') {
            $search = "%{$this->search}%";
            $query->where(fn($q) => $q
                ->where('users.name', 'like', $search)
                ->orWhere('users.phone', 'like', $search)
            );
        }

        $col = in_array($this->sortBy, ['orders', 'ship', 'bonus', 'total', 'name'])
            ? $this->sortBy : 'total';
        $dir = $this->sortDir === 'asc' ? 'asc' : 'desc';

        return $query->orderByRaw("{$col} {$dir}, users.id asc")->paginate(25);
    }

    public function getStats(): array
    {
        [$start, $end] = $this->dateRange();

        $query = DB::table('orders')
            ->whereNotNull('delivery_man_id')
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end]);

        if ($this->cityId) {
            $query->where('city_id', $this->cityId);
        }

        if ($this->search !== '') {
            $search  = "%{$this->search}%";
            $driverIds = User::role('driver')
                ->where(fn($q) => $q->where('name', 'like', $search)->orWhere('phone', 'like', $search))
                ->pluck('id');
            $query->whereIn('delivery_man_id', $driverIds);
        }

        $row = (clone $query)->selectRaw('
            COUNT(*)                           as total_orders,
            SUM(COALESCE(shipping_fee, 0))     as total_ship,
            SUM(COALESCE(bonus_fee, 0))        as total_bonus,
            COUNT(DISTINCT delivery_man_id)    as active_drivers
        ')->first();

        $ship  = (float) ($row->total_ship  ?? 0);
        $bonus = (float) ($row->total_bonus ?? 0);

        return [
            'total_orders'   => (int)   ($row->total_orders   ?? 0),
            'total_ship'     => $ship,
            'total_bonus'    => $bonus,
            'total_income'   => $ship + $bonus,
            'active_drivers' => (int)   ($row->active_drivers ?? 0),
        ];
    }

    public function getCities(): \Illuminate\Support\Collection
    {
        return City::orderBy('name')->get(['id', 'name']);
    }

    public function getPeriodLabel(): string
    {
        $tz = 'Asia/Ho_Chi_Minh';
        if ($this->mode === 'month') {
            return Carbon::parse($this->month . '-01', $tz)->format('m/Y');
        }
        return Carbon::parse($this->date, $tz)->format('d/m/Y');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager']);
    }
}
