<?php

namespace Database\Factories;

use App\Models\CardExport;
use App\Models\CardGeneration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardExport>
 */
class CardExportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_generation_id' => CardGeneration::factory(),
            'marketplace' => fake()->randomElement(['ozon', 'wildberries']),
            'status' => CardExport::STATUS_NOT_PUBLISHED,
            'payload' => [
                'seller_sku' => fake()->unique()->bothify('SKU-#####'),
                'attributes' => [],
            ],
        ];
    }
}
