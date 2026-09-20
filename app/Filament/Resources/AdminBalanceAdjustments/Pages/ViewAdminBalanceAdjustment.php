<?php

namespace App\Filament\Resources\AdminBalanceAdjustments\Pages;

use App\Filament\Resources\AdminBalanceAdjustments\AdminBalanceAdjustmentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminBalanceAdjustment extends ViewRecord
{
    protected static string $resource = AdminBalanceAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
