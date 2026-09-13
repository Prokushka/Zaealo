<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\MarketplaceCardCreationResult;
use App\Models\CardExport;

interface MarketplaceCardCreator
{
    public function submit(CardExport $export): MarketplaceCardCreationResult;

    public function check(CardExport $export): MarketplaceCardCreationResult;
}
