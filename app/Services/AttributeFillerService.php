<?php

namespace App\Services;

use App\Exceptions\AttributeFillingException;
use App\Models\CardGeneration;
use App\Models\CardImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class AttributeFillerService
{
    public function __construct(
        private MarketplaceProductSearchService $productSearch,
        private MarketplaceApiService $marketplaceApi,
        private AiTunnelService $aiTunnel,
    ) {}

    /** @return array<string, mixed> */
    public function fill(CardGeneration $card, ?string $donorUrlOrArticle = null): array
    {
        $card->loadMissing('card');

        try {
            $attributes = filled($donorUrlOrArticle)
                ? $this->donorAttributes($card, (string) $donorUrlOrArticle)
                : $this->aiAttributes($card);

            $card->update(['attributes_data' => $attributes]);

            return $attributes;
        } catch (AttributeFillingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $message = filled($donorUrlOrArticle)
                ? 'Не удалось получить характеристики из карточки-донора. Проверьте ссылку или артикул и повторите попытку.'
                : 'Не удалось заполнить характеристики товара. Повторите попытку позже.';

            throw new AttributeFillingException($message, previous: $exception);
        }
    }

    /** @return array<string, mixed> */
    private function donorAttributes(CardGeneration $card, string $donorUrlOrArticle): array
    {
        return $this->filterFilledAttributes(
            $this->productSearch->fetchDonorAttributes($card->card->marketplace, $donorUrlOrArticle),
        );
    }

    /** @return array<string, mixed> */
    private function aiAttributes(CardGeneration $card): array
    {
        $schema = $this->marketplaceApi->attributeSchema($card);

        if ($schema === []) {
            throw new RuntimeException('Для выбранной категории не найдены доступные характеристики.');
        }

        $response = $this->aiTunnel->analyzeImages(
            $this->prompt($card, $schema),
            [$this->mainImageUrl($card)],
            AIChoice::GPT4MINI,
            ['max_tokens' => 4096],
        );
        $content = data_get($response, 'choices.0.message.content');

        if (! is_string($content)) {
            throw new RuntimeException('Нейросеть не вернула характеристики товара.');
        }

        try {
            $json = (string) Str::of($content)
                ->trim()
                ->replaceMatches('/^```(?:json)?\s*|\s*```$/u', '');
            $attributes = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Нейросеть вернула некорректный JSON характеристик.', previous: $exception);
        }

        $isEmptyOzonObject = $card->card->marketplace === 'ozon'
            && $attributes === [] && str_starts_with($json, '{');

        if (! is_array($attributes) || (array_is_list($attributes) && ! $isEmptyOzonObject)) {
            throw new RuntimeException('Нейросеть вернула некорректный JSON характеристик.');
        }

        $allowedKeys = collect($schema)
            ->mapWithKeys(fn (array $attribute): array => [
                Str::lower(trim($attribute['key'])) => $attribute['key'],
            ])
            ->all();
        $normalizedAttributes = [];

        foreach ($attributes as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $normalizedKey = $allowedKeys[Str::lower(trim($key))] ?? null;

            if ($normalizedKey !== null) {
                $normalizedAttributes[$normalizedKey] = $value;
            }
        }

        $attributes = $this->filterFilledAttributes($normalizedAttributes);

        if ($attributes === [] && $card->card->marketplace !== 'ozon') {
            throw new AttributeFillingException('ИИ не заполнил ни одной допустимой характеристики для выбранной категории. Уточните категорию или используйте более информативное фото.');
        }

        return $attributes;
    }

    /** @param list<array{key: string, id: int|null, type: string|null, required: bool, unit: string|null, dictionary_id: int|null}> $schema */
    private function prompt(CardGeneration $card, array $schema): string
    {
        if ($card->card->marketplace === 'ozon') {
            return $this->ozonPrompt()."\n\n".json_encode([
                'title' => $card->card->title,
                'description' => $card->card->description,
                'schema' => $schema,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        return implode("\n\n", [
            'Проанализируй фото, название и описание товара. Твоя цель — заполнить максимально возможное количество характеристик по переданной схеме категории. Последовательно проверь каждое поле схемы и верни все поля, для которых можно сделать хотя бы правдоподную оценку. Заполняй не только очевидные свойства, но и габариты, длину, ширину, высоту, вес, объём, размер, комплектацию, материал, цвет, назначение, состав и конструктивные особенности. Если точное значение не видно, сделай реалистичную оценку по фото, типу товара и типичным параметрам этой категории — не пропускай поле только из-за отсутствия точных данных. Обязательно верни все обязательные поля. Не возвращай null, пустые строки или «неизвестно». Верни ТОЛЬКО валидный JSON с парами key-value. Каждый key должен в точности совпадать со значением schema[].key. Не добавляй новые ключи.',
            json_encode([
                'title' => $card->card->title,
                'description' => $card->card->description,
                'schema' => $schema,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    private function ozonPrompt(): string
    {
        return <<<'PROMPT'
Проанализируй фото, название и описание товара и заполни как можно больше характеристик Ozon из schema. Возвращай наиболее вероятные значения: цвет определяй по фото, а вес и размеры реалистично оценивай по типу и масштабу товара.
Ключи должны точно совпадать с schema[].key. Соблюдай type, unit и is_collection; для коллекции возвращай массив. Поле с dictionary_id > 0 заполняй только известным стандартным значением Ozon, при сомнении пропускай. ТН ВЭД возвращай только строкой из 10 цифр.
Бренд самостоятельно не заполняй. Не используй заглушки вроде «Неизвестный» и «Не указан». Название и описание уже созданы на SEO-этапе: не дублируй их и не выдумывай служебные данные для файлов, видео и объединения карточек. Если schema содержит хэштеги, обязательно заполни их.
Верни только JSON-объект с заполненными характеристиками. Не добавляй неизвестные ключи, null и пустые значения. Если определить нечего, верни {}.
PROMPT;
    }

    private function mainImageUrl(CardGeneration $card): string
    {
        $image = $card->images()
            ->where('type', CardImage::TYPE_USER_UPLOAD)
            ->orderByDesc('is_main')
            ->oldest('id')
            ->first();

        if ($image === null) {
            throw new RuntimeException('Не найдено основное фото товара для заполнения характеристик.');
        }

        return Storage::disk('s3')->temporaryUrl($image->path, now()->addMinutes(15));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filterFilledAttributes(array $attributes): array
    {
        return collect($attributes)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && filled($value))
            ->all();
    }
}
