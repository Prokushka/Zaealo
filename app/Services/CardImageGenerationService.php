<?php

declare(strict_types=1);

namespace App\Services;

use App\ImagePrompts\ImagePromptFactory;
use App\Models\CardGeneration;
use App\Models\CardImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CardImageGenerationService
{
    public function __construct(private AiTunnelService $aiTunnel) {}

    public function generate(int $cardImageId): void
    {
        $cardImage = CardImage::query()
            ->with('generation.card')
            ->findOrFail($cardImageId);
        $generation = $cardImage->generation;

        if ($generation === null || $cardImage->type !== CardImage::TYPE_AI_GENERATED) {
            throw new RuntimeException('Недоступное задание генерации изображения.');
        }

        $cardImage->update(['generation_status' => CardImage::GENERATION_STATUS_PROCESSING]);
        $prompt = $this->buildPrompt(
            $generation,
            (string) $cardImage->generation_category,
            (string) $cardImage->generation_subcategory,
            $this->infographicFeatures($cardImage),
        );
        $referenceImage = $this->referenceImage($generation);
        $image = $this->aiTunnel->editImage(
            $prompt,
            Storage::disk('s3')->get($referenceImage->path),
            basename($referenceImage->path),
        );
        $path = "card-generations/{$generation->getKey()}/ai/".Str::uuid().".{$image->extension}";

        if (! Storage::disk('s3')->put($path, $image->contents, ['ContentType' => 'image/png'])) {
            throw new RuntimeException('Не удалось сохранить сгенерированное изображение.');
        }

        $cardImage->update([
            'path' => $path,
            'generation_status' => CardImage::GENERATION_STATUS_COMPLETED,
            'generation_error' => null,
        ]);
    }

    /** @return list<string> */
    private function infographicFeatures(CardImage $cardImage): array
    {
        return collect($cardImage->generation_features ?? [])
            ->filter(fn (mixed $feature): bool => is_string($feature) && $feature !== '')
            ->values()
            ->all();
    }

    private function referenceImage(CardGeneration $generation): CardImage
    {
        $referenceImage = $generation->images()
            ->where('type', CardImage::TYPE_USER_UPLOAD)
            ->orderByDesc('is_main')
            ->oldest('id')
            ->first();

        if ($referenceImage === null) {
            throw new RuntimeException('Не найдено исходное фото товара для генерации.');
        }

        return $referenceImage;
    }

    /** @param list<string> $infographicFeatures */
    private function buildPrompt(
        CardGeneration $generation,
        string $category,
        string $subcategory,
        array $infographicFeatures,
    ): string {
        $productContext = array_filter([
            $generation->generated_title !== null && $generation->generated_title !== ''
                ? "Название товара: {$generation->generated_title}."
                : null,
            in_array($category, ['features', 'infographics'], true) && $infographicFeatures !== []
                ? 'Необязательный фоновый контекст о товаре: '.implode(', ', $infographicFeatures).'. Это лишь набор ориентиров, а не преимущества, не факты для надписей и не инструкция использовать их в кадре. Не воспроизводи и не перефразируй эти слова автоматически. Сам выбери самую сильную и уместную идею для слайда: можешь опереться на отдельные ориентиры, если это действительно улучшит визуал, либо полностью их проигнорировать. Допускаются только умеренные, правдоподобные преимущества; не придумывай точные параметры, сертификаты, гарантии, состав или неподтверждённые обещания.'
                : null,
        ]);

        return implode("\n\n", [
            ImagePromptFactory::make(
                $category,
                $subcategory,
                $generation->card->marketplace,
            )->generate(),
            ...$productContext,
        ]);
    }
}
