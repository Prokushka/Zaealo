<?php

namespace App\Filament\Resources\ZarkPackages\Pages;

use App\Filament\Resources\ZarkPackages\ZarkPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditZarkPackage extends EditRecord
{
    protected static string $resource = ZarkPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
