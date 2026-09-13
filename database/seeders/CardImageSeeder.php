<?php

namespace Database\Seeders;

use App\Models\CardImage;
use Illuminate\Database\Seeder;

class CardImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CardImage::factory()->count(10)->create();
    }
}
