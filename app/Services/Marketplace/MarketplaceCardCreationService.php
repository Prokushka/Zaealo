<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Contracts\MarketplaceCardCreator;
use App\Exceptions\MarketplaceCardCreationException;

final class MarketplaceCardCreationService
{
    public function __construct(
        private OzonCardCreator $ozon,
        private WildberriesCardCreator $wildberries,
    ) {}

    public function creator(string $marketplace): MarketplaceCardCreator
    {
        return match ($marketplace) {
            'ozon' => $this->ozon,
            'wildberries' => $this->wildberries,
            default => throw new MarketplaceCardCreationException('Этот маркетплейс пока не поддерживается.'),
        };
    }
}
