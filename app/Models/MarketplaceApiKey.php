<?php

namespace App\Models;

use Database\Factories\MarketplaceApiKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['marketplace', 'api_key', 'client_id'])]
#[Hidden(['api_key', 'client_id'])]
class MarketplaceApiKey extends Model
{
    /** @use HasFactory<MarketplaceApiKeyFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'client_id' => 'encrypted',
        ];
    }
}
