<?php

use App\Enums\PricingKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('pricing_rates')->insertOrIgnore(array_map(
            fn (PricingKey $key): array => [
                'key' => $key->value,
                'title' => $key->label(),
                'cost_zarks' => $key->defaultCost(),
                'value' => null,
                'group' => $key->group(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            PricingKey::cases(),
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('pricing_rates')->whereIn('key', array_column(PricingKey::cases(), 'value'))->delete();
    }
};
