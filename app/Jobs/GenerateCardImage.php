<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\CardImageGenerationFailed;
use App\Services\CardImageGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class GenerateCardImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $cardImageId) {}

    public function handle(CardImageGenerationService $imageGeneration): void
    {
        $imageGeneration->generate($this->cardImageId);
    }

    public function failed(?Throwable $exception): void
    {
        CardImageGenerationFailed::dispatch($this->cardImageId, $exception?->getMessage());
    }
}
