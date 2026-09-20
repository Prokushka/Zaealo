<?php

use App\Services\TelegramOidc;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('allows ten seconds to connect when exchanging an authorization code', function (): void {
    config()->set('services.telegram', [
        'client_id' => 'telegram-client-id',
        'client_secret' => 'telegram-client-secret',
        'redirect' => 'https://app.example.com/auth/telegram/callback',
    ]);

    $response = Mockery::mock(Response::class);
    $response->shouldReceive('throw')->once()->andReturnSelf();
    $response->shouldReceive('json')->once()->andReturn([]);

    $request = Mockery::mock(PendingRequest::class);
    $request->shouldReceive('withBasicAuth')
        ->once()
        ->with('telegram-client-id', 'telegram-client-secret')
        ->andReturnSelf();
    $request->shouldReceive('connectTimeout')->once()->with(10)->andReturnSelf();
    $request->shouldReceive('timeout')->once()->with(20)->andReturnSelf();
    $request->shouldReceive('post')
        ->once()
        ->with('https://oauth.telegram.org/token', [
            'grant_type' => 'authorization_code',
            'code' => 'authorization-code',
            'redirect_uri' => 'https://app.example.com/auth/telegram/callback',
            'client_id' => 'telegram-client-id',
            'code_verifier' => 'pkce-verifier',
        ])
        ->andReturn($response);

    Http::shouldReceive('asForm')->once()->andReturn($request);

    app(TelegramOidc::class)->user('authorization-code', 'pkce-verifier');
})->throws(RuntimeException::class, 'Telegram did not return an ID token.');
