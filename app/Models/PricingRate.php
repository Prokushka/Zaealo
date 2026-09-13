<?php

namespace App\Models;

use Database\Factories\PricingRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'title', 'cost_zarks', 'value', 'group'])]
class PricingRate extends Model
{
    /** @use HasFactory<PricingRateFactory> */
    use HasFactory;

    protected $attributes = ['group' => 'general'];

    protected function casts(): array
    {
        return ['cost_zarks' => 'decimal:2', 'value' => 'integer'];
    }
}
