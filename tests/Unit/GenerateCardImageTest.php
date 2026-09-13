<?php

use App\Jobs\GenerateCardImage;
use App\Services\CardImageGenerationService;

test('passes one selected scenario to the image generation service', function (): void {
    $service = Mockery::mock(CardImageGenerationService::class);
    $service->shouldReceive('generate')
        ->once()
        ->with(42);

    $job = new GenerateCardImage(cardImageId: 42);

    $job->handle($service);
});
