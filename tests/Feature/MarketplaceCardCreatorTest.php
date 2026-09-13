<?php

use App\Data\MarketplaceCardCreationResult;
use App\Exceptions\MarketplaceCardCreationException;
use App\Jobs\CheckMarketplaceCardPublication;
use App\Jobs\PublishMarketplaceCard;
use App\Models\CardExport;
use App\Models\CardGeneration;
use App\Models\User;
use App\Services\Marketplace\MarketplaceCardCreationService;
use App\Services\Marketplace\OzonCardCreator;
use App\Services\Marketplace\WildberriesCardCreator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function marketplaceExport(string $marketplace): CardExport
{
    $attributes = [
        'Бренд' => 'ZARQ',
        'Материал' => 'Сталь',
        'Цена, ₽' => '1490',
        'Старая цена, ₽' => '1990',
        'Штрихкод' => '4601234567890',
        'Длина, см' => '10',
        'Ширина, см' => '10',
        'Высота, см' => '20',
        'Вес, кг' => '0.4',
        'Вес товара с упаковкой' => '400 г',
    ];
    $user = User::factory()->create();
    $card = $user->cards()->create([
        'marketplace' => $marketplace,
        'title' => 'Термокружка',
        'description' => 'Сохраняет тепло.',
        'status' => 'ready',
    ]);
    $generation = CardGeneration::factory()->for($card, 'card')->create([
        'attributes_category_id' => 123,
        'attributes_type_id' => $marketplace === 'ozon' ? 456 : null,
        'attributes_data' => $attributes,
    ]);
    $user->marketplaceApiKeys()->create([
        'marketplace' => $marketplace,
        'api_key' => 'secret-key',
        'client_id' => $marketplace === 'ozon' ? 'client-id' : null,
    ]);

    return $generation->export()->create([
        'marketplace' => $marketplace,
        'status' => CardExport::STATUS_QUEUED,
        'payload' => [
            'category_id' => 123,
            'type_id' => $marketplace === 'ozon' ? 456 : null,
            'seller_sku' => 'MUG-001',
            'title' => 'Термокружка',
            'description' => 'Сохраняет тепло.',
            'attributes' => $attributes,
            'images' => [[
                'type' => 'user_upload',
                'path' => 'cards/source.png',
            ]],
        ],
    ]);
}

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    Cache::flush();
    Storage::fake('s3');
    Storage::disk('s3')->put('cards/source.png', 'source-image');
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path): string => "https://storage.test/{$path}",
    );
});

test('submits an ozon card and reads its asynchronous result', function (): void {
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/v1/description-category/attribute')) {
            return Http::response(['result' => [
                ['id' => 10, 'name' => 'Бренд', 'type' => 'String', 'is_required' => true],
                ['id' => 11, 'name' => 'Материал', 'type' => 'String', 'is_required' => true],
            ]]);
        }

        if (str_ends_with($request->url(), '/v3/product/import')) {
            return Http::response(['result' => ['task_id' => 777]]);
        }

        return Http::response([
            'result' => ['items' => [[
                'status' => 'imported',
                'product_id' => 888,
                'errors' => [],
            ]]],
        ]);
    });
    $export = marketplaceExport('ozon');
    $creator = app(OzonCardCreator::class);

    $submitted = $creator->submit($export);
    $export->update(['external_task_id' => $submitted->externalTaskId]);
    $completed = $creator->check($export->refresh());

    expect($submitted->status)->toBe(MarketplaceCardCreationResult::WAITING)
        ->and($submitted->externalTaskId)->toBe('777')
        ->and($completed->status)->toBe(MarketplaceCardCreationResult::COMPLETED)
        ->and($completed->externalProductId)->toBe('888');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v3/product/import')
        && $request['items'][0]['offer_id'] === 'MUG-001'
        && $request['items'][0]['primary_image'] === 'https://storage.test/cards/source.png'
        && $request['items'][0]['weight'] === 400
        && $request['items'][0]['attributes'] === [
            ['id' => 10, 'complex_id' => 0, 'values' => [['value' => 'ZARQ']]],
            ['id' => 11, 'complex_id' => 0, 'values' => [['value' => 'Сталь']]],
        ]);
});

test('sends the dictionary value ID for an Ozon dictionary attribute', function (): void {
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/v1/description-category/attribute')) {
            return Http::response(['result' => [
                ['id' => 21845, 'name' => 'Особенности касок', 'type' => 'String', 'is_required' => true, 'dictionary_id' => 10, 'is_collection' => true],
            ]]);
        }

        if (str_ends_with($request->url(), '/v1/description-category/attribute/values/search')) {
            return Http::response(['result' => [[
                'id' => $request['value'] === 'Гладкая поверхность' ? 99 : 100,
                'value' => $request['value'],
            ]]]);
        }

        return Http::response(['result' => ['task_id' => 777]]);
    });
    $export = marketplaceExport('ozon');
    $export->update(['payload' => [
        ...$export->payload,
        'attributes' => ['Особенности касок' => 'Гладкая поверхность, высокая видимость'],
    ]]);

    app(OzonCardCreator::class)->submit($export->refresh());

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v3/product/import')
        && $request['items'][0]['attributes'] === [[
            'id' => 21845,
            'complex_id' => 0,
            'values' => [[
                'value' => 'Гладкая поверхность',
                'dictionary_value_id' => 99,
            ], [
                'value' => 'высокая видимость',
                'dictionary_value_id' => 100,
            ]],
        ]]);
});

test('matches a punctuated dictionary code through generic normalization', function (): void {
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/v1/description-category/attribute')) {
            return Http::response(['result' => [[
                'id' => 700,
                'name' => 'Код классификации',
                'type' => 'String',
                'is_required' => true,
                'dictionary_id' => 10,
            ]]]);
        }

        if (str_ends_with($request->url(), '/v1/description-category/attribute/values/search')) {
            return Http::response(['result' => $request['value'] === '6506100000'
                ? [['id' => 701, 'value' => '6506100000']]
                : []]);
        }

        return Http::response(['result' => ['task_id' => 777]]);
    });
    $export = marketplaceExport('ozon');
    $export->update(['payload' => [
        ...$export->payload,
        'attributes' => ['Код классификации' => '6506.10.0000'],
    ]]);

    app(OzonCardCreator::class)->submit($export->refresh());

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v3/product/import')
        && $request['items'][0]['attributes'] === [[
            'id' => 700,
            'complex_id' => 0,
            'values' => [[
                'value' => '6506100000',
                'dictionary_value_id' => 701,
            ]],
        ]]);
});

test('drops unmatched optional dictionary values and keeps valid collection values', function (): void {
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/v1/description-category/attribute')) {
            return Http::response(['result' => [[
                'id' => 21845,
                'name' => 'Дополнительные свойства',
                'type' => 'String',
                'is_required' => true,
                'dictionary_id' => 10,
                'is_collection' => true,
            ], [
                'id' => 21846,
                'name' => 'Опциональный справочник',
                'type' => 'String',
                'is_required' => false,
                'dictionary_id' => 11,
            ]]]);
        }

        if (str_ends_with($request->url(), '/v1/description-category/attribute/values/search')) {
            return Http::response(['result' => $request['value'] === 'Гладкая поверхность'
                ? [['id' => 99, 'value' => 'Гладкая поверхность']]
                : [['id' => 100, 'value' => 'Устойчивость к механическим воздействиям']]]);
        }

        return Http::response(['result' => ['task_id' => 777]]);
    });
    $export = marketplaceExport('ozon');
    $export->update(['payload' => [
        ...$export->payload,
        'attributes' => [
            'Дополнительные свойства' => 'Гладкая поверхность, защита от повреждений',
            'Опциональный справочник' => 'Выдуманное значение',
        ],
    ]]);

    app(OzonCardCreator::class)->submit($export->refresh());

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v3/product/import')
        && $request['items'][0]['attributes'] === [[
            'id' => 21845,
            'complex_id' => 0,
            'values' => [[
                'value' => 'Гладкая поверхность',
                'dictionary_value_id' => 99,
            ]],
        ]]);
});

test('reports all unmatched required dictionary values with Ozon suggestions', function (): void {
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/v1/description-category/attribute')) {
            return Http::response(['result' => [
                ['id' => 1, 'name' => 'Аудитория', 'type' => 'String', 'is_required' => true, 'dictionary_id' => 10],
                ['id' => 2, 'name' => 'Свойства', 'type' => 'String', 'is_required' => true, 'dictionary_id' => 11],
            ]]);
        }

        if (str_ends_with($request->url(), '/v1/description-category/attribute/values/search')) {
            return Http::response(['result' => $request['attribute_id'] === 1
                ? [['id' => 10, 'value' => 'Для взрослых']]
                : [['id' => 11, 'value' => 'Устойчивость к механическим воздействиям']]]);
        }

        return Http::response(['result' => ['task_id' => 777]]);
    });
    $export = marketplaceExport('ozon');
    $export->update(['payload' => [
        ...$export->payload,
        'attributes' => [
            'Аудитория' => 'Рабочие',
            'Свойства' => 'защита от механических повреждений',
        ],
    ]]);

    try {
        app(OzonCardCreator::class)->submit($export->refresh());
        test()->fail('Expected Ozon dictionary validation to fail.');
    } catch (MarketplaceCardCreationException $exception) {
        expect($exception->getMessage())
            ->toContain('«Аудитория»', 'Для взрослых')
            ->toContain('«Свойства»', 'Устойчивость к механическим воздействиям');
    }

    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/v3/product/import'));
});

test('deduplicates and caches Ozon dictionary searches', function (): void {
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/v1/description-category/attribute')) {
            return Http::response(['result' => [[
                'id' => 50,
                'name' => 'Цвета',
                'type' => 'String',
                'is_required' => false,
                'dictionary_id' => 12,
                'is_collection' => true,
            ]]]);
        }

        if (str_ends_with($request->url(), '/v1/description-category/attribute/values/search')) {
            return Http::response(['result' => [['id' => 51, 'value' => 'Красный']]]);
        }

        return Http::response(['result' => ['task_id' => 777]]);
    });
    $export = marketplaceExport('ozon');
    $export->update(['payload' => [
        ...$export->payload,
        'attributes' => ['Цвета' => 'Красный, красный'],
    ]]);
    $creator = app(OzonCardCreator::class);

    $creator->submit($export->refresh());
    $creator->submit($export->refresh());

    Http::assertSentCount(5);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v3/product/import')
        && count($request['items'][0]['attributes'][0]['values']) === 1);
});

test('creates a wildberries card then publishes its price and images', function (): void {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/content/v2/object/charcs/')) {
            return Http::response(['data' => [
                ['charcID' => 10, 'name' => 'Бренд', 'charcType' => 1, 'required' => true],
                ['charcID' => 11, 'name' => 'Материал', 'charcType' => 1, 'required' => true],
                ['charcID' => 12, 'name' => 'Наименование', 'charcType' => 1, 'required' => true],
                ['charcID' => 88952, 'name' => 'Масса брутто', 'charcType' => 4, 'required' => true],
            ]]);
        }

        if (str_ends_with($request->url(), '/content/v2/cards/upload')) {
            return Http::response(['error' => false]);
        }

        if (str_ends_with($request->url(), '/content/v2/get/cards/list')) {
            return Http::response(['cards' => [['vendorCode' => 'MUG-001', 'nmID' => 999]]]);
        }

        return Http::response(['error' => false]);
    });
    $export = marketplaceExport('wildberries');
    $creator = app(WildberriesCardCreator::class);

    expect($creator->submit($export)->status)->toBe(MarketplaceCardCreationResult::WAITING);
    $found = $creator->check($export);
    $export->update(['external_product_id' => $found->externalProductId]);
    $completed = $creator->check($export->refresh());

    expect($found->externalProductId)->toBe('999')
        ->and($completed->status)->toBe(MarketplaceCardCreationResult::COMPLETED);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/content/v2/cards/upload')
        && $request[0]['variants'][0]['brand'] === 'ZARQ'
        && $request[0]['variants'][0]['characteristics'] === [
            ['id' => 10, 'value' => ['ZARQ']],
            ['id' => 11, 'value' => ['Сталь']],
        ]
        && $request[0]['variants'][0]['dimensions'] === [
            'length' => 10.0,
            'width' => 10.0,
            'height' => 20.0,
            'weightBrutto' => 0.4,
        ]);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/v2/upload/task')
        && $request['data'][0]['nmID'] === 999
        && $request['data'][0]['price'] === 1990
        && $request['data'][0]['discount'] === 25);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/content/v3/media/save')
        && $request['nmId'] === 999
        && $request['data'] === ['https://storage.test/cards/source.png']);
});

test('publishes and completes a marketplace export through queue jobs', function (): void {
    Queue::fake();
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/v1/description-category/attribute')) {
            return Http::response(['result' => [
                ['id' => 10, 'name' => 'Бренд', 'type' => 'String', 'is_required' => true],
            ]]);
        }

        if (str_ends_with($request->url(), '/v3/product/import')) {
            return Http::response(['result' => ['task_id' => 777]]);
        }

        return Http::response([
            'result' => ['items' => [[
                'status' => 'imported',
                'product_id' => 888,
                'errors' => [],
            ]]],
        ]);
    });
    $export = marketplaceExport('ozon');
    $marketplaces = app(MarketplaceCardCreationService::class);

    (new PublishMarketplaceCard($export->getKey()))->handle($marketplaces);

    expect($export->refresh()->status)->toBe(CardExport::STATUS_WAITING)
        ->and($export->external_task_id)->toBe('777');
    Queue::assertPushed(CheckMarketplaceCardPublication::class, 1);

    (new CheckMarketplaceCardPublication($export->getKey()))->handle($marketplaces);

    expect($export->refresh()->status)->toBe(CardExport::STATUS_COMPLETED)
        ->and($export->external_product_id)->toBe('888')
        ->and($export->completed_at)->not->toBeNull()
        ->and($export->generation->card->refresh()->is_exported)->toBeTrue();
});
