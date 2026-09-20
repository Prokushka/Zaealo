<?php

namespace App\Http\Middleware;

use App\Enums\PricingKey;
use App\Services\PricingCatalog;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private PricingCatalog $pricing) {}

    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'card_analysis' => fn (): mixed => $request->session()->get('card_analysis'),
            'pricing' => [
                'cost_export' => $this->pricing->cost(PricingKey::CardExport),
                'cost_image_generation' => $this->pricing->cost(PricingKey::ImageGeneration),
                'cost_text_regeneration' => $this->pricing->cost(PricingKey::TextRegeneration),
                'cost_pro_copywriting' => $this->pricing->cost(PricingKey::ProCopywriting),
            ],
        ];
    }
}
