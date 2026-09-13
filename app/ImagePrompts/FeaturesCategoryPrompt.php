<?php

declare(strict_types=1);

namespace App\ImagePrompts;

use InvalidArgumentException;

final readonly class FeaturesCategoryPrompt implements ImagePromptInterface
{
    public function __construct(private string $subcategory) {}

    public function generate(): string
    {
        return <<<PROMPT
Категория «Характеристики (Ракурсы)»: создай информативный предметный кадр, который помогает покупателю оценить важную характеристику, масштаб или комплектацию товара.

На этом кадре разрешены только 1–2 короткие подписи к действительно видимым деталям или подтверждённым характеристикам. Не делай длинный список, не добавляй общий рекламный заголовок и не повторяй один и тот же текст несколько раз.

Подкатегория: {$this->subcategoryInstruction()}
PROMPT;
    }

    private function subcategoryInstruction(): string
    {
        return match ($this->subcategory) {
            'macro-details' => $this->macroDetailsPrompt(),
            'size-comparison' => $this->sizeComparisonPrompt(),
            'contents' => $this->contentsPrompt(),
            default => throw new InvalidArgumentException("Unsupported features subcategory [{$this->subcategory}]."),
        };
    }

    private function macroDetailsPrompt(): string
    {
        return 'макросъёмка важной детали: текстуры, шва, материала или застёжки, резкий фокус на фактуре и аккуратный свет.';
    }

    private function sizeComparisonPrompt(): string
    {
        return 'товар расположен рядом с линейкой, монетой или телефоном для понятной оценки масштаба, нейтральный фон.';
    }

    private function contentsPrompt(): string
    {
        return 'всё содержимое коробки разложено веером, каждый предмет хорошо виден, чистая предметная раскладка и студийный свет.';
    }
}
