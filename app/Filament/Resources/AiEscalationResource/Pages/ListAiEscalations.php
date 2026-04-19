<?php

namespace App\Filament\Resources\AiEscalationResource\Pages;

use App\Filament\Resources\AiEscalationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAiEscalations extends ListRecords
{
    protected static string $resource = AiEscalationResource::class;

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('🔓 Đang mở')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'open'))
                ->badge(fn () => \App\Models\AiEscalation::where('status', 'open')->count()),

            'all' => Tab::make('Tất cả'),

            'resolved' => Tab::make('✅ Đã xử lý')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'resolved')),
        ];
    }
}
