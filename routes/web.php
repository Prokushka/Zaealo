<?php

use App\Http\Controllers\ApiIntegrationController;
use App\Http\Controllers\CardAnalysisController;
use App\Http\Controllers\CardAttributesController;
use App\Http\Controllers\CardExportController;
use App\Http\Controllers\CardHistoryController;
use App\Http\Controllers\CardImageGenerationController;
use App\Http\Controllers\CompetitorCardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketplaceCategorySearchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->middleware(['auth', 'verified']);

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/docs', fn () => Inertia::render('Docs/Index'))->name('docs');
    Route::get('/support', SupportController::class)->name('support');

    Route::get('/history', [CardHistoryController::class, 'index'])
        ->name('card-history.index');
    Route::patch('/cards/{generation}', [CardHistoryController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('cards.update');
    Route::post('/cards/{generation}/regenerate-text', [CardHistoryController::class, 'regenerateText'])
        ->middleware('throttle:10,1')
        ->name('cards.regenerate-text');
    Route::post('/cards/{generation}/regenerate-images', [CardHistoryController::class, 'regenerateImages'])
        ->middleware('throttle:10,1')
        ->name('cards.regenerate-images');
    Route::get('/integrations', [ApiIntegrationController::class, 'index'])
        ->name('integrations.index');
    Route::get('/integrations/{marketplace}/status', [ApiIntegrationController::class, 'status'])
        ->where('marketplace', 'wildberries|ozon')
        ->name('integrations.status');
    Route::put('/integrations/{marketplace}', [ApiIntegrationController::class, 'update'])
        ->where('marketplace', 'wildberries|ozon')
        ->name('integrations.update');
    Route::delete('/integrations/{marketplace}', [ApiIntegrationController::class, 'destroy'])
        ->where('marketplace', 'wildberries|ozon')
        ->name('integrations.destroy');

    Route::post('/card-analyses', [CardAnalysisController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('card-analyses.store');
    Route::post('/card-analyses/{generation}/complete', [CardAnalysisController::class, 'complete'])
        ->middleware('throttle:10,1')
        ->name('card-analyses.complete');
    Route::post('/card-generations/{generation}/images', [CardImageGenerationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('card-generations.images.store');
    Route::post('/card-generations/{generation}/attributes', [CardAttributesController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('card-generations.attributes.store');
    Route::patch('/card-generations/{generation}/attributes', [CardAttributesController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('card-generations.attributes.update');
    Route::get('/card-generations/{generation}/images', [CardImageGenerationController::class, 'index'])
        ->name('card-generations.images.index');
    Route::post('/card-generations/{generation}/exports/archive', [CardExportController::class, 'archive'])
        ->middleware('throttle:10,1')
        ->name('card-generations.exports.archive');
    Route::post('/card-generations/{generation}/exports/publish', [CardExportController::class, 'publish'])
        ->middleware('throttle:10,1')
        ->name('card-generations.exports.publish');
    Route::get('/card-generations/{generation}/exports/json', [CardExportController::class, 'json'])
        ->middleware('throttle:30,1')
        ->name('card-generations.exports.json');
    Route::get('/card-exports/{export}', [CardExportController::class, 'show'])
        ->name('card-exports.show');
    Route::get('/card-exports/{export}/download', [CardExportController::class, 'download'])
        ->middleware('throttle:10,1')
        ->name('card-exports.download');
    Route::get('/marketplace-categories/search', [MarketplaceCategorySearchController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('marketplace-categories.search');
    Route::post('/competitor-cards/search', [CompetitorCardController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('competitor-cards.search');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
