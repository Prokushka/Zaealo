<?php

namespace App\Listeners;

use App\Events\CardImageGenerationFailed;
use App\Models\CardImage;
use App\Services\ZarkWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RefundFailedCardImageGeneration
{
    public function __construct(private ZarkWallet $wallet) {}

    /**
     * Handle the event.
     */
    public function handle(CardImageGenerationFailed $event): void
    {
        DB::transaction(function () use ($event): void {
            $cardImage = CardImage::query()->lockForUpdate()->find($event->cardImageId);

            if ($cardImage === null) {
                return;
            }

            $cardImage->update([
                'generation_status' => CardImage::GENERATION_STATUS_FAILED,
                'generation_error' => $event->error === null
                    ? 'Не удалось сгенерировать изображение.'
                    : Str::limit($event->error, 250),
            ]);

            if (! $cardImage->is_paid) {
                return;
            }

            if ($this->wallet->refund($cardImage, 'Возврат за неудачную генерацию фото')) {
                $cardImage->update(['is_paid' => false]);
            }
        });
    }
}
