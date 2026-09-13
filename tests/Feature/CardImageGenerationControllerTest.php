<?php

use App\Events\CardImageGenerationFailed;
use App\Jobs\GenerateCardImage;
use App\Models\CardGeneration;
use App\Models\CardImage;
use App\Models\User;
use App\Services\ZarkWallet;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('queues one image generation job for every selected scenario', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $generation = CardGeneration::factory()
        ->for($user->cards()->create([
            'marketplace' => 'ozon',
            'title' => 'Термокружка',
            'status' => 'ready',
        ]), 'card')
        ->create(['generated_title' => 'Термокружка']);

    $response = $this->actingAs($user)->post(
        route('card-generations.images.store', $generation),
        [
            'scenarios' => [
                ['category' => 'hero', 'subcategory' => 'clean-background'],
                ['category' => 'features', 'subcategory' => 'macro-details'],
            ],
            'infographic_features' => ['Сохраняет тепло'],
        ],
    );

    $response->assertSessionHasNoErrors()
        ->assertSessionHas('ai_photo_generation.queued', 2)
        ->assertRedirect();

    Queue::assertPushed(GenerateCardImage::class, 2);
    expect($user->refresh()->balance)->toBe(50)
        ->and($generation->images()->where('is_paid', true)->count())->toBe(2)
        ->and($user->transactions()->where('type', 'debit')->count())->toBe(2);
    Queue::assertPushed(
        GenerateCardImage::class,
        fn (GenerateCardImage $job): bool => CardImage::query()
            ->whereKey($job->cardImageId)
            ->where('generation_id', $generation->getKey())
            ->where('generation_category', 'hero')
            ->where('generation_subcategory', 'clean-background')
            ->whereJsonContains('generation_features', 'Сохраняет тепло')
            ->exists(),
    );
});

test('does not queue images and shows a balance error when zarks are insufficient', function (): void {
    Queue::fake();
    $user = User::factory()->create(['balance' => 75]);
    $generation = CardGeneration::factory()
        ->for($user->cards()->create([
            'marketplace' => 'ozon',
            'title' => 'Термокружка',
            'status' => 'ready',
        ]), 'card')
        ->create();

    $this->actingAs($user)->post(
        route('card-generations.images.store', $generation),
        [
            'scenarios' => [
                ['category' => 'hero', 'subcategory' => 'clean-background'],
                ['category' => 'features', 'subcategory' => 'macro-details'],
            ],
        ],
    )->assertSessionHasErrors([
        'balance' => 'Недостаточно ZARQ: для 2 фото нужно 100, на балансе 75. Уберите часть фото или пополните баланс.',
    ]);

    expect($user->refresh()->balance)->toBe(75)
        ->and($generation->images()->count())->toBe(0)
        ->and($user->transactions()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('returns current generation progress and temporary urls for completed photos', function (): void {
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path): string => "https://storage.test/{$path}",
    );
    $user = User::factory()->create();
    $generation = CardGeneration::factory()
        ->for($user->cards()->create([
            'marketplace' => 'ozon',
            'title' => 'Термокружка',
            'status' => 'ready',
        ]), 'card')
        ->create();
    $generation->images()->create([
        'card_id' => $generation->card_id,
        'type' => CardImage::TYPE_AI_GENERATED,
        'generation_status' => CardImage::GENERATION_STATUS_COMPLETED,
        'generation_category' => 'hero',
        'generation_subcategory' => 'clean-background',
        'path' => 'card-generations/1/ai/photo.webp',
    ]);
    $generation->images()->create([
        'card_id' => $generation->card_id,
        'type' => CardImage::TYPE_AI_GENERATED,
        'generation_status' => CardImage::GENERATION_STATUS_QUEUED,
        'generation_category' => 'features',
        'generation_subcategory' => 'macro-details',
        'path' => 'pending/photo.webp',
    ]);

    $this->actingAs($user)
        ->getJson(route('card-generations.images.index', $generation))
        ->assertSuccessful()
        ->assertJsonPath('summary.total', 2)
        ->assertJsonPath('summary.queued', 1)
        ->assertJsonPath('summary.completed', 1)
        ->assertJsonFragment([
            'url' => 'https://storage.test/card-generations/1/ai/photo.webp',
        ]);
});

test('does not allow generating images for another users card', function (): void {
    Queue::fake();
    $generation = CardGeneration::factory()->create();

    $this->actingAs(User::factory()->create())->post(
        route('card-generations.images.store', $generation),
        ['scenarios' => [['category' => 'hero', 'subcategory' => 'clean-background']]],
    )->assertNotFound();

    Queue::assertNothingPushed();
});

test('refunds a paid image exactly once after permanent generation failure', function (): void {
    $user = User::factory()->create(['balance' => 100]);
    $generation = CardGeneration::factory()
        ->for($user->cards()->create([
            'marketplace' => 'ozon',
            'title' => 'Термокружка',
            'status' => 'ready',
        ]), 'card')
        ->create();
    $image = $generation->card->images()->create([
        'generation_id' => $generation->getKey(),
        'type' => CardImage::TYPE_AI_GENERATED,
        'generation_status' => CardImage::GENERATION_STATUS_QUEUED,
        'path' => 'pending/image.png',
        'is_paid' => true,
    ]);
    app(ZarkWallet::class)->debit($user, 50, 'Генерация фото', $image);

    CardImageGenerationFailed::dispatch($image->getKey(), 'Ошибка провайдера');
    CardImageGenerationFailed::dispatch($image->getKey(), 'Повторная ошибка');

    expect($user->refresh()->balance)->toBe(100)
        ->and($image->refresh()->is_paid)->toBeFalse()
        ->and($image->generation_status)->toBe(CardImage::GENERATION_STATUS_FAILED)
        ->and($user->transactions()->where('type', 'credit')->count())->toBe(1);
});

test('rejects a subcategory that does not belong to its category', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $generation = CardGeneration::factory()
        ->for($user->cards()->create([
            'marketplace' => 'ozon',
            'title' => 'Термокружка',
            'status' => 'ready',
        ]), 'card')
        ->create();

    $this->actingAs($user)->post(
        route('card-generations.images.store', $generation),
        ['scenarios' => [['category' => 'hero', 'subcategory' => 'macro-details']]],
    )->assertSessionHasErrors('scenarios.0');

    Queue::assertNothingPushed();
});
