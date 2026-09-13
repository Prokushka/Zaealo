<?php

declare(strict_types=1);

namespace App\ImagePrompts;

use InvalidArgumentException;

final readonly class PackagingCategoryPrompt implements ImagePromptInterface
{
    public function __construct(private string $subcategory) {}

    public function generate(): string
    {
        return <<<PROMPT
Категория «Упаковка и брендинг»: покажи товар вместе с упаковкой так, чтобы кадр передавал аккуратность, ценность и впечатление от получения товара. Упаковка и товар остаются главными объектами.

Не добавляй на этот кадр рекламный текст, характеристики, преимущества, плашки, подписи, цифры или выноски. Видимые оригинальные надписи на самой упаковке сохрани без изменений.

Подкатегория: {$this->subcategoryInstruction()}
PROMPT;
    }

    private function subcategoryInstruction(): string
    {
        return match ($this->subcategory) {
            'unboxing' => $this->unboxingPrompt(),
            'gift-ready' => $this->giftReadyPrompt(),
            default => throw new InvalidArgumentException("Unsupported packaging subcategory [{$this->subcategory}]."),
        };
    }

    private function unboxingPrompt(): string
    {
        return 'момент распаковки: коробка открывается, товар и упаковка хорошо видны, премиальный мягкий свет и ощущение ожидания.';
    }

    private function giftReadyPrompt(): string
    {
        return 'подарочный вид: товар в аккуратной фирменной упаковке с элегантными лентами, мягкий премиальный свет.';
    }
}
