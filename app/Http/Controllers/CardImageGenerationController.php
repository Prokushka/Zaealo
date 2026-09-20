<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PricingKey;
use App\Exceptions\InsufficientZarks;
use App\Http\Requests\GenerateCardImagesRequest;
use App\Models\CardGeneration;
use App\Models\CardImage;
use App\Services\PricingCatalog;
use App\Services\QueueCardImageGeneration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class CardImageGenerationController extends Controller
{
    public function __construct(
        private QueueCardImageGeneration $queueImages,
        private PricingCatalog $pricing,
    ) {}

    public function store(GenerateCardImagesRequest $request, CardGeneration $generation): RedirectResponse
    {
        $generation->loadMissing('card');
        abort_unless($generation->card->user_id === $request->user()->getAuthIdentifier(), 404);

        $scenarios = $request->validated('scenarios');
        $features = $request->validated('infographic_features', []);

        try {
            $this->queueImages->handle($request->user(), $generation, $scenarios, $features);
        } catch (InsufficientZarks $exception) {
            $photoCount = count($scenarios);
            $required = $photoCount * $this->pricing->cost(PricingKey::ImageGeneration);

            throw ValidationException::withMessages([
                'balance' => "Недостаточно ZARQ: для {$photoCount} фото нужно {$required}, на балансе {$exception->available}. Уберите часть фото или пополните баланс.",
            ]);
        }

        return back()->with('ai_photo_generation', [
            'queued' => count($scenarios),
        ]);
    }

    public function index(Request $request, CardGeneration $generation): JsonResponse
    {
        $generation->loadMissing('card');
        abort_unless($generation->card->user_id === $request->user()->getAuthIdentifier(), 404);

        $images = $generation->images()
            ->where('type', CardImage::TYPE_AI_GENERATED)
            ->latest()
            ->get();

        $statusCounts = $images->countBy('generation_status');

        return response()->json([
            'images' => $images->map(fn (CardImage $image): array => [
                'id' => $image->getKey(),
                'status' => $image->generation_status,
                'category' => $image->generation_category,
                'subcategory' => $image->generation_subcategory,
                'error' => $image->generation_error,
                'url' => $image->generation_status === CardImage::GENERATION_STATUS_COMPLETED
                    ? Storage::disk('s3')->temporaryUrl($image->path, now()->addMinutes(15))
                    : null,
            ])->values(),
            'summary' => [
                'total' => $images->count(),
                'queued' => $statusCounts->get(CardImage::GENERATION_STATUS_QUEUED, 0),
                'processing' => $statusCounts->get(CardImage::GENERATION_STATUS_PROCESSING, 0),
                'completed' => $statusCounts->get(CardImage::GENERATION_STATUS_COMPLETED, 0),
                'failed' => $statusCounts->get(CardImage::GENERATION_STATUS_FAILED, 0),
            ],
        ]);
    }
}
