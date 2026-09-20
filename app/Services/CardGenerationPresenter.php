<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PricingKey;
use App\Models\CardGeneration;
use App\Models\CardImage;
use App\Models\OzonCategory;
use App\Models\WbCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class CardGenerationPresenter
{
    public function __construct(private PricingCatalog $pricing) {}

    /**
     * @param  Collection<int, CardGeneration>  $generations
     * @return Collection<int, array<string, mixed>>
     */
    public function history(Collection $generations): Collection
    {
        $categories = $this->categories($generations);

        return $generations->map(function (CardGeneration $generation) use ($categories): array {
            $preview = $generation->images->firstWhere('is_main', true)
                ?? $generation->images->first(fn (CardImage $image): bool => $this->imageIsAvailable($image));
            $hasGeneratedPhoto = $generation->images->contains(
                fn (CardImage $image): bool => $image->type === CardImage::TYPE_AI_GENERATED
                    && $image->generation_status === CardImage::GENERATION_STATUS_COMPLETED,
            );
            $archivePrepared = $hasGeneratedPhoto || (int) ($generation->export?->cost_zarks ?? 0) > 0;

            return [
                'id' => $generation->getKey(),
                'card_id' => $generation->card_id,
                'marketplace' => $generation->card->marketplace,
                'title' => $generation->generated_title ?: $generation->card->title,
                'category' => $categories->get($generation->getKey())['full_path'] ?? null,
                'status' => $generation->status,
                'created_at' => $generation->created_at?->toIso8601String(),
                'preview_url' => $preview !== null && $this->imageIsAvailable($preview)
                    ? $this->temporaryUrl($preview)
                    : null,
                'archive_cost' => $archivePrepared ? 0 : $this->pricing->cost(PricingKey::CardExport),
                'can_open' => $generation->status === 'completed',
            ];
        });
    }

    /** @return array<string, mixed> */
    public function editor(CardGeneration $generation): array
    {
        $generation->loadMissing(['card', 'images', 'export']);
        $selectedCategory = $this->categories(collect([$generation]))->get($generation->getKey());
        $generatedImages = $generation->images
            ->where('type', CardImage::TYPE_AI_GENERATED)
            ->sortByDesc('id')
            ->values();
        $statusCounts = $generatedImages->countBy('generation_status');
        $hasGeneratedPhoto = $statusCounts->get(CardImage::GENERATION_STATUS_COMPLETED, 0) > 0;

        return [
            'id' => $generation->card_id,
            'generation_id' => $generation->getKey(),
            'is_editing' => true,
            'title' => $generation->generated_title ?: $generation->card->title,
            'description' => $generation->generated_description ?: $generation->card->description,
            'marketplace' => $generation->card->marketplace,
            'allowed_photo_slots' => $generation->card->allowed_photo_slots,
            'generation_mode' => $generation->mode,
            'copywriting_quality' => $generation->selected_style,
            'category_match' => $generation->category_match,
            'selected_category' => $selectedCategory,
            'attributes' => $generation->attributes_data ?? [],
            'infographic_features' => $generation->generated_bullets ?? [],
            'images' => $generation->images
                ->where('type', CardImage::TYPE_USER_UPLOAD)
                ->sortByDesc('is_main')
                ->map(fn (CardImage $image): array => [
                    'id' => $image->getKey(),
                    'url' => $this->temporaryUrl($image),
                    'name' => basename($image->path),
                    'size' => 0,
                    'is_main' => $image->is_main,
                ])->values()->all(),
            'generated_images' => $generatedImages->map(fn (CardImage $image): array => [
                'id' => $image->getKey(),
                'status' => $image->generation_status,
                'category' => $image->generation_category,
                'subcategory' => $image->generation_subcategory,
                'error' => $image->generation_error,
                'url' => $image->generation_status === CardImage::GENERATION_STATUS_COMPLETED
                    ? $this->temporaryUrl($image)
                    : null,
            ])->all(),
            'image_generation_summary' => [
                'total' => $generatedImages->count(),
                'queued' => $statusCounts->get(CardImage::GENERATION_STATUS_QUEUED, 0),
                'processing' => $statusCounts->get(CardImage::GENERATION_STATUS_PROCESSING, 0),
                'completed' => $statusCounts->get(CardImage::GENERATION_STATUS_COMPLETED, 0),
                'failed' => $statusCounts->get(CardImage::GENERATION_STATUS_FAILED, 0),
            ],
            'archive_prepared' => $hasGeneratedPhoto || (int) ($generation->export?->cost_zarks ?? 0) > 0,
        ];
    }

    /** @return array<string, mixed> */
    public function export(CardGeneration $generation): array
    {
        $card = $this->editor($generation);

        return [
            'generation_id' => $card['generation_id'],
            'card_id' => $card['id'],
            'marketplace' => $card['marketplace'],
            'title' => $card['title'],
            'description' => $card['description'],
            'category' => $card['selected_category'],
            'attributes' => $card['attributes'],
            'infographic_features' => $card['infographic_features'],
            'source_images' => $card['images'],
            'generated_images' => $card['generated_images'],
            'created_at' => $generation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, CardGeneration>  $generations
     * @return Collection<int, array{category_id: int, type_id: int|null, full_path: string}>
     */
    private function categories(Collection $generations): Collection
    {
        $resolved = collect();
        $ozonIds = $generations
            ->filter(fn (CardGeneration $generation): bool => $generation->card->marketplace === 'ozon')
            ->pluck('attributes_category_id')->filter()->unique()->values();
        $wbIds = $generations
            ->filter(fn (CardGeneration $generation): bool => $generation->card->marketplace === 'wildberries')
            ->pluck('attributes_category_id')->filter()->unique()->values();
        $ozonCategories = $ozonIds->isEmpty()
            ? collect()
            : OzonCategory::query()->whereIn('description_category_id', $ozonIds)->get();
        $wbCategories = $wbIds->isEmpty()
            ? collect()
            : WbCategory::query()->whereIn('subject_id', $wbIds)->get()->keyBy('subject_id');

        foreach ($generations as $generation) {
            $category = null;

            if ($generation->attributes_category_id !== null && $generation->card->marketplace === 'ozon') {
                $match = $ozonCategories->first(
                    fn (OzonCategory $candidate): bool => $candidate->description_category_id === $generation->attributes_category_id
                        && ($generation->attributes_type_id === null || $candidate->type_id === $generation->attributes_type_id),
                );

                if ($match !== null) {
                    $category = [
                        'category_id' => $match->description_category_id,
                        'type_id' => $match->type_id,
                        'full_path' => $match->full_path,
                    ];
                }
            } elseif ($generation->attributes_category_id !== null) {
                $match = $wbCategories->get($generation->attributes_category_id);

                if ($match !== null) {
                    $category = [
                        'category_id' => $match->subject_id,
                        'type_id' => null,
                        'full_path' => $match->full_path,
                    ];
                }
            }

            $category ??= $this->recommendedCategory($generation);

            if ($category !== null) {
                $resolved->put($generation->getKey(), $category);
            }
        }

        return $resolved;
    }

    /** @return array{category_id: int, type_id: int|null, full_path: string}|null */
    private function recommendedCategory(CardGeneration $generation): ?array
    {
        $recommendedId = (int) data_get($generation->category_match, 'recommended_id');
        $candidate = collect(data_get($generation->category_match, 'candidates', []))
            ->first(function (mixed $candidate) use ($generation, $recommendedId): bool {
                if (! is_array($candidate)) {
                    return false;
                }

                $candidateId = $generation->card->marketplace === 'ozon'
                    ? (int) ($candidate['description_category_id'] ?? 0)
                    : (int) ($candidate['subject_id'] ?? 0);

                return $candidateId === $recommendedId;
            });

        if (! is_array($candidate) || ! is_string($candidate['full_path'] ?? null)) {
            return null;
        }

        return [
            'category_id' => $recommendedId,
            'type_id' => $generation->card->marketplace === 'ozon'
                ? (int) ($candidate['type_id'] ?? 0) ?: null
                : null,
            'full_path' => $candidate['full_path'],
        ];
    }

    private function imageIsAvailable(CardImage $image): bool
    {
        return $image->type === CardImage::TYPE_USER_UPLOAD
            || $image->generation_status === CardImage::GENERATION_STATUS_COMPLETED;
    }

    private function temporaryUrl(CardImage $image): string
    {
        return Storage::disk('s3')->temporaryUrl($image->path, now()->addMinutes(30));
    }
}
