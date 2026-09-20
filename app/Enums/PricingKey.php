<?php

namespace App\Enums;

enum PricingKey: string
{
    case ImageGeneration = 'image_generation';
    case TextRegeneration = 'text_regeneration';
    case CardExport = 'card_export';
    case ProCopywriting = 'pro_copywriting';

    public function label(): string
    {
        return match ($this) {
            self::ImageGeneration => 'Генерация изображения',
            self::TextRegeneration => 'Перегенерация текста',
            self::CardExport => 'Экспорт карточки',
            self::ProCopywriting => 'Профессиональный текст',
        };
    }

    public function defaultCost(): int
    {
        return match ($this) {
            self::ImageGeneration, self::ProCopywriting => 50,
            self::TextRegeneration, self::CardExport => 25,
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ImageGeneration, self::TextRegeneration, self::ProCopywriting => 'generation',
            self::CardExport => 'export',
        };
    }
}
