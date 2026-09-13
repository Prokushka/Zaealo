<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class CardImageGenerationFailed
{
    use Dispatchable;

    public function __construct(
        public readonly int $cardImageId,
        public readonly ?string $error,
    ) {}
}
