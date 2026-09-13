<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ZenRowsService
{
    /** @param array<string, string|int> $parameters */
    public function fetch(string $url, array $parameters = []): Response
    {
        $apiKey = config('services.zenrows.key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Ключ ZenRows не настроен.');
        }

        return Http::retry(
            [2000, 5000],
            when: fn (Throwable $exception, PendingRequest $request): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException
                    && ($exception->response->tooManyRequests() || $exception->response->serverError())),
        )
            ->connectTimeout(10)
            ->timeout(90)
            ->get(config('services.zenrows.base_url'), [
                'apikey' => $apiKey,
                'url' => $url,
                ...$parameters,
            ])
            ->throw();
    }
}
