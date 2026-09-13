<?php

namespace Database\Seeders;

use App\Models\ZarkPackage;
use Illuminate\Database\Seeder;

class ZarkPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ZarkPackage::factory()->count(4)->create();
    }
}
