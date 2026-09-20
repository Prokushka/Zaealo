<?php

namespace App\Services;

use App\Enums\PricingKey;
use App\Models\PricingRate;

class PricingCatalog
{
    /** @var array<string, int> */
    private array $resolvedCosts = [];

    public function cost(PricingKey $key): int
    {
        return $this->resolvedCosts[$key->value] ??= $this->resolveCost($key);
    }

    private function resolveCost(PricingKey $key): int
    {
        $cost = PricingRate::query()->where('key', $key->value)->value('cost_zarks');

        return $cost === null ? $key->defaultCost() : max(0, (int) $cost);
    }
}
