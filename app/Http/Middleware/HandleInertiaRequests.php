<?php

namespace App\Http\Middleware;

use App\Enums\ZarkPrice;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
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
                'cost_export' => ZarkPrice::CardExport,
                'cost_image_generation' => ZarkPrice::ImageGeneration,
                'cost_text_regeneration' => ZarkPrice::TextRegeneration,
                'cost_pro_copywriting' => ZarkPrice::ProCopywriting,
            ],
        ];
    }
}
