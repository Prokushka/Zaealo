<?php

namespace App\Filament\Resources\PricingRates\Pages;

use App\Filament\Resources\PricingRates\PricingRateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPricingRate extends ViewRecord
{
    protected static string $resource = PricingRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
