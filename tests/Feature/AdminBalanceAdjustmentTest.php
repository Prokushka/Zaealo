<?php

use App\Models\AdminBalanceAdjustment;
use App\Models\User;
use App\Services\AdjustUserBalance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

test('support can adjust balance with an immutable audit trail', function () {
    $administrator = User::factory()->support()->create();
    $user = User::factory()->create(['balance' => 100]);
    $service = app(AdjustUserBalance::class);

    $credit = $service->handle($administrator, $user, 25, 'Компенсация за ошибку генерации');
    $debit = $service->handle($administrator, $user, -40, 'Отмена ошибочного начисления');

    expect($user->refresh()->balance)->toBe(85)
        ->and($credit->administrator->is($administrator))->toBeTrue()
        ->and($debit->user->is($user))->toBeTrue()
        ->and($user->transactions()->count())->toBe(2)
        ->and($user->transactions()->latest('id')->value('amount'))->toBe('-40.00')
        ->and(AdminBalanceAdjustment::query()->count())->toBe(2);
});

test('balance adjustment cannot make balance negative', function () {
    $administrator = User::factory()->owner()->create();
    $user = User::factory()->create(['balance' => 10]);

    expect(fn () => app(AdjustUserBalance::class)->handle($administrator, $user, -11, 'Ручное списание'))
        ->toThrow(ValidationException::class);

    expect($user->refresh()->balance)->toBe(10)
        ->and($user->transactions()->count())->toBe(0)
        ->and($user->balanceAdjustments()->count())->toBe(0);
});

test('regular user cannot adjust another user balance', function () {
    $administrator = User::factory()->create();
    $user = User::factory()->create();

    expect(fn () => app(AdjustUserBalance::class)->handle($administrator, $user, 10, 'Ручное начисление'))
        ->toThrow(AuthorizationException::class);
});
