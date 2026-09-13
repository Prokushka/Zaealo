<?php

namespace Database\Seeders;

use App\Models\CardGeneration;
use Illuminate\Database\Seeder;

class CardGenerationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CardGeneration::factory()->count(10)->create();
    }
}
