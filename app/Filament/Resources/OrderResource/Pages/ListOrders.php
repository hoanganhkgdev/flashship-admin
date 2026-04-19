<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tạo đơn hàng')
                ->icon('heroicon-m-plus')
                ->color('primary'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => \Filament\Resources\Components\Tab::make('Tất cả')
                ->icon('heroicon-m-list-bullet'),
            'new' => \Filament\Resources\Components\Tab::make('Đơn mới')
                ->icon('heroicon-m-sparkles')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('status', ['draft', 'pending']))
                ->badge(fn() => OrderResource::getEloquentQuery()->whereIn('status', ['draft', 'pending'])->count())
                ->badgeColor('info'),
            'delivering' => \Filament\Resources\Components\Tab::make('Đang xử lý')
                ->icon('heroicon-m-truck')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('status', ['assigned', 'confirmed', 'processing', 'picked_up', 'on_the_way', 'delivering']))
                ->badge(fn() => OrderResource::getEloquentQuery()->whereIn('status', ['assigned', 'confirmed', 'processing', 'picked_up', 'on_the_way', 'delivering'])->count())
                ->badgeColor('warning'),
            'completed' => \Filament\Resources\Components\Tab::make('Hoàn tất')
                ->icon('heroicon-m-check-badge')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'completed'))
                ->badge(fn() => OrderResource::getEloquentQuery()->where('status', 'completed')->count())
                ->badgeColor('success'),
            'confirmation' => \Filament\Resources\Components\Tab::make('Chờ xác nhận')
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'delivered_pending'))
                ->badge(fn() => OrderResource::getEloquentQuery()->where('status', 'delivered_pending')->count())
                ->badgeColor('purple'),
            'cancelled' => \Filament\Resources\Components\Tab::make('Đã hủy')
                ->icon('heroicon-m-x-circle')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'cancelled'))
                ->badge(fn() => OrderResource::getEloquentQuery()->where('status', 'cancelled')->count())
                ->badgeColor('danger'),
        ];
    }
}
