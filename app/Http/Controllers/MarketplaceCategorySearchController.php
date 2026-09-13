<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchMarketplaceCategoriesRequest;
use App\Models\OzonCategory;
use App\Models\WbCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MarketplaceCategorySearchController extends Controller
{
    public function index(SearchMarketplaceCategoriesRequest $request): JsonResponse
    {
        $query = Str::lower(Str::squish($request->validated('query')));
        $categories = match ($request->validated('marketplace')) {
            'ozon' => $this->searchOzon($query),
            'wildberries' => $this->searchWildberries($query),
        };

        return response()->json(['categories' => $categories]);
    }

    /** @return list<array{category_id: int, type_id: int, full_path: string}> */
    private function searchOzon(string $query): array
    {
        return OzonCategory::query()
            ->select(['description_category_id', 'type_id', 'full_path'])
            ->whereRaw('LOWER(full_path) LIKE ?', ["%{$query}%"])
            ->orderBy('full_path')
            ->limit(20)
            ->get()
            ->map(fn (OzonCategory $category): array => [
                'category_id' => $category->description_category_id,
                'type_id' => $category->type_id,
                'full_path' => $category->full_path,
            ])
            ->all();
    }

    /** @return list<array{category_id: int, type_id: null, full_path: string}> */
    private function searchWildberries(string $query): array
    {
        return WbCategory::query()
            ->select(['subject_id', 'full_path'])
            ->whereRaw('LOWER(full_path) LIKE ?', ["%{$query}%"])
            ->orderBy('full_path')
            ->limit(20)
            ->get()
            ->map(fn (WbCategory $category): array => [
                'category_id' => $category->subject_id,
                'type_id' => null,
                'full_path' => $category->full_path,
            ])
            ->all();
    }
}
