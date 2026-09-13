<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCardExportRequest;
use App\Jobs\CheckMarketplaceCardPublication;
use App\Jobs\PublishMarketplaceCard;
use App\Models\CardExport;
use App\Models\CardGeneration;
use App\Models\CardImage;
use App\Services\CardExportArchive;
use App\Services\CardExportService;
use App\Services\CardGenerationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CardExportController extends Controller
{
    public function __construct(
        private CardExportService $exports,
        private CardExportArchive $archive,
        private CardGenerationPresenter $presenter,
    ) {}

    public function archive(Request $request, CardGeneration $generation): JsonResponse
    {
        $generation->loadMissing('card');
        abort_unless($generation->card->user_id === $request->user()->getAuthIdentifier(), 404);

        $result = $this->exports->prepareArchive($generation, $request->user());

        return response()->json([
            'export_id' => $result['export']->getKey(),
            'cost_zarks' => $result['charged_zarks'],
            'balance' => $request->user()->refresh()->balance,
            'download_url' => route('card-exports.download', $result['export']),
        ]);
    }

    public function publish(StoreCardExportRequest $request, CardGeneration $generation): JsonResponse
    {
        $generation->loadMissing('card');
        abort_unless($generation->card->user_id === $request->user()->getAuthIdentifier(), 404);

        $result = $this->exports->publish($generation, $request->user(), $request->validated());
        $export = $result['export'];

        if ($result['should_dispatch']) {
            $job = $export->status === CardExport::STATUS_WAITING
                ? new CheckMarketplaceCardPublication($export->getKey())
                : new PublishMarketplaceCard($export->getKey());
            dispatch($job)->onQueue('marketplace-export')->afterCommit();
        }

        return response()->json([
            'export_id' => $export->getKey(),
            'status' => $export->status,
            'balance' => $request->user()->refresh()->balance,
        ]);
    }

    public function show(Request $request, CardExport $export): JsonResponse
    {
        $this->authorizeExport($request, $export);

        return response()->json([
            'id' => $export->getKey(),
            'status' => $export->status,
            'external_product_id' => $export->external_product_id,
            'error' => $export->error,
        ]);
    }

    public function download(Request $request, CardExport $export): BinaryFileResponse
    {
        $this->authorizeExport($request, $export);
        $hasGeneratedPhoto = $export->generation->images()
            ->where('type', CardImage::TYPE_AI_GENERATED)
            ->where('generation_status', CardImage::GENERATION_STATUS_COMPLETED)
            ->exists();
        abort_unless(
            $export->cost_zarks > 0 || $hasGeneratedPhoto,
            403,
            'Сначала подготовьте ZIP-архив. Без готового ИИ-фото он стоит 25 ZARQ.',
        );
        $archive = $this->archive->create($export);

        return response()->download(
            $archive['path'],
            $archive['name'],
            ['Content-Type' => 'application/zip'],
        )->deleteFileAfterSend();
    }

    public function json(Request $request, CardGeneration $generation): StreamedResponse
    {
        $generation->loadMissing(['card', 'images', 'export']);
        abort_unless($generation->card->user_id === $request->user()->getAuthIdentifier(), 404);
        $payload = $this->presenter->export($generation);

        return response()->streamDownload(
            fn () => print json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
            "card-{$generation->card_id}-generation-{$generation->getKey()}.json",
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    private function authorizeExport(Request $request, CardExport $export): void
    {
        $export->loadMissing('generation.card');
        abort_unless(
            $export->generation->card->user_id === $request->user()->getAuthIdentifier(),
            404,
        );
    }
}
