<?php

namespace Database\Seeders;

use App\Models\PricingRate;
use Illuminate\Database\Seeder;

class PricingRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PricingRate::factory()->count(5)->create();
    }
}
