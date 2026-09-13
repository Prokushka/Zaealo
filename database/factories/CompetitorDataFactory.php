<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\CompetitorData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitorData>
 */
class CompetitorDataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'marketplace' => fake()->randomElement(['ozon', 'wildberries']),
            'parsed_cards' => [
                ['title' => fake()->sentence(4), 'price' => fake()->numberBetween(100, 10000)],
            ],
        ];
    }
}
