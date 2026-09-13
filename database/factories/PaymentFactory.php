<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use App\Models\ZarkPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'zark_package_id' => ZarkPackage::factory(),
            'payment_system' => fake()->randomElement(['yookassa', 'cloudpayments']),
            'external_payment_id' => fake()->optional()->uuid(),
            'amount_rub' => fake()->randomFloat(2, 99, 9999),
            'zarks_added' => fake()->randomFloat(2, 50, 5000),
            'status' => fake()->randomElement(['pending', 'paid', 'failed']),
        ];
    }
}
