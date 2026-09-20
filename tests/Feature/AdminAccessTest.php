<?php

use App\Enums\AdminRole;
use App\Models\AdminBalanceAdjustment;
use App\Models\Card;
use App\Models\CardExport;
use App\Models\CardGeneration;
use App\Models\CardImage;
use App\Models\MarketplaceApiKey;
use App\Models\Payment;
use App\Models\PricingRate;
use App\Models\Transaction;
use App\Models\User;
use App\Models\ZarkPackage;

test('only users with an administrative role can open the panel', function () {
    $regularUser = User::factory()->create();
    $support = User::factory()->support()->create();
    $owner = User::factory()->owner()->create();

    $this->get('/admin')->assertRedirect('/admin/login');
    $this->actingAs($regularUser)->get('/admin')->assertForbidden();
    $this->actingAs($support)->get('/admin')->assertSuccessful();
    $this->actingAs($owner)->get('/admin')->assertSuccessful();
});

test('support can inspect operations but cannot manage commercial settings', function () {
    $support = User::factory()->support()->create();
    $customer = User::factory()->create();
    $card = Card::factory()->for($customer)->create();
    $pricingRate = PricingRate::query()->firstOrFail();

    if (extension_loaded('intl')) {
        $this->actingAs($support)->get('/admin/users')->assertSuccessful();
    }

    $this->actingAs($support)->get("/admin/users/{$customer->getKey()}")->assertSuccessful();
    $this->actingAs($support)->get("/admin/cards/{$card->getKey()}")->assertSuccessful();
    $this->actingAs($support)->get('/admin/pricing-rates')->assertForbidden();
    $this->actingAs($support)->get("/admin/pricing-rates/{$pricingRate->getKey()}/edit")->assertForbidden();
});

test('owner can manage users and commercial settings', function () {
    $owner = User::factory()->owner()->create();
    $customer = User::factory()->create();
    $pricingRate = PricingRate::query()->firstOrFail();

    $this->actingAs($owner)->get("/admin/users/{$customer->getKey()}/edit")->assertSuccessful();
    $this->actingAs($owner)->get("/admin/pricing-rates/{$pricingRate->getKey()}/edit")->assertSuccessful();
    $this->actingAs($owner)->get('/admin/zark-packages/create')->assertSuccessful();
});

test('support can open every operational record view', function () {
    $support = User::factory()->support()->create();
    $customer = User::factory()->create();
    $card = Card::factory()->for($customer)->create();
    $generation = CardGeneration::factory()->for($card)->create();
    $image = CardImage::factory()->for($card)->for($generation, 'generation')->create();
    $export = CardExport::factory()->for($generation, 'generation')->create();
    $package = ZarkPackage::factory()->create();
    $payment = Payment::factory()->for($customer)->for($package, 'zarkPackage')->create();
    $transaction = Transaction::factory()->for($customer)->for($payment, 'reference')->create();
    $adjustment = AdminBalanceAdjustment::factory()
        ->for($customer)
        ->for($support, 'administrator')
        ->create();

    $urls = [
        "/admin/cards/{$card->getKey()}",
        "/admin/card-generations/{$generation->getKey()}",
        "/admin/card-images/{$image->getKey()}",
        "/admin/card-exports/{$export->getKey()}",
        "/admin/transactions/{$transaction->getKey()}",
        "/admin/admin-balance-adjustments/{$adjustment->getKey()}",
    ];

    if (extension_loaded('intl')) {
        $urls[] = "/admin/payments/{$payment->getKey()}";
        $urls[] = '/admin/admin-balance-adjustments';
    }

    foreach ($urls as $url) {
        $this->actingAs($support)->get($url)->assertSuccessful();
    }
});

test('admin user view never renders marketplace secrets', function () {
    $support = User::factory()->support()->create();
    $customer = User::factory()->create();
    MarketplaceApiKey::factory()->for($customer)->create([
        'api_key' => 'top-secret-api-key',
        'client_id' => 'top-secret-client-id',
    ]);

    $this->actingAs($support)
        ->get("/admin/users/{$customer->getKey()}")
        ->assertSuccessful()
        ->assertDontSee('top-secret-api-key')
        ->assertDontSee('top-secret-client-id');
});

test('admin access commands grant and revoke roles', function () {
    $user = User::factory()->create();

    $this->artisan('admin:grant', ['email' => $user->email, '--role' => AdminRole::Support->value])
        ->assertSuccessful();
    expect($user->refresh()->admin_role)->toBe(AdminRole::Support);

    $this->artisan('admin:revoke', ['email' => $user->email])->assertSuccessful();
    expect($user->refresh()->admin_role)->toBeNull();
});
