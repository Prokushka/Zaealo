<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
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
            'amount' => fake()->randomFloat(2, -500, 500),
            'type' => fake()->randomElement(['credit', 'debit']),
            'description' => fake()->sentence(),
            'reference_type' => null,
            'reference_id' => null,
        ];
    }
}
