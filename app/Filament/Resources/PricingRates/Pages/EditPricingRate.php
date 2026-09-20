<?php

namespace App\Filament\Resources\PricingRates\Pages;

use App\Filament\Resources\PricingRates\PricingRateResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPricingRate extends EditRecord
{
    protected static string $resource = PricingRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
