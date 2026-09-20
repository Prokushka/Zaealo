<?php

namespace App\Filament\Resources\CardImages\Pages;

use App\Filament\Resources\CardImages\CardImageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCardImage extends EditRecord
{
    protected static string $resource = CardImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
