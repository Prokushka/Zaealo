<?php

namespace App\Models;

use Database\Factories\CardImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['card_id', 'generation_id', 'type', 'generation_status', 'generation_category', 'generation_subcategory', 'generation_features', 'generation_error', 'path', 'is_main', 'is_paid'])]
class CardImage extends Model
{
    public const TYPE_USER_UPLOAD = 'user_upload';

    public const TYPE_AI_GENERATED = 'ai_generated';

    public const GENERATION_STATUS_QUEUED = 'queued';

    public const GENERATION_STATUS_PROCESSING = 'processing';

    public const GENERATION_STATUS_COMPLETED = 'completed';

    public const GENERATION_STATUS_FAILED = 'failed';

    /** @use HasFactory<CardImageFactory> */
    use HasFactory;

    protected $attributes = [
        'type' => self::TYPE_USER_UPLOAD,
        'generation_status' => self::GENERATION_STATUS_COMPLETED,
        'is_main' => false,
        'is_paid' => false,
    ];

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /** @return BelongsTo<CardGeneration, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(CardGeneration::class, 'generation_id');
    }

    protected function casts(): array
    {
        return ['generation_features' => 'array', 'is_main' => 'boolean', 'is_paid' => 'boolean'];
    }
}
