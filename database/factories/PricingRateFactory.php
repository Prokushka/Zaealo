<?php

namespace Database\Factories;

use App\Models\PricingRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingRate>
 */
class PricingRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'cost_zarks' => fake()->randomFloat(2, 1, 500),
            'value' => fake()->optional()->numberBetween(1, 100),
            'group' => fake()->randomElement(['general', 'generation', 'export']),
        ];
    }
}
