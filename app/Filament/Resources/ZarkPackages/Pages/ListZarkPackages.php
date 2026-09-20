<?php

namespace App\Filament\Resources\ZarkPackages\Pages;

use App\Filament\Resources\ZarkPackages\ZarkPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListZarkPackages extends ListRecords
{
    protected static string $resource = ZarkPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
