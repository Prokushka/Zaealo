<?php

declare(strict_types=1);

namespace App\Data;

final readonly class MarketplaceCardCreationResult
{
    public const WAITING = 'waiting';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public function __construct(
        public string $status,
        public ?string $externalTaskId = null,
        public ?string $externalProductId = null,
        public ?string $error = null,
    ) {}

    public static function waiting(?string $externalTaskId = null, ?string $externalProductId = null): self
    {
        return new self(self::WAITING, $externalTaskId, $externalProductId);
    }

    public static function completed(?string $externalProductId = null): self
    {
        return new self(self::COMPLETED, externalProductId: $externalProductId);
    }

    public static function failed(string $error): self
    {
        return new self(self::FAILED, error: $error);
    }
}
