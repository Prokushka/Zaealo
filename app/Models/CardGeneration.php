<?php

namespace App\Models;

use Database\Factories\CardGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['card_id', 'mode', 'selected_style', 'prompt_input', 'generated_title', 'generated_description', 'generated_bullets', 'category_match', 'attributes_category_id', 'attributes_type_id', 'attributes_data', 'status', 'cost_zarks'])]
class CardGeneration extends Model
{
    /** @use HasFactory<CardGenerationFactory> */
    use HasFactory;

    protected $attributes = ['mode' => 'fast', 'status' => 'pending', 'cost_zarks' => 0];

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /** @return HasMany<CardImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(CardImage::class, 'generation_id');
    }

    /** @return HasOne<CardExport, $this> */
    public function export(): HasOne
    {
        return $this->hasOne(CardExport::class);
    }

    protected function casts(): array
    {
        return [
            'generated_bullets' => 'array',
            'category_match' => 'array',
            'attributes_category_id' => 'integer',
            'attributes_type_id' => 'integer',
            'attributes_data' => 'array',
            'cost_zarks' => 'decimal:2',
        ];
    }
}
