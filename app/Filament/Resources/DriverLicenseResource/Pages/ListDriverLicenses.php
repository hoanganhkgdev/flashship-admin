<?php

namespace App\Filament\Resources\DriverLicenseResource\Pages;

use App\Filament\Resources\DriverLicenseResource;
use App\Filament\Resources\DriverLicenseResource\Widgets\DriverLicenseOverviewWidget;
use App\Models\DriverLicense;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDriverLicenses extends ListRecords
{
    protected static string $resource = DriverLicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Thêm hồ sơ')];
    }

    protected function getHeaderWidgets(): array
    {
        return [DriverLicenseOverviewWidget::class];
    }

    private function cityQuery(): Builder
    {
        $query = DriverLicense::query();
        $user  = auth()->user();

        if ($user->hasRole('admin')) {
            if ($cityId = session('current_city_id')) {
                $query->whereHas('user', fn($q) => $q->where('city_id', $cityId));
            }
        } elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $query->whereHas('user', fn($q) => $q->where('city_id', $user->city_id));
        }

        return $query;
    }

    public function getTabs(): array
    {
        $base     = $this->cityQuery();
        $all      = (clone $base)->count();
        $pending  = (clone $base)->where('status', DriverLicense::STATUS_PENDING)->count();
        $approved = (clone $base)->where('status', DriverLicense::STATUS_APPROVED)->count();
        $rejected = (clone $base)->where('status', DriverLicense::STATUS_REJECTED)->count();

        return [
            'all' => Tab::make('Tất cả')
                ->icon('heroicon-o-identification')
                ->badge($all),

            'pending' => Tab::make('Chờ duyệt')
                ->icon('heroicon-o-clock')
                ->badge($pending)->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', DriverLicense::STATUS_PENDING)),

            'approved' => Tab::make('Đã duyệt')
                ->icon('heroicon-o-check-badge')
                ->badge($approved)->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', DriverLicense::STATUS_APPROVED)),

            'rejected' => Tab::make('Từ chối')
                ->icon('heroicon-o-x-circle')
                ->badge($rejected)->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', DriverLicense::STATUS_REJECTED)),
        ];
    }
}
