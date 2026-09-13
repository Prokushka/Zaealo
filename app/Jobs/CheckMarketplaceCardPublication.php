<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\MarketplaceCardCreationResult;
use App\Models\CardExport;
use App\Services\Marketplace\MarketplaceCardCreationService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class CheckMarketplaceCardPublication implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    private const MAXIMUM_STATUS_CHECKS = 120;

    public int $tries = 3;

    public int $timeout = 240;

    public int $uniqueFor = 300;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $cardExportId) {}

    public function uniqueId(): string
    {
        return (string) $this->cardExportId;
    }

    public function handle(MarketplaceCardCreationService $marketplaces): void
    {
        $export = DB::transaction(function (): ?CardExport {
            $export = CardExport::query()->lockForUpdate()->find($this->cardExportId);

            if ($export === null || $export->status !== CardExport::STATUS_WAITING) {
                return null;
            }

            if ($export->status_checks >= self::MAXIMUM_STATUS_CHECKS) {
                $export->update([
                    'status' => CardExport::STATUS_FAILED,
                    'error' => 'Маркетплейс слишком долго создаёт карточку. Запустите экспорт повторно позже.',
                ]);

                return null;
            }

            $export->increment('status_checks');

            return $export->refresh();
        });

        if ($export === null) {
            return;
        }

        $result = $marketplaces->creator($export->marketplace)->check($export);
        $this->applyResult($export, $result);
    }

    public function failed(?Throwable $exception): void
    {
        CardExport::query()->whereKey($this->cardExportId)->update([
            'status' => CardExport::STATUS_FAILED,
            'error' => Str::limit(
                $exception?->getMessage() ?? 'Не удалось проверить статус карточки в маркетплейсе.',
                1000,
            ),
        ]);
    }

    private function applyResult(CardExport $export, MarketplaceCardCreationResult $result): void
    {
        if ($result->status === MarketplaceCardCreationResult::FAILED) {
            $export->update([
                'status' => CardExport::STATUS_FAILED,
                'error' => $result->error,
            ]);

            return;
        }

        if ($result->status === MarketplaceCardCreationResult::COMPLETED) {
            $export->update([
                'status' => CardExport::STATUS_COMPLETED,
                'external_product_id' => $result->externalProductId ?? $export->external_product_id,
                'error' => null,
                'completed_at' => now(),
            ]);
            $export->generation->card()->update(['is_exported' => true]);

            return;
        }

        $export->update([
            'external_task_id' => $result->externalTaskId ?? $export->external_task_id,
            'external_product_id' => $result->externalProductId ?? $export->external_product_id,
            'error' => null,
        ]);

        self::dispatch($export->getKey())
            ->onQueue('marketplace-export')
            ->delay(now()->addSeconds(15));
    }
}
