<?php

use App\Exceptions\InsufficientZarks;
use App\Models\CardGeneration;
use App\Models\User;
use App\Services\ZarkWallet;

test('debits and refunds zarks idempotently for a reference', function (): void {
    $user = User::factory()->create(['balance' => 100]);
    $generation = CardGeneration::factory()->create();
    $wallet = app(ZarkWallet::class);

    $wallet->debit($user, 50, 'Генерация фото', $generation);
    $wallet->debit($user, 50, 'Повторное списание', $generation);

    expect($user->refresh()->balance)->toBe(50)
        ->and($wallet->refund($generation, 'Возврат'))->toBeTrue()
        ->and($user->refresh()->balance)->toBe(100)
        ->and($wallet->refund($generation, 'Повторный возврат'))->toBeFalse()
        ->and($user->refresh()->balance)->toBe(100)
        ->and($user->transactions()->count())->toBe(2);
});

test('does not debit zarks when balance is insufficient', function (): void {
    $user = User::factory()->create(['balance' => 10]);
    $generation = CardGeneration::factory()->create();

    expect(fn () => app(ZarkWallet::class)->debit($user, 50, 'Генерация фото', $generation))
        ->toThrow(InsufficientZarks::class);

    expect($user->refresh()->balance)->toBe(10)
        ->and($user->transactions()->count())->toBe(0);
});
