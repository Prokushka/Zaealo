<?php

namespace App\Console\Commands;

use App\Models\OzonCategory;
use App\Services\AiTunnelService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

#[Signature('ozon:sync-categories')]
#[Description('Synchronize the Ozon product category catalogue')]
class OzonSyncCategories extends Command
{
    private const EMBEDDING_BATCH_SIZE = 100;

    public function handle(AiTunnelService $aiTunnel): int
    {
        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'Client-Id' => $this->configuredValue('services.ozon.categories_client_id'),
                    'Api-Key' => $this->configuredValue('services.ozon.categories_api_key'),
                ])
                ->connectTimeout(5)
                ->timeout(60)
                ->retry([250, 750, 1500], throw: false)
                ->post('https://api-seller.ozon.ru/v1/description-category/tree', ['language' => 'DEFAULT'])
                ->throw();
            $categories = $response->json('result');

            if (! is_array($categories)) {
                throw new RuntimeException('Ozon returned a response without a category tree.');
            }

            $rows = $this->rowsFromTree($categories);

            if ($rows === []) {
                throw new RuntimeException('Ozon returned no usable category types.');
            }

            $rows = $this->withEmbeddings($rows, $aiTunnel);
            $this->replaceCatalogue($rows);
            $this->info('Ozon categories synchronized: '.count($rows));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Не удалось синхронизировать категории Ozon: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @param  list<string>  $path
     * @return list<array{description_category_id: int, type_id: int, category_name: string, type_name: string, full_path: string}>
     */
    private function rowsFromTree(array $nodes, array $path = [], ?int $categoryId = null, ?string $categoryName = null): array
    {
        $rows = [];

        foreach ($nodes as $node) {
            if (! is_array($node) || ($node['disabled'] ?? false) === true) {
                continue;
            }

            if (isset($node['type_id'])) {
                $typeName = Str::squish((string) ($node['type_name'] ?? ''));

                if ($categoryId !== null && $categoryName !== null && $typeName !== '') {
                    $rows[] = [
                        'description_category_id' => $categoryId,
                        'type_id' => (int) $node['type_id'],
                        'category_name' => $categoryName,
                        'type_name' => $typeName,
                        'full_path' => implode(' > ', [...$path, $typeName]),
                    ];
                }

                continue;
            }

            $nextCategoryId = isset($node['description_category_id']) ? (int) $node['description_category_id'] : $categoryId;
            $nextCategoryName = Str::squish((string) ($node['category_name'] ?? $categoryName ?? ''));
            $nextPath = $nextCategoryName === '' ? $path : [...$path, $nextCategoryName];
            $children = $node['children'] ?? [];

            if (is_array($children)) {
                $rows = [...$rows, ...$this->rowsFromTree($children, $nextPath, $nextCategoryId, $nextCategoryName)];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{description_category_id: int, type_id: int, category_name: string, type_name: string, full_path: string}>  $rows
     * @return list<array{description_category_id: int, type_id: int, category_name: string, type_name: string, full_path: string, embedding: string, created_at: Carbon, updated_at: Carbon}>
     */
    private function withEmbeddings(array $rows, AiTunnelService $aiTunnel): array
    {
        $timestamp = now();

        return collect($rows)
            ->chunk(self::EMBEDDING_BATCH_SIZE)
            ->flatMap(function ($chunk) use ($aiTunnel, $timestamp) {
                $embeddings = $aiTunnel->embeddings($chunk->pluck('full_path')->all());

                return $chunk->values()->map(fn (array $row, int $index): array => [
                    ...$row,
                    'embedding' => json_encode($embeddings[$index], JSON_THROW_ON_ERROR),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            })
            ->values()
            ->all();
    }

    /** @param list<array<string, mixed>> $rows */
    private function replaceCatalogue(array $rows): void
    {
        DB::transaction(function () use ($rows): void {
            OzonCategory::query()->truncate();

            foreach (array_chunk($rows, self::EMBEDDING_BATCH_SIZE) as $batch) {
                OzonCategory::query()->insert($batch);
            }
        });
    }

    private function configuredValue(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Missing [$key] configuration value.");
        }

        return $value;
    }
}
