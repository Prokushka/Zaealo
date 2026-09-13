<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CardExport;
use App\Models\CardImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

final class CardExportArchive
{
    /** @return array{path: string, name: string} */
    public function create(CardExport $export): array
    {
        $export->loadMissing('generation.images');
        $temporaryPath = tempnam(sys_get_temp_dir(), 'zarq-export-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Не удалось подготовить архив для скачивания.');
        }

        $zip = new ZipArchive;

        if ($zip->open($temporaryPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryPath);

            throw new RuntimeException('Не удалось открыть архив для записи.');
        }

        $addedFiles = 0;

        $images = $export->generation->images
            ->filter(fn (CardImage $image): bool => $image->type === CardImage::TYPE_USER_UPLOAD
                || ($image->type === CardImage::TYPE_AI_GENERATED
                    && $image->generation_status === CardImage::GENERATION_STATUS_COMPLETED))
            ->sortBy(fn (CardImage $image): string => sprintf(
                '%d-%010d',
                $image->type === CardImage::TYPE_AI_GENERATED ? 0 : 1,
                $image->getKey(),
            ))
            ->values();

        try {
            foreach ($images as $index => $image) {
                $path = $image->path;

                if (! Storage::disk('s3')->exists($path)) {
                    continue;
                }

                $contents = Storage::disk('s3')->get($path);
                $directory = $image->type === CardImage::TYPE_AI_GENERATED ? 'generated' : 'source';
                $basename = Str::of(pathinfo($path, PATHINFO_FILENAME))->slug()->limit(60, '')->value();
                $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
                $filename = sprintf('%s/%02d-%s.%s', $directory, $index + 1, $basename ?: 'photo', $extension ?: 'bin');

                if ($zip->addFromString($filename, $contents)) {
                    $addedFiles++;
                }
            }
        } catch (Throwable $exception) {
            $zip->close();
            @unlink($temporaryPath);

            throw $exception;
        }

        $zip->close();

        if ($addedFiles === 0) {
            @unlink($temporaryPath);

            throw new RuntimeException('В карточке нет доступных фотографий для экспорта.');
        }

        $sellerSku = Str::of((string) ($export->payload['seller_sku'] ?? $export->getKey()))->slug();

        return [
            'path' => $temporaryPath,
            'name' => "{$export->marketplace}-{$sellerSku}-photos.zip",
        ];
    }
}
