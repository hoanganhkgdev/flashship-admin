<?php

namespace App\Filament\Resources\DriverDebtResource\Pages;

use App\Filament\Resources\DriverDebtResource;
use App\Filament\Resources\DriverDebtResource\Widgets\DriverDebtOverviewWidget;
use App\Models\DriverDebt;
use App\Models\Plan;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;

class ListCommissionDebts extends ListRecords
{
    protected static string $resource = DriverDebtResource::class;

    /**
     * Trả về danh sách plan type có ở khu vực hiện tại, theo thứ tự ưu tiên.
     * Toàn quốc (no city) → trả về tất cả loại có thể.
     */
    protected function cityPlanTypes(): array
    {
        $cityId   = session('current_city_id') ?: null;
        $priority = ['commission', 'partner', 'weekly'];

        if (!$cityId) {
            return $priority;
        }

        $active = Plan::active()->forCity($cityId)->pluck('type')->unique()->toArray();

        return array_values(array_filter($priority, fn($t) => in_array($t, $active)));
    }

    protected function getHeaderActions(): array
    {
        return [
            // Nút Tính lại CK — hiển thị cho tab commission và partner
            Actions\Action::make('generate_commission')
                ->label('Tính lại CK')
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->visible(fn() => in_array($this->activeTab ?? 'commission', ['commission', 'partner']))
                ->modalHeading('Tính / Tính lại công nợ chiết khấu')
                ->modalDescription('Hệ thống tính lại tổng tiền ship × % chiết khấu cho tất cả tài xế trong ngày chọn.')
                ->form([
                    Forms\Components\DatePicker::make('date')
                        ->label('Ngày cần tính / tính lại')
                        ->displayFormat('d/m/Y')
                        ->default(now()->subDay()->toDateString())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $cityId = session('current_city_id') ?: null;
                    try {
                        $args = ['--date' => $data['date']];
                        if ($cityId) {
                            $args['--city'] = $cityId;
                        }
                        Artisan::call('debt:calculate-daily-commission', $args);
                        Notification::make()
                            ->title('Đã tính lại công nợ ngày ' . Carbon::parse($data['date'])->format('d/m/Y'))
                            ->success()->send();
                    } catch (\Exception $e) {
                        Notification::make()->title('Lỗi: ' . $e->getMessage())->danger()->send();
                    }
                }),

            // Nút Tạo công nợ tuần — chỉ hiển thị tab weekly
            Actions\Action::make('generate_weekly')
                ->label('Tạo công nợ tuần')
                ->icon('heroicon-o-calendar-days')
                ->color('primary')
                ->visible(fn() => $this->activeTab === 'weekly')
                ->requiresConfirmation()
                ->modalHeading('Tạo công nợ tuần này')
                ->modalDescription('Tạo công nợ tuần hiện tại cho tất cả tài xế gói cố định chưa có phiếu. Bỏ qua tài xế đã tồn tại.')
                ->action(function () {
                    $cityId = session('current_city_id') ?: null;
                    try {
                        $args = [];
                        if ($cityId) {
                            $args['--city'] = $cityId;
                        }
                        Artisan::call('driver:generate-debts', $args);
                        Notification::make()->title('Đã tạo công nợ tuần thành công')->success()->send();
                    } catch (\Exception $e) {
                        Notification::make()->title('Lỗi: ' . $e->getMessage())->danger()->send();
                    }
                }),

            // Nút Import Excel — chỉ hiển thị tab weekly
            Actions\Action::make('import_weekly')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
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
                        Notification::make()->title('Import công nợ thành công!')->success()->send();
                    } catch (\Exception $e) {
                        Notification::make()->title('Lỗi Import: ' . $e->getMessage())->danger()->send();
                    } finally {
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                }),

            Actions\CreateAction::make()->label('Thêm thủ công'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [DriverDebtOverviewWidget::class];
    }

    public function getTabs(): array
    {
        $cityId = session('current_city_id') ?: null;
        $types  = $this->cityPlanTypes();

        // Base query pending, city-filtered
        $base = DriverDebt::query()
            ->where('status', 'pending')
            ->when($cityId, fn($q) => $q->whereHas('driver', fn($d) => $d->where('city_id', $cityId)));

        $tabs = [];

        // Tab: Chiết khấu (%) — tài xế không có custom_commission_rate
        if (in_array('commission', $types)) {
            $count = (clone $base)
                ->where('debt_type', 'commission')
                ->whereHas('driver', fn($q) => $q->whereNull('custom_commission_rate'))
                ->count();

            $tabs['commission'] = Tab::make('Chiết khấu (%)')
                ->icon('heroicon-m-receipt-percent')
                ->badge($count ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('debt_type', 'commission')
                    ->whereHas('driver', fn($q) => $q->whereNull('custom_commission_rate')));
        }

        // Tab: Đối tác (%) — tài xế có custom_commission_rate
        if (in_array('partner', $types)) {
            $count = (clone $base)
                ->where('debt_type', 'commission')
                ->whereHas('driver', fn($q) => $q->whereNotNull('custom_commission_rate'))
                ->count();

            $tabs['partner'] = Tab::make('Đối tác (%)')
                ->icon('heroicon-m-user-group')
                ->badge($count ?: null)
                ->badgeColor('info')
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('debt_type', 'commission')
                    ->whereHas('driver', fn($q) => $q->whereNotNull('custom_commission_rate')));
        }

        // Tab: Gói tuần
        if (in_array('weekly', $types)) {
            $count = (clone $base)->where('debt_type', 'weekly')->count();

            $tabs['weekly'] = Tab::make('Gói tuần')
                ->icon('heroicon-m-calendar-days')
                ->badge($count ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('debt_type', 'weekly'));
        }

        return $tabs;
    }

    public function getTable(): Table
    {
        $table = parent::getTable();

        if ($this->activeTab === 'weekly') {
            return DriverDebtResource::tableWeekly($table);
        }

        // commission và partner đều dùng layout chiết khấu
        return DriverDebtResource::tableCommission($table);
    }
}
