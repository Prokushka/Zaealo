<?php

use App\Services\AiTunnelService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('requests a png from gpt image and decodes its contents', function (): void {
    config()->set('services.aitunnel.base_url', 'https://api.aitunnel.test');
    config()->set('services.aitunnel.key', 'test-key');
    Http::fake([
        'https://api.aitunnel.test/v1/images/generations' => Http::response([
            'data' => [['b64_json' => base64_encode("\x89PNG\r\n\x1a\ngenerated-image")]],
        ]),
    ]);

    $image = app(AiTunnelService::class)->generateImage('Создай фото термокружки');

    expect($image->contents)->toBe("\x89PNG\r\n\x1a\ngenerated-image")
        ->and($image->extension)->toBe('png');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.aitunnel.test/v1/images/generations'
            && $request['model'] === 'gpt-image-2'
            && $request['prompt'] === 'Создай фото термокружки'
            && $request['size'] === '1024x1536'
            && $request['output_format'] === 'png'
            && ! array_key_exists('output_compression', $request->data());
    });
});

test('rejects a response that is not actually png', function (): void {
    config()->set('services.aitunnel.base_url', 'https://api.aitunnel.test');
    config()->set('services.aitunnel.key', 'test-key');
    Http::fake([
        'https://api.aitunnel.test/v1/images/generations' => Http::response([
            'data' => [['b64_json' => base64_encode('not-a-png')]],
        ]),
    ]);

    app(AiTunnelService::class)->generateImage('Создай фото термокружки');
})->throws(RuntimeException::class, 'did not return the requested PNG');

test('edits the supplied reference image with high fidelity', function (): void {
    config()->set('services.aitunnel.base_url', 'https://api.aitunnel.test');
    config()->set('services.aitunnel.key', 'test-key');
    Http::fake([
        'https://api.aitunnel.test/v1/images/edits' => Http::response([
            'data' => [['b64_json' => base64_encode("\x89PNG\r\n\x1a\ngenerated-image")]],
        ]),
    ]);

    $image = app(AiTunnelService::class)->editImage(
        'Создай фото термокружки',
        'reference-image',
        'product.png',
    );

    expect($image->extension)->toBe('png');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.aitunnel.test/v1/images/edits'
            && str_contains($request->body(), 'name="image[]"')
            && str_contains($request->body(), 'input_fidelity')
            && str_contains($request->body(), 'high');
    });
});
