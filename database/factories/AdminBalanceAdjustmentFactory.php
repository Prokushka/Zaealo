<?php

namespace Database\Factories;

use App\Models\AdminBalanceAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminBalanceAdjustment>
 */
class AdminBalanceAdjustmentFactory extends Factory
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
            'administrator_id' => User::factory(),
            'amount' => fake()->randomElement([-50, -25, 25, 50]),
            'reason' => fake()->sentence(),
        ];
    }
}
