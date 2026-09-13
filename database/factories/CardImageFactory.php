<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\CardImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardImage>
 */
class CardImageFactory extends Factory
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
            'generation_id' => null,
            'type' => 'user_upload',
            'path' => 'card-images/'.fake()->uuid().'.jpg',
            'is_main' => false,
            'is_paid' => false,
        ];
    }
}
