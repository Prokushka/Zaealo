<?php

use App\Services\MarketplaceProductSearchService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('services.zenrows.key', 'zenrows-test-key');
    config()->set('services.zenrows.base_url', 'https://api.zenrows.test/v1/');
    Http::preventStrayRequests();
});

test('it extracts the first three ozon cards from a zenrows document', function () {
    Http::fake(function (Request $request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $zenRowsQuery);
        $targetUrl = $zenRowsQuery['url'] ?? '';

        if (str_contains($targetUrl, '/search/')) {
            return Http::response(<<<'HTML'
            <html><body><ul>
                <li data-index="1"><a href="/product/kruzhka-101/" data-widget="tile">Кружка 1</a><span>1 500 ₽</span><img src="//cdn.ozon.test/101.jpg" alt="Кружка 1"></li>
                <li data-index="2"><a href="/product/termos-202/">Термос 2</a></li>
                <li data-index="3"><a href="/product/butylka-303/">Бутылка 3</a></li>
                <li data-index="4"><a href="/product/stakan-404/">Стакан 4</a></li>
            </ul></body></html>
            HTML);
        }

        $productId = Str::match('/-(\d+)\//', $targetUrl);

        return Http::response([
            'html' => '<html><body><script id="__NEXT_DATA__" type="application/json">{"props":{"product":{"id":"'.$productId.'","attributes":[{"name":"Объём","value":"500 мл"}]}}}</script></body></html>',
            'xhr' => [
                [
                    'url' => "https://api.ozon.test/product/{$productId}",
                    'method' => 'GET',
                    'status_code' => 200,
                    'body' => json_encode([
                        'productId' => $productId,
                        'characteristics' => [['name' => 'Материал', 'value' => 'Сталь']],
                    ]),
                ],
                [
                    'url' => 'https://analytics.ozon.test/event',
                    'method' => 'POST',
                    'status_code' => 200,
                    'body' => '{"event":"view"}',
                ],
            ],
        ]);
    });

    $products = app(MarketplaceProductSearchService::class)->searchOzon('термокружка');

    expect($products)->toHaveCount(3)
        ->and($products[0])->toMatchArray([
            'id' => '101',
            'title' => 'Кружка 1',
            'url' => 'https://www.ozon.ru/product/kruzhka-101/',
            'text' => 'Кружка 11 500 ₽',
            'link_attributes' => [
                'href' => '/product/kruzhka-101/',
                'data-widget' => 'tile',
            ],
        ])
        ->and($products[0]['html'])->toContain('1 500 ₽')
        ->and($products[0]['images'][0]['src'])->toBe('https://cdn.ozon.test/101.jpg')
        ->and($products[0]['product_data'])->toHaveCount(2)
        ->and($products[0]['product_data'][0]['body']['characteristics'][0])->toBe([
            'name' => 'Материал',
            'value' => 'Сталь',
        ])
        ->and($products[0]['characteristics'])->toContain([
            'key' => 'characteristics',
            'data' => [['name' => 'Материал', 'value' => 'Сталь']],
        ])
        ->and($products[0]['characteristics'])->toContain([
            'key' => 'attributes',
            'data' => [['name' => 'Объём', 'value' => '500 мл']],
        ]);

    Http::assertSentCount(4);
});

test('it extracts the first three wildberries cards from zenrows json', function () {
    config()->set('services.wildberries.search_url', 'https://search.wb.test/search');
    Http::fake(function (Request $request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $zenRowsQuery);
        $targetUrl = $zenRowsQuery['url'] ?? '';

        if (str_starts_with($targetUrl, 'https://search.wb.test/search')) {
            return Http::response([
                'data' => [
                    'products' => collect(range(501, 504))->map(fn (int $id): array => [
                        'id' => $id,
                        'name' => "Каска {$id}",
                        'brand' => 'Защита',
                        'colors' => [['name' => 'Красный']],
                        'sizes' => [['price' => ['product' => 120000]]],
                        'extended' => ['basicSale' => 15],
                    ])->all(),
                ],
            ]);
        }

        $productId = Str::match('/\/(\d+)\/info\/ru\/card\.json/', $targetUrl);

        if ($productId === '502') {
            return Http::response(['code' => 'RESP002'], 404);
        }

        return Http::response([
            'nm_id' => (int) $productId,
            'options' => [['name' => 'Материал', 'value' => 'Пластик']],
            'grouped_options' => [[
                'group_name' => 'Основные характеристики',
                'options' => [['name' => 'Цвет', 'value' => 'Красный']],
            ]],
        ]);
    });

    $products = app(MarketplaceProductSearchService::class)->searchWildberries('термокружка');

    expect($products)->toHaveCount(3)
        ->and(array_column($products, 'id'))->toBe([501, 503, 504])
        ->and($products[0])->toMatchArray([
            'id' => 501,
            'name' => 'Каска 501',
            'brand' => 'Защита',
            'colors' => [['name' => 'Красный']],
            'sizes' => [['price' => ['product' => 120000]]],
            'extended' => ['basicSale' => 15],
            'url' => 'https://www.wildberries.ru/catalog/501/detail.aspx',
            'card_data_url' => 'https://basket-01.wbbasket.ru/vol0/part0/501/info/ru/card.json',
        ])
        ->and($products[0]['product_data'][0]['body']['options'][0])->toBe([
            'name' => 'Материал',
            'value' => 'Пластик',
        ])
        ->and($products[0]['characteristics'])->toContain([
            'key' => 'options',
            'data' => [['name' => 'Материал', 'value' => 'Пластик']],
        ]);

    Http::assertSentCount(5);
});

test('ozon search uses browser rendering through zenrows', function () {
    Http::fake([
        'api.zenrows.test/*' => Http::response('<html><body></body></html>'),
    ]);

    app(MarketplaceProductSearchService::class)->searchOzon('термокружка');

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.zenrows.test/v1/')
        && str_contains($request->url(), 'js_render=true')
        && str_contains($request->url(), 'premium_proxy=true')
        && str_contains($request->url(), 'proxy_country=ru'));
});

test('ozon reports resp001 when zenrows cannot render the search page', function () {
    Http::fake([
        'api.zenrows.test/*' => Http::sequence()
            ->push(['code' => 'RESP001'], 422),
    ]);

    expect(fn () => app(MarketplaceProductSearchService::class)->searchOzon('термокружка'))
        ->toThrow(RequestException::class);

    Http::assertSentCount(1);
});

test('wildberries requests json through zenrows without browser rendering', function () {
    config()->set('services.wildberries.search_url', 'https://search.wb.test/search');
    Http::fake([
        'api.zenrows.test/*' => Http::response(['data' => ['products' => []]]),
    ]);

    app(MarketplaceProductSearchService::class)->searchWildberries('Каска строительная защитная красная');

    Http::assertSent(function (Request $request): bool {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $zenRowsQuery);
        parse_str(parse_url($zenRowsQuery['url'] ?? '', PHP_URL_QUERY) ?? '', $wildberriesQuery);

        return str_starts_with($request->url(), 'https://api.zenrows.test/v1/')
            && str_starts_with($zenRowsQuery['url'] ?? '', 'https://search.wb.test/search?')
            && ($wildberriesQuery['query'] ?? null) === 'Каска строительная защитная красная'
            && ($zenRowsQuery['premium_proxy'] ?? null) === 'true'
            && ! isset($zenRowsQuery['js_render']);
    });
});

test('it extracts one Wildberries donor card by article', function (): void {
    Http::fake([
        'api.zenrows.test/*' => Http::response([
            'options' => [
                ['name' => 'Материал', 'value' => 'Сталь'],
                ['name' => 'brand', 'value' => 'Не сохранять'],
            ],
        ]),
    ]);

    $attributes = app(MarketplaceProductSearchService::class)
        ->fetchDonorAttributes('wildberries', '123456');

    expect($attributes)->toBe([
        'Материал' => 'Сталь',
        'brand' => 'Не сохранять',
    ]);
});

test('it extracts one Ozon donor card by URL', function (): void {
    Http::fake([
        'api.zenrows.test/*' => Http::response([
            'xhr' => [[
                'url' => 'https://api.ozon.test/product/101',
                'body' => json_encode([
                    'product_id' => 101,
                    'characteristics' => [['name' => 'Материал', 'value' => 'Сталь']],
                ]),
            ]],
        ]),
    ]);

    $attributes = app(MarketplaceProductSearchService::class)
        ->fetchDonorAttributes('ozon', 'https://www.ozon.ru/product/termos-101/');

    expect($attributes)->toBe(['Материал' => 'Сталь']);
});
