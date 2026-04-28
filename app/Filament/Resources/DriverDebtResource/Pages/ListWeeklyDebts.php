<?php

namespace App\Filament\Resources\DriverDebtResource\Pages;

use App\Filament\Resources\DriverDebtResource;
use App\Models\DriverDebt;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;

class ListWeeklyDebts extends ListRecords
{
    protected static string $resource = DriverDebtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ⬆️ Import công nợ tuần từ Excel
            Actions\Action::make('import_excel')
                ->label('Import Công nợ tuần')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading('Import danh sách công nợ tuần từ file Excel')
                ->modalDescription(new \Illuminate\Support\HtmlString(
                    '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px;font-size:13px;line-height:1.8">'
                    . '📋 <strong>Yêu cầu định dạng file:</strong><br>'
                    . 'Cột A: <code>ID Tài xế</code> (Số) &nbsp; Cột C: <code>Từ ngày</code> (dd/mm/yyyy)<br>'
                    . 'Cột D: <code>Đến ngày</code> &nbsp; Cột E: <code>Công nợ</code> &nbsp; Cột G: <code>Trạng thái</code><br>'
                    . '</div>'
                ))
                ->form([
                    Forms\Components\FileUpload::make('excel_file')
                        ->label('Chọn file Excel (.xlsx)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->disk('local')
                        ->directory('temp_imports')
                        ->required(),
                ])
                ->modalSubmitActionLabel('Bắt đầu import')
                ->action(function (array $data) {
                    // Lấy đường dẫn tuyệt đối chính xác từ Disk local
                    $filePath = \Illuminate\Support\Facades\Storage::disk('local')->path($data['excel_file']);

                    if (!file_exists($filePath)) {
                        \Filament\Notifications\Notification::make()
                            ->title('❌ Lỗi: PHP không tìm thấy file tại ' . $filePath)
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\DriverDebtImport, $filePath);

                        \Filament\Notifications\Notification::make()
                            ->title('✅ Import công nợ thành công!')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('❌ Lỗi Import: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                }),

            Actions\CreateAction::make(),
        ];
    }

    public function mount(): void
    {
        // 🔄 Tự động chuyển hướng dựa theo cấu hình Khu vực (chỉ khi vào route index)
        if (request()->routeIs('filament.admin.resources.driver-debts.index') && session()->has('current_city_id') && !session('debt_redirected')) {
            $cityId = session('current_city_id');

            $hasWeeklyPlan = \App\Models\Plan::active()->forCity($cityId)->weekly()->exists();

            if (!$hasWeeklyPlan) {
                session(['debt_redirected' => true]);
                $this->redirect(DriverDebtResource::getUrl('commission'));
                return;
            }
        }
        // Reset cờ khi đã vào đúng trang
        session()->forget('debt_redirected');
    }

    public function getTabs(): array
    {
        $cityId = session('current_city_id');

        // Gộp 2 query thành 1 để tránh 2 full table scan riêng biệt
        $counts = \App\Models\DriverDebt::query()
            ->whereIn('debt_type', ['commission', 'weekly'])
            ->where('status', 'pending')
            ->when($cityId, fn($q) => $q->whereHas('driver', fn($d) => $d->where('city_id', $cityId)))
            ->selectRaw('debt_type, COUNT(*) as total')
            ->groupBy('debt_type')
            ->pluck('total', 'debt_type');

        $commissionCount = $counts->get('commission', 0);
        $weeklyCount     = $counts->get('weekly', 0);

        return [
            'commission' => \Filament\Resources\Pages\ListRecords\Tab::make('Chiết khấu (%)')
                ->icon('heroicon-m-receipt-percent')
                ->badge($commissionCount > 0 ? $commissionCount : null)
                ->badgeColor('warning')
                ->url(DriverDebtResource::getUrl('commission')),
            'weekly' => \Filament\Resources\Pages\ListRecords\Tab::make('Gói cố định (Tuần)')
                ->icon('heroicon-m-calendar-days')
                ->badge($weeklyCount > 0 ? $weeklyCount : null)
                ->badgeColor('danger')
                ->url(DriverDebtResource::getUrl('weekly')),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->where('debt_type', 'weekly')
            ->orderByDesc('week_start');
    }

    public function getTable(): Table
    {
        $table = parent::getTable();
        $table = DriverDebtResource::tableWeekly($table);

        // Override export action để áp dụng filter tuần
        $table->headerActions([
            ExportAction::make()
                ->label('Xuất Excel')
                ->exports([
                    ExcelExport::make()
                        ->fromModel()
                        ->modifyQueryUsing(function ($query) {
                            $baseQuery = DriverDebtResource::getEloquentQuery();
                            $query = $baseQuery->where('debt_type', 'weekly');

                            $tableFilters  = $this->tableFilters ?? [];
                            $weekFilterData = $tableFilters['week_start'] ?? null;
                            $weekStart = $weekEnd = null;

                            if ($weekFilterData && !empty($weekFilterData['week_start'])) {
                                $date = \Carbon\Carbon::parse($weekFilterData['week_start']);
                                $weekStart = $date->copy()->startOfWeek();
                                $weekEnd   = $date->copy()->endOfWeek();
                            }

                            if (!$weekStart || !$weekEnd) {
                                $weekStart = \Carbon\Carbon::now()->startOfWeek();
                                $weekEnd   = \Carbon\Carbon::now()->endOfWeek();
                            }

                            $query->whereBetween('week_start', [
                                $weekStart->toDateString(),
                                $weekEnd->toDateString(),
                            ]);

                            return $query;
                        })
                        ->withFilename(function () {
                            $tableFilters  = $this->tableFilters ?? [];
                            $weekFilterData = $tableFilters['week_start'] ?? null;

                            if ($weekFilterData && !empty($weekFilterData['week_start'])) {
                                $date = \Carbon\Carbon::parse($weekFilterData['week_start']);
                                $ws   = $date->copy()->startOfWeek();
                                $we   = $date->copy()->endOfWeek();
                                return 'Danh_sach_cong_no_tuan_' . $ws->format('Y_m_d') . '_' . $we->format('Y_m_d');
                            }

                            $ws = \Carbon\Carbon::now()->startOfWeek();
                            $we = \Carbon\Carbon::now()->endOfWeek();
                            return 'Danh_sach_cong_no_tuan_' . $ws->format('Y_m_d') . '_' . $we->format('Y_m_d');
                        })
                        ->withColumns([
                            Column::make('driver.name')->heading('Tài xế'),
                            Column::make('week_start')
                                ->heading('Từ ngày')
                                ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : ''),
                            Column::make('week_end')
                                ->heading('Đến ngày')
                                ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : ''),
                            Column::make('amount_due')
                                ->heading('Công nợ')
                                ->formatStateUsing(fn($state) => is_numeric($state) ? number_format($state, 0, ',', '.') : ''),
                            Column::make('amount_paid')
                                ->heading('Đã thanh toán')
                                ->formatStateUsing(fn($state) => is_numeric($state) ? number_format($state, 0, ',', '.') : ''),
                            Column::make('status')
                                ->heading('Trạng thái')
                                ->formatStateUsing(fn($state) => match ($state) {
                                    'pending' => 'Chưa thanh toán',
                                    'overdue' => 'Quá hạn',
                                    'paid'    => 'Đã thanh toán',
                                    default   => ucfirst($state ?? ''),
                                }),
                        ])
                        ->except(['id', 'date', 'ref_id', 'created_at', 'updated_at'])
                ]),
        ]);

        return $table;
    }
}
