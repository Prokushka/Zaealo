<?php

use App\Models\Card;
use App\Models\CardGeneration;
use App\Models\MarketplaceApiKey;
use App\Models\User;
use App\Services\MarketplaceApiService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('normalizes the official Ozon category schema', function (): void {
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
    ]);
    $apiKey = new MarketplaceApiKey(['api_key' => 'test-key', 'client_id' => 'client-id']);
    $relation = Mockery::mock(HasMany::class);
    $relation->shouldReceive('where')->once()->with('marketplace', 'ozon')->andReturnSelf();
    $relation->shouldReceive('first')->once()->andReturn($apiKey);
    $user = Mockery::mock(User::class);
    $user->shouldReceive('marketplaceApiKeys')->once()->andReturn($relation);
    $card = new Card(['marketplace' => 'ozon']);
    $card->setRelation('user', $user);
    $generation = new CardGeneration([
        'attributes_category_id' => 100,
        'attributes_type_id' => 200,
    ]);
    $generation->setRelation('card', $card);

    $schema = app(MarketplaceApiService::class)->attributeSchema($generation);

    expect($schema)->toBe([[
        'key' => 'Материал',
        'id' => 1,
        'type' => 'String',
        'required' => true,
        'unit' => null,
        'dictionary_id' => null,
        'is_collection' => false,
    ]]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-seller.ozon.ru/v1/description-category/attribute'
        && $request->hasHeader('Client-Id', 'client-id')
        && $request->hasHeader('Api-Key', 'test-key')
        && $request['description_category_id'] === 100
        && $request['type_id'] === 200);
});
