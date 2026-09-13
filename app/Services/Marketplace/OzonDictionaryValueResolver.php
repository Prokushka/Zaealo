<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Exceptions\MarketplaceCardCreationException;
use App\Models\MarketplaceApiKey;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class OzonDictionaryValueResolver
{
    /**
     * @param  list<array{key: string, attribute_key: string, attribute_id: int, value: string}>  $lookups
     * @return array<string, array{match: array{id: int, value: string}|null, suggestions: list<string>}>
     */
    public function resolve(
        MarketplaceApiKey $apiKey,
        int $descriptionCategoryId,
        int $typeId,
        array $lookups,
    ): array {
        $candidateSets = $this->searchBatch($apiKey, $descriptionCategoryId, $typeId, $lookups);
        $fallbackLookups = collect($lookups)
            ->filter(fn (array $lookup): bool => $this->match($lookup['value'], $candidateSets[$lookup['key']] ?? []) === null)
            ->filter(fn (array $lookup): bool => $this->shouldSearchCompactValue($lookup['value']))
            ->map(fn (array $lookup): array => [
                ...$lookup,
                'value' => $this->compactValue($lookup['value']),
            ])
            ->values()
            ->all();
        $fallbackCandidateSets = $this->searchBatch(
            $apiKey,
            $descriptionCategoryId,
            $typeId,
            $fallbackLookups,
        );

        return collect($lookups)
            ->mapWithKeys(function (array $lookup) use ($candidateSets, $fallbackCandidateSets): array {
                $candidates = collect([
                    ...($candidateSets[$lookup['key']] ?? []),
                    ...($fallbackCandidateSets[$lookup['key']] ?? []),
                ])->unique('id')->values()->all();

                return [$lookup['key'] => [
                    'match' => $this->match($lookup['value'], $candidates),
                    'suggestions' => collect($candidates)
                        ->pluck('value')
                        ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
                        ->unique()
                        ->take(5)
                        ->values()
                        ->all(),
                ]];
            })
            ->all();
    }

    /**
     * @param  list<array{key: string, attribute_key: string, attribute_id: int, value: string}>  $lookups
     * @return array<string, list<array{id: int, value: string}>>
     */
    private function searchBatch(
        MarketplaceApiKey $apiKey,
        int $descriptionCategoryId,
        int $typeId,
        array $lookups,
    ): array {
        if ($lookups === []) {
            return [];
        }

        $requests = collect($lookups)
            ->mapWithKeys(function (array $lookup) use ($descriptionCategoryId, $typeId): array {
                $requestKey = $this->requestKey(
                    $descriptionCategoryId,
                    $typeId,
                    $lookup['attribute_id'],
                    $lookup['value'],
                );

                return [$requestKey => $lookup];
            })
            ->all();
        $candidateSetsByRequest = [];
        $uncachedRequests = [];

        foreach ($requests as $requestKey => $lookup) {
            $cached = Cache::get($requestKey);

            if (is_array($cached)) {
                $candidateSetsByRequest[$requestKey] = $cached;
            } else {
                $uncachedRequests[$requestKey] = $lookup;
            }
        }

        if ($uncachedRequests !== []) {
            try {
                $responses = Http::pool(
                    fn (Pool $pool): array => collect($uncachedRequests)
                        ->map(fn (array $lookup, string $requestKey) => $pool
                            ->as($requestKey)
                            ->acceptJson()
                            ->withHeaders([
                                'Client-Id' => $apiKey->client_id,
                                'Api-Key' => $apiKey->api_key,
                            ])
                            ->connectTimeout(5)
                            ->timeout(60)
                            ->retry([500, 1500, 3000], throw: false)
                            ->post('https://api-seller.ozon.ru/v1/description-category/attribute/values/search', [
                                'attribute_id' => $lookup['attribute_id'],
                                'description_category_id' => $descriptionCategoryId,
                                'type_id' => $typeId,
                                'value' => $lookup['value'],
                                'limit' => 100,
                                'language' => 'DEFAULT',
                            ]))
                        ->values()
                        ->all(),
                    concurrency: 5,
                );
            } catch (Throwable $exception) {
                throw new MarketplaceCardCreationException(
                    'Не удалось получить значения справочников Ozon.',
                    previous: $exception,
                );
            }

            foreach ($uncachedRequests as $requestKey => $lookup) {
                $response = $responses[$requestKey] ?? null;

                if (! $response instanceof Response || ! $response->successful()) {
                    throw new MarketplaceCardCreationException(
                        "Не удалось получить справочник Ozon для характеристики «{$lookup['attribute_key']}».",
                    );
                }

                $result = $response->json('result');

                if (! is_array($result)) {
                    throw new MarketplaceCardCreationException(
                        "Ozon вернул некорректный справочник для характеристики «{$lookup['attribute_key']}».",
                    );
                }

                $candidates = $this->candidates($result);
                $candidateSetsByRequest[$requestKey] = $candidates;
                Cache::put($requestKey, $candidates, now()->addHour());
            }
        }

        return collect($lookups)
            ->mapWithKeys(function (array $lookup) use ($candidateSetsByRequest, $descriptionCategoryId, $typeId): array {
                $requestKey = $this->requestKey(
                    $descriptionCategoryId,
                    $typeId,
                    $lookup['attribute_id'],
                    $lookup['value'],
                );

                return [$lookup['key'] => $candidateSetsByRequest[$requestKey] ?? []];
            })
            ->all();
    }

    /**
     * @param  array<int, mixed>  $result
     * @return list<array{id: int, value: string}>
     */
    private function candidates(array $result): array
    {
        return collect($result)
            ->filter(fn (mixed $candidate): bool => is_array($candidate)
                && is_numeric($candidate['id'] ?? null)
                && is_string($candidate['value'] ?? null)
                && trim($candidate['value']) !== '')
            ->map(fn (array $candidate): array => [
                'id' => (int) $candidate['id'],
                'value' => trim($candidate['value']),
            ])
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{id: int, value: string}>  $candidates
     * @return array{id: int, value: string}|null
     */
    private function match(string $value, array $candidates): ?array
    {
        $normalizedValue = $this->normalizedValue($value);
        $exactMatches = collect($candidates)
            ->filter(fn (array $candidate): bool => $this->normalizedValue($candidate['value']) === $normalizedValue)
            ->values();

        if ($exactMatches->count() === 1) {
            return $exactMatches->first();
        }

        $compactValue = $this->compactValue($value);

        if ($compactValue === '') {
            return null;
        }

        $compactMatches = collect($candidates)
            ->filter(fn (array $candidate): bool => $this->compactValue($candidate['value']) === $compactValue)
            ->values();

        return $compactMatches->count() === 1 ? $compactMatches->first() : null;
    }

    private function normalizedValue(string $value): string
    {
        return (string) Str::of($value)
            ->trim()
            ->lower()
            ->replaceMatches('/\s+/u', ' ');
    }

    private function compactValue(string $value): string
    {
        return (string) Str::of($this->normalizedValue($value))
            ->replaceMatches('/[^\pL\pN]+/u', '');
    }

    private function shouldSearchCompactValue(string $value): bool
    {
        $normalizedValue = $this->normalizedValue($value);
        $compactValue = $this->compactValue($value);

        if ($compactValue === '' || $compactValue === $normalizedValue) {
            return false;
        }

        return ctype_digit($compactValue)
            || preg_match('/[^\pL\pN\s]/u', $normalizedValue) === 1;
    }

    private function requestKey(
        int $descriptionCategoryId,
        int $typeId,
        int $attributeId,
        string $value,
    ): string {
        return 'ozon:attribute-values-search:v1:'.hash('sha256', implode('|', [
            $descriptionCategoryId,
            $typeId,
            $attributeId,
            $this->normalizedValue($value),
        ]));
    }
}
