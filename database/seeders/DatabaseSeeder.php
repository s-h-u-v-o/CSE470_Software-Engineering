<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Call our AssetSeeder here
        $this->call([
            AssetSeeder::class,
        ]);
    }
}