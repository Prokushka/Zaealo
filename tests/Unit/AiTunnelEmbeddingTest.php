<?php

use App\Services\AiTunnelService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config()->set('services.aitunnel', [
        'key' => 'test-key',
        'base_url' => 'https://api.aitunnel.test',
    ]);

    Http::preventStrayRequests();
});

test('creates ordered embedding vectors in one batch request', function (): void {
    $vector = array_fill(0, AiTunnelService::EMBEDDING_DIMENSIONS, 0.25);

    Http::fake([
        'https://api.aitunnel.test/v1/embeddings' => Http::response([
            'data' => [
                ['index' => 1, 'embedding' => $vector],
                ['index' => 0, 'embedding' => $vector],
            ],
        ]),
    ]);

    $embeddings = app(AiTunnelService::class)->embeddings(['Кепка', 'Шапка']);

    expect($embeddings)->toHaveCount(2)
        ->and($embeddings[0])->toBe($vector)
        ->and($embeddings[1])->toBe($vector);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.aitunnel.test/v1/embeddings'
        && $request['model'] === 'text-embedding-3-small'
        && $request['input'] === ['Кепка', 'Шапка']);
});

test('rejects an embedding response with an unexpected vector size', function (): void {
    Http::fake([
        'https://api.aitunnel.test/v1/embeddings' => Http::response([
            'data' => [['index' => 0, 'embedding' => [0.1]]],
        ]),
    ]);

    app(AiTunnelService::class)->embeddings(['Кепка']);
})->throws(RuntimeException::class, 'invalid embedding vector');
