<?php

namespace App\Filament\Resources\CardGenerations\Pages;

use App\Filament\Resources\CardGenerations\CardGenerationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCardGeneration extends EditRecord
{
    protected static string $resource = CardGenerationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
