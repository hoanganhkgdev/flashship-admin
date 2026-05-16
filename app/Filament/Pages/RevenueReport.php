<?php

namespace App\Filament\Pages;

use App\Models\City;
use App\Services\RevenueReportService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class RevenueReport extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Báo cáo doanh thu';
    protected static ?string $navigationGroup = 'BÁO CÁO';
    protected static ?int    $navigationSort  = 3;
    protected static string  $view            = 'filament.pages.revenue-report';

    public string $mode  = 'month'; // 'month' | 'week'
    public string $year;
    public string $from;
    public string $until;
    public ?int   $cityId = null;

    public function mount(): void
    {
        $tz          = 'Asia/Ho_Chi_Minh';
        $this->year  = now($tz)->format('Y');
        $this->from  = now($tz)->subWeeks(11)->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->until = now($tz)->endOfWeek(Carbon::SUNDAY)->toDateString();

        $user = auth()->user();
        if ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $this->cityId = $user->city_id;
        } else {
            $this->cityId = session('current_city_id') ?: null;
        }
    }

    public function getData(): Collection
    {
        return $this->mode === 'month'
            ? RevenueReportService::monthData((int) $this->year, $this->cityId)
            : RevenueReportService::weekData($this->from, $this->until, $this->cityId);
    }

    public function getStats(): array
    {
        $data  = $this->getData();
        $ship  = (float) $data->sum('total_ship_fee');
        $bonus = (float) $data->sum('total_bonus_fee');

        return [
            'total_orders'     => (int) $data->sum('total_orders'),
            'completed_orders' => (int) $data->sum('completed_orders'),
            'cancelled_orders' => (int) $data->sum('cancelled_orders'),
            'total_ship_fee'   => $ship,
            'total_bonus_fee'  => $bonus,
            'total_revenue'    => $ship + $bonus,
        ];
    }

    public function getCities(): Collection
    {
        return City::orderBy('name')->get(['id', 'name']);
    }

    public function getAvailableYears(): array
    {
        $current = (int) now('Asia/Ho_Chi_Minh')->format('Y');
        $years   = [];
        for ($y = $current; $y >= $current - 3; $y--) {
            $years[$y] = "Năm {$y}";
        }
        return $years;
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
                        'year'    => $this->year,
                        'from'    => $this->from,
                        'until'   => $this->until,
                        'city_id' => $this->cityId,
                    ], fn($v) => $v !== null && $v !== '');

                    $url = url('/reports/revenue/export') . '?' . http_build_query($params);
                    $this->js("window.location.href = " . json_encode($url));
                }),
        ];
    }

    public function exportExcel(): void
    {
        $params = array_filter([
            'mode'    => $this->mode,
            'year'    => $this->year,
            'from'    => $this->from,
            'until'   => $this->until,
            'city_id' => $this->cityId,
        ], fn($v) => $v !== null && $v !== '');

        $url = url('/reports/revenue/export') . '?' . http_build_query($params);
        $this->js("window.location.href = " . json_encode($url));
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager']);
    }
}
