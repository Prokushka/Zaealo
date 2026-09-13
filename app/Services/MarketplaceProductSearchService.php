<?php

namespace App\Services;

use DiDom\Document;
use DiDom\Element;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use RuntimeException;

class MarketplaceProductSearchService
{
    public function __construct(private ZenRowsService $zenRows) {}

    /** @return array<string, mixed> */
    public function fetchDonorAttributes(string $marketplace, string $donorUrlOrArticle): array
    {
        return match ($marketplace) {
            'ozon' => $this->ozonDonorAttributes($donorUrlOrArticle),
            'wildberries' => $this->wildberriesDonorAttributes($donorUrlOrArticle),
            default => throw new RuntimeException('Неподдерживаемый маркетплейс для карточки-донора.'),
        };
    }

    /** @return array<string, mixed> */
    private function ozonDonorAttributes(string $donorUrlOrArticle): array
    {
        $url = $this->ozonProductUrl($donorUrlOrArticle);
        $productId = Str::match('/-(\d+)(?:\/|\?|$)/', $url);

        if ($productId === '') {
            throw new RuntimeException('Не удалось определить артикул карточки Ozon.');
        }

        return $this->attributeMapFromProductData(
            $this->productPageJsonResponses($this->requestOzonProductData($url), $productId),
        );
    }

    /** @return array<string, mixed> */
    private function wildberriesDonorAttributes(string $donorUrlOrArticle): array
    {
        $productId = $this->wildberriesProductId($donorUrlOrArticle);
        $cardDataUrl = $this->wildberriesCardDataUrl($productId);
        $rawCardData = $this->zenRows->fetch($cardDataUrl, [
            'premium_proxy' => 'true',
            'proxy_country' => 'ru',
        ])->json();

        if (! is_array($rawCardData['options'] ?? null)) {
            throw new RuntimeException("Wildberries вернул некорректный JSON карточки {$productId}.");
        }

        return $this->attributeMapFromProductData([[
            'source' => 'wildberries_card_json',
            'url' => $cardDataUrl,
            'body' => $rawCardData,
        ]]);
    }

    private function ozonProductUrl(string $donorUrlOrArticle): string
    {
        $value = trim($donorUrlOrArticle);

        if (ctype_digit($value)) {
            $searchUrl = 'https://www.ozon.ru/search/?'.http_build_query(['text' => $value]);
            $document = new Document($this->requestOzonThroughZenRows($searchUrl)->body(), false, 'UTF-8');

            foreach ($document->find('a[href*="/product/"]') as $link) {
                $href = $link->attr('href');

                if (Str::match('/-(\d+)(?:\/|\?|$)/', $href ?? '') === $value) {
                    return $this->absoluteUrl('https://www.ozon.ru', $href);
                }
            }

            throw new RuntimeException('Карточка Ozon с указанным артикулом не найдена.');
        }

        $host = Str::lower((string) parse_url($value, PHP_URL_HOST));

        if (! ($host === 'ozon.ru' || Str::endsWith($host, '.ozon.ru'))) {
            throw new RuntimeException('Укажите ссылку на карточку Ozon или её числовой артикул.');
        }

        return $value;
    }

    private function wildberriesProductId(string $donorUrlOrArticle): int
    {
        $value = trim($donorUrlOrArticle);

        if (ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        $host = Str::lower((string) parse_url($value, PHP_URL_HOST));

        if (! ($host === 'wildberries.ru' || Str::endsWith($host, '.wildberries.ru'))) {
            throw new RuntimeException('Укажите ссылку на карточку Wildberries или её числовой артикул.');
        }

        $productId = Str::match('#/catalog/(\d+)(?:/|$)#', $value);

        if ($productId === '' || (int) $productId <= 0) {
            throw new RuntimeException('Не удалось определить артикул карточки Wildberries.');
        }

        return (int) $productId;
    }

    /** @return array<int, array<string, mixed>> */
    public function searchOzon(string $marketplaceSearchQuery): array
    {
        $searchUrl = 'https://www.ozon.ru/search/?'.http_build_query(['text' => $marketplaceSearchQuery]);
        $document = new Document(
            $this->requestOzonThroughZenRows($searchUrl)->body(),
            false,
            'UTF-8',
        );
        $products = [];

        foreach ($document->find('a[href*="/product/"]') as $link) {
            $href = $link->attr('href');
            $productId = Str::match('/-(\d+)(?:\/|\?|$)/', $href ?? '');

            if ($productId === '' || isset($products[$productId])) {
                continue;
            }

            $card = $this->closestProductCard($link);
            $products[$productId] = [
                'id' => $productId,
                'title' => $this->ozonTitle($link),
                'url' => $this->absoluteUrl('https://www.ozon.ru', $href),
                'text' => Str::squish($card->text()),
                'html' => $card->html(),
                'link_attributes' => $link->attributes(),
                'images' => $this->extractImages($card, 'https://www.ozon.ru'),
            ];

            if (count($products) === 3) {
                break;
            }
        }

        return collect(array_values($products))
            ->map(function (array $product): array {
                $response = $this->requestOzonProductData($product['url']);
                $productData = $this->productPageJsonResponses($response, (string) $product['id']);

                return [
                    ...$product,
                    'characteristics' => $this->extractCharacteristicBlocks($productData),
                    'product_data' => $productData,
                ];
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function searchWildberries(string $marketplaceSearchQuery): array
    {
        $searchUrl = config('services.wildberries.search_url').'?'.http_build_query([
            'ab_testing' => 'false',
            'appType' => 1,
            'curr' => 'rub',
            'dest' => -1257786,
            'lang' => 'ru',
            'page' => 1,
            'query' => $marketplaceSearchQuery,
            'resultset' => 'catalog',
            'sort' => 'popular',
            'spp' => 30,
            'suppressSpellcheck' => 'false',
        ]);
        $response = $this->zenRows->fetch($searchUrl, [
            'premium_proxy' => 'true',
            'proxy_country' => 'ru',
        ]);
        $products = $response->json('data.products') ?? $response->json('products');

        if (! is_array($products)) {
            throw new RuntimeException('Wildberries вернул JSON без массива товаров.');
        }

        return collect($products)
            ->take(10)
            ->map(fn (array $product): ?array => $this->wildberriesProductData($product))
            ->filter()
            ->take(3)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>|null
     */
    private function wildberriesProductData(array $product): ?array
    {
        $productId = (string) $product['id'];
        $cardDataUrl = $this->wildberriesCardDataUrl((int) $productId);

        try {
            $response = $this->zenRows->fetch($cardDataUrl, [
                'premium_proxy' => 'true',
                'proxy_country' => 'ru',
            ]);
        } catch (RequestException $exception) {
            if ($exception->response->notFound()) {
                return null;
            }

            throw $exception;
        }

        $rawCardData = $response->json();
        $cardData = $rawCardData['options'] ?? null;

        if (! is_array($cardData)) {
            throw new RuntimeException("Wildberries вернул некорректный JSON карточки {$productId}.");
        }

        $productData = [[
            'source' => 'wildberries_card_json',
            'url' => $cardDataUrl,
            'body' => $rawCardData,
        ]];

        return [
            ...$product,
            'url' => "https://www.wildberries.ru/catalog/{$productId}/detail.aspx",
            'card_data_url' => $cardDataUrl,
            'product_data' => $productData,
            'characteristics' => $this->extractCharacteristicBlocks($productData),
        ];
    }

    private function wildberriesCardDataUrl(int $productId): string
    {
        $volume = intdiv($productId, 100000);
        $part = intdiv($productId, 1000);
        $basket = match (true) {
            $volume < 144 => 1,
            $volume < 288 => 2,
            $volume < 432 => 3,
            $volume < 720 => 4,
            $volume < 1008 => 5,
            $volume < 1062 => 6,
            $volume < 1116 => 7,
            $volume < 1170 => 8,
            $volume < 1314 => 9,
            $volume < 1602 => 10,
            $volume < 1656 => 11,
            $volume < 1920 => 12,
            $volume < 2046 => 13,
            $volume < 2190 => 14,
            $volume < 2406 => 15,
            $volume < 2622 => 16,
            $volume < 2838 => 17,
            $volume < 3054 => 18,
            $volume < 3270 => 19,
            default => 20 + intdiv($volume - 3270, 216),
        };
        $basketHost = str_pad((string) $basket, 2, '0', STR_PAD_LEFT);

        return "https://basket-{$basketHost}.wbbasket.ru/vol{$volume}/part{$part}/{$productId}/info/ru/card.json";
    }

    /** @return array<int, array<string, mixed>> */
    private function productPageJsonResponses(Response $response, string $productId): array
    {
        return [
            ...$this->capturedJsonResponses($response, $productId),
            ...$this->embeddedJsonResponses($response),
        ];
    }

    private function requestOzonProductData(string $url): Response
    {
        $strategies = [
            [
                'js_render' => 'true',
                'json_response' => 'true',
                'premium_proxy' => 'true',
                'wait' => 5000,
            ],
            [
                'js_render' => 'true',
                'json_response' => 'true',
                'premium_proxy' => 'true',
                'proxy_country' => 'ru',
                'wait' => 5000,
            ],
        ];
        $lastException = null;

        foreach ($strategies as $parameters) {
            try {
                return $this->zenRows->fetch($url, $parameters);
            } catch (RequestException $exception) {
                if ($exception->response->status() !== 422) {
                    throw $exception;
                }

                $lastException = $exception;
            }
        }

        throw $lastException ?? new RuntimeException('ZenRows не смог загрузить карточку Ozon.');
    }

    /** @return array<int, array<string, mixed>> */
    private function capturedJsonResponses(Response $response, string $productId): array
    {
        $capturedRequests = $response->json('xhr') ?? $response->json('xhr_requests') ?? [];

        if (! is_array($capturedRequests)) {
            throw new RuntimeException('ZenRows вернул ответ без списка XHR-запросов.');
        }

        $jsonResponses = collect($capturedRequests)
            ->filter(fn (mixed $request): bool => is_array($request))
            ->map(function (array $request): ?array {
                $body = $request['body'] ?? null;

                if (is_string($body)) {
                    $body = json_decode($body, true);
                }

                if (! is_array($body)) {
                    return null;
                }

                return [
                    'url' => $request['url'] ?? null,
                    'method' => $request['method'] ?? null,
                    'status_code' => $request['status_code'] ?? null,
                    'body' => $body,
                ];
            })
            ->filter()
            ->values();

        $productResponses = $jsonResponses
            ->filter(function (array $request) use ($productId): bool {
                if (str_contains((string) $request['url'], $productId)) {
                    return true;
                }

                return str_contains(
                    (string) json_encode($request['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $productId,
                );
            })
            ->values();

        return ($productResponses->isNotEmpty() ? $productResponses : $jsonResponses)->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function embeddedJsonResponses(Response $response): array
    {
        $html = $response->json('html');

        if (! is_string($html) || $html === '') {
            return [];
        }

        $document = new Document($html, false, 'UTF-8');

        return collect($document->find('script'))
            ->filter(function (Element $script): bool {
                $type = mb_strtolower($script->attr('type') ?? '');

                return $script->attr('id') === '__NEXT_DATA__'
                    || str_contains($type, 'json');
            })
            ->map(function (Element $script): ?array {
                $body = json_decode(
                    html_entity_decode(trim($script->text()), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    true,
                );

                if (! is_array($body)) {
                    return null;
                }

                return [
                    'source' => 'embedded_json',
                    'script_id' => $script->attr('id'),
                    'script_type' => $script->attr('type'),
                    'body' => $body,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<int, array{key: string, data: array<mixed>}> */
    private function extractCharacteristicBlocks(array $productData): array
    {
        $blocks = [];

        foreach ($productData as $response) {
            $this->collectCharacteristicBlocks($response['body'] ?? [], $blocks);
        }

        return collect($blocks)
            ->unique(fn (array $block): string => $block['key'].'|'.json_encode(
                $block['data'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $productData
     * @return array<string, mixed>
     */
    private function attributeMapFromProductData(array $productData): array
    {
        $attributes = [];

        foreach ($this->extractCharacteristicBlocks($productData) as $block) {
            $this->collectNameValuePairs($block['data'], $attributes);
        }

        return $attributes;
    }

    /** @param array<string, mixed> $attributes */
    private function collectNameValuePairs(mixed $value, array &$attributes): void
    {
        if (! is_array($value)) {
            return;
        }

        $name = $value['name'] ?? null;

        if (is_string($name) && $name !== '' && array_key_exists('value', $value)) {
            $attributes[Str::squish($name)] = $value['value'];
        }

        foreach ($value as $nestedValue) {
            $this->collectNameValuePairs($nestedValue, $attributes);
        }
    }

    /**
     * @param  array<int, array{key: string, data: array<mixed>}>  $blocks
     */
    private function collectCharacteristicBlocks(mixed $value, array &$blocks): void
    {
        if (is_string($value)) {
            $decodedValue = json_decode($value, true);

            if (is_array($decodedValue)) {
                $this->collectCharacteristicBlocks($decodedValue, $blocks);
            }

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $nestedValue) {
            if (is_string($key)
                && is_array($nestedValue)
                && preg_match('/characteristic|attribute|option|specification|propert|feature/iu', $key) === 1) {
                $blocks[] = [
                    'key' => $key,
                    'data' => $nestedValue,
                ];
            }

            $this->collectCharacteristicBlocks($nestedValue, $blocks);
        }
    }

    private function requestOzonThroughZenRows(string $url): Response
    {
        $strategies = [
            'js_render' => 'true',
            'premium_proxy' => 'true',
            'proxy_country' => 'ru',
            'wait' => 5000,
        ];
        $lastException = null;

        try {
            return $this->zenRows->fetch($url, $strategies);
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 422) {
                throw $exception;
            }

            $lastException = $exception;
        }
        throw $lastException ?? new RuntimeException('ZenRows не смог загрузить страницу Ozon.');
    }

    private function closestProductCard(Element $link): Element
    {
        $element = $link;

        while ($element->parent() instanceof Element) {
            $parent = $element->parent();

            if (in_array($parent->tagName(), ['article', 'li'], true)
                || $parent->hasAttribute('data-index')) {
                return $parent;
            }

            $element = $parent;
        }

        return $link;
    }

    private function ozonTitle(Element $link): string
    {
        $text = Str::squish($link->text());

        if ($text !== '') {
            return $text;
        }

        return Str::squish($link->attr('aria-label') ?? $link->attr('title') ?? '');
    }

    /** @return array<int, array<string, mixed>> */
    private function extractImages(Element $card, string $baseUrl): array
    {
        return collect($card->find('img'))
            ->map(function (Element $image) use ($baseUrl): array {
                $source = $image->attr('src') ?? $image->attr('data-src') ?? '';

                return [
                    ...$image->attributes(),
                    'src' => $this->absoluteUrl($baseUrl, $source),
                ];
            })
            ->filter(fn (array $image): bool => $image['src'] !== '')
            ->values()
            ->all();
    }

    private function absoluteUrl(string $baseUrl, ?string $url): string
    {
        if (! is_string($url) || $url === '') {
            return '';
        }

        if (Str::startsWith($url, '//')) {
            return 'https:'.$url;
        }

        return Str::startsWith($url, 'http') ? $url : $baseUrl.'/'.ltrim($url, '/');
    }
}
