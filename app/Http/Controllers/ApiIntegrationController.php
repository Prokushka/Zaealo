<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertMarketplaceApiKeyRequest;
use App\MarketplaceApiKeyValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApiIntegrationController extends Controller
{
    /** @var list<string> */
    private const MARKETPLACES = ['wildberries', 'ozon'];

    public function __construct(private MarketplaceApiKeyValidator $apiKeyValidator) {}

    public function index(Request $request): Response
    {
        $connectedMarketplaces = $request->user()
            ->marketplaceApiKeys()
            ->get()
            ->filter(fn ($apiKey): bool => $apiKey->marketplace !== 'ozon' || filled($apiKey->client_id))
            ->pluck('marketplace')
            ->flip();

        return Inertia::render('Integrations', [
            'integrations' => collect(self::MARKETPLACES)
                ->mapWithKeys(fn (string $marketplace): array => [
                    $marketplace => [
                        'has_key' => $connectedMarketplaces->has($marketplace),
                    ],
                ])
                ->all(),
            'status' => session('status'),
        ]);
    }

    public function update(UpsertMarketplaceApiKeyRequest $request, string $marketplace): RedirectResponse
    {
        $apiKey = $request->validated('api_key');
        $clientId = $request->validated('client_id');
        $validationError = $this->apiKeyValidator->validationError(
            $marketplace,
            $apiKey,
            $clientId,
        );

        if ($validationError !== null) {
            throw ValidationException::withMessages(['api_key' => $validationError]);
        }

        $request->user()->marketplaceApiKeys()->updateOrCreate(
            ['marketplace' => $marketplace],
            ['api_key' => $apiKey, 'client_id' => $clientId],
        );

        return back()->with('status', 'API-ключи проверены и сохранены.');
    }

    public function status(Request $request, string $marketplace): JsonResponse
    {
        $query = $request->user()->marketplaceApiKeys()->where('marketplace', $marketplace);

        if ($marketplace === 'ozon') {
            $query->whereNotNull('client_id')->where('client_id', '!=', '');
        }

        return response()->json(['has_key' => $query->exists()]);
    }

    public function destroy(Request $request, string $marketplace): RedirectResponse
    {
        $request->user()->marketplaceApiKeys()
            ->where('marketplace', $marketplace)
            ->delete();

        return back()->with('status', 'API-ключ удалён.');
    }
}
