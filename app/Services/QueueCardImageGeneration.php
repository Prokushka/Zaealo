<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PricingKey;
use App\Exceptions\InsufficientZarks;
use App\Jobs\GenerateCardImage;
use App\Models\CardGeneration;
use App\Models\CardImage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class QueueCardImageGeneration
{
    public function __construct(
        private ZarkWallet $wallet,
        private PricingCatalog $pricing,
    ) {}

    /**
     * @param  list<array{category: string, subcategory: string}>  $scenarios
     * @param  list<string>  $features
     * @return array{image_ids: list<int>, charged_zarks: int}
     *
     * @throws InsufficientZarks
     */
    public function handle(User $user, CardGeneration $generation, array $scenarios, array $features): array
    {
        $generation->loadMissing('card');
        $imageCost = $this->pricing->cost(PricingKey::ImageGeneration);
        $required = count($scenarios) * $imageCost;

        $imageIds = DB::transaction(function () use ($user, $generation, $scenarios, $features, $required, $imageCost): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->balance < $required) {
                throw new InsufficientZarks($required, $lockedUser->balance);
            }

            return collect($scenarios)->map(function (array $scenario) use ($user, $generation, $features, $imageCost): int {
                $image = $generation->card->images()->create([
                    'generation_id' => $generation->getKey(),
                    'type' => CardImage::TYPE_AI_GENERATED,
                    'generation_status' => CardImage::GENERATION_STATUS_QUEUED,
                    'generation_category' => $scenario['category'],
                    'generation_subcategory' => $scenario['subcategory'],
                    'generation_features' => $features,
                    'path' => 'pending/'.Str::uuid().'.png',
                    'is_paid' => true,
                ]);

                $this->wallet->debit(
                    $user,
                    $imageCost,
                    'Генерация фото товарной карточки',
                    $image,
                );

                return $image->getKey();
            })->all();
        });

        foreach ($imageIds as $imageId) {
            GenerateCardImage::dispatch($imageId)
                ->onQueue('image-generation')
                ->afterCommit();
        }

        return ['image_ids' => $imageIds, 'charged_zarks' => $required];
    }
}
