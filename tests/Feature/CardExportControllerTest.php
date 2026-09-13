<?php

use App\Jobs\PublishMarketplaceCard;
use App\Models\CardExport;
use App\Models\CardGeneration;
use App\Models\CardImage;
use App\Models\User;
use App\Services\CardExportArchive;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function createExportableGeneration(User $user, bool $withApiKey = true): CardGeneration
{
    $card = $user->cards()->create([
        'marketplace' => 'ozon',
        'title' => 'Термокружка для кофе',
        'description' => 'Долго сохраняет температуру напитка.',
        'status' => 'ready',
    ]);
    $generation = CardGeneration::factory()->for($card, 'card')->create([
        'status' => 'completed',
        'attributes_category_id' => 17027425,
        'attributes_type_id' => 971391569,
        'attributes_data' => [
            'Бренд' => 'ZARQ',
            'Материал' => 'Сталь',
        ],
    ]);
    $card->images()->create([
        'generation_id' => $generation->getKey(),
        'path' => 'cards/source.png',
        'is_main' => true,
    ]);

    if ($withApiKey) {
        $user->marketplaceApiKeys()->create([
            'marketplace' => 'ozon',
            'api_key' => 'secret',
            'client_id' => 'client-id',
        ]);
    }

    return $generation;
}

function validPublicationPayload(): array
{
    return [
        'title' => 'Термокружка для кофе',
        'description' => 'Долго сохраняет температуру напитка.',
        'category_id' => 17027425,
        'type_id' => 971391569,
    ];
}

test('charges 25 zarks once for a photo archive without generated photos', function (): void {
    Queue::fake();
    Storage::fake('s3');
    $user = User::factory()->create(['balance' => 100]);
    $generation = createExportableGeneration($user, false);

    $response = $this->actingAs($user)->postJson(
        route('card-generations.exports.archive', $generation),
    );

    $response->assertSuccessful()
        ->assertJsonPath('cost_zarks', 25)
        ->assertJsonPath('balance', 75);
    expect($user->refresh()->balance)->toBe(75)
        ->and($user->transactions()->where('type', 'debit')->count())->toBe(1)
        ->and($generation->export()->value('cost_zarks'))->toBe(25)
        ->and($generation->export()->value('status'))->toBe(CardExport::STATUS_NOT_PUBLISHED);
    Queue::assertNothingPushed();

    $this->actingAs($user)->postJson(
        route('card-generations.exports.archive', $generation),
    )->assertSuccessful()
        ->assertJsonPath('cost_zarks', 0)
        ->assertJsonPath('balance', 75);

    expect($user->refresh()->balance)->toBe(75)
        ->and($user->transactions()->where('type', 'debit')->count())->toBe(1);
});

test('exports for free after one completed ai photo and builds one zip with all photos', function (): void {
    Queue::fake();
    Storage::fake('s3');
    Storage::disk('s3')->put('cards/source.png', 'source-image');
    Storage::disk('s3')->put('cards/generated.png', 'generated-image');
    $user = User::factory()->create(['balance' => 100]);
    $generation = createExportableGeneration($user, false);
    $generation->card->images()->create([
        'generation_id' => $generation->getKey(),
        'type' => CardImage::TYPE_AI_GENERATED,
        'generation_status' => CardImage::GENERATION_STATUS_COMPLETED,
        'path' => 'cards/generated.png',
        'is_paid' => true,
    ]);

    $response = $this->actingAs($user)->postJson(
        route('card-generations.exports.archive', $generation),
    );

    $response->assertSuccessful()
        ->assertJsonPath('cost_zarks', 0)
        ->assertJsonPath('balance', 100);
    expect($user->refresh()->balance)->toBe(100)
        ->and($user->transactions()->count())->toBe(0);

    $export = CardExport::query()->firstOrFail();
    $archive = app(CardExportArchive::class)->create($export);
    $zip = new ZipArchive;
    expect($zip->open($archive['path']))->toBeTrue()
        ->and($zip->numFiles)->toBe(2)
        ->and(collect(range(0, $zip->numFiles - 1))->map(fn (int $index): string|false => $zip->getNameIndex($index))->all())
        ->toContain('generated/01-generated.png', 'source/02-source.png');
    $zip->close();
    unlink($archive['path']);
});

test('returns a friendly error and creates no archive export when balance is insufficient', function (): void {
    Queue::fake();
    $user = User::factory()->create(['balance' => 10]);
    $generation = createExportableGeneration($user, false);

    $this->actingAs($user)->postJson(
        route('card-generations.exports.archive', $generation),
    )->assertUnprocessable()->assertJsonValidationErrors('balance');

    expect($user->refresh()->balance)->toBe(10)
        ->and(CardExport::query()->count())->toBe(0)
        ->and($user->transactions()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('publishes a draft without charging and uses category attributes', function (): void {
    Queue::fake();
    $user = User::factory()->create(['balance' => 100]);
    $generation = createExportableGeneration($user);

    $response = $this->actingAs($user)->postJson(
        route('card-generations.exports.publish', $generation),
        validPublicationPayload(),
    );

    $response->assertSuccessful()
        ->assertJsonPath('status', CardExport::STATUS_QUEUED)
        ->assertJsonPath('balance', 100);
    $export = $generation->export()->firstOrFail();
    expect($export->payload['attributes'])->toBe([
        'Бренд' => 'ZARQ',
        'Материал' => 'Сталь',
    ])->and($export->payload['images'])->toHaveCount(1)
        ->and($export->cost_zarks)->toBe(0)
        ->and($user->refresh()->balance)->toBe(100);
    Queue::assertPushed(PublishMarketplaceCard::class, 1);
});

test('does not allow bypassing the archive charge through a publication export', function (): void {
    Queue::fake();
    $user = User::factory()->create(['balance' => 100]);
    $generation = createExportableGeneration($user);

    $this->actingAs($user)->postJson(
        route('card-generations.exports.publish', $generation),
        validPublicationPayload(),
    )->assertSuccessful();

    $this->actingAs($user)->get(
        route('card-exports.download', $generation->export()->firstOrFail()),
    )->assertForbidden();

    expect($user->refresh()->balance)->toBe(100);
});

test('does not allow exporting another users generation', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $generation = createExportableGeneration($owner, false);

    $this->actingAs(User::factory()->create())->postJson(
        route('card-generations.exports.archive', $generation),
    )->assertNotFound();

    Queue::assertNothingPushed();
});
