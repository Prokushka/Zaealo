<?php

namespace Database\Factories;

use App\Models\ZarkPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZarkPackage>
 */
class ZarkPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'zarks_amount' => fake()->randomFloat(2, 50, 5000),
            'price_rub' => fake()->randomFloat(2, 99, 9999),
            'discount_percent' => fake()->numberBetween(0, 30),
            'is_popular' => fake()->boolean(20),
            'is_active' => true,
        ];
    }
}
