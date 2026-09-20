<?php

namespace App\Services;

use App\Models\AdminBalanceAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdjustUserBalance
{
    public function handle(User $administrator, User $user, int $amount, string $reason): AdminBalanceAdjustment
    {
        Gate::forUser($administrator)->authorize('adjustBalance', $user);

        $reason = Str::squish($reason);

        if ($amount === 0) {
            throw ValidationException::withMessages(['amount' => 'Сумма корректировки не может быть равна нулю.']);
        }

        if (Str::length($reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'Укажите причину длиной не менее 5 символов.']);
        }

        return DB::transaction(function () use ($administrator, $user, $amount, $reason): AdminBalanceAdjustment {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $newBalance = $lockedUser->balance + $amount;

            if ($newBalance < 0) {
                throw ValidationException::withMessages(['amount' => 'Баланс пользователя не может стать отрицательным.']);
            }

            $adjustment = AdminBalanceAdjustment::query()->create([
                'user_id' => $lockedUser->getKey(),
                'administrator_id' => $administrator->getKey(),
                'amount' => $amount,
                'reason' => $reason,
            ]);

            $lockedUser->update(['balance' => $newBalance]);
            $lockedUser->transactions()->create([
                'amount' => $amount,
                'type' => $amount > 0 ? 'credit' : 'debit',
                'description' => 'Корректировка администратором: '.$reason,
                'reference_type' => $adjustment->getMorphClass(),
                'reference_id' => $adjustment->getKey(),
            ]);

            return $adjustment;
        });
    }
}
