<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardHeaderWidget;
use App\Filament\Widgets\DashboardStatsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'dispatcher', 'manager', 'accountant', 'subadmin']);
    }

    public function getWidgets(): array
    {
        return [
            DashboardHeaderWidget::class,
            DashboardStatsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
