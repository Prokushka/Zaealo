<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class YandexOAuth
{
    private const AUTHORIZATION_URL = 'https://oauth.yandex.ru/authorize';

    private const TOKEN_URL = 'https://oauth.yandex.ru/token';

    private const USER_INFO_URL = 'https://login.yandex.ru/info';

    /**
     * @return array{url: string, state: string, verifier: string}
     */
    public function authorization(): array
    {
        $state = Str::random(64);
        $verifier = Str::random(96);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], encoding_type: PHP_QUERY_RFC3986);

        return [
            'url' => self::AUTHORIZATION_URL.'?'.$query,
            'state' => $state,
            'verifier' => $verifier,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function user(string $code, string $verifier): array
    {
        $tokens = Http::asForm()
            ->connectTimeout(3)
            ->timeout(10)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri' => $this->redirectUri(),
                'code_verifier' => $verifier,
            ])
            ->throw()
            ->json();

        $accessToken = $tokens['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Яндекс не вернул токен');
        }

        $user = Http::acceptJson()
            ->withHeaders(['Authorization' => 'OAuth '.$accessToken])
            ->connectTimeout(3)
            ->timeout(10)
            ->get(self::USER_INFO_URL, ['format' => 'json'])
            ->throw()
            ->json();

        if (! isset($user['id']) || ! is_string($user['id'])) {
            throw new RuntimeException('Пользователь яндекса не имеет Id.');
        }

        return $user;
    }

    private function clientId(): string
    {
        return $this->configuredValue('client_id');
    }

    private function clientSecret(): string
    {
        return $this->configuredValue('client_secret');
    }

    private function redirectUri(): string
    {
        return $this->configuredValue('redirect');
    }

    private function configuredValue(string $key): string
    {
        $value = config("services.yandex.$key");

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Yandex OAuth [$key] is not configured.");
        }

        return $value;
    }
}
