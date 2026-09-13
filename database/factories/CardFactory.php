<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'marketplace' => fake()->randomElement(['ozon', 'wildberries']),
            'title' => fake()->sentence(5),
            'description' => fake()->optional()->paragraph(),
            'attributes' => ['brand' => fake()->company()],
            'status' => fake()->randomElement(['draft', 'processing', 'ready']),
            'allowed_photo_slots' => fake()->numberBetween(1, 10),
            'is_exported' => false,
        ];
    }
}
