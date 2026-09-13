<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\GeneratedProductCopyData;
use App\Enums\ZarkPrice;
use App\Models\CardGeneration;
use App\Models\CardImage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Throwable;

final class RegenerateCardText
{
    public function __construct(
        private AiTunnelService $aiTunnel,
        private PromptEngineer $promptEngineer,
        private ZarkWallet $wallet,
    ) {}

    public function handle(User $user, CardGeneration $source): CardGeneration
    {
        $source->loadMissing(['card', 'images']);

        if ($source->status !== 'completed') {
            throw ValidationException::withMessages([
                'card' => 'Перегенерация доступна только для завершённой карточки.',
            ]);
        }

        $sourceImages = $source->images
            ->where('type', CardImage::TYPE_USER_UPLOAD)
            ->values();

        if ($sourceImages->isEmpty()) {
            throw ValidationException::withMessages([
                'card' => 'У карточки нет исходного фото для перегенерации текста.',
            ]);
        }

        $generation = DB::transaction(function () use ($user, $source): CardGeneration {
            $generation = $source->card->generations()->create([
                'mode' => $source->mode,
                'selected_style' => $source->selected_style,
                'prompt_input' => $source->prompt_input,
                'category_match' => $source->category_match,
                'attributes_category_id' => $source->attributes_category_id,
                'attributes_type_id' => $source->attributes_type_id,
                'attributes_data' => $source->attributes_data,
                'status' => 'processing',
                'cost_zarks' => ZarkPrice::TextRegeneration,
            ]);

            $source->images
                ->filter(fn (CardImage $image): bool => $image->type === CardImage::TYPE_USER_UPLOAD
                    || ($image->type === CardImage::TYPE_AI_GENERATED
                        && $image->generation_status === CardImage::GENERATION_STATUS_COMPLETED))
                ->each(function (CardImage $image) use ($generation): void {
                    $generation->images()->create([
                        'card_id' => $generation->card_id,
                        'type' => $image->type,
                        'generation_status' => $image->generation_status,
                        'generation_category' => $image->generation_category,
                        'generation_subcategory' => $image->generation_subcategory,
                        'generation_features' => $image->generation_features,
                        'generation_error' => null,
                        'path' => $image->path,
                        'is_main' => $image->is_main,
                        'is_paid' => $image->is_paid,
                    ]);
                });

            $this->wallet->debit(
                $user,
                ZarkPrice::TextRegeneration,
                'Перегенерация текста товарной карточки',
                $generation,
            );

            return $generation;
        });

        try {
            $imageUrls = $sourceImages
                ->map(fn (CardImage $image): string => Storage::disk('s3')->temporaryUrl($image->path, now()->addMinutes(15)))
                ->all();
            $copy = $this->parseGeneratedCopy($this->aiTunnel->analyzeImages(
                $this->promptEngineer->regenerationPrompt([
                    'title' => $source->generated_title ?? $source->card->title,
                    'description' => $source->generated_description ?? $source->card->description,
                    'attributes' => $source->attributes_data ?? [],
                ], $source->card->marketplace),
                $imageUrls,
                AIChoice::QWEN_FLASH,
            ));

            DB::transaction(function () use ($generation, $copy): void {
                $generation->update([
                    'generated_title' => $copy->title,
                    'generated_description' => $copy->description,
                    'generated_bullets' => $copy->infographicFeatures,
                    'status' => 'completed',
                ]);
                $generation->card()->update([
                    'title' => $copy->title,
                    'description' => $copy->description,
                    'status' => 'ready',
                ]);
            });

            return $generation->refresh()->load(['card', 'images', 'export']);
        } catch (Throwable $exception) {
            $this->wallet->refund($generation, 'Возврат за неудачную перегенерацию текста');
            $generation->update(['status' => 'failed', 'cost_zarks' => 0]);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $response */
    private function parseGeneratedCopy(array $response): GeneratedProductCopyData
    {
        $content = data_get($response, 'choices.0.message.content');

        if (! is_string($content)) {
            throw new RuntimeException('Нейросеть вернула ответ в неизвестном формате.');
        }

        try {
            $decoded = json_decode(
                preg_replace('/^```(?:json)?\s*|\s*```$/u', '', trim($content)) ?? '',
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Нейросеть вернула некорректный JSON.', previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Нейросеть вернула некорректный JSON.');
        }

        $validated = Validator::make($decoded, [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:20000'],
            'infographic_features' => ['required', 'array', 'min:1'],
            'infographic_features.*' => ['required', 'string', 'max:120', 'distinct'],
        ])->validate();

        return GeneratedProductCopyData::fromValidated($validated);
    }
}
