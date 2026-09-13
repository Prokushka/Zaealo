<?php

use App\Models\SocialAccount;
use App\Services\TelegramOidc;

beforeEach(function () {
    config()->set('services.telegram', [
        'client_id' => '8855417827',
        'client_secret' => 'telegram-client-secret',
        'redirect' => 'http://127.0.0.1:8000/auth/telegram/callback',
    ]);
});

test('telegram authentication redirect contains state and PKCE challenge', function () {
    $response = $this->get(route('telegram.redirect'));

    $response->assertRedirect();
    $response->assertSessionHas('telegram_oauth_state');
    $response->assertSessionHas('telegram_oauth_verifier');

    $query = [];
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query)
        ->toMatchArray([
            'client_id' => '8855417827',
            'redirect_uri' => 'http://127.0.0.1:8000/auth/telegram/callback',
            'response_type' => 'code',
            'scope' => 'openid profile',
            'code_challenge_method' => 'S256',
        ])
        ->and($query['state'])->toBe(session('telegram_oauth_state'))
        ->and($query['code_challenge'])->not->toBeEmpty();
});

test('users can authenticate through telegram using a Laravel session', function () {
    $telegram = $this->mock(TelegramOidc::class);
    $telegram->shouldReceive('user')
        ->once()
        ->with('authorization-code', 'pkce-verifier')
        ->andReturn([
            'sub' => 'telegram-user-123',
            'name' => 'Иван Иванов',
            'preferred_username' => 'ivan',
        ]);

    $response = $this
        ->withSession([
            'telegram_oauth_state' => 'expected-state',
            'telegram_oauth_verifier' => 'pkce-verifier',
        ])
        ->get(route('telegram.callback', [
            'code' => 'authorization-code',
            'state' => 'expected-state',
        ]));

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $account = SocialAccount::query()->where([
        'provider' => 'telegram',
        'provider_id' => 'telegram-user-123',
    ])->firstOrFail();

    expect($account->user->name)->toBe('Иван Иванов')
        ->and($account->user->email)->toBeNull();
});

test('telegram callback rejects an invalid state', function () {
    $telegram = $this->mock(TelegramOidc::class);
    $telegram->shouldNotReceive('user');

    $response = $this
        ->withSession([
            'telegram_oauth_state' => 'expected-state',
            'telegram_oauth_verifier' => 'pkce-verifier',
        ])
        ->get(route('telegram.callback', [
            'code' => 'authorization-code',
            'state' => 'forged-state',
        ]));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('telegram');
});

test('telegram callback handles a cancelled login', function () {
    $telegram = $this->mock(TelegramOidc::class);
    $telegram->shouldNotReceive('user');

    $response = $this
        ->withSession([
            'telegram_oauth_state' => 'expected-state',
            'telegram_oauth_verifier' => 'pkce-verifier',
        ])
        ->get(route('telegram.callback', [
            'error' => 'access_denied',
            'state' => 'expected-state',
        ]));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('telegram');
});
