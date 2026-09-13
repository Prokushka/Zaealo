<?php

declare(strict_types=1);

namespace App\ImagePrompts;

use InvalidArgumentException;

final readonly class LifestyleCategoryPrompt implements ImagePromptInterface
{
    public function __construct(private string $subcategory) {}

    public function generate(): string
    {
        return <<<PROMPT
Категория «Лайфстайл (Контекст)»: покажи товар в правдоподобной среде или моменте использования. Сцена должна усиливать назначение товара, но не отвлекать от него.

Не добавляй на этот кадр текст, характеристики, выгоды, плашки, подписи, цифры или выноски. Лайфстайл должен работать только через сцену и товар.

Подкатегория: {$this->subcategoryInstruction()}
PROMPT;
    }

    private function subcategoryInstruction(): string
    {
        return match ($this->subcategory) {
            'environment' => $this->environmentPrompt(),
            'hands-in-use' => $this->handsInUsePrompt(),
            'emotion-scene' => $this->emotionScenePrompt(),
            'flat-lay' => $this->flatLayPrompt(),
            default => throw new InvalidArgumentException("Unsupported lifestyle subcategory [{$this->subcategory}]."),
        };
    }

    private function environmentPrompt(): string
    {
        return 'товар находится в естественной для него среде: на кухне, полке, рабочем столе или в другом подходящем месте, мягкий дневной свет.';
    }

    private function handsInUsePrompt(): string
    {
        return 'человек естественно держит или использует товар, лицо не попадает в кадр либо остаётся нейтральным, внимание на действии и товаре.';
    }

    private function emotionScenePrompt(): string
    {
        return 'полная эмоциональная сцена с человеком в интерьере, на улице или на природе, атмосфера соответствует назначению товара.';
    }

    private function flatLayPrompt(): string
    {
        return 'вид сверху: товар окружён уместными сопутствующими предметами, например кофе, ноутбуком или аксессуарами, сбалансированная композиция.';
    }
}
