<?php

declare(strict_types=1);

use App\Data\GeneratedProductCopyData;

it('preserves the validated generated copy payload', function (): void {
    $payload = [
        'title' => 'Термокружка для города',
        'description' => 'Практичная термокружка для горячих напитков.',
        'infographic_features' => ['Сохраняет тепло', 'Удобная крышка'],
    ];

    expect(GeneratedProductCopyData::fromValidated($payload)->toArray())
        ->toBe($payload);
});
