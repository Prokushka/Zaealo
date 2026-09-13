<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\CardGeneration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardGeneration>
 */
class CardGenerationFactory extends Factory
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
            'mode' => fake()->randomElement(['quick', 'standard']),
            'selected_style' => fake()->optional()->word(),
            'prompt_input' => fake()->optional()->sentence(),
            'generated_title' => fake()->optional()->sentence(5),
            'generated_description' => fake()->optional()->paragraph(),
            'generated_bullets' => fake()->optional()->passthrough(fake()->sentences(3)),
            'category_match' => null,
            'attributes_category_id' => null,
            'attributes_type_id' => null,
            'attributes_data' => null,
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'failed']),
            'cost_zarks' => fake()->randomFloat(2, 0, 500),
        ];
    }
}
