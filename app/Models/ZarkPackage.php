<?php

namespace App\Models;

use Database\Factories\ZarkPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'zarks_amount', 'price_rub', 'discount_percent', 'is_popular', 'is_active'])]
class ZarkPackage extends Model
{
    /** @use HasFactory<ZarkPackageFactory> */
    use HasFactory;

    protected $attributes = [
        'discount_percent' => 0,
        'is_popular' => false,
        'is_active' => true,
    ];

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected function casts(): array
    {
        return [
            'zarks_amount' => 'decimal:2',
            'price_rub' => 'decimal:2',
            'discount_percent' => 'integer',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
