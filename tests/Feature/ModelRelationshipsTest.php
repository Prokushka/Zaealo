<?php

use App\Models\Card;
use App\Models\CardGeneration;
use App\Models\CardImage;
use App\Models\CompetitorData;
use App\Models\Payment;
use App\Models\PricingRate;
use App\Models\Transaction;
use App\Models\User;
use App\Models\ZarkPackage;

it('persists domain models with their relationships and casts', function () {
    $user = User::factory()->create();
    $card = Card::factory()->for($user)->create(['attributes' => ['color' => 'purple']]);
    $generation = CardGeneration::factory()->for($card)->create(['generated_bullets' => ['Первый пункт']]);
    $image = CardImage::factory()->for($card)->for($generation, 'generation')->create(['is_main' => true]);
    $competitorData = CompetitorData::factory()->for($card)->create();
    $package = ZarkPackage::factory()->create();
    $payment = Payment::factory()->for($user)->for($package, 'zarkPackage')->create();
    $transaction = Transaction::factory()->for($user)->for($payment, 'reference')->create();
    $pricingRate = PricingRate::factory()->create();

    expect($card->user->is($user))->toBeTrue()
        ->and($card->attributes)->toBe(['color' => 'purple'])
        ->and($generation->card->is($card))->toBeTrue()
        ->and($generation->generated_bullets)->toBe(['Первый пункт'])
        ->and($image->generation->is($generation))->toBeTrue()
        ->and($image->is_main)->toBeTrue()
        ->and($competitorData->card->is($card))->toBeTrue()
        ->and($payment->zarkPackage->is($package))->toBeTrue()
        ->and($transaction->reference->is($payment))->toBeTrue()
        ->and($pricingRate->cost_zarks)->toBeString();
});
