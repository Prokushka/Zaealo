<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PricingKey;
use App\Exceptions\InsufficientZarks;
use App\Models\CardExport;
use App\Models\CardGeneration;
use App\Models\CardImage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CardExportService
{
    public function __construct(
        private ZarkWallet $wallet,
        private PricingCatalog $pricing,
    ) {}

    /** @return array{export: CardExport, charged_zarks: int} */
    public function prepareArchive(CardGeneration $generation, User $user): array
    {
        $this->ensureNoPendingImages($generation);

        try {
            return DB::transaction(function () use ($generation, $user): array {
                $lockedGeneration = $this->lockedGeneration($generation);
                $this->ensureLoadedGenerationHasNoPendingImages($lockedGeneration);
                $export = $this->firstOrCreateExport($lockedGeneration);
                $hasGeneratedPhoto = $this->hasGeneratedPhoto($lockedGeneration);
                $cost = ! $hasGeneratedPhoto && $export->cost_zarks === 0
                    ? $this->pricing->cost(PricingKey::CardExport)
                    : 0;

                $export->update([
                    'payload' => $this->payload($lockedGeneration),
                    'cost_zarks' => $export->cost_zarks + $cost,
                ]);

                if ($cost > 0) {
                    $this->wallet->debit(
                        $user,
                        $cost,
                        'Скачивание ZIP-архива товарной карточки без ИИ-фото',
                        $export,
                    );
                }

                return ['export' => $export->refresh(), 'charged_zarks' => $cost];
            });
        } catch (InsufficientZarks $exception) {
            $cost = $this->pricing->cost(PricingKey::CardExport);

            throw ValidationException::withMessages([
                'balance' => "Недостаточно ZARQ: ZIP без ИИ-фото стоит {$cost}, на балансе {$exception->available}. Сгенерируйте хотя бы одно фото — тогда скачивание будет бесплатным — или пополните баланс.",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{export: CardExport, should_dispatch: bool}
     */
    public function publish(CardGeneration $generation, User $user, array $validated): array
    {
        $generation->loadMissing('card');
        $this->ensureNoPendingImages($generation);
        $this->ensureApiKeyExists($generation, $user);

        return DB::transaction(function () use ($generation, $validated): array {
            $lockedGeneration = $this->lockedGeneration($generation);
            $this->ensureLoadedGenerationHasNoPendingImages($lockedGeneration);
            $this->updateCategoryData($lockedGeneration, $validated);
            $export = $this->firstOrCreateExport($lockedGeneration);

            if (in_array($export->status, [
                CardExport::STATUS_SUBMITTING,
                CardExport::STATUS_WAITING,
                CardExport::STATUS_COMPLETED,
            ], true)) {
                return ['export' => $export, 'should_dispatch' => false];
            }

            $export->update([
                'payload' => $this->payload($lockedGeneration),
                'status' => $export->external_task_id === null
                    ? CardExport::STATUS_QUEUED
                    : CardExport::STATUS_WAITING,
                'error' => null,
                'status_checks' => 0,
            ]);

            return ['export' => $export->refresh(), 'should_dispatch' => true];
        });
    }

    private function firstOrCreateExport(CardGeneration $generation): CardExport
    {
        return CardExport::query()
            ->whereBelongsTo($generation, 'generation')
            ->lockForUpdate()
            ->first() ?? $generation->export()->create([
                'marketplace' => $generation->card->marketplace,
                'status' => CardExport::STATUS_NOT_PUBLISHED,
                'payload' => $this->payload($generation),
            ]);
    }

    private function lockedGeneration(CardGeneration $generation): CardGeneration
    {
        return CardGeneration::query()
            ->with(['card', 'images'])
            ->lockForUpdate()
            ->findOrFail($generation->getKey());
    }

    private function ensureNoPendingImages(CardGeneration $generation): void
    {
        $hasPendingImages = $generation->images()
            ->where('type', CardImage::TYPE_AI_GENERATED)
            ->whereIn('generation_status', [
                CardImage::GENERATION_STATUS_QUEUED,
                CardImage::GENERATION_STATUS_PROCESSING,
            ])
            ->exists();

        if ($hasPendingImages) {
            throw ValidationException::withMessages([
                'export' => 'Дождитесь завершения генерации всех ИИ-фото.',
            ]);
        }
    }

    private function ensureLoadedGenerationHasNoPendingImages(CardGeneration $generation): void
    {
        if ($generation->images->contains(
            fn (CardImage $image): bool => $image->type === CardImage::TYPE_AI_GENERATED
                && in_array($image->generation_status, [
                    CardImage::GENERATION_STATUS_QUEUED,
                    CardImage::GENERATION_STATUS_PROCESSING,
                ], true),
        )) {
            throw ValidationException::withMessages([
                'export' => 'Дождитесь завершения генерации всех ИИ-фото.',
            ]);
        }
    }

    private function ensureApiKeyExists(CardGeneration $generation, User $user): void
    {
        $apiKey = $user->marketplaceApiKeys()
            ->where('marketplace', $generation->card->marketplace)
            ->first();

        if ($apiKey === null || ! filled($apiKey->api_key)
            || ($generation->card->marketplace === 'ozon' && ! filled($apiKey->client_id))) {
            throw ValidationException::withMessages([
                'export' => 'Подключите действующий API-ключ маркетплейса перед публикацией.',
            ]);
        }
    }

    /** @param array<string, mixed> $validated */
    private function updateCategoryData(CardGeneration $generation, array $validated): void
    {
        $generation->card->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
        ]);
        $generation->update([
            'generated_title' => $validated['title'],
            'generated_description' => $validated['description'],
            'attributes_category_id' => $validated['category_id'],
            'attributes_type_id' => $generation->card->marketplace === 'ozon'
                ? $validated['type_id']
                : null,
        ]);
    }

    private function hasGeneratedPhoto(CardGeneration $generation): bool
    {
        return $generation->images
            ->where('type', CardImage::TYPE_AI_GENERATED)
            ->where('generation_status', CardImage::GENERATION_STATUS_COMPLETED)
            ->isNotEmpty();
    }

    /** @return array<string, mixed> */
    private function payload(CardGeneration $generation): array
    {
        $images = $generation->images
            ->filter(fn (CardImage $image): bool => $image->type === CardImage::TYPE_USER_UPLOAD
                || ($image->type === CardImage::TYPE_AI_GENERATED
                    && $image->generation_status === CardImage::GENERATION_STATUS_COMPLETED))
            ->sortBy(fn (CardImage $image): string => sprintf(
                '%d-%010d',
                $image->type === CardImage::TYPE_AI_GENERATED ? 0 : 1,
                $image->getKey(),
            ))
            ->map(fn (CardImage $image): array => [
                'id' => $image->getKey(),
                'type' => $image->type,
                'path' => $image->path,
                'category' => $image->generation_category,
                'subcategory' => $image->generation_subcategory,
            ])
            ->values()
            ->all();

        return [
            'title' => $generation->generated_title ?: $generation->card->title,
            'description' => $generation->generated_description ?: $generation->card->description,
            'category_id' => $generation->attributes_category_id,
            'type_id' => $generation->attributes_type_id,
            'seller_sku' => "zarq-{$generation->card_id}-{$generation->getKey()}",
            'attributes' => $generation->attributes_data ?? [],
            'images' => $images,
        ];
    }
}
