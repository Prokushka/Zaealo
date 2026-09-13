<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientZarks;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class ZarkWallet
{
    public function debit(User $user, int $amount, string $description, Model $reference): Transaction
    {
        return DB::transaction(function () use ($user, $amount, $description, $reference): Transaction {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $existingDebit = Transaction::query()
                ->where('user_id', $lockedUser->getKey())
                ->where('reference_type', $reference->getMorphClass())
                ->where('reference_id', $reference->getKey())
                ->where('type', 'debit')
                ->first();

            if ($existingDebit !== null) {
                return $existingDebit;
            }

            if ($lockedUser->balance < $amount) {
                throw new InsufficientZarks($amount, $lockedUser->balance);
            }

            $lockedUser->decrement('balance', $amount);

            return $lockedUser->transactions()->create([
                'amount' => -$amount,
                'type' => 'debit',
                'description' => $description,
                'reference_type' => $reference->getMorphClass(),
                'reference_id' => $reference->getKey(),
            ]);
        });
    }

    public function refund(Model $reference, string $description): bool
    {
        return DB::transaction(function () use ($reference, $description): bool {
            $debit = Transaction::query()
                ->where('reference_type', $reference->getMorphClass())
                ->where('reference_id', $reference->getKey())
                ->where('type', 'debit')
                ->lockForUpdate()
                ->first();

            if ($debit === null) {
                return false;
            }

            $alreadyRefunded = Transaction::query()
                ->where('reference_type', $reference->getMorphClass())
                ->where('reference_id', $reference->getKey())
                ->where('type', 'credit')
                ->exists();

            if ($alreadyRefunded) {
                return false;
            }

            $lockedUser = User::query()->lockForUpdate()->findOrFail($debit->user_id);
            $amount = abs((int) $debit->amount);
            $lockedUser->increment('balance', $amount);
            $lockedUser->transactions()->create([
                'amount' => $amount,
                'type' => 'credit',
                'description' => $description,
                'reference_type' => $reference->getMorphClass(),
                'reference_id' => $reference->getKey(),
            ]);

            return true;
        });
    }
}
