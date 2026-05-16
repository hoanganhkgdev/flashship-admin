<?php

namespace App\Filament\Pages;

use App\Models\City;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

class DriverCashReport extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon  = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationLabel = 'Thu / Chi tài xế';
    protected static ?string $navigationGroup = 'BÁO CÁO';
    protected static ?int    $navigationSort  = 4;
    protected static string  $view            = 'filament.pages.driver-cash-report';

    public string  $mode    = 'month';
    public string  $month;
    public string  $date;
    public string  $from;
    public string  $until;
    public string  $search  = '';
    public string  $sortBy  = 'thu';
    public string  $sortDir = 'desc';
    public ?int    $cityId  = null;

    public function mount(): void
    {
        $tz          = 'Asia/Ho_Chi_Minh';
        $this->month = now($tz)->format('Y-m');
        $this->date  = now($tz)->toDateString();
        $this->from  = now($tz)->startOfMonth()->toDateString();
        $this->until = now($tz)->toDateString();

        $user = auth()->user();
        if ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $this->cityId = $user->city_id;
        } else {
            $this->cityId = session('current_city_id') ?: null;
        }
    }

    public function updatedMode(): void   { $this->resetPage(); }
    public function updatedMonth(): void  { $this->resetPage(); }
    public function updatedDate(): void   { $this->resetPage(); }
    public function updatedFrom(): void   { $this->resetPage(); }
    public function updatedUntil(): void  { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }
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

    private function dateRange(): array
    {
        $tz = 'Asia/Ho_Chi_Minh';
        if ($this->mode === 'month') {
            $start = Carbon::parse($this->month . '-01', $tz)->startOfMonth();
            return [$start, $start->copy()->endOfMonth()];
        }
        if ($this->mode === 'range') {
            $start = Carbon::parse($this->from, $tz)->startOfDay();
            $end   = Carbon::parse($this->until, $tz)->endOfDay();
            if ($end->lt($start)) $end = $start->copy()->endOfDay();
            return [$start, $end];
        }
        $start = Carbon::parse($this->date, $tz)->startOfDay();
        return [$start, $start->copy()->endOfDay()];
    }

    public function getData(): LengthAwarePaginator
    {
        [$start, $end] = $this->dateRange();

        // Thu: tổng tiền tài xế đã đóng (driver_debts đã thanh toán trong kỳ)
        $debtSub = DB::table('driver_debts')
            ->selectRaw('driver_id, SUM(COALESCE(amount_paid, 0)) as thu')
            ->where('amount_paid', '>', 0)
            ->whereBetween('updated_at', [$start, $end])
            ->groupBy('driver_id');

        // Chi: tổng tiền đã duyệt rút (withdraw_requests approved trong kỳ)
        $withdrawSub = DB::table('withdraw_requests')
            ->selectRaw('driver_id, SUM(COALESCE(amount, 0)) as chi')
            ->where('status', 'approved')
            ->whereBetween('updated_at', [$start, $end])
            ->groupBy('driver_id');

        $allowedSorts = ['thu', 'chi', 'net', 'name'];
        $col = in_array($this->sortBy, $allowedSorts) ? $this->sortBy : 'thu';
        $dir = $this->sortDir === 'asc' ? 'asc' : 'desc';

        $query = User::role('driver')
            ->leftJoinSub($debtSub, 'd', 'd.driver_id', '=', 'users.id')
            ->leftJoinSub($withdrawSub, 'w', 'w.driver_id', '=', 'users.id')
            ->selectRaw('
                users.id,
                users.name,
                users.phone,
                users.city_id,
                COALESCE(d.thu, 0)                       as thu,
                COALESCE(w.chi, 0)                       as chi,
                COALESCE(d.thu, 0) - COALESCE(w.chi, 0) as net
            ')
            ->where('users.status', 1)
            ->where(function ($q) {
                $q->whereRaw('COALESCE(d.thu, 0) > 0')
                  ->orWhereRaw('COALESCE(w.chi, 0) > 0');
            });

        if ($this->cityId) {
            $query->where('users.city_id', $this->cityId);
        }

        if ($this->search !== '') {
            $s = "%{$this->search}%";
            $query->where(fn($q) => $q
                ->where('users.name', 'like', $s)
                ->orWhere('users.phone', 'like', $s)
            );
        }

        return $query
            ->orderByRaw("{$col} {$dir}, users.id asc")
            ->paginate(25);
    }

    public function getStats(): array
    {
        [$start, $end] = $this->dateRange();

        $thuQuery = DB::table('driver_debts')
            ->where('amount_paid', '>', 0)
            ->whereBetween('updated_at', [$start, $end]);

        $chiQuery = DB::table('withdraw_requests')
            ->where('status', 'approved')
            ->whereBetween('updated_at', [$start, $end]);

        if ($this->cityId) {
            $driverIds = User::role('driver')->where('city_id', $this->cityId)->pluck('id');
            $thuQuery->whereIn('driver_id', $driverIds);
            $chiQuery->whereIn('driver_id', $driverIds);
        }

        if ($this->search !== '') {
            $s = "%{$this->search}%";
            $ids = User::role('driver')
                ->where(fn($q) => $q->where('name', 'like', $s)->orWhere('phone', 'like', $s))
                ->pluck('id');
            $thuQuery->whereIn('driver_id', $ids);
            $chiQuery->whereIn('driver_id', $ids);
        }

        $thu = (float) $thuQuery->sum('amount_paid');
        $chi = (float) $chiQuery->sum('amount');

        $countThuDrivers = (int) $thuQuery->distinct('driver_id')->count('driver_id');
        $countChiDrivers = (int) $chiQuery->distinct('driver_id')->count('driver_id');

        return [
            'total_thu'         => $thu,
            'total_chi'         => $chi,
            'net'               => $thu - $chi,
            'count_thu_drivers' => $countThuDrivers,
            'count_chi_drivers' => $countChiDrivers,
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
        if ($this->mode === 'range') {
            $f = Carbon::parse($this->from, $tz)->format('d/m/Y');
            $u = Carbon::parse($this->until, $tz)->format('d/m/Y');
            return "{$f} – {$u}";
        }
        return Carbon::parse($this->date, $tz)->format('d/m/Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Xuất Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $params = array_filter([
                        'mode'    => $this->mode,
                        'month'   => $this->month,
                        'date'    => $this->date,
                        'from'    => $this->from,
                        'until'   => $this->until,
                        'city_id' => $this->cityId,
                        'search'  => $this->search,
                    ], fn($v) => $v !== null && $v !== '');

                    $url = url('/reports/driver-cash/export') . '?' . http_build_query($params);
                    $this->js("window.location.href = " . json_encode($url));
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager']);
    }
}
