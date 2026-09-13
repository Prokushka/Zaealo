<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Contracts\MarketplaceCardCreator;
use App\Data\MarketplaceCardCreationResult;
use App\Exceptions\MarketplaceCardCreationException;
use App\Models\CardExport;
use App\Models\MarketplaceApiKey;
use App\Services\MarketplaceApiService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class WildberriesCardCreator implements MarketplaceCardCreator
{
    private const PACKAGING_WEIGHT_ATTRIBUTE_ID = 88952;

    public function __construct(private MarketplaceApiService $marketplaceApi) {}

    public function submit(CardExport $export): MarketplaceCardCreationResult
    {
        $payload = $export->payload;
        $values = $this->normalizedValues($payload['attributes'] ?? []);
        $size = array_filter([
            'techSize' => $this->firstValue($values, ['размер производителя', 'techsize']),
            'wbSize' => $this->firstValue($values, ['российский размер', 'wbsize']),
            'skus' => filled($this->firstValue($values, ['штрихкод', 'barcode']))
                ? [(string) $this->firstValue($values, ['штрихкод', 'barcode'])]
                : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
        $variant = [
            'vendorCode' => (string) $payload['seller_sku'],
            'title' => (string) $payload['title'],
            'description' => (string) ($payload['description'] ?? ''),
            'brand' => (string) ($this->firstValue($values, ['бренд', 'brand']) ?? ''),
            'dimensions' => $this->dimensions($values),
            'characteristics' => $this->characteristics($export),
            'sizes' => $size === [] ? [] : [$size],
        ];

        try {
            $response = $this->client($this->apiKey($export))
                ->post('/content/v2/cards/upload', [[
                    'subjectID' => (int) $payload['category_id'],
                    'variants' => [$variant],
                ]])
                ->throw();
        } catch (RequestException $exception) {
            throw new MarketplaceCardCreationException(
                $this->requestError($exception, 'Wildberries не принял карточку.'),
                previous: $exception,
            );
        }

        if ($response->json('error') === true) {
            $error = $response->json('errorText');

            throw new MarketplaceCardCreationException(
                is_string($error) && $error !== '' ? "Wildberries не принял карточку: {$error}" : 'Wildberries не принял карточку.',
            );
        }

        return MarketplaceCardCreationResult::waiting();
    }

    public function check(CardExport $export): MarketplaceCardCreationResult
    {
        if (filled($export->external_product_id)) {
            return $this->uploadImages($export, (string) $export->external_product_id);
        }

        try {
            $response = $this->client($this->apiKey($export))
                ->post('/content/v2/get/cards/list', [
                    'settings' => [
                        'sort' => ['ascending' => false],
                        'filter' => [
                            'textSearch' => (string) $export->payload['seller_sku'],
                            'withPhoto' => -1,
                        ],
                        'cursor' => ['limit' => 100],
                    ],
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new MarketplaceCardCreationException(
                $this->requestError($exception, 'Не удалось проверить создание карточки Wildberries.'),
                previous: $exception,
            );
        }

        $sellerSku = (string) $export->payload['seller_sku'];
        $card = collect($response->json('cards', []))
            ->first(fn (mixed $card): bool => is_array($card)
                && (string) ($card['vendorCode'] ?? '') === $sellerSku);

        if (! is_array($card)) {
            return MarketplaceCardCreationResult::waiting();
        }

        $nmId = $card['nmID'] ?? null;

        if (! is_int($nmId) && ! is_string($nmId)) {
            return MarketplaceCardCreationResult::waiting();
        }

        return MarketplaceCardCreationResult::waiting(externalProductId: (string) $nmId);
    }

    private function uploadImages(CardExport $export, string $nmId): MarketplaceCardCreationResult
    {
        $this->publishPrice($export, $nmId);

        $urls = collect($export->payload['images'] ?? [])->map(function (mixed $image): string {
            $path = is_array($image) ? ($image['path'] ?? null) : null;

            if (! is_string($path) || ! Storage::disk('s3')->exists($path)) {
                throw new MarketplaceCardCreationException('Не найден файл изображения для Wildberries.');
            }

            return Storage::disk('s3')->temporaryUrl($path, now()->addDay());
        })->values()->all();

        if ($urls === []) {
            return MarketplaceCardCreationResult::failed('В карточке нет изображений для Wildberries.');
        }

        try {
            $response = $this->client($this->apiKey($export))
                ->post('/content/v3/media/save', ['nmId' => (int) $nmId, 'data' => $urls])
                ->throw();
        } catch (RequestException $exception) {
            throw new MarketplaceCardCreationException(
                $this->requestError($exception, 'Не удалось загрузить фотографии в Wildberries.'),
                previous: $exception,
            );
        }

        if ($response->json('error') === true) {
            $error = $response->json('errorText');

            return MarketplaceCardCreationResult::failed(
                is_string($error) && $error !== '' ? "Wildberries отклонил фотографии: {$error}" : 'Wildberries отклонил фотографии.',
            );
        }

        return MarketplaceCardCreationResult::completed($nmId);
    }

    private function publishPrice(CardExport $export, string $nmId): void
    {
        $values = $this->normalizedValues($export->payload['attributes'] ?? []);
        $rawPrice = $this->firstValue($values, ['цена', 'цена, ₽', 'price']);

        if (! is_numeric($rawPrice)) {
            return;
        }

        $price = (float) $rawPrice;
        $oldPrice = $this->firstValue($values, ['старая цена', 'старая цена, ₽', 'old_price']);
        $basePrice = is_numeric($oldPrice)
            ? (float) $oldPrice
            : $price;
        $discount = $basePrice > $price
            ? min(99, (int) round((1 - ($price / $basePrice)) * 100))
            : 0;

        try {
            $response = Http::baseUrl('https://discounts-prices-api.wildberries.ru')
                ->acceptJson()
                ->withToken($this->apiKey($export)->api_key)
                ->connectTimeout(5)
                ->timeout(60)
                ->retry([500, 1500, 3000], throw: false)
                ->post('/api/v2/upload/task', [
                    'data' => [[
                        'nmID' => (int) $nmId,
                        'price' => (int) round($basePrice),
                        'discount' => $discount,
                    ]],
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new MarketplaceCardCreationException(
                $this->requestError($exception, 'Не удалось установить цену карточки Wildberries.'),
                previous: $exception,
            );
        }

        if ($response->json('error') === true) {
            $error = $response->json('errorText');

            throw new MarketplaceCardCreationException(
                is_string($error) && $error !== ''
                    ? "Wildberries не принял цену: {$error}"
                    : 'Wildberries не принял цену карточки.',
            );
        }
    }

    private function apiKey(CardExport $export): MarketplaceApiKey
    {
        $export->loadMissing('generation.card.user');
        $apiKey = $export->generation->card->user->marketplaceApiKeys()
            ->where('marketplace', 'wildberries')
            ->first();

        if ($apiKey === null || ! filled($apiKey->api_key)) {
            throw new MarketplaceCardCreationException('Подключите действующий API-ключ Wildberries.');
        }

        return $apiKey;
    }

    private function client(MarketplaceApiKey $apiKey): PendingRequest
    {
        return Http::baseUrl('https://content-api.wildberries.ru')
            ->acceptJson()
            ->withToken($apiKey->api_key)
            ->connectTimeout(5)
            ->timeout(60)
            ->retry([500, 1500, 3000], throw: false);
    }

    /** @return list<array{id: int, value: mixed}> */
    private function characteristics(CardExport $export): array
    {
        $schema = $this->marketplaceApi->attributeSchema($export->generation);
        $values = collect($export->payload['attributes'] ?? [])
            ->mapWithKeys(fn (mixed $value, mixed $key): array => [Str::lower((string) $key) => $value]);

        return collect($schema)->map(function (array $attribute) use ($values): ?array {
            if ($this->isSeoField($attribute['key'])
                || (int) $attribute['id'] === self::PACKAGING_WEIGHT_ATTRIBUTE_ID
                || $this->isPackagingDimension($attribute['key'])) {
                return null;
            }

            $value = $values->get(Str::lower($attribute['key']));

            if (! filled($value)) {
                if ($attribute['required']) {
                    throw new MarketplaceCardCreationException("Заполните обязательную характеристику Wildberries «{$attribute['key']}».");
                }

                return null;
            }

            if (is_string($value) && is_numeric($value)) {
                $value = str_contains($value, '.') ? (float) $value : (int) $value;
            } elseif (is_string($value)) {
                $value = collect(explode(',', $value))->map(fn (string $part): string => trim($part))->filter()->values()->all();
            }

            return ['id' => (int) $attribute['id'], 'value' => $value];
        })->filter()->values()->all();
    }

    private function requestError(RequestException $exception, string $fallback): string
    {
        $message = $exception->response?->json('errorText')
            ?? $exception->response?->json('message');

        return is_string($message) && $message !== '' ? "{$fallback} {$message}" : $fallback;
    }

    /** @param array<string, mixed> $attributes */
    private function normalizedValues(array $attributes): array
    {
        return collect($attributes)
            ->mapWithKeys(fn (mixed $value, mixed $key): array => [$this->normalizedKey((string) $key) => $value])
            ->all();
    }

    /** @param array<string, mixed> $values */
    private function firstValue(array $values, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $values[$this->normalizedKey($key)] ?? null;

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{length: float, width: float, height: float, weightBrutto: float}
     */
    private function dimensions(array $values): array
    {
        $dimensions = [
            'length' => $this->measurement($values, ['длина упаковки', 'длина с упаковкой', 'длина'], 'length'),
            'width' => $this->measurement($values, ['ширина упаковки', 'ширина с упаковкой', 'ширина'], 'length'),
            'height' => $this->measurement($values, ['высота упаковки', 'высота с упаковкой', 'высота'], 'length'),
            'weightBrutto' => $this->measurement($values, ['вес товара с упаковкой', 'вес с упаковкой', 'вес упаковки', 'вес брутто', 'вес'], 'weight'),
        ];

        if (in_array(null, $dimensions, true)) {
            throw new MarketplaceCardCreationException(
                'Для Wildberries заполните на шаге «Категория» длину, ширину, высоту и вес с упаковкой.',
            );
        }

        return $dimensions;
    }

    private function normalizedKey(string $key): string
    {
        return (string) Str::of($key)
            ->lower()
            ->replace('ё', 'е')
            ->replaceMatches('/[^\p{L}\p{N}]+/u', ' ')
            ->squish();
    }

    /** @param array<string, mixed> $values */
    private function measurement(array $values, array $names, string $type): ?float
    {
        foreach ($names as $name) {
            $normalizedName = $this->normalizedKey($name);

            foreach ($values as $key => $value) {
                if (! Str::contains($key, $normalizedName)) {
                    continue;
                }

                $numericValue = $this->numericValue($value);

                if ($numericValue === null) {
                    continue;
                }

                $valueUnit = is_string($value) ? $this->normalizedKey($value) : '';

                if ($type === 'weight' && (
                    Str::contains($key, [' грамм', ' гр '])
                    || Str::endsWith($key, ' г')
                    || Str::contains($valueUnit, [' грамм', ' гр '])
                    || Str::endsWith($valueUnit, ' г')
                )) {
                    return $numericValue / 1000;
                }

                if ($type === 'length' && (
                    Str::contains($key, ' миллиметр')
                    || Str::endsWith($key, ' мм')
                    || Str::contains($valueUnit, ' миллиметр')
                    || Str::endsWith($valueUnit, ' мм')
                )) {
                    return $numericValue / 10;
                }

                return $numericValue;
            }
        }

        return null;
    }

    private function numericValue(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        preg_match('/[-+]?\d+(?:[.,]\d+)?/u', $value, $matches);

        return isset($matches[0])
            ? (float) str_replace(',', '.', $matches[0])
            : null;
    }

    private function isPackagingDimension(string $key): bool
    {
        $key = $this->normalizedKey($key);

        return (Str::contains($key, 'вес') && Str::contains($key, 'упаков'))
            || Str::contains($key, [
                'вес с упаковкой',
                'вес упаковки',
                'вес брутто',
                'длина упаковки',
                'ширина упаковки',
                'высота упаковки',
            ]);
    }

    private function isSeoField(string $key): bool
    {
        return in_array($this->normalizedKey($key), ['наименование', 'наименование товара', 'описание'], true);
    }
}
