<?php

use App\Models\OzonCategory;
use App\Models\WbCategory;
use App\Services\AiTunnelService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function categoryEmbedding(float $firstValue): array
{
    return [$firstValue, ...array_fill(0, AiTunnelService::EMBEDDING_DIMENSIONS - 1, 0.0)];
}

beforeEach(function (): void {
    config()->set('services.ozon.categories_client_id', 'ozon-client');
    config()->set('services.ozon.categories_api_key', 'ozon-key');
    config()->set('services.wildberries.categories_api_key', 'wb-key');
    Http::preventStrayRequests();
});

test('syncs the flattened Ozon category tree with embeddings', function (): void {
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldReceive('embeddings')
        ->once()
        ->with(['Одежда > Головные уборы > Кепки', 'Одежда > Головные уборы > Шапки'])
        ->andReturn([categoryEmbedding(0.1), categoryEmbedding(0.2)]);
    app()->instance(AiTunnelService::class, $aiTunnel);
    Http::fake([
        'api-seller.ozon.ru/v1/description-category/tree' => Http::response([
            'result' => [[
                'description_category_id' => 10,
                'category_name' => 'Одежда',
                'children' => [[
                    'description_category_id' => 20,
                    'category_name' => 'Головные уборы',
                    'children' => [
                        ['type_id' => 30, 'type_name' => 'Кепки', 'children' => []],
                        ['type_id' => 31, 'type_name' => 'Шапки', 'children' => []],
                    ],
                ]],
            ]],
        ]),
    ]);

    $this->artisan('ozon:sync-categories')
        ->expectsOutput('Ozon categories synchronized: 2')
        ->assertSuccessful();

    expect(OzonCategory::query()->count())->toBe(2)
        ->and(OzonCategory::query()->where('type_id', 30)->value('full_path'))->toBe('Одежда > Головные уборы > Кепки')
        ->and(OzonCategory::query()->where('type_id', 30)->value('description_category_id'))->toBe(20);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-seller.ozon.ru/v1/description-category/tree'
        && $request->hasHeader('Client-Id', 'ozon-client')
        && $request->hasHeader('Api-Key', 'ozon-key')
        && $request['language'] === 'DEFAULT');
});

test('syncs all Wildberries subjects for every parent category with embeddings', function (): void {
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldReceive('embeddings')
        ->once()
        ->with(['Спецодежда > Каски', 'Спецодежда > Защитные очки', 'Одежда > Шапки'])
        ->andReturn([categoryEmbedding(0.1), categoryEmbedding(0.2), categoryEmbedding(0.3)]);
    app()->instance(AiTunnelService::class, $aiTunnel);
    Http::fake([
        'content-api.wildberries.ru/content/v2/object/parent/all*' => Http::response([
            'data' => [
                ['id' => 10, 'name' => 'Спецодежда'],
                ['id' => 20, 'name' => 'Одежда'],
            ],
        ]),
        'content-api.wildberries.ru/content/v2/object/all?locale=ru&parentID=10' => Http::response([
            'data' => [
                ['subjectID' => 100, 'subjectName' => 'Каски', 'parentName' => 'Спецодежда'],
                ['subjectID' => 101, 'subjectName' => 'Защитные очки', 'parentName' => 'Спецодежда'],
            ],
        ]),
        'content-api.wildberries.ru/content/v2/object/all?locale=ru&parentID=20' => Http::response([
            'data' => [
                ['subjectID' => 102, 'subjectName' => 'Шапки', 'parentName' => 'Одежда'],
            ],
        ]),
    ]);

    $this->artisan('wb:sync-categories')
        ->expectsOutput('Wildberries categories synchronized: 3')
        ->assertSuccessful();

    expect(WbCategory::query()->count())->toBe(3)
        ->and(WbCategory::query()->where('subject_id', 100)->value('full_path'))->toBe('Спецодежда > Каски')
        ->and(WbCategory::query()->where('subject_id', 102)->value('full_path'))->toBe('Одежда > Шапки');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://content-api.wildberries.ru/content/v2/object/parent/all?locale=ru'
        && $request->hasHeader('Authorization', 'Bearer wb-key'));
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://content-api.wildberries.ru/content/v2/object/all?locale=ru&parentID=10');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://content-api.wildberries.ru/content/v2/object/all?locale=ru&parentID=20');
});

test('does not regenerate embeddings for unchanged Wildberries subjects', function (): void {
    $existingCategory = WbCategory::query()->create([
        'subject_id' => 100,
        'subject_name' => 'Каски',
        'parent_name' => 'Спецодежда',
        'full_path' => 'Спецодежда > Каски',
        'embedding' => categoryEmbedding(0.1),
    ]);
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldNotReceive('embeddings');
    app()->instance(AiTunnelService::class, $aiTunnel);
    Http::fake([
        'content-api.wildberries.ru/content/v2/object/parent/all*' => Http::response([
            'data' => [['id' => 10, 'name' => 'Спецодежда']],
        ]),
        'content-api.wildberries.ru/content/v2/object/all?locale=ru&parentID=10' => Http::response([
            'data' => [['subjectID' => 100, 'subjectName' => 'Каски', 'parentName' => 'Спецодежда']],
        ]),
    ]);

    $this->artisan('wb:sync-categories')
        ->expectsOutput('Wildberries categories synchronized: 1')
        ->assertSuccessful();

    $embedding = WbCategory::query()->findOrFail($existingCategory->getKey())->embedding;

    expect(WbCategory::query()->count())->toBe(1)
        ->and($embedding)->toHaveCount(AiTunnelService::EMBEDDING_DIMENSIONS)
        ->and($embedding[0])->toBe(0.1);
});

test('keeps the existing Wildberries catalogue when fetching a parent fails', function (): void {
    $existingCategory = WbCategory::query()->create([
        'subject_id' => 100,
        'subject_name' => 'Каски',
        'parent_name' => 'Спецодежда',
        'full_path' => 'Спецодежда > Каски',
        'embedding' => categoryEmbedding(0.1),
    ]);
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldNotReceive('embeddings');
    app()->instance(AiTunnelService::class, $aiTunnel);
    Http::fake([
        'content-api.wildberries.ru/content/v2/object/parent/all*' => Http::response([
            'data' => [
                ['id' => 10, 'name' => 'Спецодежда'],
                ['id' => 20, 'name' => 'Одежда'],
            ],
        ]),
        'content-api.wildberries.ru/content/v2/object/all?locale=ru&parentID=10' => Http::response([
            'data' => [['subjectID' => 100, 'subjectName' => 'Каски', 'parentName' => 'Спецодежда']],
        ]),
        'content-api.wildberries.ru/content/v2/object/all?locale=ru&parentID=20' => Http::response([], 500),
    ]);

    $this->artisan('wb:sync-categories')->assertFailed();

    $this->assertModelExists($existingCategory);
    expect(WbCategory::query()->count())->toBe(1)
        ->and(WbCategory::query()->findOrFail($existingCategory->getKey())->full_path)->toBe('Спецодежда > Каски');
});
