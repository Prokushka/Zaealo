<?php

use App\Enums\PricingKey;
use App\Models\PricingRate;
use App\Services\PricingCatalog;

test('pricing catalogue reads administrator configured costs', function () {
    PricingRate::query()
        ->where('key', PricingKey::ImageGeneration->value)
        ->update(['cost_zarks' => 73]);

    expect(app(PricingCatalog::class)->cost(PricingKey::ImageGeneration))->toBe(73);
});

test('pricing catalogue has safe defaults for a missing rate', function () {
    PricingRate::query()->where('key', PricingKey::CardExport->value)->delete();

    expect(app(PricingCatalog::class)->cost(PricingKey::CardExport))
        ->toBe(PricingKey::CardExport->defaultCost());
});
