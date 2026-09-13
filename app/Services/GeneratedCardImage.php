<?php

declare(strict_types=1);

namespace App\Services;

final readonly class GeneratedCardImage
{
    public function __construct(
        public string $contents,
        public string $extension = 'png',
    ) {}
}
