<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CardGeneration;
use App\Services\CardGenerationPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(private CardGenerationPresenter $presenter) {}

    public function index(Request $request): Response
    {
        $generationId = $request->integer('card_id');

        if ($generationId <= 0) {
            return Inertia::render('Dashboard', [
                'emailVerified' => $request->boolean('verified'),
            ]);
        }

        $generation = CardGeneration::query()
            ->with(['card', 'images', 'export'])
            ->findOrFail($generationId);
        abort_unless($generation->card->user_id === $request->user()->getAuthIdentifier(), 404);

        return Inertia::render('Dashboard', [
            'card' => $this->presenter->editor($generation),
            'emailVerified' => $request->boolean('verified'),
        ]);
    }
}
