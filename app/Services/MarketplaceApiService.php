<?php

namespace App\Services;

use App\Models\CardGeneration;
use App\Models\MarketplaceApiKey;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MarketplaceApiService
{
    /** @return list<array{key: string, id: int|null, type: string|null, required: bool, unit: string|null, dictionary_id: int|null, is_collection: bool}> */
    public function attributeSchema(CardGeneration $generation): array
    {
        $generation->loadMissing('card.user');
        $marketplace = $generation->card->marketplace;
        $apiKey = $this->apiKey($generation, $marketplace);

        return match ($marketplace) {
            'ozon' => $this->ozonAttributeSchema($generation, $apiKey),
            'wildberries' => $this->wildberriesAttributeSchema($generation, $apiKey),
            default => throw new RuntimeException('Неподдерживаемый маркетплейс для получения схемы атрибутов.'),
        };
    }

    private function apiKey(CardGeneration $generation, string $marketplace): MarketplaceApiKey
    {
        $apiKey = $generation->card->user->marketplaceApiKeys()
            ->where('marketplace', $marketplace)
            ->first();

        if ($apiKey === null || ! filled($apiKey->api_key)
            || ($marketplace === 'ozon' && ! filled($apiKey->client_id))) {
            throw new RuntimeException('Подключите API-ключ '.$this->marketplaceName($marketplace).' для получения характеристик категории.');
        }

        return $apiKey;
    }

    /** @return list<array{key: string, id: int|null, type: string|null, required: bool, unit: string|null, dictionary_id: int|null, is_collection: bool}> */
    private function ozonAttributeSchema(CardGeneration $generation, MarketplaceApiKey $apiKey): array
    {
        if ($generation->attributes_category_id === null || $generation->attributes_type_id === null) {
            throw new RuntimeException('Выберите категорию и тип товара Ozon.');
        }

        $response = Http::acceptJson()
            ->withHeaders([
                'Client-Id' => $apiKey->client_id,
                'Api-Key' => $apiKey->api_key,
            ])
            ->connectTimeout(5)
            ->timeout(60)
            ->retry([250, 750, 1500], throw: false)
            ->post('https://api-seller.ozon.ru/v1/description-category/attribute', [
                'description_category_id' => $generation->attributes_category_id,
                'type_id' => $generation->attributes_type_id,
                'language' => 'DEFAULT',
            ])
            ->throw();
        $attributes = $response->json('result');

        if (! is_array($attributes)) {
            throw new RuntimeException('Ozon вернул некорректную схему характеристик.');
        }

        return $this->normalizeSchema($attributes, 'name', 'id', 'type', 'is_required', null);
    }

    /** @return list<array{key: string, id: int|null, type: string|null, required: bool, unit: string|null, dictionary_id: int|null, is_collection: bool}> */
    private function wildberriesAttributeSchema(CardGeneration $generation, MarketplaceApiKey $apiKey): array
    {
        if ($generation->attributes_category_id === null) {
            throw new RuntimeException('Выберите предмет товара Wildberries.');
        }

        $response = Http::acceptJson()
            ->withToken($apiKey->api_key)
            ->connectTimeout(5)
            ->timeout(60)
            ->retry([250, 750, 1500], throw: false)
            ->get("https://content-api.wildberries.ru/content/v2/object/charcs/{$generation->attributes_category_id}", [
                'locale' => 'ru',
            ])
            ->throw();
        $attributes = $response->json('data');

        if (! is_array($attributes)) {
            throw new RuntimeException('Wildberries вернул некорректную схему характеристик.');
        }

        return $this->normalizeSchema($attributes, 'name', 'charcID', 'charcType', 'required', 'unitName');
    }

    /**
     * @param  array<int, mixed>  $attributes
     * @return list<array{key: string, id: int|null, type: string|null, required: bool, unit: string|null, dictionary_id: int|null, is_collection: bool}>
     */
    private function normalizeSchema(
        array $attributes,
        string $nameField,
        string $idField,
        string $typeField,
        string $requiredField,
        ?string $unitField,
    ): array {
        return collect($attributes)
            ->filter(fn (mixed $attribute): bool => is_array($attribute))
            ->map(function (array $attribute) use ($nameField, $idField, $typeField, $requiredField, $unitField): ?array {
                $key = trim((string) ($attribute[$nameField] ?? ''));

                if ($key === '') {
                    return null;
                }

                return [
                    'key' => $key,
                    'id' => is_numeric($attribute[$idField] ?? null) ? (int) $attribute[$idField] : null,
                    'type' => isset($attribute[$typeField]) ? (string) $attribute[$typeField] : null,
                    'required' => (bool) ($attribute[$requiredField] ?? false),
                    'unit' => $unitField !== null && isset($attribute[$unitField])
                        ? (string) $attribute[$unitField]
                        : null,
                    'dictionary_id' => is_numeric($attribute['dictionary_id'] ?? null)
                        ? (int) $attribute['dictionary_id']
                        : null,
                    'is_collection' => (bool) ($attribute['is_collection'] ?? false),
                ];
            })
            ->filter()
            ->unique('key')
            ->values()
            ->all();
    }

    private function marketplaceName(string $marketplace): string
    {
        return $marketplace === 'ozon' ? 'Ozon' : 'Wildberries';
    }
}
