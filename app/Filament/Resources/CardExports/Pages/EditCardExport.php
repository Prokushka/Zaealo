<?php

namespace App\Filament\Resources\CardExports\Pages;

use App\Filament\Resources\CardExports\CardExportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCardExport extends EditRecord
{
    protected static string $resource = CardExportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
