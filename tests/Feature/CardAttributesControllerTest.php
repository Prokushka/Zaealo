<?php

use App\Models\CardGeneration;
use App\Models\CardImage;
use App\Models\OzonCategory;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function cardGenerationForAttributes(User $user): CardGeneration
{
    return CardGeneration::factory()
        ->for($user->cards()->create([
            'marketplace' => 'ozon',
            'title' => 'Термокружка',
            'description' => 'Стальная термокружка для напитков.',
            'status' => 'ready',
        ]), 'card')
        ->create();
}

function ozonAttributeCategory(): OzonCategory
{
    return OzonCategory::query()->create([
        'description_category_id' => 100,
        'type_id' => 200,
        'category_name' => 'Посуда',
        'type_name' => 'Термокружки',
        'full_path' => 'Посуда > Термокружки',
    ]);
}

test('fills and saves donor attributes through the API', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'api-seller.ozon.ru/v1/description-category/attribute' => Http::response([
            'result' => [
                ['id' => 1, 'name' => 'Материал', 'type' => 'String', 'is_required' => true],
                ['id' => 2, 'name' => 'ТН ВЭД коды ЕАЭС', 'type' => 'String', 'is_required' => true],
                ['id' => 3, 'name' => 'Цвет', 'type' => 'String', 'is_required' => false],
                ['id' => 4, 'name' => 'Бренд', 'type' => 'String', 'is_required' => false],
            ],
        ]),
        'api.zenrows.test/*' => Http::response([
            'xhr' => [[
                'url' => 'https://api.ozon.test/product/101',
                'body' => json_encode([
                    'characteristics' => [
                        ['name' => 'Материал', 'value' => 'Сталь'],
                        ['name' => 'brand', 'value' => 'Не сохранять'],
                        ['name' => 'price', 'value' => 1990],
                    ],
                ]),
            ]],
        ]),
    ]);
    config()->set('services.zenrows.key', 'test-key');
    config()->set('services.zenrows.base_url', 'https://api.zenrows.test/v1/');
    $user = User::factory()->create();
    $user->marketplaceApiKeys()->create([
        'marketplace' => 'ozon',
        'api_key' => 'ozon-key',
        'client_id' => 'client-id',
    ]);
    $generation = cardGenerationForAttributes($user);
    ozonAttributeCategory();

    $this->actingAs($user)
        ->postJson(route('card-generations.attributes.store', $generation), [
            'category_id' => 100,
            'type_id' => 200,
            'donor_url' => 'https://www.ozon.ru/product/termos-101/',
        ])
        ->assertSuccessful()
        ->assertJson(['attributes' => [
            'Материал' => 'Сталь',
            'brand' => 'Не сохранять',
            'price' => 1990,
        ]]);

    expect($generation->refresh()->attributes_data)->toBe([
        'Материал' => 'Сталь',
        'brand' => 'Не сохранять',
        'price' => 1990,
    ])
        ->and($generation->attributes_category_id)->toBe(100)
        ->and($generation->attributes_type_id)->toBe(200);
});

test('fills attributes from the official schema and main image through AI', function (): void {
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path): string => "https://storage.test/{$path}",
    );
    Http::preventStrayRequests();
    Http::fake([
        'api-seller.ozon.ru/v1/description-category/attribute' => Http::response([
            'result' => [[
                'id' => 1,
                'name' => 'Материал',
                'type' => 'String',
                'is_required' => true,
            ]],
        ]),
        'api.aitunnel.test/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => "```json\n{\"материал\":\"Сталь\",\"Лишнее\":\"Нет\"}\n```"]]],
        ]),
    ]);
    config()->set('services.aitunnel.key', 'test-key');
    config()->set('services.aitunnel.base_url', 'https://api.aitunnel.test');
    $user = User::factory()->create();
    $user->marketplaceApiKeys()->create([
        'marketplace' => 'ozon',
        'api_key' => 'ozon-key',
        'client_id' => 'client-id',
    ]);
    $generation = cardGenerationForAttributes($user);
    $generation->images()->create([
        'card_id' => $generation->card_id,
        'type' => CardImage::TYPE_USER_UPLOAD,
        'path' => 'cards/thermos.jpg',
        'is_main' => true,
    ]);
    ozonAttributeCategory();

    $this->actingAs($user)
        ->postJson(route('card-generations.attributes.store', $generation), [
            'category_id' => 100,
            'type_id' => 200,
        ])
        ->assertSuccessful()
        ->assertJson(['attributes' => ['Материал' => 'Сталь']]);

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://api.aitunnel.test/v1/chat/completions') {
            return false;
        }

        $content = $request['messages'][0]['content'];

        return $request['model'] === 'gpt-4o-mini'
            && str_contains($content[0]['text'], 'заполни как можно больше характеристик Ozon из schema')
            && $request['max_tokens'] === 4096
            && $content[1]['image_url']['url'] === 'https://storage.test/cards/thermos.jpg';
    });
});

test('drops AI attributes outside the category schema', function (): void {
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path): string => "https://storage.test/{$path}",
    );
    Http::preventStrayRequests();
    Http::fake([
        'api-seller.ozon.ru/v1/description-category/attribute' => Http::response([
            'result' => [[
                'id' => 1,
                'name' => 'Материал',
                'type' => 'String',
                'is_required' => true,
            ]],
        ]),
        'api.aitunnel.test/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '{"Несуществующее поле":"Сталь"}']]],
        ]),
    ]);
    config()->set('services.aitunnel.key', 'test-key');
    config()->set('services.aitunnel.base_url', 'https://api.aitunnel.test');
    $user = User::factory()->create();
    $user->marketplaceApiKeys()->create([
        'marketplace' => 'ozon',
        'api_key' => 'ozon-key',
        'client_id' => 'client-id',
    ]);
    $generation = cardGenerationForAttributes($user);
    $generation->images()->create([
        'card_id' => $generation->card_id,
        'type' => CardImage::TYPE_USER_UPLOAD,
        'path' => 'cards/thermos.jpg',
        'is_main' => true,
    ]);
    ozonAttributeCategory();

    $this->actingAs($user)
        ->postJson(route('card-generations.attributes.store', $generation), [
            'category_id' => 100,
            'type_id' => 200,
        ])
        ->assertSuccessful()
        ->assertJson(['attributes' => []]);

    expect($generation->refresh()->attributes_data)->toBe([]);
});

test('saves manually edited attributes through the API', function (): void {
    $user = User::factory()->create();
    $generation = cardGenerationForAttributes($user);

    $this->actingAs($user)
        ->patchJson(route('card-generations.attributes.update', $generation), [
            'attributes' => [
                'Материал' => 'Алюминий',
                'Цвет' => 'Серебристый',
                'Пустое поле' => ' ',
                'Удалённое поле' => null,
            ],
        ])
        ->assertSuccessful()
        ->assertJson([
            'attributes' => [
                'Материал' => 'Алюминий',
                'Цвет' => 'Серебристый',
            ],
        ]);

    expect($generation->refresh()->attributes_data)->toBe([
        'Материал' => 'Алюминий',
        'Цвет' => 'Серебристый',
    ]);
});

test('requires an authenticated owner', function (): void {
    $user = User::factory()->create();
    $generation = cardGenerationForAttributes($user);

    $this->postJson(route('card-generations.attributes.store', $generation), [])
        ->assertUnauthorized();
});

test('requires a valid category', function (): void {
    $user = User::factory()->create();
    $generation = cardGenerationForAttributes($user);

    $this->actingAs($user)
        ->postJson(route('card-generations.attributes.store', $generation), [
            'category_id' => 100,
            'type_id' => 200,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id');
});
