<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchCompetitorCardsRequest;
use App\Services\MarketplaceProductSearchService;

class CompetitorCardController extends Controller
{
    public function __construct(private MarketplaceProductSearchService $productSearch) {}

    public function store(SearchCompetitorCardsRequest $request): never
    {
        $products = match ($request->validated('marketplace')) {
            'ozon' => $this->productSearch->searchOzon($request->validated('marketplace_search_query')),
            'wildberries' => $this->productSearch->searchWildberries($request->validated('marketplace_search_query')),
        };

        dd($products);
    }
}
