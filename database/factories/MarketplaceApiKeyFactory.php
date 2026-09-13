<?php

namespace Database\Factories;

use App\Models\MarketplaceApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketplaceApiKey>
 */
class MarketplaceApiKeyFactory extends Factory
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
            'marketplace' => fake()->randomElement(['wildberries', 'ozon']),
            'api_key' => Str::random(40),
        ];
    }
}
