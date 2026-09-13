<?php

namespace App\Models;

use Database\Factories\CompetitorDataFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['card_id', 'marketplace', 'parsed_cards'])]
class CompetitorData extends Model
{
    /** @use HasFactory<CompetitorDataFactory> */
    use HasFactory;

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    protected function casts(): array
    {
        return ['parsed_cards' => 'array'];
    }
}
