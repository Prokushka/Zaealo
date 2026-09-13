<?php

declare(strict_types=1);

namespace App\ImagePrompts;

use InvalidArgumentException;

final class ImagePromptFactory
{
    public static function make(
        string $category,
        string $subcategory,
        string $marketplace = 'wildberries',
    ): ImagePromptInterface {
        $categoryPrompt = match ($category) {
            'hero' => new MainCategoryPrompt($subcategory),
            'lifestyle' => new LifestyleCategoryPrompt($subcategory),
            'features' => new FeaturesCategoryPrompt($subcategory),
            'infographics' => new InfographicsCategoryPrompt($subcategory),
            'packaging' => new PackagingCategoryPrompt($subcategory),
            default => throw new InvalidArgumentException("Unsupported image prompt category [{$category}]."),
        };

        $marketplacePrompt = match ($marketplace) {
            'ozon' => new OzonMarketplacePhotoPrompt,
            'wildberries' => new WildberriesMarketplacePhotoPrompt,
            default => throw new InvalidArgumentException("Unsupported image prompt marketplace [{$marketplace}]."),
        };

        return new UniversalProductPhotoPrompt($marketplacePrompt, $categoryPrompt);
    }
}
