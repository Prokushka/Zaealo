<?php

namespace App\Filament\Resources\ZarkPackages\Pages;

use App\Filament\Resources\ZarkPackages\ZarkPackageResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewZarkPackage extends ViewRecord
{
    protected static string $resource = ZarkPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
