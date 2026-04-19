<?php

namespace App\Filament\Resources\AiKnowledgeResource\Pages;

use App\Filament\Resources\AiKnowledgeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAiKnowledge extends EditRecord
{
    protected static string $resource = AiKnowledgeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
