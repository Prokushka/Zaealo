<?php

declare(strict_types=1);

namespace App\ImagePrompts;

use InvalidArgumentException;

final readonly class MainCategoryPrompt implements ImagePromptInterface
{
    public function __construct(private string $subcategory) {}

    public function generate(): string
    {
        return <<<PROMPT
Категория «Главное (Hero)»: создай ключевое коммерческое фото, которое быстро объясняет покупателю, что представляет собой товар. Товар занимает центральное место и остаётся единственным визуальным акцентом.

Это главное фото товара. Товар — главный объект и должен сразу считываться в ленте. Не слишком перегружай изображение текстом: Название и краткая характеристика или просто название. Остальные решение по стилю, фону, свету, композиции и количеству декоративных элементов прими самостоятельно, чтобы кадр выглядел выразительно и коммерчески.

Подкатегория: {$this->subcategoryInstruction()}
PROMPT;
    }

    private function subcategoryInstruction(): string
    {
        return match ($this->subcategory) {
            'clean-background' => $this->cleanBackgroundPrompt(),
            'model-background' => $this->modelBackgroundPrompt(),
            'in-hand' => $this->inHandPrompt(),
            default => throw new InvalidArgumentException("Unsupported hero subcategory [{$this->subcategory}]."),
        };
    }

    private function cleanBackgroundPrompt(): string
    {
        return 'товар расположен по центру в чистой вертикальной композиции без лишнего реквизита.';
    }

    private function modelBackgroundPrompt(): string
    {
        return 'товар расположен по центру на контрастном цветном фоне в вертикальной композиции, чистый рекламный свет и минимум отвлекающих деталей.';
    }

    private function inHandPrompt(): string
    {
        return 'крупный план товара в ладони, чтобы показать реальный масштаб, нейтральный чистый фон и естественное положение руки.';
    }
}
