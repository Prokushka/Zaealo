<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['user_id', 'zark_package_id', 'payment_system', 'external_payment_id', 'amount_rub', 'zarks_added', 'status'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'pending'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ZarkPackage, $this> */
    public function zarkPackage(): BelongsTo
    {
        return $this->belongsTo(ZarkPackage::class);
    }

    /** @return MorphMany<Transaction, $this> */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }

    protected function casts(): array
    {
        return ['amount_rub' => 'decimal:2', 'zarks_added' => 'decimal:2'];
    }
}
