<?php

namespace App;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class MarketplaceApiKeyValidator
{
    public function validationError(
        string $marketplace,
        string $apiKey,
        ?string $clientId = null,
    ): ?string {
        try {
            $response = match ($marketplace) {
                'wildberries' => Http::acceptJson()
                    ->withToken($apiKey)
                    ->connectTimeout(5)
                    ->timeout(15)
                    ->get('https://common-api.wildberries.ru/ping'),
                'ozon' => Http::acceptJson()
                    ->withHeaders([
                        'Client-Id' => (string) $clientId,
                        'Api-Key' => $apiKey,
                    ])
                    ->connectTimeout(5)
                    ->timeout(15)
                    ->withBody('{}', 'application/json')
                    ->post('https://api-seller.ozon.ru/v1/seller/info'),
            };
        } catch (ConnectionException) {
            return 'Не удалось проверить ключ. Повторите попытку позже.';
        }

        if ($response->successful()) {
            return null;
        }

        if ($response->status() === 401) {
            return 'Маркетплейс не принял ключ или у него недостаточно прав доступа.';
        }

        if ($marketplace === 'ozon') {
            return $this->ozonValidationError($response);
        }

        return 'Не удалось проверить ключ у маркетплейса. Повторите попытку позже.';
    }

    private function ozonValidationError(Response $response): string
    {
        $message = data_get($response->json(), 'message');
        $message = is_string($message) ? Str::limit(trim($message), 300) : '';
        $responseBody = Str::limit(Str::squish(strip_tags($response->body())), 300);

        Log::warning('Ozon rejected API key validation.', [
            'http_status' => $response->status(),
            'trace_id' => $response->header('x-o3-trace-id'),
            'response_body' => $responseBody,
        ]);

        if ($response->status() === 403 && Str::contains(Str::lower($message), 'offer not signed')) {
            return 'Ozon ограничил доступ к Seller API: примите оферту в личном кабинете продавца.';
        }

        if ($response->status() === 403) {
            if ($message !== '') {
                return "Ozon ограничил доступ к Seller API: {$message}";
            }

            if ($responseBody !== '') {
                return "Ozon вернул ошибку HTTP 403: {$responseBody}";
            }

            return 'Ozon ограничил доступ к Seller API. Проверьте Client ID, статус ключа и ограничения IP в личном кабинете Ozon.';
        }

        return $message !== ''
            ? "Ozon вернул ошибку HTTP {$response->status()}: {$message}"
            : "Ozon вернул ошибку HTTP {$response->status()} при проверке ключа.";
    }
}
