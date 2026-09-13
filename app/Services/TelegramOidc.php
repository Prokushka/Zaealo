<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class TelegramOidc
{
    private const AUTHORIZATION_URL = 'https://oauth.telegram.org/auth';

    private const ISSUER = 'https://oauth.telegram.org';

    private const JWKS_URL = 'https://oauth.telegram.org/.well-known/jwks.json';

    private const TOKEN_URL = 'https://oauth.telegram.org/token';

    /**
     * @return array{url: string, state: string, verifier: string}
     */
    public function authorization(): array
    {
        $state = Str::random(64);
        $verifier = Str::random(96);
        $challenge = $this->base64UrlEncode(hash('sha256', $verifier, true));

        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid profile',
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
            ->withBasicAuth($this->clientId(), $this->clientSecret())
            ->connectTimeout(3)
            ->timeout(10)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
                'client_id' => $this->clientId(),
                'code_verifier' => $verifier,
            ])
            ->throw()
            ->json();

        $idToken = $tokens['id_token'] ?? null;

        if (! is_string($idToken)) {
            throw new RuntimeException('Telegram did not return an ID token.');
        }

        $jwks = Cache::remember('telegram.oidc.jwks', now()->addHour(), fn (): array => Http::acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->get(self::JWKS_URL)
            ->throw()
            ->json());

        JWT::$leeway = 60;
        $claims = (array) JWT::decode($idToken, JWK::parseKeySet($jwks));

        $audience = (array) ($claims['aud'] ?? []);

        if (($claims['iss'] ?? null) !== self::ISSUER || ! in_array($this->clientId(), $audience, true)) {
            throw new RuntimeException('Telegram returned an ID token with invalid claims.');
        }

        if (! isset($claims['sub']) || ! is_string($claims['sub'])) {
            throw new RuntimeException('Telegram ID token does not contain a subject.');
        }

        return $claims;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function clientId(): string
    {
        return $this->configuredValue('client_id');
    }

    private function clientSecret(): string
    {
        return $this->configuredValue('client_secret');
    }

    private function configuredValue(string $key): string
    {
        $value = config("services.telegram.$key");

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Telegram OIDC [$key] is not configured.");
        }

        return $value;
    }

    private function redirectUri(): string
    {
        return $this->configuredValue('redirect');
    }
}
