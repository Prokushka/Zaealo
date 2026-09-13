<?php

namespace App\Http\Controllers;

use App\Exceptions\AttributeFillingException;
use App\Http\Requests\FillCardAttributesRequest;
use App\Http\Requests\UpdateCardAttributesRequest;
use App\Models\CardGeneration;
use App\Services\AttributeFillerService;
use Illuminate\Http\JsonResponse;

class CardAttributesController extends Controller
{
    public function __construct(private AttributeFillerService $attributeFiller) {}

    public function store(FillCardAttributesRequest $request, CardGeneration $generation): JsonResponse
    {
        $generation->loadMissing('card');
        abort_unless($generation->card->user_id === $request->user()->getAuthIdentifier(), 404);

        $generation->update([
            'attributes_category_id' => $request->validated('category_id'),
            'attributes_type_id' => $generation->card->marketplace === 'ozon'
                ? $request->validated('type_id')
                : null,
        ]);

        try {
            $attributes = $this->attributeFiller->fill(
                $generation,
                $request->validated('donor_url'),
            );
        } catch (AttributeFillingException $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['attributes' => $attributes]);
    }

    public function update(UpdateCardAttributesRequest $request, CardGeneration $generation): JsonResponse
    {
        $generation->loadMissing('card');
        abort_unless($generation->card->user_id === $request->user()->getAuthIdentifier(), 404);

        /** @var array<string, string|null> $attributes */
        $attributes = collect($request->validated('attributes'))
            ->filter(fn (?string $value): bool => filled($value))
            ->all();
        $generation->update(['attributes_data' => $attributes]);

        return response()->json(['attributes' => $attributes]);
    }
}
