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

final class OzonCardCreator implements MarketplaceCardCreator
{
    public function __construct(
        private MarketplaceApiService $marketplaceApi,
        private OzonDictionaryValueResolver $dictionaryValueResolver,
    ) {}

    public function submit(CardExport $export): MarketplaceCardCreationResult
    {
        $export->loadMissing('generation.card.user');
        $apiKey = $this->apiKey($export);
        $payload = $export->payload;
        $imageUrls = $this->imageUrls($payload['images'] ?? []);
        $item = [
            'attributes' => $this->attributes($export, $apiKey),
            'description_category_id' => (int) $payload['category_id'],
            'type_id' => (int) $payload['type_id'],
            'currency_code' => 'RUB',
            'images' => array_values(array_slice($imageUrls, 1)),
            'primary_image' => $imageUrls[0] ?? '',
            'name' => (string) $payload['title'],
            'offer_id' => (string) $payload['seller_sku'],
            'vat' => '0',
        ];
        $item = $this->addCommerceFields($item, $payload['attributes'] ?? []);

        try {
            $response = $this->client($apiKey)
                ->post('/v3/product/import', [
                    'items' => [$item],
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new MarketplaceCardCreationException(
                $this->requestError($exception, 'Ozon не принял карточку.'),
                previous: $exception,
            );
        }

        $taskId = $response->json('result.task_id');

        if (! is_int($taskId) && ! is_string($taskId)) {
            throw new MarketplaceCardCreationException('Ozon не вернул номер задачи создания карточки.');
        }

        return MarketplaceCardCreationResult::waiting((string) $taskId);
    }

    public function check(CardExport $export): MarketplaceCardCreationResult
    {
        if (! filled($export->external_task_id)) {
            return MarketplaceCardCreationResult::failed('Не найден номер задачи Ozon.');
        }

        try {
            $response = $this->client($this->apiKey($export))
                ->post('/v1/product/import/info', ['task_id' => (int) $export->external_task_id])
                ->throw();
        } catch (RequestException $exception) {
            throw new MarketplaceCardCreationException(
                $this->requestError($exception, 'Не удалось проверить создание карточки Ozon.'),
                previous: $exception,
            );
        }

        $item = $response->json('result.items.0');

        if (! is_array($item)) {
            return MarketplaceCardCreationResult::waiting($export->external_task_id);
        }

        $status = Str::lower((string) ($item['status'] ?? ''));
        $productId = $item['product_id'] ?? null;

        if (in_array($status, ['imported', 'processed', 'success'], true)) {
            return MarketplaceCardCreationResult::completed(
                is_int($productId) || is_string($productId) ? (string) $productId : null,
            );
        }

        if (in_array($status, ['failed', 'error'], true) || filled($item['errors'] ?? null)) {
            return MarketplaceCardCreationResult::failed(
                $this->ozonItemError($item),
            );
        }

        return MarketplaceCardCreationResult::waiting($export->external_task_id);
    }

    private function apiKey(CardExport $export): MarketplaceApiKey
    {
        $export->loadMissing('generation.card.user');
        $apiKey = $export->generation->card->user->marketplaceApiKeys()
            ->where('marketplace', 'ozon')
            ->first();

        if ($apiKey === null || ! filled($apiKey->api_key) || ! filled($apiKey->client_id)) {
            throw new MarketplaceCardCreationException('Подключите действующий API-ключ Ozon.');
        }

        return $apiKey;
    }

    private function client(MarketplaceApiKey $apiKey): PendingRequest
    {
        return Http::baseUrl('https://api-seller.ozon.ru')
            ->acceptJson()
            ->withHeaders(['Client-Id' => $apiKey->client_id, 'Api-Key' => $apiKey->api_key])
            ->connectTimeout(5)
            ->timeout(60)
            ->retry([500, 1500, 3000], throw: false);
    }

    /** @param array<int, mixed> $images */
    private function imageUrls(array $images): array
    {
        return collect($images)->map(function (mixed $image): string {
            $path = is_array($image) ? ($image['path'] ?? null) : null;
            $extension = is_string($path) ? Str::lower(pathinfo($path, PATHINFO_EXTENSION)) : '';

            if (! is_string($path) || ! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                throw new MarketplaceCardCreationException('Ozon принимает для карточки только изображения JPEG и PNG.');
            }

            if (! Storage::disk('s3')->exists($path)) {
                throw new MarketplaceCardCreationException('Не найден файл изображения для Ozon.');
            }

            return Storage::disk('s3')->temporaryUrl($path, now()->addDay());
        })->values()->all();
    }

    /** @return list<array{id: int, complex_id: int, values: list<array{value: string, dictionary_value_id?: int}>}> */
    private function attributes(CardExport $export, MarketplaceApiKey $apiKey): array
    {
        $schema = $this->marketplaceApi->attributeSchema($export->generation);
        $values = collect($export->payload['attributes'] ?? [])
            ->mapWithKeys(fn (mixed $value, mixed $key): array => [Str::lower((string) $key) => $value]);
        $preparedAttributes = collect($schema)->map(function (array $attribute) use ($export, $values): ?array {
            $key = Str::lower($attribute['key']);
            $value = $values->get($key);

            if (Str::contains($key, ['аннотация', 'описание'])) {
                $value = $export->payload['description'];
            } elseif (Str::contains($key, ['название модели', 'наименование товара'])) {
                $value = $export->payload['title'];
            }

            if (! filled($value)) {
                if ($attribute['required']) {
                    throw new MarketplaceCardCreationException("Заполните обязательную характеристику Ozon «{$attribute['key']}».");
                }

                return null;
            }

            return [
                'attribute' => $attribute,
                'values' => ($attribute['dictionary_id'] ?? 0) > 0
                    ? $this->dictionaryItems($attribute, $value)
                    : [is_array($value) ? implode(', ', $value) : (string) $value],
            ];
        })->filter()->values();
        $lookups = $preparedAttributes
            ->filter(fn (array $prepared): bool => ($prepared['attribute']['dictionary_id'] ?? 0) > 0)
            ->flatMap(fn (array $prepared, int $attributeIndex): array => collect($prepared['values'])
                ->map(fn (string $value, int $valueIndex): array => [
                    'key' => "{$attributeIndex}:{$valueIndex}",
                    'attribute_key' => $prepared['attribute']['key'],
                    'attribute_id' => (int) $prepared['attribute']['id'],
                    'value' => $value,
                ])
                ->all())
            ->values()
            ->all();
        $dictionaryResults = $this->dictionaryValueResolver->resolve(
            $apiKey,
            (int) $export->payload['category_id'],
            (int) $export->payload['type_id'],
            $lookups,
        );
        $issues = [];
        $attributes = $preparedAttributes
            ->map(function (array $prepared, int $attributeIndex) use ($dictionaryResults, &$issues): ?array {
                $attribute = $prepared['attribute'];

                if (($attribute['dictionary_id'] ?? 0) <= 0) {
                    return [
                        'id' => (int) $attribute['id'],
                        'complex_id' => 0,
                        'values' => [['value' => $prepared['values'][0]]],
                    ];
                }

                $matchedValues = [];
                $suggestions = [];

                foreach ($prepared['values'] as $valueIndex => $value) {
                    $result = $dictionaryResults["{$attributeIndex}:{$valueIndex}"] ?? null;
                    $match = is_array($result) ? ($result['match'] ?? null) : null;

                    if (is_array($match)) {
                        $matchedValues[] = [
                            'value' => $match['value'],
                            'dictionary_value_id' => $match['id'],
                        ];
                    } else {
                        $suggestions = [
                            ...$suggestions,
                            ...(is_array($result) ? ($result['suggestions'] ?? []) : []),
                        ];
                    }
                }

                if ($matchedValues === []) {
                    if ($attribute['required']) {
                        $issues[] = [
                            'attribute' => $attribute['key'],
                            'value' => implode(', ', $prepared['values']),
                            'suggestions' => collect($suggestions)->unique()->take(5)->values()->all(),
                        ];
                    }

                    return null;
                }

                return [
                    'id' => (int) $attribute['id'],
                    'complex_id' => 0,
                    'values' => $matchedValues,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($issues !== []) {
            throw new MarketplaceCardCreationException($this->dictionaryIssuesMessage($issues));
        }

        return $attributes;
    }

    /**
     * @param  array{key: string, id: int|null, type: string|null, required: bool, unit: string|null, dictionary_id: int|null, is_collection: bool}  $attribute
     * @return list<string>
     */
    private function dictionaryItems(array $attribute, mixed $value): array
    {
        return $attribute['is_collection']
            ? collect(is_array($value) ? $value : explode(',', (string) $value))
                ->map(fn (mixed $item): string => trim((string) $item))
                ->filter()
                ->unique(fn (string $item): string => Str::lower($item))
                ->values()
                ->all()
            : [is_array($value) ? implode(', ', $value) : (string) $value];
    }

    /**
     * @param  list<array{attribute: string, value: string, suggestions: list<string>}>  $issues
     */
    private function dictionaryIssuesMessage(array $issues): string
    {
        $lines = collect($issues)->map(function (array $issue): string {
            $suggestions = $issue['suggestions'] === []
                ? 'Ozon не предложил подходящих вариантов.'
                : 'Варианты Ozon: '.implode(', ', $issue['suggestions']).'.';

            return "• «{$issue['attribute']}»: значение «{$issue['value']}» отсутствует в справочнике. {$suggestions}";
        });

        return 'Проверьте обязательные характеристики Ozon на шаге «Категория»:'."\n".$lines->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function addCommerceFields(array $item, array $attributes): array
    {
        $values = $this->normalizedValues($attributes);
        $barcode = $this->firstValue($values, ['штрихкод', 'barcode']);
        $price = $this->firstValue($values, ['цена', 'цена, ₽', 'price']);
        $oldPrice = $this->firstValue($values, ['старая цена', 'старая цена, ₽', 'old_price']);

        if (filled($barcode)) {
            $item['barcode'] = (string) $barcode;
        }

        if (filled($price)) {
            $item['price'] = (string) $price;
        }

        if (filled($oldPrice)) {
            $item['old_price'] = (string) $oldPrice;
        }

        $dimensions = [
            'depth' => $this->centimetresToMillimetres($this->firstValue($values, ['длина упаковки, см', 'длина, см'])),
            'width' => $this->centimetresToMillimetres($this->firstValue($values, ['ширина упаковки, см', 'ширина, см'])),
            'height' => $this->centimetresToMillimetres($this->firstValue($values, ['высота упаковки, см', 'высота, см'])),
        ];

        if (! in_array(null, $dimensions, true)) {
            $item = [...$item, ...$dimensions, 'dimension_unit' => 'mm'];
        }

        $weight = $this->kilogramsToGrams($this->firstValue($values, ['вес упаковки, кг', 'вес, кг']));

        if ($weight !== null) {
            $item['weight'] = $weight;
            $item['weight_unit'] = 'g';
        }

        return $item;
    }

    /** @param array<string, mixed> $attributes */
    private function normalizedValues(array $attributes): array
    {
        return collect($attributes)
            ->mapWithKeys(fn (mixed $value, mixed $key): array => [Str::lower(trim((string) $key)) => $value])
            ->all();
    }

    /** @param array<string, mixed> $values */
    private function firstValue(array $values, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (filled($values[$key] ?? null)) {
                return $values[$key];
            }
        }

        return null;
    }

    private function centimetresToMillimetres(mixed $centimetres): ?int
    {
        return is_numeric($centimetres)
            ? max(1, (int) round((float) $centimetres * 10))
            : null;
    }

    private function kilogramsToGrams(mixed $kilograms): ?int
    {
        return is_numeric($kilograms)
            ? max(1, (int) round((float) $kilograms * 1000))
            : null;
    }

    private function requestError(RequestException $exception, string $fallback): string
    {
        $message = $exception->response?->json('message');

        return is_string($message) && $message !== '' ? "{$fallback} {$message}" : $fallback;
    }

    /** @param array<string, mixed> $item */
    private function ozonItemError(array $item): string
    {
        $description = data_get($item, 'errors.0.message')
            ?? data_get($item, 'errors.0.description');

        return is_string($description) && $description !== ''
            ? "Ozon отклонил карточку: {$description}"
            : 'Ozon отклонил карточку. Проверьте обязательные характеристики товара.';
    }
}
