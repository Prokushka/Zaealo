<?php

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'parent_id', 'marketplace', 'title', 'description', 'attributes', 'status', 'allowed_photo_slots', 'is_exported'])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    protected $attributes = [
        'marketplace' => 'wildberries',
        'status' => 'draft',
        'allowed_photo_slots' => 3,
        'is_exported' => false,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Card, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Card, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<CompetitorData, $this> */
    public function competitorData(): HasMany
    {
        return $this->hasMany(CompetitorData::class);
    }

    /** @return HasMany<CardGeneration, $this> */
    public function generations(): HasMany
    {
        return $this->hasMany(CardGeneration::class);
    }

    /** @return HasMany<CardImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(CardImage::class);
    }

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'allowed_photo_slots' => 'integer',
            'is_exported' => 'boolean',
        ];
    }
}
