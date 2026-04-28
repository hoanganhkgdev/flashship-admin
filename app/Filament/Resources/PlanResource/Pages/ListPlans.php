<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Filament\Resources\PlanResource;
use App\Filament\Resources\PlanResource\Widgets\PlanOverviewWidget;
use App\Models\Plan;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Thêm gói cước')];
    }

    protected function getHeaderWidgets(): array
    {
        return [PlanOverviewWidget::class];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tất cả')
                ->badge(Plan::count()),

            Plan::TYPE_WEEKLY => Tab::make('Cước tuần')
                ->icon('heroicon-o-calendar-days')
                ->badge(Plan::weekly()->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn(Builder $query) => $query->weekly()),

            Plan::TYPE_COMMISSION => Tab::make('Chiết khấu %')
                ->icon('heroicon-o-percent-badge')
                ->badge(Plan::commission()->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->commission()),

            Plan::TYPE_PARTNER => Tab::make('Tài xế đối tác')
                ->icon('heroicon-o-user-group')
                ->badge(Plan::partner()->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->partner()),

            Plan::TYPE_FREE => Tab::make('Miễn phí')
                ->icon('heroicon-o-gift')
                ->badge(Plan::free()->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn(Builder $query) => $query->free()),
        ];
    }
}
