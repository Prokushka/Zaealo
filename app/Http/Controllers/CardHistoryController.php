<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PricingKey;
use App\Exceptions\InsufficientZarks;
use App\Http\Requests\GenerateCardImagesRequest;
use App\Http\Requests\UpdateSavedCardRequest;
use App\Models\CardGeneration;
use App\Services\CardGenerationPresenter;
use App\Services\PricingCatalog;
use App\Services\QueueCardImageGeneration;
use App\Services\RegenerateCardText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CardHistoryController extends Controller
{
    public function __construct(
        private CardGenerationPresenter $presenter,
        private RegenerateCardText $regenerateText,
        private QueueCardImageGeneration $queueImages,
        private PricingCatalog $pricing,
    ) {}

    public function index(Request $request): Response
    {
        $generations = $request->user()->cardGenerations()
            ->with(['card', 'images', 'export'])
            ->orderByDesc((new CardGeneration)->qualifyColumn('created_at'))
            ->paginate(12)
            ->withQueryString();

        $generations->setCollection($this->presenter->history($generations->getCollection()));

        return Inertia::render('CardHistory/Index', [
            'generations' => $generations,
        ]);
    }

    public function update(UpdateSavedCardRequest $request, CardGeneration $generation): JsonResponse
    {
        $this->authorizeGeneration($request, $generation);

        if ($generation->status !== 'completed') {
            throw ValidationException::withMessages([
                'card' => 'Редактирование доступно только для завершённой карточки.',
            ]);
        }

        DB::transaction(function () use ($request, $generation): void {
            $generation->update([
                'generated_title' => $request->validated('title'),
                'generated_description' => $request->validated('description'),
            ]);
            $generation->card()->update([
                'title' => $request->validated('title'),
                'description' => $request->validated('description'),
            ]);
        });

        return response()->json([
            'message' => 'Изменения сохранены.',
            'card' => $this->presenter->editor($generation->refresh()),
        ]);
    }

    public function regenerateText(Request $request, CardGeneration $generation): JsonResponse
    {
        $this->authorizeGeneration($request, $generation);

        try {
            $newGeneration = $this->regenerateText->handle($request->user(), $generation);
        } catch (InsufficientZarks $exception) {
            $cost = $this->pricing->cost(PricingKey::TextRegeneration);

            throw ValidationException::withMessages([
                'balance' => "Недостаточно ZARQ: перегенерация текста стоит {$cost}, на балансе {$exception->available}. Пополните баланс и повторите попытку.",
            ]);
        }

        return response()->json([
            'generation_id' => $newGeneration->getKey(),
            'balance' => $request->user()->refresh()->balance,
            'card' => $this->presenter->editor($newGeneration),
        ]);
    }

    public function regenerateImages(GenerateCardImagesRequest $request, CardGeneration $generation): JsonResponse
    {
        $this->authorizeGeneration($request, $generation);

        if ($generation->status !== 'completed') {
            throw ValidationException::withMessages([
                'card' => 'Генерация фото доступна только для завершённой карточки.',
            ]);
        }

        $scenarios = $request->validated('scenarios');

        try {
            $result = $this->queueImages->handle(
                $request->user(),
                $generation,
                $scenarios,
                $request->validated('infographic_features', []),
            );
        } catch (InsufficientZarks $exception) {
            $photoCount = count($scenarios);
            $required = $photoCount * $this->pricing->cost(PricingKey::ImageGeneration);

            throw ValidationException::withMessages([
                'balance' => "Недостаточно ZARQ: для {$photoCount} фото нужно {$required}, на балансе {$exception->available}. Уберите часть фото или пополните баланс.",
            ]);
        }

        return response()->json([
            'queued' => count($result['image_ids']),
            'charged_zarks' => $result['charged_zarks'],
            'balance' => $request->user()->refresh()->balance,
        ], 202);
    }

    private function authorizeGeneration(Request $request, CardGeneration $generation): void
    {
        $generation->loadMissing('card');
        abort_unless($generation->card->user_id === $request->user()->getAuthIdentifier(), 404);
    }
}
