<?php

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\YandexOAuth;

beforeEach(function () {
    config()->set('services.yandex', [
        'client_id' => 'yandex-client-id',
        'client_secret' => 'yandex-client-secret',
        'redirect' => 'http://127.0.0.1:8000/auth/yandex/callback',
    ]);
});

test('yandex authentication redirect contains state and PKCE challenge', function () {
    $response = $this->get(route('yandex.redirect'));

    $response->assertRedirect();
    $response->assertSessionHas('yandex_oauth_state');
    $response->assertSessionHas('yandex_oauth_verifier');

    $query = [];
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query)
        ->toMatchArray([
            'client_id' => 'yandex-client-id',
            'redirect_uri' => 'http://127.0.0.1:8000/auth/yandex/callback',
            'response_type' => 'code',
            'code_challenge_method' => 'S256',
        ])
        ->and($query['state'])->toBe(session('yandex_oauth_state'))
        ->and($query['code_challenge'])->not->toBeEmpty();
});

test('users can authenticate through yandex using a Laravel session', function () {
    $yandex = $this->mock(YandexOAuth::class);
    $yandex->shouldReceive('user')
        ->once()
        ->with('authorization-code', 'pkce-verifier')
        ->andReturn([
            'id' => 'yandex-user-123',
            'real_name' => 'Иван Иванов',
            'default_email' => 'ivan@yandex.ru',
        ]);

    $response = $this
        ->withSession([
            'yandex_oauth_state' => 'expected-state',
            'yandex_oauth_verifier' => 'pkce-verifier',
        ])
        ->get(route('yandex.callback', [
            'code' => 'authorization-code',
            'state' => 'expected-state',
        ]));

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $account = SocialAccount::query()->where([
        'provider' => 'yandex',
        'provider_id' => 'yandex-user-123',
    ])->firstOrFail();

    expect($account->user->name)->toBe('Иван Иванов')
        ->and($account->user->email)->toBe('ivan@yandex.ru')
        ->and($account->user->email_verified_at)->not->toBeNull();
});

test('yandex account verifies and links an existing user with the same email', function () {
    $existingUser = User::factory()->unverified()->create(['email' => 'ivan@yandex.ru']);

    $yandex = $this->mock(YandexOAuth::class);
    $yandex->shouldReceive('user')->once()->andReturn([
        'id' => 'yandex-user-123',
        'real_name' => 'Иван Иванов',
        'default_email' => 'ivan@yandex.ru',
    ]);

    $this
        ->withSession([
            'yandex_oauth_state' => 'expected-state',
            'yandex_oauth_verifier' => 'pkce-verifier',
        ])
        ->get(route('yandex.callback', [
            'code' => 'authorization-code',
            'state' => 'expected-state',
        ]))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($existingUser);
    expect(User::query()->count())->toBe(1)
        ->and($existingUser->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($existingUser->socialAccounts()->where('provider', 'yandex')->exists())->toBeTrue();
});

test('yandex callback rejects an invalid state', function () {
    $yandex = $this->mock(YandexOAuth::class);
    $yandex->shouldNotReceive('user');

    $response = $this
        ->withSession([
            'yandex_oauth_state' => 'expected-state',
            'yandex_oauth_verifier' => 'pkce-verifier',
        ])
        ->get(route('yandex.callback', [
            'code' => 'authorization-code',
            'state' => 'forged-state',
        ]));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('yandex');
});

test('yandex callback handles a cancelled login', function () {
    $yandex = $this->mock(YandexOAuth::class);
    $yandex->shouldNotReceive('user');

    $response = $this
        ->withSession([
            'yandex_oauth_state' => 'expected-state',
            'yandex_oauth_verifier' => 'pkce-verifier',
        ])
        ->get(route('yandex.callback', [
            'error' => 'access_denied',
            'state' => 'expected-state',
        ]));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('yandex');
});
