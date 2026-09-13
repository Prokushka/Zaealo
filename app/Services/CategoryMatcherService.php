<?php

namespace App\Services;

use App\Models\OzonCategory;
use App\Models\WbCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CategoryMatcherService
{
    public function __construct(private AiTunnelService $aiTunnel) {}

    /**
     * @return array{recommended_id: ?int, candidates: list<array<string, mixed>>}
     */
    public function match(string $marketplace, string $title): array
    {
        $title = Str::squish($title);

        if ($title === '') {
            throw new InvalidArgumentException('Category matching requires a product title.');
        }

        $modelClass = $this->categoryModelClass($marketplace);

        if (! $modelClass::query()->whereNotNull('embedding')->exists()) {
            return ['recommended_id' => null, 'candidates' => []];
        }

        $embedding = $this->aiTunnel->embeddings([$title])[0];
        $candidates = $this->nearestCandidates($marketplace, $modelClass, $embedding);
        $candidatePayload = $candidates
            ->map(fn (Model $candidate): array => $this->candidatePayload($marketplace, $candidate))
            ->values()
            ->all();

        return [
            'recommended_id' => $this->recommendedId($marketplace, $title, $candidatePayload),
            'candidates' => $candidatePayload,
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<float>  $embedding
     * @return Collection<int, Model>
     */
    private function nearestCandidates(string $marketplace, string $modelClass, array $embedding): Collection
    {
        $columns = $marketplace === 'ozon'
            ? ['id', 'description_category_id', 'type_id', 'category_name', 'type_name', 'full_path']
            : ['id', 'subject_id', 'subject_name', 'parent_name', 'full_path'];

        if ((new $modelClass)->getConnection()->getDriverName() === 'pgsql') {
            return $modelClass::query()
                ->select($columns)
                ->selectRaw('embedding <=> ?::vector as distance', [$this->vectorLiteral($embedding)])
                ->whereNotNull('embedding')
                ->orderBy('distance')
                ->limit(5)
                ->get();
        }

        return $modelClass::query()
            ->select([...$columns, 'embedding'])
            ->whereNotNull('embedding')
            ->get()
            ->map(function (Model $candidate) use ($embedding): Model {
                $candidate->setAttribute('distance', $this->cosineDistance($embedding, $candidate->embedding));

                return $candidate;
            })
            ->sortBy('distance')
            ->take(5)
            ->values();
    }

    /** @return array<string, mixed> */
    private function candidatePayload(string $marketplace, Model $candidate): array
    {
        return $marketplace === 'ozon'
            ? [
                'id' => $candidate->getKey(),
                'description_category_id' => $candidate->description_category_id,
                'type_id' => $candidate->type_id,
                'category_name' => $candidate->category_name,
                'type_name' => $candidate->type_name,
                'full_path' => $candidate->full_path,
                'distance' => (float) $candidate->distance,
            ]
            : [
                'id' => $candidate->getKey(),
                'subject_id' => $candidate->subject_id,
                'subject_name' => $candidate->subject_name,
                'parent_name' => $candidate->parent_name,
                'full_path' => $candidate->full_path,
                'distance' => (float) $candidate->distance,
            ];
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    private function recommendedId(string $marketplace, string $title, array $candidates): ?int
    {
        if ($candidates === []) {
            return null;
        }

        $response = $this->aiTunnel->chatCompletion([
            [
                'role' => 'system',
                'content' => 'Ты выбираешь одну наиболее точную категорию товара. Верни только валидный JSON вида {"recommended_id": число}. Выбирай ID только из списка кандидатов. Не добавляй пояснений.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'marketplace' => $marketplace,
                    'title' => $title,
                    'candidates' => $candidates,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ],
        ], AIChoice::GPT4MINI);

        $content = data_get($response, 'choices.0.message.content');
        $decoded = is_string($content) ? json_decode($content, true) : null;

        $availableIds = collect($candidates)
            ->pluck($marketplace === 'ozon' ? 'description_category_id' : 'subject_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $fallbackId = $availableIds[0] ?? null;

        if (! is_array($decoded)) {
            return $fallbackId;
        }

        $recommendedId = $decoded['recommended_id'] ?? null;

        if (! is_int($recommendedId) && (! is_string($recommendedId) || ! ctype_digit($recommendedId))) {
            return $fallbackId;
        }
        $recommendedId = (int) $recommendedId;

        return in_array($recommendedId, $availableIds, true) ? $recommendedId : $fallbackId;
    }

    /** @return class-string<Model> */
    private function categoryModelClass(string $marketplace): string
    {
        return match ($marketplace) {
            'ozon' => OzonCategory::class,
            'wb' => WbCategory::class,
            default => throw new InvalidArgumentException('Unsupported marketplace category catalogue.'),
        };
    }

    /** @param list<float> $embedding */
    private function vectorLiteral(array $embedding): string
    {
        return json_encode($embedding, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<float>  $queryEmbedding
     * @param  list<float>|null  $candidateEmbedding
     */
    private function cosineDistance(array $queryEmbedding, ?array $candidateEmbedding): float
    {
        if ($candidateEmbedding === null || count($candidateEmbedding) !== count($queryEmbedding)) {
            return INF;
        }

        $dotProduct = 0.0;
        $queryNorm = 0.0;
        $candidateNorm = 0.0;

        foreach ($queryEmbedding as $index => $queryValue) {
            $candidateValue = $candidateEmbedding[$index];
            $dotProduct += $queryValue * $candidateValue;
            $queryNorm += $queryValue ** 2;
            $candidateNorm += $candidateValue ** 2;
        }

        if ($queryNorm === 0.0 || $candidateNorm === 0.0) {
            return INF;
        }

        return 1 - ($dotProduct / (sqrt($queryNorm) * sqrt($candidateNorm)));
    }
}
