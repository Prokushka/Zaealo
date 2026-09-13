<?php

use App\Jobs\GenerateCardImage;
use App\Models\CardGeneration;
use App\Models\CardImage;
use App\Models\OzonCategory;
use App\Models\User;
use App\Services\AIChoice;
use App\Services\AiTunnelService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function savedGeneration(User $user, array $generationAttributes = []): CardGeneration
{
    $card = $user->cards()->create([
        'marketplace' => 'ozon',
        'title' => 'Термокружка',
        'description' => 'Сохраняет температуру напитка.',
        'status' => 'ready',
    ]);
    $generation = CardGeneration::factory()->for($card, 'card')->create([
        'generated_title' => 'Термокружка',
        'generated_description' => 'Сохраняет температуру напитка.',
        'generated_bullets' => ['Сохраняет тепло'],
        'status' => 'completed',
        ...$generationAttributes,
    ]);
    $generation->images()->create([
        'card_id' => $card->getKey(),
        'type' => CardImage::TYPE_USER_UPLOAD,
        'path' => "cards/{$generation->getKey()}/source.jpg",
        'is_main' => true,
    ]);

    return $generation;
}

beforeEach(function (): void {
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path): string => "https://storage.test/{$path}",
    );
});

test('history contains only current user generations ordered newest first and paginated by twelve', function (): void {
    $user = User::factory()->create();
    $generations = collect(range(1, 13))->map(function (int $day) use ($user): CardGeneration {
        $generation = savedGeneration($user);
        $generation->forceFill(['created_at' => now()->subDays($day)])->saveQuietly();

        return $generation;
    });
    $otherGeneration = savedGeneration(User::factory()->create());

    $this->actingAs($user)
        ->get(route('card-history.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('CardHistory/Index')
            ->has('generations.data', 12)
            ->where('generations.data.0.id', $generations->first()->getKey())
            ->where('generations.total', 13)
            ->where('generations.data.0.preview_url', "https://storage.test/cards/{$generations->first()->getKey()}/source.jpg")
            ->where('generations.data', fn ($items): bool => $items->doesntContain('id', $otherGeneration->getKey())));
});

test('dashboard restores a saved generation for its owner', function (): void {
    $user = User::factory()->create();
    OzonCategory::query()->create([
        'description_category_id' => 100,
        'type_id' => 200,
        'category_name' => 'Посуда',
        'type_name' => 'Термокружки',
        'full_path' => 'Посуда > Термокружки',
    ]);
    $generation = savedGeneration($user, [
        'attributes_category_id' => 100,
        'attributes_type_id' => 200,
        'attributes_data' => ['Материал' => 'Сталь'],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['card_id' => $generation->getKey()]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('card.generation_id', $generation->getKey())
            ->where('card.is_editing', true)
            ->where('card.selected_category.full_path', 'Посуда > Термокружки')
            ->where('card.attributes.Материал', 'Сталь')
            ->has('card.images', 1));

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', ['card_id' => $generation->getKey()]))
        ->assertNotFound();
});

test('owner can save title and description in a completed generation', function (): void {
    $user = User::factory()->create();
    $generation = savedGeneration($user);

    $this->actingAs($user)
        ->patchJson(route('cards.update', $generation), [
            'title' => 'Новое название',
            'description' => 'Новое описание.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Изменения сохранены.');

    expect($generation->refresh()->generated_title)->toBe('Новое название')
        ->and($generation->generated_description)->toBe('Новое описание.')
        ->and($generation->card->refresh()->title)->toBe('Новое название');
});

test('text regeneration charges twenty five zarks and creates a separate version', function (): void {
    $user = User::factory()->create(['balance' => 100]);
    $source = savedGeneration($user, [
        'attributes_category_id' => 100,
        'attributes_type_id' => 200,
        'attributes_data' => ['Материал' => 'Сталь'],
    ]);
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldReceive('analyzeImages')
        ->once()
        ->withArgs(fn (string $prompt, array $urls, AIChoice $model): bool => $model === AIChoice::QWEN_FLASH
            && str_contains($prompt, 'Термокружка')
            && $urls === ["https://storage.test/cards/{$source->getKey()}/source.jpg"])
        ->andReturn([
            'choices' => [['message' => ['content' => json_encode([
                'title' => 'Обновлённая термокружка',
                'description' => 'Новое продающее описание.',
                'infographic_features' => ['Тепло надолго'],
            ], JSON_UNESCAPED_UNICODE)]]],
        ]);
    app()->instance(AiTunnelService::class, $aiTunnel);

    $response = $this->actingAs($user)
        ->postJson(route('cards.regenerate-text', $source));

    $response->assertSuccessful()
        ->assertJsonPath('balance', 75)
        ->assertJsonPath('card.title', 'Обновлённая термокружка');
    $newGeneration = CardGeneration::query()->findOrFail($response->json('generation_id'));

    expect($newGeneration->getKey())->not->toBe($source->getKey())
        ->and($newGeneration->attributes_data)->toBe(['Материал' => 'Сталь'])
        ->and($newGeneration->images()->count())->toBe(1)
        ->and($source->refresh()->generated_title)->toBe('Термокружка')
        ->and($user->refresh()->balance)->toBe(75)
        ->and($user->transactions()->where('type', 'debit')->count())->toBe(1);
});

test('text regeneration returns 422 without creating a version when balance is insufficient', function (): void {
    $user = User::factory()->create(['balance' => 20]);
    $source = savedGeneration($user);
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldNotReceive('analyzeImages');
    app()->instance(AiTunnelService::class, $aiTunnel);

    $this->actingAs($user)
        ->postJson(route('cards.regenerate-text', $source))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('balance');

    expect($source->card->generations()->count())->toBe(1)
        ->and($user->refresh()->balance)->toBe(20)
        ->and($user->transactions()->count())->toBe(0);
});

test('text regeneration refunds its charge when the ai provider fails', function (): void {
    $user = User::factory()->create(['balance' => 100]);
    $source = savedGeneration($user);
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldReceive('analyzeImages')->once()->andThrow(new RuntimeException('Provider failed'));
    app()->instance(AiTunnelService::class, $aiTunnel);

    $this->actingAs($user)
        ->postJson(route('cards.regenerate-text', $source))
        ->assertServerError();

    $failedGeneration = $source->card->generations()->latest('id')->firstOrFail();

    expect($failedGeneration->status)->toBe('failed')
        ->and($failedGeneration->cost_zarks)->toBe('0.00')
        ->and($user->refresh()->balance)->toBe(100)
        ->and($user->transactions()->where('type', 'debit')->count())->toBe(1)
        ->and($user->transactions()->where('type', 'credit')->count())->toBe(1);
});

test('saved card image endpoint charges fifty zarks per selected image', function (): void {
    Queue::fake();
    $user = User::factory()->create(['balance' => 150]);
    $generation = savedGeneration($user);

    $this->actingAs($user)
        ->postJson(route('cards.regenerate-images', $generation), [
            'scenarios' => [
                ['category' => 'hero', 'subcategory' => 'clean-background'],
                ['category' => 'features', 'subcategory' => 'macro-details'],
            ],
            'infographic_features' => ['Сохраняет тепло'],
        ])
        ->assertAccepted()
        ->assertJsonPath('queued', 2)
        ->assertJsonPath('charged_zarks', 100)
        ->assertJsonPath('balance', 50);

    expect($generation->images()->where('type', CardImage::TYPE_AI_GENERATED)->count())->toBe(2);
    Queue::assertPushed(GenerateCardImage::class, 2);
});

test('saved card image endpoint returns 422 and queues nothing when balance is insufficient', function (): void {
    Queue::fake();
    $user = User::factory()->create(['balance' => 49]);
    $generation = savedGeneration($user);

    $this->actingAs($user)
        ->postJson(route('cards.regenerate-images', $generation), [
            'scenarios' => [
                ['category' => 'hero', 'subcategory' => 'clean-background'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('balance');

    expect($generation->images()->where('type', CardImage::TYPE_AI_GENERATED)->count())->toBe(0)
        ->and($user->refresh()->balance)->toBe(49)
        ->and($user->transactions()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('json export is free and unavailable to another user', function (): void {
    $user = User::factory()->create(['balance' => 100]);
    $generation = savedGeneration($user, ['attributes_data' => ['Материал' => 'Сталь']]);

    $response = $this->actingAs($user)
        ->get(route('card-generations.exports.json', $generation));

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/json; charset=UTF-8')
        ->assertDownload("card-{$generation->card_id}-generation-{$generation->getKey()}.json");
    expect(json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray([
            'generation_id' => $generation->getKey(),
            'title' => 'Термокружка',
            'attributes' => ['Материал' => 'Сталь'],
        ])
        ->and($user->refresh()->balance)->toBe(100)
        ->and($user->transactions()->count())->toBe(0);

    $this->actingAs(User::factory()->create())
        ->get(route('card-generations.exports.json', $generation))
        ->assertNotFound();
});
