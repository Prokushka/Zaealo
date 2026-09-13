<?php

use App\MarketplaceApiKeyValidator;
use App\Models\MarketplaceApiKey;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

test('guests cannot manage api integrations', function (): void {
    $this->get(route('integrations.index'))->assertRedirect(route('login'));
    $this->put(route('integrations.update', 'wildberries'), ['api_key' => 'secret'])
        ->assertRedirect(route('login'));
    $this->delete(route('integrations.destroy', 'wildberries'))
        ->assertRedirect(route('login'));
});

test('integration page exposes only key connection statuses', function (): void {
    $user = User::factory()->create();
    MarketplaceApiKey::factory()->for($user)->create([
        'marketplace' => 'wildberries',
        'api_key' => 'wildberries-secret',
    ]);

    $this->actingAs($user)
        ->get(route('integrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Integrations')
            ->where('integrations.wildberries.has_key', true)
            ->where('integrations.ozon.has_key', false)
            ->missing('integrations.wildberries.api_key'));
});

test('user can save an encrypted marketplace api key', function (): void {
    Http::fake(['*' => Http::response(['Status' => 'OK'])]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('integrations.index'))
        ->put(route('integrations.update', 'wildberries'), ['api_key' => 'wildberries-secret'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('integrations.index'));

    $apiKey = MarketplaceApiKey::query()->firstOrFail();

    expect($apiKey->api_key)->toBe('wildberries-secret')
        ->and(DB::table('marketplace_api_keys')->value('api_key'))->not->toBe('wildberries-secret');
});

test('user can replace and delete only their own marketplace api key', function (): void {
    Http::fake(['*' => Http::response(['roles' => []])]);
    $user = User::factory()->create();
    $otherUserApiKey = MarketplaceApiKey::factory()->create([
        'marketplace' => 'ozon',
        'api_key' => 'other-user-secret',
    ]);

    $this->actingAs($user)
        ->from(route('integrations.index'))
        ->put(route('integrations.update', 'ozon'), [
            'api_key' => 'first-secret',
            'client_id' => 'first-client-id',
        ])
        ->assertRedirect(route('integrations.index'));

    $this->actingAs($user)
        ->from(route('integrations.index'))
        ->put(route('integrations.update', 'ozon'), [
            'api_key' => 'second-secret',
            'client_id' => 'second-client-id',
        ])
        ->assertRedirect(route('integrations.index'));

    $this->actingAs($user)
        ->from(route('integrations.index'))
        ->delete(route('integrations.destroy', 'ozon'))
        ->assertRedirect(route('integrations.index'));

    expect(MarketplaceApiKey::query()->where('user_id', $user->id)->doesntExist())
        ->toBeTrue()
        ->and($otherUserApiKey->fresh()->api_key)->toBe('other-user-secret');
});

test('api key is required and marketplace must be supported', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('integrations.index'))
        ->put(route('integrations.update', 'wildberries'), ['api_key' => ''])
        ->assertSessionHasErrors('api_key')
        ->assertRedirect(route('integrations.index'));

    $this->actingAs($user)
        ->put('/integrations/unsupported', ['api_key' => 'secret'])
        ->assertNotFound();
});

test('marketplace key status is returned without exposing the key', function (): void {
    $user = User::factory()->create();
    MarketplaceApiKey::factory()->for($user)->create([
        'marketplace' => 'ozon',
        'api_key' => 'ozon-secret',
        'client_id' => 'ozon-client-id',
    ]);

    $this->actingAs($user)
        ->get(route('integrations.status', 'ozon'))
        ->assertOk()
        ->assertJson(['has_key' => true])
        ->assertJsonMissing(['api_key' => 'ozon-secret']);

    $this->actingAs($user)
        ->get(route('integrations.status', 'wildberries'))
        ->assertOk()
        ->assertJson(['has_key' => false]);
});

test('saving an api key from dashboard redirects back to dashboard', function (): void {
    Http::fake(['*' => Http::response(['Status' => 'OK'])]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->put(route('integrations.update', 'wildberries'), ['api_key' => 'wildberries-secret'])
        ->assertRedirect(route('dashboard'));
});

test('ozon requires a client id and verifies credentials before saving', function (): void {
    Http::fake(['*' => Http::response(['roles' => []])]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('integrations.index'))
        ->put(route('integrations.update', 'ozon'), ['api_key' => 'ozon-secret'])
        ->assertSessionHasErrors('client_id');

    $this->actingAs($user)
        ->from(route('integrations.index'))
        ->put(route('integrations.update', 'ozon'), [
            'api_key' => 'ozon-secret',
            'client_id' => 'ozon-client-id',
        ])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-seller.ozon.ru/v1/seller/info'
        && $request->hasHeader('Client-Id', 'ozon-client-id')
        && $request->hasHeader('Api-Key', 'ozon-secret')
        && $request->hasHeader('Content-Type', 'application/json')
        && $request->body() === '{}');
});

test('reports credentials rejected by the marketplace', function (): void {
    Http::fake(fn () => Http::response([], 401));

    expect(app(MarketplaceApiKeyValidator::class)->validationError('wildberries', 'invalid-token'))
        ->toBe('Маркетплейс не принял ключ или у него недостаточно прав доступа.');
});

test('reports an unsigned Ozon offer separately from invalid credentials', function (): void {
    Http::fake(fn () => Http::response(['message' => 'Offer not signed'], 403));

    expect(app(MarketplaceApiKeyValidator::class)->validationError('ozon', 'ozon-key', 'ozon-client-id'))
        ->toBe('Ozon ограничил доступ к Seller API: примите оферту в личном кабинете продавца.');
});

test('reports an Ozon Seller API access restriction without assuming a missing role', function (): void {
    Http::fake(fn () => Http::response([], 403));

    expect(app(MarketplaceApiKeyValidator::class)->validationError('ozon', 'ozon-key', 'ozon-client-id'))
        ->toBe('Ozon ограничил доступ к Seller API. Проверьте Client ID, статус ключа и ограничения IP в личном кабинете Ozon.');
});

test('reports the Ozon access error returned by the API', function (): void {
    Http::fake(fn () => Http::response(['message' => 'Api-Key is restricted to specific IP addresses'], 403));

    expect(app(MarketplaceApiKeyValidator::class)->validationError('ozon', 'ozon-key', 'ozon-client-id'))
        ->toBe('Ozon ограничил доступ к Seller API: Api-Key is restricted to specific IP addresses');
});
