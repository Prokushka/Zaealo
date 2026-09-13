<?php

declare(strict_types=1);

namespace App\ImagePrompts;

use InvalidArgumentException;

final readonly class InfographicsCategoryPrompt implements ImagePromptInterface
{
    public function __construct(private string $subcategory) {}

    public function generate(): string
    {
        return <<<PROMPT
Категория «Инфографика»: создай понятный готовый инфографический слайд. Предусмотри композицию, свободные зоны и 2–4 короткие подписи к подтверждённым характеристикам без перекрытия товара.

Характеристики и выгоды разрешены только здесь: отбирай самые сильные, не превращай их в список и не добавляй неподтверждённые цифры, обещания или сертификаты.

Подкатегория: {$this->subcategoryInstruction()}
PROMPT;
    }

    private function subcategoryInstruction(): string
    {
        return match ($this->subcategory) {
            'diagram' => $this->diagramPrompt(),
            'text-space' => $this->textSpacePrompt(),
            'before-after' => $this->beforeAfterPrompt(),
            default => throw new InvalidArgumentException("Unsupported infographics subcategory [{$this->subcategory}]."),
        };
    }

    private function diagramPrompt(): string
    {
        return 'чистый товар с цифровыми указателями 1, 2 и 3 и краткими подписями только из переданных ключевых качеств; подписи не должны перекрывать товар.';
    }

    private function textSpacePrompt(): string
    {
        return 'товар расположен сбоку, слева или справа оставлено большое чистое пространство для размера, цены или преимуществ.';
    }

    private function beforeAfterPrompt(): string
    {
        return 'два достоверных состояния одного товара расположены рядом: например сложен и разложен либо сухой и влажный; добавь аккуратные подписи, только если они подтверждены переданными ключевыми качествами.';
    }
}
