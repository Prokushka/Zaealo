<?php

declare(strict_types=1);

use App\ImagePrompts\ImagePromptFactory;
use App\ImagePrompts\UniversalProductPhotoPrompt;
use InvalidArgumentException;

it('wraps every category strategy in the marketplace-specific universal context', function (string $category, string $subcategory, string $marketplace): void {
    expect(ImagePromptFactory::make($category, $subcategory, $marketplace))
        ->toBeInstanceOf(UniversalProductPhotoPrompt::class);
})->with([
    'ozon hero' => ['hero', 'clean-background', 'ozon'],
    'wildberries lifestyle' => ['lifestyle', 'environment', 'wildberries'],
    'ozon features' => ['features', 'macro-details', 'ozon'],
    'wildberries infographics' => ['infographics', 'diagram', 'wildberries'],
    'ozon packaging' => ['packaging', 'unboxing', 'ozon'],
]);

it('generates marketplace, category and subcategory instructions', function (string $category, string $subcategory, string $marketplace, string $marketplaceFragment, string $categoryFragment, string $subcategoryFragment): void {
    expect(ImagePromptFactory::make($category, $subcategory, $marketplace)->generate())
        ->toContain('Ты — сильный e-commerce creative director, SEO- и CRO-менеджер маркетплейсов')
        ->toContain('Правила работы:')
        ->toContain($marketplaceFragment)
        ->toContain($categoryFragment)
        ->toContain($subcategoryFragment)
        ->toContain('Подкатегория:')
        ->toContain('не подменяй её визуально похожими латинскими буквами');
})->with([
    'ozon hero' => ['hero', 'in-hand', 'ozon', 'Стратегия Ozon', 'Категория «Главное (Hero)»', 'товара в ладони'],
    'wildberries lifestyle' => ['lifestyle', 'flat-lay', 'wildberries', 'Стратегия Wildberries', 'Категория «Лайфстайл (Контекст)»', 'вид сверху'],
    'ozon features' => ['features', 'contents', 'ozon', 'Стратегия Ozon', 'Категория «Характеристики (Ракурсы)»', 'содержимое коробки'],
    'wildberries infographics' => ['infographics', 'text-space', 'wildberries', 'Стратегия Wildberries', 'Категория «Инфографика»', 'чистое пространство'],
    'ozon packaging' => ['packaging', 'gift-ready', 'ozon', 'Стратегия Ozon', 'Категория «Упаковка и брендинг»', 'подарочный вид'],
]);

it('rejects an unknown category', function (): void {
    ImagePromptFactory::make('unknown', 'clean-background');
})->throws(InvalidArgumentException::class);

it('rejects an unknown subcategory', function (): void {
    ImagePromptFactory::make('hero', 'unknown')->generate();
})->throws(InvalidArgumentException::class);

it('rejects an unknown marketplace', function (): void {
    ImagePromptFactory::make('hero', 'clean-background', 'unknown');
})->throws(InvalidArgumentException::class);

it('limits text on hero slides to the product name only', function (): void {
    expect(ImagePromptFactory::make('hero', 'clean-background', 'ozon')->generate())
        ->toContain('Это главное фото товара')
        ->toContain('Не перегружай изображение текстом')
        ->toContain('прими самостоятельно');
});

it('allows characteristic text only on feature and infographic slides', function (): void {
    expect(ImagePromptFactory::make('features', 'macro-details', 'ozon')->generate())
        ->toContain('разрешены только 1–2 короткие подписи')
        ->and(ImagePromptFactory::make('infographics', 'diagram', 'ozon')->generate())
        ->toContain('Характеристики и выгоды разрешены только здесь')
        ->and(ImagePromptFactory::make('lifestyle', 'environment', 'ozon')->generate())
        ->toContain('Не добавляй на этот кадр текст, характеристики, выгоды');
});
