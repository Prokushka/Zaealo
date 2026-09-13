<?php

namespace Database\Seeders;

use App\Models\CompetitorData;
use Illuminate\Database\Seeder;

class CompetitorDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CompetitorData::factory()->count(10)->create();
    }
}
