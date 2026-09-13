<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CardExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['card_generation_id', 'marketplace', 'status', 'payload', 'external_task_id', 'external_product_id', 'error', 'cost_zarks', 'status_checks', 'completed_at'])]
class CardExport extends Model
{
    public const STATUS_NOT_PUBLISHED = 'not_published';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SUBMITTING = 'submitting';

    public const STATUS_WAITING = 'waiting';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @use HasFactory<CardExportFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => self::STATUS_NOT_PUBLISHED,
        'cost_zarks' => 0,
        'status_checks' => 0,
    ];

    /** @return BelongsTo<CardGeneration, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(CardGeneration::class, 'card_generation_id');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'cost_zarks' => 'integer',
            'status_checks' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
