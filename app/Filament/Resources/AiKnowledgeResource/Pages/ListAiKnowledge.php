<?php

namespace App\Filament\Resources\AiKnowledgeResource\Pages;

use App\Filament\Resources\AiKnowledgeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAiKnowledge extends ListRecords
{
    protected static string $resource = AiKnowledgeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
