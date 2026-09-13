<?php

use App\Services\AIChoice;
use App\Services\AiTunnelService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.aitunnel', [
        'key' => 'test-key',
        'base_url' => 'https://api.aitunnel.ru',
        'model' => 'auto',
    ]);

    Http::preventStrayRequests();
});

it('sends a text chat completion request', function () {
    Http::fake([
        'api.aitunnel.ru/v1/chat/completions' => Http::response([
            'model' => 'gpt-4o',
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Ответ']]],
            'usage' => ['total_tokens' => 12],
        ]),
    ]);

    $response = app(AiTunnelService::class)->chatCompletion([
        ['role' => 'user', 'content' => 'Привет'],
    ], AIChoice::GPT4MINI);

    expect($response['choices'][0]['message']['content'])->toBe('Ответ');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.aitunnel.ru/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['model'] === 'gpt-4o-mini'
        && $request['temperature'] === 0.0
        && $request['messages'][0]['content'] === 'Привет');
});

it('sends several images for multimodal analysis', function () {
    Http::fake([
        'api.aitunnel.ru/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Описание товара']]],
        ]),
    ]);

    app(AiTunnelService::class)->analyzeImages(
        'Опиши товар',
        ['https://storage.test/front.jpg', 'https://storage.test/side.jpg'],
        AIChoice::QWEN_FLASH,
    );

    Http::assertSent(function (Request $request): bool {
        $content = $request['messages'][0]['content'];

        return $request['model'] === 'qwen3.7-flash'
            && $content[0] === ['type' => 'text', 'text' => 'Опиши товар']
            && $content[1]['image_url']['url'] === 'https://storage.test/front.jpg'
            && $content[2]['image_url']['url'] === 'https://storage.test/side.jpg';
    });
});

it('rejects an image analysis request without images', function () {
    app(AiTunnelService::class)->analyzeImages('Опиши товар', [], AIChoice::QWEN_FLASH);
})->throws(InvalidArgumentException::class);

it('throws when AITunnel is not configured', function () {
    config()->set('services.aitunnel.key');

    app(AiTunnelService::class)->chatCompletion([
        ['role' => 'user', 'content' => 'Привет'],
    ], AIChoice::GPT4MINI);
})->throws(RuntimeException::class, 'AITunnel [key] is not configured.');
