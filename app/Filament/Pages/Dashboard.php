<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        // Cho phép admin, dispatcher, manager, accountant xem Bảng điều khiển
        return auth()->user()->hasAnyRole(['admin', 'dispatcher', 'manager', 'accountant', 'subadmin']);
    }
}

