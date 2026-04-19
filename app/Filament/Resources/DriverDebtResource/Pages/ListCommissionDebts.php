<?php

namespace App\Filament\Resources\DriverDebtResource\Pages;

use App\Filament\Resources\DriverDebtResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Components\Tab;


class ListCommissionDebts extends ListRecords
{
    protected static string $resource = DriverDebtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 📊 Tính công nợ chiết khấu (chỉ hiện ở tab commission)
            Actions\Action::make('generate_commission')
                ->label('Tính Công nợ CK')
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->visible(fn() => $this->activeTab !== 'weekly')
                ->modalHeading('Tính / Tính lại công nợ chiết khấu')
                ->modalDescription('Hệ thống sẽ tính lại tổng tiền ship × % chiết khấu cho từng tài xế trong ngày được chọn.')
                ->form([
                    Forms\Components\DatePicker::make('date')
                        ->label('Ngày cần tính / tính lại')
                        ->displayFormat('d/m/Y')
                        ->default(now()->subDay()->toDateString())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $date = $data['date'];
                    $cityId = session('current_city_id');
                    try {
                        \Illuminate\Support\Facades\Artisan::call('driver:generate-commission-debts', ['--date' => $date, '--city' => $cityId]);
                        \Filament\Notifications\Notification::make()->title('✅ Đã tính lại công nợ ngày ' . \Carbon\Carbon::parse($date)->format('d/m/Y'))->success()->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()->title('❌ Lỗi: ' . $e->getMessage())->danger()->send();
                    }
                }),

            // ⬆️ Import công nợ tuần (chỉ hiện ở tab weekly)
            Actions\Action::make('import_weekly')
                ->label('Import Công nợ tuần')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->visible(fn() => $this->activeTab === 'weekly')
                ->modalHeading('Import danh sách công nợ tuần từ file Excel')
                ->form([
                    Forms\Components\FileUpload::make('excel_file')
                        ->label('Chọn file Excel (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->disk('local')
                        ->directory('temp_imports')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $filePath = \Illuminate\Support\Facades\Storage::disk('local')->path($data['excel_file']);
                    try {
                        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\DriverDebtImport, $filePath);
                        \Filament\Notifications\Notification::make()->title('✅ Import công nợ thành công!')->success()->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()->title('❌ Lỗi Import: ' . $e->getMessage())->danger()->send();
                    } finally {
                        if (file_exists($filePath)) unlink($filePath);
                    }
                }),

            Actions\CreateAction::make(),
        ];
    }

    public function mount(): void
    {
        // 1. Nếu vào bằng route định danh cụ thể, ưu tiên tab đó
        if (request()->routeIs('*.weekly')) {
            $this->activeTab = 'weekly';
        } elseif (request()->routeIs('*.commission')) {
            $this->activeTab = 'commission';
        } 
        // 2. Nếu vào bằng route index, tự động chọn tab theo cấu hình khu vực
        elseif (session()->has('current_city_id') && !session('debt_redirected')) {
            $cityId = session('current_city_id');
            $hasWeeklyPlan = \App\Models\Plan::where('city_id', $cityId)
                ->where('type', 'weekly')
                ->where('is_active', true)
                ->exists();

            $this->activeTab = $hasWeeklyPlan ? 'weekly' : 'commission';
            session(['debt_redirected' => true]);
        }
        
        // Reset cờ cho lần sau
        session()->forget('debt_redirected');
    }

    public function getTabs(): array
    {
        $cityId = session('current_city_id');
        
        $commissionCount = \App\Models\DriverDebt::where('debt_type', 'commission')
            ->when($cityId, function($q) use ($cityId) {
                $q->whereHas('driver', fn($d) => $d->where('city_id', $cityId));
            })
            ->where('status', 'pending')
            ->count();

        $weeklyCount = \App\Models\DriverDebt::where('debt_type', 'weekly')
            ->when($cityId, function($q) use ($cityId) {
                $q->whereHas('driver', fn($d) => $d->where('city_id', $cityId));
            })
            ->where('status', 'pending')
            ->count();

        return [
            'commission' => Tab::make('Chiết khấu (%)')
                ->icon('heroicon-m-receipt-percent')
                ->badge($commissionCount > 0 ? $commissionCount : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('debt_type', 'commission')),
            'weekly' => Tab::make('Gói cố định (Tuần)')
                ->icon('heroicon-m-calendar-days')
                ->badge($weeklyCount > 0 ? $weeklyCount : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('debt_type', 'weekly')),
        ];
    }

    protected function modifyQueryWith(Builder $query): Builder
    {
        if ($this->activeTab === 'commission') {
            // Logic đặc thù cho Commission (join với orders)
            $subquery = \App\Models\Order::selectRaw('
                    delivery_man_id,
                    DATE(completed_at) as order_date,
                    COUNT(*) as orders_count
                ')
                ->where('status', 'completed')
                ->whereNotNull('completed_at')
                ->groupBy('delivery_man_id', DB::raw('DATE(completed_at)'));

            $query->leftJoinSub($subquery, 'daily_orders', function($join) {
                    $join->on('driver_debts.driver_id', '=', 'daily_orders.delivery_man_id')
                         ->whereColumn('driver_debts.date', '=', 'daily_orders.order_date');
                })
                ->orderByDesc('driver_debts.date')
                ->orderByDesc(DB::raw('COALESCE(daily_orders.orders_count, 0)'));
        } else {
            // Logic cho Weekly
            $query->orderByDesc('week_start');
        }
        
        return $query;
    }

    public function getTable(): Table
    {
        $table = parent::getTable();
        
        if ($this->activeTab === 'weekly') {
            return DriverDebtResource::tableWeekly($table);
        }
        
        return DriverDebtResource::tableCommission($table);
    }
}

