<?php

namespace App\Console\Commands;

use App\Models\WbCategory;
use App\Services\AiTunnelService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

#[Signature('wb:sync-categories')]
#[Description('Synchronize the Wildberries product category catalogue')]
class WbSyncCategories extends Command
{
    private const EMBEDDING_BATCH_SIZE = 100;

    private const REQUEST_INTERVAL_MICROSECONDS = 650_000;

    private ?int $nextRequestAt = null;

    public function handle(AiTunnelService $aiTunnel): int
    {
        try {
            $rows = $this->subjectRows();

            if ($rows === []) {
                throw new RuntimeException('Wildberries returned no usable product subjects.');
            }

            $this->synchronizeCatalogue($rows, $aiTunnel);
            $this->info('Wildberries categories synchronized: '.count($rows));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Не удалось синхронизировать категории Wildberries: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return list<array{subject_id: int, subject_name: string, parent_name: ?string, full_path: string}>
     */
    private function subjectRows(): array
    {
        $parents = $this->get('/content/v2/object/parent/all', ['locale' => 'ru'])->json('data');

        if (! is_array($parents)) {
            throw new RuntimeException('Wildberries returned a response without parent categories.');
        }

        $rows = [];

        foreach ($parents as $parent) {
            if (! is_array($parent) || ! is_numeric($parent['id'] ?? null)) {
                continue;
            }

            $parentName = Str::squish((string) ($parent['name'] ?? ''));
            $subjects = $this->get('/content/v2/object/all', [
                'locale' => 'ru',
                'parentID' => (int) $parent['id'],
            ])->json('data');

            if (! is_array($subjects)) {
                throw new RuntimeException('Wildberries returned a response without product subjects.');
            }

            foreach ($subjects as $subject) {
                if (! is_array($subject)) {
                    continue;
                }

                $row = $this->subjectRow($subject, $parentName);

                if ($row !== null) {
                    $rows[$row['subject_id']] = $row;
                }
            }
        }

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $subject
     * @return array{subject_id: int, subject_name: string, parent_name: ?string, full_path: string}|null
     */
    private function subjectRow(array $subject, string $fallbackParentName): ?array
    {
        $subjectName = Str::squish((string) ($subject['subjectName'] ?? ''));
        $parentName = Str::squish((string) ($subject['parentName'] ?? $fallbackParentName));

        if (! is_numeric($subject['subjectID'] ?? null) || $subjectName === '') {
            return null;
        }

        return [
            'subject_id' => (int) $subject['subjectID'],
            'subject_name' => $subjectName,
            'parent_name' => $parentName === '' ? null : $parentName,
            'full_path' => $parentName === '' ? $subjectName : "{$parentName} > {$subjectName}",
        ];
    }

    /**
     * @param  list<array{subject_id: int, subject_name: string, parent_name: ?string, full_path: string}>  $rows
     * @return list<array{subject_id: int, subject_name: string, parent_name: ?string, full_path: string, embedding: string, created_at: Carbon, updated_at: Carbon}>
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

    /** @param list<array{subject_id: int, subject_name: string, parent_name: ?string, full_path: string}> $rows */
    private function synchronizeCatalogue(array $rows, AiTunnelService $aiTunnel): void
    {
        foreach (array_chunk($rows, self::EMBEDDING_BATCH_SIZE) as $chunk) {
            $existingCategories = WbCategory::query()
                ->select(['subject_id', 'full_path', 'embedding'])
                ->whereIn('subject_id', array_column($chunk, 'subject_id'))
                ->get()
                ->keyBy('subject_id');
            $rowsNeedingEmbeddings = array_values(array_filter(
                $chunk,
                fn (array $row): bool => ! isset($existingCategories[$row['subject_id']])
                    || $existingCategories[$row['subject_id']]->full_path !== $row['full_path']
                    || $existingCategories[$row['subject_id']]->embedding === null,
            ));
            $unchangedRows = array_values(array_filter(
                $chunk,
                fn (array $row): bool => ! in_array($row, $rowsNeedingEmbeddings, true),
            ));

            if ($unchangedRows !== []) {
                WbCategory::query()->upsert(
                    $this->rowsWithTimestamps($unchangedRows),
                    ['subject_id'],
                    ['subject_name', 'parent_name', 'full_path', 'updated_at'],
                );
            }

            if ($rowsNeedingEmbeddings !== []) {
                WbCategory::query()->upsert(
                    $this->withEmbeddings($rowsNeedingEmbeddings, $aiTunnel),
                    ['subject_id'],
                    ['subject_name', 'parent_name', 'full_path', 'embedding', 'updated_at'],
                );
            }
        }

        WbCategory::query()
            ->whereNotIn('subject_id', array_column($rows, 'subject_id'))
            ->delete();
    }

    /**
     * @param  list<array{subject_id: int, subject_name: string, parent_name: ?string, full_path: string}>  $rows
     * @return list<array{subject_id: int, subject_name: string, parent_name: ?string, full_path: string, created_at: Carbon, updated_at: Carbon}>
     */
    private function rowsWithTimestamps(array $rows): array
    {
        $timestamp = now();

        return array_map(fn (array $row): array => [
            ...$row,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $rows);
    }

    /** @param array<string, scalar> $query */
    private function get(string $path, array $query): Response
    {
        if ($this->nextRequestAt !== null) {
            $remainingMicroseconds = intdiv($this->nextRequestAt - hrtime(true), 1_000);

            if ($remainingMicroseconds > 0) {
                usleep($remainingMicroseconds);
            }
        }

        $this->nextRequestAt = hrtime(true) + (self::REQUEST_INTERVAL_MICROSECONDS * 1_000);

        return Http::acceptJson()
            ->withToken($this->configuredApiKey())
            ->connectTimeout(5)
            ->timeout(60)
            ->retry([750, 1500, 3000], throw: false)
            ->get("https://content-api.wildberries.ru{$path}", $query)
            ->throw();
    }

    private function configuredApiKey(): string
    {
        $apiKey = config('services.wildberries.categories_api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Missing [services.wildberries.categories_api_key] configuration value.');
        }

        return $apiKey;
    }
}
