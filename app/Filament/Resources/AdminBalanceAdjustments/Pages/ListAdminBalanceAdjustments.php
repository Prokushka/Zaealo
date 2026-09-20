<?php

namespace App\Filament\Resources\AdminBalanceAdjustments\Pages;

use App\Filament\Resources\AdminBalanceAdjustments\AdminBalanceAdjustmentResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminBalanceAdjustments extends ListRecords
{
    protected static string $resource = AdminBalanceAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
